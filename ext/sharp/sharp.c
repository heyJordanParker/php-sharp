#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include <errno.h>
#include <fcntl.h>
#include <sys/wait.h>
#include <unistd.h>

#include "php.h"
#include "php_ini.h"
#include "ext/hash/php_hash.h"
#include "ext/hash/php_hash_xxhash.h"
#include "ext/json/php_json_parser.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/info.h"
#include "zend_closures.h"
#include "zend_enum.h"
#include "zend_exceptions.h"
#include "zend_smart_str.h"
#include "zend_system_id.h"
#include "php_sharp.h"
#include "sharp_arginfo.h"
#include "sharp_unit.h"
#include "sharp_build_id.h"

#define SHARP_FILE_EXTENSION ".sharp"
#define SHARP_COMPILED_FOLDER ".sharp"

#define SHARP_KIND_IS_ZEND_KIND(kind) \
	ZEND_STATIC_ASSERT((zend_ast_kind) SHARP_AST_##kind == ZEND_AST_##kind, "SHARP_AST_" #kind " differs from ZEND_AST_" #kind);
SHARP_KINDS(SHARP_KIND_IS_ZEND_KIND)

ZEND_STATIC_ASSERT(sizeof(sharp_unit_header) == 104, "a .sharpc header is 104 bytes");
ZEND_STATIC_ASSERT(sizeof(sharp_input) == 40, "a .sharpc input is 40 bytes");
ZEND_STATIC_ASSERT(sizeof(sharp_node) == 56, "a .sharpc node is 56 bytes");

#define SHARP_SPARE_COLLECTIONS 4

ZEND_BEGIN_MODULE_GLOBALS(sharp)
	zend_object *spare_collections[SHARP_SPARE_COLLECTIONS];
	HashTable folder_roots;
	HashTable request_stamps;
	char *compile_command;
	/* Undefined until the request runs its compile command, then the checker report the command printed, or null. */
	zval compile_command_report;
ZEND_END_MODULE_GLOBALS(sharp)

ZEND_DECLARE_MODULE_GLOBALS(sharp)

#define SHARP_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(sharp, v)

PHP_INI_BEGIN()
	STD_PHP_INI_ENTRY("sharp.compile_command", "", PHP_INI_SYSTEM, OnUpdateString, compile_command, zend_sharp_globals, sharp_globals)
PHP_INI_END()

static zend_op_array *(*sharp_next_compile_file)(zend_file_handle *file_handle, int type);

typedef struct {
	const sharp_node *nodes;
	const uint32_t *children;
	const char *texts;
	uint32_t root;
} sharp_unit;

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index);

static zend_string *sharp_string(const sharp_unit *unit, sharp_str text)
{
	return zend_string_init(unit->texts + text.offset, text.len, 0);
}

static zend_ast *sharp_translate_zval(const sharp_unit *unit, const sharp_node *node)
{
	zval value;

	switch (node->value) {
		case SHARP_NULL:
			ZVAL_NULL(&value);
			break;
		case SHARP_FALSE:
			ZVAL_FALSE(&value);
			break;
		case SHARP_TRUE:
			ZVAL_TRUE(&value);
			break;
		case SHARP_LONG:
			ZVAL_LONG(&value, node->long_value);
			break;
		case SHARP_DOUBLE:
			ZVAL_DOUBLE(&value, node->double_value);
			break;
		case SHARP_STRING:
			ZVAL_STR(&value, sharp_string(unit, node->text));
			break;
		EMPTY_SWITCH_DEFAULT_CASE();
	}

	return zend_ast_create_zval_ex(&value, node->attr);
}

static zend_ast_attr sharp_operator_attr(zend_ast_kind kind, uint32_t attr)
{
	switch (kind) {
		case ZEND_AST_BINARY_OP:
		case ZEND_AST_ASSIGN_OP:
			return attr == ZEND_ADD || attr == ZEND_SUB || attr == ZEND_MUL || attr == ZEND_POW
				? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
		case ZEND_AST_CAST:
			return attr == IS_LONG ? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
		case ZEND_AST_UNARY_MINUS:
		case ZEND_AST_PRE_INC:
		case ZEND_AST_PRE_DEC:
		case ZEND_AST_POST_INC:
		case ZEND_AST_POST_DEC:
			return attr | ZEND_SHARP_OPERATOR_SYNTAX;
		case ZEND_AST_DIM:
			return attr | ZEND_DIM_SHARP;
		case ZEND_AST_ARRAY:
			return attr | ZEND_ARRAY_SHARP;
		case ZEND_AST_METHOD_CALL:
		case ZEND_AST_NULLSAFE_METHOD_CALL:
			return attr | ZEND_METHOD_CALL_SHARP;
		default:
			return attr;
	}
}

static zend_ast *sharp_translate_list(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *list = zend_ast_create_list(0, kind);

	for (uint32_t i = 0; i < node->child_count; i++) {
		list = zend_ast_list_add(list, sharp_translate(unit, unit->children[node->first_child + i]));
	}
	list->attr = sharp_operator_attr(kind, node->attr);

	return list;
}

static zend_ast *sharp_translate_decl(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *child[5];

	ZEND_ASSERT(node->child_count == 5);
	for (uint32_t i = 0; i < 5; i++) {
		child[i] = sharp_translate(unit, unit->children[node->first_child + i]);
	}

	/* php-src's grammar gives a closure and an arrow function no name. */
	CG(zend_lineno) = node->end_line;
	return zend_ast_create_decl(kind, node->attr, node->line, NULL,
		kind == ZEND_AST_CLOSURE || kind == ZEND_AST_ARROW_FUNC ? NULL : sharp_string(unit, node->text),
		child[0], child[1], child[2], child[3], child[4]);
}

static zend_ast *sharp_translate_fixed(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *child[6] = {0};
	uint32_t child_count = kind >> ZEND_AST_NUM_CHILDREN_SHIFT;
	zend_ast *ast;

	ZEND_ASSERT(node->child_count == child_count);
	for (uint32_t i = 0; i < child_count; i++) {
		child[i] = sharp_translate(unit, unit->children[node->first_child + i]);
	}

	ast = zend_ast_create_ex(kind, sharp_operator_attr(kind, node->attr), child[0], child[1], child[2], child[3], child[4], child[5]);
	ast->lineno = node->line;

	return ast;
}

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index)
{
	const sharp_node *node;
	zend_ast_kind kind;
	zend_ast *ast;

	if (index == UINT32_MAX) {
		return NULL;
	}

	node = &unit->nodes[index];
	kind = (zend_ast_kind) node->kind;

	if (kind == ZEND_AST_ZVAL) {
		CG(zend_lineno) = node->line;
		return sharp_translate_zval(unit, node);
	}
	if ((kind >> ZEND_AST_IS_LIST_SHIFT) & 1) {
		ast = sharp_translate_list(unit, node, kind);
		ast->lineno = node->line;
		return ast;
	}
	if (kind >= ZEND_AST_FUNC_DECL && kind <= ZEND_AST_PROPERTY_HOOK) {
		return sharp_translate_decl(unit, node, kind);
	}
	return sharp_translate_fixed(unit, node, kind);
}

typedef enum {
	SHARP_CURRENT,
	SHARP_NOT_COMPILED,
	SHARP_OTHER_ENGINE,
	SHARP_OUT_OF_DATE,
} sharp_state;

typedef struct {
	bool exists;
	bool hashed;
	uint64_t size;
	int64_t mtime_ns;
	uint8_t hash[16];
} sharp_stamp;

typedef struct {
	zend_string *source;
	bool rooted;
	size_t root_length;
	zend_string *bytes;
	const char *changed;
	size_t changed_length;
	sharp_unit unit;
} sharp_compiled;

static int64_t sharp_mtime_ns(const zend_stat_t *status)
{
#ifdef __APPLE__
	return (int64_t) status->st_mtimespec.tv_sec * 1000000000 + status->st_mtimespec.tv_nsec;
#else
	return (int64_t) status->st_mtim.tv_sec * 1000000000 + status->st_mtim.tv_nsec;
#endif
}

static bool sharp_hash_file(const char *path, uint8_t hash[16])
{
	PHP_XXH3_128_CTX context = {0};
	unsigned char buffer[65536];
	ssize_t read_length;
	int file = VCWD_OPEN(path, O_RDONLY);

	if (file < 0) {
		return false;
	}
	PHP_XXH3_128_Init(&context, NULL);
	while ((read_length = read(file, buffer, sizeof(buffer))) > 0) {
		PHP_XXH3_128_Update(&context, buffer, read_length);
	}
	close(file);
	if (read_length < 0) {
		return false;
	}
	PHP_XXH3_128_Final(hash, &context);

	return true;
}

static void sharp_free_stamp(zval *stamp)
{
	efree(Z_PTR_P(stamp));
}

static sharp_stamp *sharp_stamp_of(zend_string *path)
{
	sharp_stamp *stamp = zend_hash_find_ptr(&SHARP_G(request_stamps), path);
	sharp_stamp fresh = {0};
	zend_stat_t status;

	if (stamp) {
		return stamp;
	}
	if (VCWD_STAT(ZSTR_VAL(path), &status) == 0 && S_ISREG(status.st_mode)) {
		fresh.exists = true;
		fresh.size = (uint64_t) status.st_size;
		fresh.mtime_ns = sharp_mtime_ns(&status);
	}

	return zend_hash_add_mem(&SHARP_G(request_stamps), path, &fresh, sizeof(fresh));
}

static size_t sharp_parent(const char *path, size_t length)
{
	while (length > 0 && path[length - 1] != '/') {
		length--;
	}

	return length > 0 ? length - 1 : 0;
}

static bool sharp_holds_compiled_folder(const char *folder, size_t length)
{
	zend_stat_t status;
	zend_string *compiled = zend_string_concat3(folder, length, "/", 1, SHARP_COMPILED_FOLDER, sizeof(SHARP_COMPILED_FOLDER) - 1);
	bool holds = VCWD_STAT(ZSTR_VAL(compiled), &status) == 0 && S_ISDIR(status.st_mode);

	zend_string_release(compiled);

	return holds;
}

static bool sharp_find_root(const zend_string *source, size_t *root_length)
{
	const char *path = ZSTR_VAL(source);
	size_t folder = ZSTR_LEN(source);
	zval root;

	do {
		zval *known;

		folder = sharp_parent(path, folder);
		known = zend_hash_str_find(&SHARP_G(folder_roots), path, folder);
		if (known) {
			*root_length = Z_LVAL_P(known);
			break;
		}
		if (sharp_holds_compiled_folder(path, folder)) {
			*root_length = folder;
			break;
		}
		if (folder == 0) {
			return false;
		}
	} while (true);

	ZVAL_LONG(&root, *root_length);
	for (folder = sharp_parent(path, ZSTR_LEN(source)); folder > *root_length; folder = sharp_parent(path, folder)) {
		zend_hash_str_update(&SHARP_G(folder_roots), path, folder, &root);
	}
	zend_hash_str_update(&SHARP_G(folder_roots), path, *root_length, &root);

	return true;
}

static zend_string *sharp_read_compiled(const sharp_compiled *compiled)
{
	const char *source = ZSTR_VAL(compiled->source);
	zend_string *path = zend_strpprintf(0, "%.*s/" SHARP_COMPILED_FOLDER "%.*sc",
		(int) compiled->root_length, source,
		(int) (ZSTR_LEN(compiled->source) - compiled->root_length), source + compiled->root_length);
	php_stream *stream;
	zend_string *bytes;

	stream = php_stream_open_wrapper(ZSTR_VAL(path), "rb", STREAM_DISABLE_OPEN_BASEDIR, NULL);
	zend_string_release(path);
	if (!stream) {
		return NULL;
	}
	bytes = php_stream_copy_to_mem(stream, PHP_STREAM_COPY_ALL, 0);
	php_stream_close(stream);

	return bytes ? bytes : ZSTR_EMPTY_ALLOC();
}

static bool sharp_text_fits(const sharp_unit_header *header, sharp_str text)
{
	return (uint64_t) text.offset + text.len <= header->texts_size;
}

static bool sharp_input_current(const sharp_compiled *compiled, const sharp_input *input)
{
	static const uint8_t absent[16] = {0};
	zend_string *path = zend_string_concat3(
		ZSTR_VAL(compiled->source), compiled->root_length,
		"/", 1,
		compiled->unit.texts + input->path.offset, input->path.len);
	sharp_stamp *stamp = sharp_stamp_of(path);
	bool current;

	if (input->size == 0 && memcmp(input->hash, absent, sizeof(absent)) == 0) {
		current = !stamp->exists;
	} else if (!stamp->exists) {
		current = false;
	} else if (stamp->size == input->size && stamp->mtime_ns == input->mtime_ns) {
		current = true;
	} else {
		if (!stamp->hashed) {
			stamp->hashed = sharp_hash_file(ZSTR_VAL(path), stamp->hash);
		}
		current = stamp->hashed && memcmp(stamp->hash, input->hash, sizeof(stamp->hash)) == 0;
	}
	zend_string_release(path);

	return current;
}

static bool sharp_offsets_fit(const sharp_unit_header *header, const sharp_unit *unit)
{
	if (header->root >= header->node_count) {
		return false;
	}
	for (uint32_t i = 0; i < header->node_count; i++) {
		const sharp_node *node = &unit->nodes[i];

		if (!sharp_text_fits(header, node->text)
				|| (uint64_t) node->first_child + node->child_count > header->children_count) {
			return false;
		}
	}
	for (uint32_t i = 0; i < header->children_count; i++) {
		if (unit->children[i] != UINT32_MAX && unit->children[i] >= header->node_count) {
			return false;
		}
	}

	return true;
}

static sharp_state sharp_check(sharp_compiled *compiled, const char *source, size_t source_length)
{
	const char *bytes;
	const sharp_unit_header *header;
	const sharp_input *inputs;

	if (!compiled->rooted || !compiled->bytes || ZSTR_LEN(compiled->bytes) < sizeof(sharp_unit_header)) {
		return SHARP_NOT_COMPILED;
	}
	bytes = ZSTR_VAL(compiled->bytes);
	header = (const sharp_unit_header *) bytes;
	if (memcmp(header->magic, SHARP_UNIT_MAGIC, sizeof(header->magic)) != 0
			|| memcmp(header->abi, SHARP_UNIT_ABI, sizeof(header->abi)) != 0) {
		return SHARP_OTHER_ENGINE;
	}
	if (sizeof(sharp_unit_header)
			+ sizeof(sharp_input) * (uint64_t) header->input_count
			+ sizeof(sharp_node) * (uint64_t) header->node_count
			+ sizeof(uint32_t) * (uint64_t) header->children_count
			+ (uint64_t) header->texts_size + header->facts_size != ZSTR_LEN(compiled->bytes)) {
		return SHARP_NOT_COMPILED;
	}

	inputs = (const sharp_input *) (bytes + sizeof(sharp_unit_header));
	compiled->unit.nodes = (const sharp_node *) (inputs + header->input_count);
	compiled->unit.children = (const uint32_t *) (compiled->unit.nodes + header->node_count);
	compiled->unit.texts = (const char *) (compiled->unit.children + header->children_count);
	compiled->unit.root = header->root;

	if (source) {
		uint8_t hash[16];
		PHP_XXH3_128_CTX context = {0};

		PHP_XXH3_128_Init(&context, NULL);
		PHP_XXH3_128_Update(&context, (const unsigned char *) source, source_length);
		PHP_XXH3_128_Final(hash, &context);
		if (header->source_size != source_length || memcmp(header->source_hash, hash, sizeof(hash)) != 0) {
			compiled->changed = ZSTR_VAL(compiled->source) + compiled->root_length + 1;
			compiled->changed_length = ZSTR_LEN(compiled->source) - compiled->root_length - 1;
			return SHARP_OUT_OF_DATE;
		}
	}
	for (uint32_t i = 0; i < header->input_count; i++) {
		if (!sharp_text_fits(header, inputs[i].path)) {
			return SHARP_NOT_COMPILED;
		}
		if (!sharp_input_current(compiled, &inputs[i])) {
			compiled->changed = compiled->unit.texts + inputs[i].path.offset;
			compiled->changed_length = inputs[i].path.len;
			return SHARP_OUT_OF_DATE;
		}
	}
	if (source && !sharp_offsets_fit(header, &compiled->unit)) {
		return SHARP_NOT_COMPILED;
	}

	return SHARP_CURRENT;
}

static void sharp_locate(sharp_compiled *compiled, const char *filename)
{
	char real[MAXPATHLEN];

	memset(compiled, 0, sizeof(*compiled));
	compiled->source = VCWD_REALPATH(filename, real)
		? zend_string_init(real, strlen(real), 0)
		: zend_string_init(filename, strlen(filename), 0);
	compiled->rooted = sharp_find_root(compiled->source, &compiled->root_length);
	if (compiled->rooted) {
		compiled->bytes = sharp_read_compiled(compiled);
	}
}

static void sharp_release(sharp_compiled *compiled)
{
	zend_string_release(compiled->source);
	if (compiled->bytes) {
		zend_string_release(compiled->bytes);
	}
}

/* Returns the member of a decoded JSON object when it has the given type, or NULL. */
static zval *sharp_member(const zval *object, const char *key, uint8_t type)
{
	zval *member = object && Z_TYPE_P(object) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(object), key, strlen(key)) : NULL;

	return member && Z_TYPE_P(member) == type ? member : NULL;
}

/* Refuses a file with the errors the compile command's checker reported for it: the first in the file, its line, and
 * how many there are. The report lists errors by checking pass rather than by line, and counts lines from 0. Returns
 * false when the report holds no error for the file. */
static ZEND_COLD bool sharp_refuse_checked(const sharp_compiled *compiled, const char *name)
{
	zval *issues = sharp_member(&SHARP_G(compile_command_report), "issues", IS_ARRAY), *issue;
	zend_string *message = NULL;
	zend_long first_offset = 0, line = 0;
	uint32_t count = 0;
	size_t length;

	if (!issues) {
		return false;
	}
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(issues), issue) {
		zval *level = sharp_member(issue, "level", IS_STRING), *text = sharp_member(issue, "message", IS_STRING);
		zval *annotations = sharp_member(issue, "annotations", IS_ARRAY), *annotation, *span = NULL, *path, *start, *offset, *start_line;

		if (!level || !zend_string_equals_literal(Z_STR_P(level), "Error") || !text || !annotations) {
			continue;
		}
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(annotations), annotation) {
			zval *kind = sharp_member(annotation, "kind", IS_STRING);

			if (kind && zend_string_equals_literal(Z_STR_P(kind), "Primary")) {
				span = sharp_member(annotation, "span", IS_ARRAY);
				break;
			}
		} ZEND_HASH_FOREACH_END();
		path = sharp_member(sharp_member(span, "file_id", IS_ARRAY), "path", IS_STRING);
		start = sharp_member(span, "start", IS_ARRAY);
		offset = sharp_member(start, "offset", IS_LONG);
		start_line = sharp_member(start, "line", IS_LONG);
		if (!path || !zend_string_equals(Z_STR_P(path), compiled->source) || !offset || !start_line) {
			continue;
		}
		count++;
		if (!message || Z_LVAL_P(offset) < first_offset) {
			message = Z_STR_P(text);
			first_offset = Z_LVAL_P(offset);
			line = Z_LVAL_P(start_line) + 1;
		}
	} ZEND_HASH_FOREACH_END();

	if (!message) {
		return false;
	}
	length = ZSTR_LEN(message) - (ZSTR_LEN(message) > 0 && ZSTR_VAL(message)[ZSTR_LEN(message) - 1] == '.');
	if (count == 1) {
		zend_throw_exception_ex(zend_ce_compile_error, 0,
			"%s has an error on line " ZEND_LONG_FMT ": %.*s. Run vendor/bin/mago compile to see it.",
			name, line, (int) length, ZSTR_VAL(message));
	} else {
		zend_throw_exception_ex(zend_ce_compile_error, 0,
			"%s has %u errors. The first is on line " ZEND_LONG_FMT ": %.*s. Run vendor/bin/mago compile to see them all.",
			name, count, line, (int) length, ZSTR_VAL(message));
	}

	return true;
}

static ZEND_COLD void sharp_refuse(const sharp_compiled *compiled, sharp_state state)
{
	const char *name = ZSTR_VAL(compiled->source) + (compiled->rooted ? compiled->root_length + 1 : 0);

	if (sharp_refuse_checked(compiled, name)) {
		return;
	}
	switch (state) {
		case SHARP_NOT_COMPILED:
			zend_throw_exception_ex(zend_ce_compile_error, 0,
				"%s isn't compiled. Run vendor/bin/mago compile.", name);
			break;
		case SHARP_OTHER_ENGINE:
			zend_throw_exception_ex(zend_ce_compile_error, 0,
				"%s was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.", name);
			break;
		case SHARP_OUT_OF_DATE:
			zend_throw_exception_ex(zend_ce_compile_error, 0,
				"%s is out of date (%.*s changed). Run vendor/bin/mago compile.", name, (int) compiled->changed_length, compiled->changed);
			break;
		EMPTY_SWITCH_DEFAULT_CASE();
	}
}

/* Runs sharp.compile_command through /bin/sh in the project root, and waits for it however long it takes. The command
 * runs with MAGO_REPORTING_FORMAT=json, and the checker report it prints is decoded for the request's refusals. What it
 * prints goes to a temporary file, read once it exits: a pipe would also wait on every process it leaves running. */
static void sharp_run_compile_command(const sharp_compiled *compiled)
{
	char *root = estrndup(ZSTR_VAL(compiled->source), compiled->root_length ? compiled->root_length : 1);
	php_stream *output = php_stream_fopen_tmpfile();
	int output_fd = -1;
	int status;
	pid_t child;

	ZVAL_NULL(&SHARP_G(compile_command_report));
	if (output && php_stream_cast(output, PHP_STREAM_AS_FD, (void **) &output_fd, false) != SUCCESS) {
		output_fd = -1;
	}
	child = fork();
	if (child == 0) {
		int nowhere = open("/dev/null", O_RDWR);

		if (chdir(root) != 0 || nowhere < 0 || dup2(nowhere, STDIN_FILENO) < 0
				|| dup2(output_fd >= 0 ? output_fd : nowhere, STDOUT_FILENO) < 0 || dup2(nowhere, STDERR_FILENO) < 0) {
			_exit(127);
		}
		execl("/usr/bin/env", "env", "MAGO_REPORTING_FORMAT=json", "/bin/sh", "-c", SHARP_G(compile_command), (char *) NULL);
		_exit(127);
	}
	if (child > 0) {
		while (waitpid(child, &status, 0) < 0 && errno == EINTR);
	}
	efree(root);
	if (output) {
		zend_string *printed;

		php_stream_rewind(output);
		printed = php_stream_copy_to_mem(output, PHP_STREAM_COPY_ALL, false);
		php_stream_close(output);
		if (printed) {
			php_json_parser parser;

			php_json_parser_init(&parser, &SHARP_G(compile_command_report), ZSTR_VAL(printed), ZSTR_LEN(printed),
				PHP_JSON_OBJECT_AS_ARRAY, PHP_JSON_PARSER_DEFAULT_DEPTH);
			/* As php_json_decode_ex() does, a failed parse leaves null, whatever the parser built. */
			if (php_json_parse(&parser) != 0) {
				zval_ptr_dtor(&SHARP_G(compile_command_report));
				ZVAL_NULL(&SHARP_G(compile_command_report));
			}
			zend_string_release(printed);
		}
	}
}

static int sharp_parse(void)
{
	const char *source = (const char *) LANG_SCNG(yy_start);
	size_t source_length = LANG_SCNG(yy_limit) - LANG_SCNG(yy_start);
	const char *filename = ZSTR_VAL(zend_get_compiled_filename());
	sharp_compiled compiled;
	sharp_state state;
	bool failed = false;

	sharp_locate(&compiled, filename);
	state = sharp_check(&compiled, source, source_length);
	/* A file with no root has no project to compile, and the current folder is no guess for one. */
	if (state != SHARP_CURRENT && compiled.rooted
			&& SHARP_G(compile_command) && *SHARP_G(compile_command) && Z_ISUNDEF(SHARP_G(compile_command_report))) {
		sharp_run_compile_command(&compiled);
		sharp_release(&compiled);
		sharp_locate(&compiled, filename);
		state = sharp_check(&compiled, source, source_length);
	}
	if (state != SHARP_CURRENT) {
		sharp_refuse(&compiled, state);
		sharp_release(&compiled);
		return FAILURE;
	}

	zend_try {
		CG(ast) = sharp_translate(&compiled.unit, compiled.unit.root);
		CG(zend_lineno) = compiled.unit.nodes[compiled.unit.root].end_line;
	} zend_catch {
		failed = true;
	} zend_end_try();

	sharp_release(&compiled);

	if (failed) {
		zend_bailout();
	}

	return SUCCESS;
}

static bool sharp_is_sharp_file(const zend_string *filename)
{
	size_t length = sizeof(SHARP_FILE_EXTENSION) - 1;

	return ZSTR_LEN(filename) > length
		&& memcmp(ZSTR_VAL(filename) + ZSTR_LEN(filename) - length, SHARP_FILE_EXTENSION, length) == 0;
}

static zend_op_array *sharp_compile_file(zend_file_handle *file_handle, int type)
{
	if (!file_handle->filename || !sharp_is_sharp_file(file_handle->filename)) {
		return sharp_next_compile_file(file_handle, type);
	}

	return zend_compile_file_with(file_handle, type, sharp_parse);
}

/* The revision is the low 63 bits of the compiled file's key, which changes whenever its code may. It runs the
 * checks that need no source, so a source edit reaches it only through the compiled file's inputs. */
static bool sharp_compiled_revision(zend_file_handle *file_handle, zend_long *revision)
{
	zend_string *path = file_handle->opened_path ? file_handle->opened_path : file_handle->filename;
	sharp_compiled compiled;

	if (!path || !sharp_is_sharp_file(path)) {
		return false;
	}

	sharp_locate(&compiled, ZSTR_VAL(path));
	*revision = 0;
	if (sharp_check(&compiled, NULL, 0) == SHARP_CURRENT) {
		const uint8_t *key = ((const sharp_unit_header *) ZSTR_VAL(compiled.bytes))->key;
		uint64_t low = 0;

		for (int i = 8; i < 16; i++) {
			low = (low << 8) | key[i];
		}
		*revision = (zend_long) (low & ZEND_LONG_MAX);
		if (*revision == 0) {
			*revision = 1;
		}
	}
	sharp_release(&compiled);

	return true;
}

zend_class_entry *sharp_ce_collection;
static zend_object_handlers sharp_collection_handlers;

/* The receiver of a PHP# method call on a List or Map, which PHP stores as an array. It changes the
 * array where it lives: a local or a plain property through its slot, and a property with hooks, or
 * one the caller may not write in place, through write_property with a changed copy. A value that
 * lives nowhere is a copy. It exists only as the $this of that one call. */
typedef struct {
	zval *slot;
	zend_object *owner;
	zend_string *property;
	const zend_class_entry *scope;
	zval value;
	zend_object std;
} sharp_collection;

static sharp_collection *sharp_collection_from(zend_object *object)
{
	return (sharp_collection *) ((char *) object - XtOffsetOf(sharp_collection, std));
}

static zend_object *sharp_collection_create(zend_class_entry *ce)
{
	sharp_collection *collection = zend_object_alloc(sizeof(sharp_collection), ce);

	memset(collection, 0, XtOffsetOf(sharp_collection, std));
	zend_object_std_init(&collection->std, ce);

	return &collection->std;
}

/* A destructor the owner or the copy runs may make another collection, so the fields are cleared
 * before anything is released. */
static void sharp_collection_clear(sharp_collection *collection)
{
	zend_object *owner = collection->owner;
	zend_string *property = collection->property;
	zval value;

	ZVAL_COPY_VALUE(&value, &collection->value);
	memset(collection, 0, XtOffsetOf(sharp_collection, std));
	if (owner) {
		OBJ_RELEASE(owner);
	}
	if (property) {
		zend_string_release(property);
	}
	zval_ptr_dtor(&value);
}

static void sharp_collection_free(zend_object *object)
{
	sharp_collection_clear(sharp_collection_from(object));
	zend_object_std_dtor(object);
}

/* Making and freeing an object costs more than a collection method, so a request keeps a few
 * collections for reuse. dtor_obj runs when the last reference goes: after the call returns, or as an
 * exception unwinds a call whose arguments threw. It lets go of the owner and the copy then, and keeps
 * the collection alive as a spare, as a destructor may keep its $this. */
static void sharp_collection_release(zend_object *object)
{
	zend_object **spares = SHARP_G(spare_collections);

	sharp_collection_clear(sharp_collection_from(object));
	for (uint32_t i = 0; i < SHARP_SPARE_COLLECTIONS; i++) {
		if (spares[i] == object) {
			return;
		}
	}
	for (uint32_t i = 0; i < SHARP_SPARE_COLLECTIONS; i++) {
		if (!spares[i]) {
			spares[i] = object;
			GC_ADDREF(object);
			GC_DEL_FLAGS(object, IS_OBJ_DESTRUCTOR_CALLED);
			return;
		}
	}
}

static sharp_collection *sharp_collection_new(zval *result)
{
	zend_object **spares = SHARP_G(spare_collections);

	for (uint32_t i = 0; i < SHARP_SPARE_COLLECTIONS; i++) {
		if (spares[i]) {
			ZVAL_OBJ(result, spares[i]);
			spares[i] = NULL;

			return sharp_collection_from(Z_OBJ_P(result));
		}
	}
	ZVAL_OBJ(result, sharp_collection_create(sharp_ce_collection));

	return sharp_collection_from(Z_OBJ_P(result));
}

void sharp_collection_of_local(zval *result, zval *local)
{
	sharp_collection_new(result)->slot = local;
}

void sharp_collection_of_value(zval *result, zval *value)
{
	ZVAL_COPY_DEREF(&sharp_collection_new(result)->value, value);
}

/* result holds the array read from the property, and the caller's frame is still the current one. */
void sharp_collection_of_property(zval *result, zend_object *object, zend_string *name, void **cache_slot)
{
	zval array;
	ZVAL_COPY_VALUE(&array, result);
	sharp_collection *collection = sharp_collection_new(result);
	/* NULL for a property with hooks, a readonly one, or one the caller may not set. A dynamic
	 * property lives in a hash table that may move before the call runs. */
	zval *slot = object->handlers->get_property_ptr_ptr(object, name, BP_VAR_RW, cache_slot);

	GC_ADDREF(object);
	collection->owner = object;
	if (slot && slot >= object->properties_table && slot < object->properties_table + object->ce->default_properties_count) {
		collection->slot = slot;
		zval_ptr_dtor(&array);
	} else {
		collection->property = zend_string_copy(name);
		collection->scope = zend_get_executed_scope();
		ZVAL_COPY_VALUE(&collection->value, &array);
	}
}

/* Plain PHP code may unset a property between the fetch and the call. */
static zval *sharp_collection_array(sharp_collection *collection)
{
	zval *array = collection->slot ? collection->slot : &collection->value;

	ZVAL_DEREF(array);
	if (UNEXPECTED(Z_TYPE_P(array) != IS_ARRAY)) {
		zend_throw_error(NULL, "Cannot call %s() on a collection that was unset", get_active_function_name());
		return NULL;
	}

	return array;
}

static HashTable *sharp_collection_change(sharp_collection *collection)
{
	zval *array = sharp_collection_array(collection);

	if (!array) {
		return NULL;
	}
	SEPARATE_ARRAY(array);

	return Z_ARRVAL_P(array);
}

/* Runs a property's set hook once with the changed array, in the caller's scope. */
static void sharp_collection_write_back(sharp_collection *collection)
{
	if (!collection->property) {
		return;
	}

	const zend_class_entry *scope = EG(fake_scope);
	EG(fake_scope) = collection->scope;
	collection->owner->handlers->write_property(collection->owner, collection->property, &collection->value, NULL);
	EG(fake_scope) = scope;
}

typedef enum {
	SHARP_PARSED,
	SHARP_NOT_A_STRING,
	SHARP_NOT_A_NUMBER,
	SHARP_OUT_OF_RANGE,
} sharp_parse_result;

/* The white space C#'s int.Parse skips: U+0009 to U+000D and U+0020. */
static bool sharp_is_space(char c)
{
	return c == ' ' || (c >= '\t' && c <= '\r');
}

static const char *sharp_skip_digits(const char *p)
{
	while (*p >= '0' && *p <= '9') {
		p++;
	}

	return p;
}

/* Accepts what C#'s int.Parse accepts: [ws][sign]digits[ws], in ASCII digits only. */
static sharp_parse_result sharp_parse_int(const zval *value, zend_long *result)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return SHARP_NOT_A_STRING;
	}

	const char *p = Z_STRVAL_P(value);
	const char *end = p + Z_STRLEN_P(value);
	while (sharp_is_space(*p)) {
		p++;
	}
	bool negative = *p == '-';
	if (*p == '-' || *p == '+') {
		p++;
	}
	const char *digits = p;
	p = sharp_skip_digits(p);
	if (p == digits) {
		return SHARP_NOT_A_NUMBER;
	}
	const char *digits_end = p;
	while (sharp_is_space(*p)) {
		p++;
	}
	if (p != end) {
		return SHARP_NOT_A_NUMBER;
	}

	zend_ulong limit = negative ? (zend_ulong) ZEND_LONG_MAX + 1 : (zend_ulong) ZEND_LONG_MAX;
	zend_ulong magnitude = 0;
	for (p = digits; p < digits_end; p++) {
		zend_ulong digit = *p - '0';
		if (magnitude > (limit - digit) / 10) {
			return SHARP_OUT_OF_RANGE;
		}
		magnitude = magnitude * 10 + digit;
	}
	*result = negative ? (zend_long) (0 - magnitude) : (zend_long) magnitude;

	return SHARP_PARSED;
}

/* Accepts [ws][sign](digits[.digits] | .digits)([eE][sign]digits)?[ws], so no thousands separator, NaN or Infinity. */
static sharp_parse_result sharp_parse_float(const zval *value, double *result)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return SHARP_NOT_A_STRING;
	}

	const char *p = Z_STRVAL_P(value);
	const char *end = p + Z_STRLEN_P(value);
	while (sharp_is_space(*p)) {
		p++;
	}
	const char *number = p;
	if (*p == '-' || *p == '+') {
		p++;
	}
	const char *integer = p;
	p = sharp_skip_digits(p);
	if (*p == '.') {
		const char *fraction = ++p;
		p = sharp_skip_digits(p);
		if (p == fraction) {
			return SHARP_NOT_A_NUMBER;
		}
	} else if (p == integer) {
		return SHARP_NOT_A_NUMBER;
	}
	if (*p == 'e' || *p == 'E') {
		p++;
		if (*p == '-' || *p == '+') {
			p++;
		}
		const char *exponent = p;
		p = sharp_skip_digits(p);
		if (p == exponent) {
			return SHARP_NOT_A_NUMBER;
		}
	}
	while (sharp_is_space(*p)) {
		p++;
	}
	if (p != end) {
		return SHARP_NOT_A_NUMBER;
	}

	*result = zend_strtod(number, NULL);

	return zend_finite(*result) ? SHARP_PARSED : SHARP_OUT_OF_RANGE;
}

/* Shows the string the way an unhandled match case does, so zend.exception_ignore_args hides request data. */
static ZEND_COLD void sharp_throw_parse_error(sharp_parse_result parsed, const zval *value, const char *number, const char *range)
{
	if (parsed == SHARP_NOT_A_STRING) {
		zend_argument_value_error(1, "must be a string, %s given", zend_zval_value_name(value));
		return;
	}

	zend_long max_len = EG(exception_string_param_max_len);
	smart_str shown = {0};
	if (EG(exception_ignore_args) || max_len == 0) {
		smart_str_appends(&shown, "string");
	} else {
		smart_str_append_scalar(&shown, value, max_len);
	}
	smart_str_0(&shown);

	if (parsed == SHARP_OUT_OF_RANGE) {
		zend_argument_error(zend_ce_arithmetic_error, 1, "must hold %s, %s given", range, ZSTR_VAL(shown.s));
	} else {
		zend_argument_value_error(1, "must hold %s, %s given", number, ZSTR_VAL(shown.s));
	}

	smart_str_free(&shown);
}

ZEND_METHOD(Sharp_Int, parse)
{
	zval *value;
	zend_long result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_parse_result parsed = sharp_parse_int(value, &result);
	if (parsed != SHARP_PARSED) {
		sharp_throw_parse_error(parsed, value, "an int", "an int from PHP_INT_MIN to PHP_INT_MAX");
		RETURN_THROWS();
	}

	RETURN_LONG(result);
}

ZEND_METHOD(Sharp_Int, tryParse)
{
	zval *value;
	zend_long result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (sharp_parse_int(value, &result) != SHARP_PARSED) {
		RETURN_NULL();
	}

	RETURN_LONG(result);
}

ZEND_METHOD(Sharp_Float, parse)
{
	zval *value;
	double result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_parse_result parsed = sharp_parse_float(value, &result);
	if (parsed != SHARP_PARSED) {
		sharp_throw_parse_error(parsed, value, "a float", "a float from -PHP_FLOAT_MAX to PHP_FLOAT_MAX");
		RETURN_THROWS();
	}

	RETURN_DOUBLE(result);
}

ZEND_METHOD(Sharp_Float, tryParse)
{
	zval *value;
	double result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (sharp_parse_float(value, &result) != SHARP_PARSED) {
		RETURN_NULL();
	}

	RETURN_DOUBLE(result);
}

ZEND_METHOD(Sharp_Collection, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(Sharp_Collection, add)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	HashTable *array = sharp_collection_change(collection);
	if (!array) {
		RETURN_THROWS();
	}
	Z_TRY_ADDREF_P(value);
	if (!zend_hash_next_index_insert(array, value)) {
		zval_ptr_dtor(value);
		zend_cannot_add_element();
		RETURN_THROWS();
	}
	sharp_collection_write_back(collection);
}

ZEND_METHOD(Sharp_Collection, set)
{
	zend_long index;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_LONG(index)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	zval *array = sharp_collection_array(collection);
	if (!array) {
		RETURN_THROWS();
	}
	if (!zend_hash_index_exists(Z_ARRVAL_P(array), index)) {
		zend_throw_exception_ex(spl_ce_OutOfRangeException, 0, "Undefined array key " ZEND_LONG_FMT, index);
		RETURN_THROWS();
	}

	/* The old element is released last, so a destructor it runs sees the changed collection. */
	zval *element = zend_hash_index_find(sharp_collection_change(collection), index);
	zval old;
	ZVAL_COPY_VALUE(&old, element);
	ZVAL_COPY(element, value);
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);
}

/* The key a Map method takes: an int, a string, or a backed enum case, which stands for its value. */
static bool sharp_collection_key(zval *key, zend_string **string_key, zend_long *long_key)
{
	zval *value = zend_sharp_enum_key(key);

	if (value) {
		key = value;
	}
	if (Z_TYPE_P(key) == IS_LONG) {
		*string_key = NULL;
		*long_key = Z_LVAL_P(key);
		return true;
	}
	if (Z_TYPE_P(key) == IS_STRING) {
		*string_key = Z_STR_P(key);
		return true;
	}

	zend_argument_type_error(1, "must be of type BackedEnum|string|int, %s given", zend_zval_value_name(key));
	return false;
}

ZEND_METHOD(Sharp_Collection, get)
{
	zval *key;
	zend_string *string_key;
	zend_long long_key;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_collection_key(key, &string_key, &long_key)) {
		RETURN_THROWS();
	}

	zval *array = sharp_collection_array(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	if (!array) {
		RETURN_THROWS();
	}
	zval *element = string_key
		? zend_symtable_find(Z_ARRVAL_P(array), string_key)
		: zend_hash_index_find(Z_ARRVAL_P(array), long_key);
	if (!element) {
		RETURN_NULL();
	}

	RETURN_COPY_DEREF(element);
}

ZEND_METHOD(Sharp_Collection, entries)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zval *array = sharp_collection_array(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	if (!array) {
		RETURN_THROWS();
	}

	RETURN_COPY(array);
}

ZEND_METHOD(Sharp_Collection, delete)
{
	zval *key;
	zend_string *string_key;
	zend_long long_key;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_collection_key(key, &string_key, &long_key)) {
		RETURN_THROWS();
	}

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	zval *array = sharp_collection_array(collection);
	if (!array) {
		RETURN_THROWS();
	}
	if (string_key
		? !zend_symtable_exists(Z_ARRVAL_P(array), string_key)
		: !zend_hash_index_exists(Z_ARRVAL_P(array), long_key)) {
		return;
	}

	HashTable *changed = sharp_collection_change(collection);
	if (string_key) {
		zend_symtable_del(changed, string_key);
	} else {
		zend_hash_index_del(changed, long_key);
	}
	sharp_collection_write_back(collection);
}

/* The array a method that takes a function reads, held while the function runs, since the function
 * may change the collection: a change then separates the collection from this array. */
static zend_array *sharp_collection_read(zval *this_ptr)
{
	zval *array = sharp_collection_array(sharp_collection_from(Z_OBJ_P(this_ptr)));

	if (!array) {
		return NULL;
	}
	GC_TRY_ADDREF(Z_ARRVAL_P(array));

	return Z_ARRVAL_P(array);
}

/* Calls a collection method's function with one element. Returns false when the function threw. */
static bool sharp_collection_call(zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *element, zval *result)
{
	fci->retval = result;
	fci->params = element;
	fci->param_count = 1;
	zend_call_function(fci, fcc);

	return !EG(exception);
}

/* Calls the function with each element, and keeps an element the function returns true for. */
static void sharp_collection_filter(INTERNAL_FUNCTION_PARAMETERS, bool keep_keys)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_collection_read(ZEND_THIS);
	if (!elements) {
		RETURN_THROWS();
	}

	zval kept, keep;
	zend_string *string_key;
	zend_ulong long_key;
	zval *element;
	array_init(&kept);
	ZEND_HASH_FOREACH_KEY_VAL(elements, long_key, string_key, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &keep)) {
			break;
		}
		if (zend_is_true(&keep)) {
			Z_TRY_ADDREF_P(element);
			if (!keep_keys) {
				zend_hash_next_index_insert_new(Z_ARRVAL(kept), element);
			} else if (string_key) {
				zend_hash_add_new(Z_ARRVAL(kept), string_key, element);
			} else {
				zend_hash_index_add_new(Z_ARRVAL(kept), long_key, element);
			}
		}
		zval_ptr_dtor(&keep);
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (EG(exception)) {
		zval_ptr_dtor(&kept);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&kept);
}

/* Spec section 12: filter renumbers what it keeps. */
ZEND_METHOD(Sharp_Collection, filter)
{
	sharp_collection_filter(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

/* Spec section 12: filterValues keeps the keys. */
ZEND_METHOD(Sharp_Collection, filterValues)
{
	sharp_collection_filter(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

ZEND_METHOD(Sharp_Collection, map)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_collection_read(ZEND_THIS);
	if (!elements) {
		RETURN_THROWS();
	}

	zval mapped, value;
	zval *element;
	array_init_size(&mapped, zend_hash_num_elements(elements));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &value)) {
			break;
		}
		zend_hash_next_index_insert_new(Z_ARRVAL(mapped), &value);
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (EG(exception)) {
		zval_ptr_dtor(&mapped);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&mapped);
}

/* Adds as PHP#'s + does, which throws where an int sum overflows. */
ZEND_METHOD(Sharp_Collection, sumOf)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_collection_read(ZEND_THIS);
	if (!elements) {
		RETURN_THROWS();
	}

	zval sum, value;
	zval *element;
	ZVAL_LONG(&sum, 0);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &value)) {
			break;
		}
		checked_add_function(&sum, &sum, &value);
		zval_ptr_dtor(&value);
		if (EG(exception)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (EG(exception)) {
		zval_ptr_dtor(&sum);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&sum);
}

/* Finds the first element the function returns true for, and returns NULL when none is found or the
 * function threw. */
static zval *sharp_collection_find(INTERNAL_FUNCTION_PARAMETERS, zend_array **elements)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END_EX(return NULL);

	*elements = sharp_collection_read(ZEND_THIS);
	if (!*elements) {
		return NULL;
	}

	zval found;
	zval *element;
	ZEND_HASH_FOREACH_VAL(*elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &found)) {
			return NULL;
		}
		bool is_found = zend_is_true(&found);
		zval_ptr_dtor(&found);
		if (is_found) {
			return element;
		}
	} ZEND_HASH_FOREACH_END();

	return NULL;
}

/* Kotlin's first: the first element the function returns true for, and an exception when there is
 * none, as a bare index read throws for a missing index. */
ZEND_METHOD(Sharp_Collection, first)
{
	zend_array *elements = NULL;
	zval *element = sharp_collection_find(INTERNAL_FUNCTION_PARAM_PASSTHRU, &elements);

	if (element) {
		ZVAL_COPY_DEREF(return_value, element);
	} else if (!EG(exception)) {
		zend_throw_exception(spl_ce_OutOfRangeException, "No element matches the predicate", 0);
	}
	if (elements) {
		zend_array_release(elements);
	}
}

ZEND_METHOD(Sharp_Collection, any)
{
	zend_array *elements = NULL;
	zval *element = sharp_collection_find(INTERNAL_FUNCTION_PARAM_PASSTHRU, &elements);

	if (elements) {
		zend_array_release(elements);
	}
	if (EG(exception)) {
		RETURN_THROWS();
	}
	RETURN_BOOL(element != NULL);
}

/* The slot of a map under the key a function gave, which spec section 12 makes an int or a string. A
 * string of digits is the int key, as in any PHP array. */
static zval *sharp_collection_key_slot(HashTable *map, zval *key)
{
	zend_ulong index;

	switch (Z_TYPE_P(key)) {
		case IS_LONG:
			return zend_hash_index_lookup(map, Z_LVAL_P(key));
		case IS_STRING:
			if (ZEND_HANDLE_NUMERIC_STR(Z_STRVAL_P(key), Z_STRLEN_P(key), index)) {
				return zend_hash_index_lookup(map, index);
			}
			return zend_hash_lookup(map, Z_STR_P(key));
		default:
			zend_type_error("A map key must be of type int|string, %s given", zend_zval_value_name(key));
			return NULL;
	}
}

/* Puts each element under the key the function gives it: in a list of the elements under that key,
 * or alone, where the last element with a key replaces the ones before it. */
static void sharp_collection_key_by(INTERNAL_FUNCTION_PARAMETERS, bool group)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_collection_read(ZEND_THIS);
	if (!elements) {
		RETURN_THROWS();
	}

	zval map, key;
	zval *element;
	array_init(&map);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &key)) {
			break;
		}
		zval *slot = sharp_collection_key_slot(Z_ARRVAL(map), &key);
		zval_ptr_dtor(&key);
		if (!slot) {
			break;
		}
		Z_TRY_ADDREF_P(element);
		if (!group) {
			zval_ptr_dtor(slot);
			ZVAL_COPY_VALUE(slot, element);
			continue;
		}
		if (Z_TYPE_P(slot) == IS_NULL) {
			array_init(slot);
		}
		zend_hash_next_index_insert_new(Z_ARRVAL_P(slot), element);
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (EG(exception)) {
		zval_ptr_dtor(&map);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&map);
}

ZEND_METHOD(Sharp_Collection, groupBy)
{
	sharp_collection_key_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

ZEND_METHOD(Sharp_Collection, associateBy)
{
	sharp_collection_key_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

/* Orders two [key, element] pairs by key, as PHP's <=> does, and equal keys by their original order,
 * which zend_hash_sort keeps in each bucket's extra space, so the sort is stable as Kotlin's is. */
static int sharp_collection_compare_keys(Bucket *a, Bucket *b)
{
	int result = zend_compare(zend_hash_index_find(Z_ARRVAL(a->val), 0), zend_hash_index_find(Z_ARRVAL(b->val), 0));

	if (result) {
		return result;
	}

	return Z_EXTRA(a->val) < Z_EXTRA(b->val) ? -1 : Z_EXTRA(a->val) > Z_EXTRA(b->val);
}

ZEND_METHOD(Sharp_Collection, sortedBy)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_collection_read(ZEND_THIS);
	if (!elements) {
		RETURN_THROWS();
	}

	zval pairs, pair, key;
	zval *element;
	array_init_size(&pairs, zend_hash_num_elements(elements));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &key)) {
			break;
		}
		array_init_size(&pair, 2);
		zend_hash_next_index_insert_new(Z_ARRVAL(pair), &key);
		Z_TRY_ADDREF_P(element);
		zend_hash_next_index_insert_new(Z_ARRVAL(pair), element);
		zend_hash_next_index_insert_new(Z_ARRVAL(pairs), &pair);
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (!EG(exception)) {
		zend_hash_sort(Z_ARRVAL(pairs), sharp_collection_compare_keys, true);
	}
	if (EG(exception)) {
		zval_ptr_dtor(&pairs);
		RETURN_THROWS();
	}

	zval *sorted_pair;
	array_init_size(return_value, zend_hash_num_elements(Z_ARRVAL(pairs)));
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL(pairs), sorted_pair) {
		element = zend_hash_index_find(Z_ARRVAL_P(sorted_pair), 1);
		Z_TRY_ADDREF_P(element);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), element);
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&pairs);
}

static PHP_MINIT_FUNCTION(sharp)
{
	REGISTER_INI_ENTRIES();
	register_class_Sharp_Int();
	register_class_Sharp_Float();
	sharp_ce_collection = register_class_Sharp_Collection();
	sharp_ce_collection->create_object = sharp_collection_create;
	memcpy(&sharp_collection_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_collection_handlers.offset = XtOffsetOf(sharp_collection, std);
	sharp_collection_handlers.dtor_obj = sharp_collection_release;
	sharp_collection_handlers.free_obj = sharp_collection_free;
	sharp_collection_handlers.clone_obj = NULL;
	sharp_ce_collection->default_object_handlers = &sharp_collection_handlers;

	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_next_compile_file = zend_compile_file;
	zend_compile_file = sharp_compile_file;
	zend_compiled_revision = sharp_compiled_revision;

	return SUCCESS;
}

static PHP_MSHUTDOWN_FUNCTION(sharp)
{
	UNREGISTER_INI_ENTRIES();

	return SUCCESS;
}

static PHP_GINIT_FUNCTION(sharp)
{
	memset(sharp_globals, 0, sizeof(*sharp_globals));
	zend_hash_init(&sharp_globals->folder_roots, 8, NULL, NULL, true);
}

static PHP_GSHUTDOWN_FUNCTION(sharp)
{
	zend_hash_destroy(&sharp_globals->folder_roots);
}

static PHP_RINIT_FUNCTION(sharp)
{
	zend_hash_init(&SHARP_G(request_stamps), 8, NULL, sharp_free_stamp, false);
	ZVAL_UNDEF(&SHARP_G(compile_command_report));

	return SUCCESS;
}

/* Runs after the request's destructors and before its objects are freed. A spare is freed without
 * its dtor_obj, which would keep it. */
static PHP_RSHUTDOWN_FUNCTION(sharp)
{
	zend_object **spares = SHARP_G(spare_collections);

	for (uint32_t i = 0; i < SHARP_SPARE_COLLECTIONS; i++) {
		zend_object *spare = spares[i];

		if (spare) {
			spares[i] = NULL;
			GC_ADD_FLAGS(spare, IS_OBJ_DESTRUCTOR_CALLED);
			OBJ_RELEASE(spare);
		}
	}
	zend_hash_destroy(&SHARP_G(request_stamps));
	zval_ptr_dtor(&SHARP_G(compile_command_report));

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "Mago commit", SHARP_MAGO_COMMIT);
	php_info_print_table_end();

	DISPLAY_INI_ENTRIES();
}

zend_module_entry sharp_module_entry = {
	STANDARD_MODULE_HEADER,
	"sharp",
	NULL,
	PHP_MINIT(sharp),
	PHP_MSHUTDOWN(sharp),
	PHP_RINIT(sharp),
	PHP_RSHUTDOWN(sharp),
	PHP_MINFO(sharp),
	PHP_VERSION,
	PHP_MODULE_GLOBALS(sharp),
	PHP_GINIT(sharp),
	PHP_GSHUTDOWN(sharp),
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};

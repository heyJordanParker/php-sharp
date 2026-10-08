#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include <errno.h>
#include <fcntl.h>
#include <sys/wait.h>
#include <unistd.h>

#include "php.h"
#include "php_ini.h"
#include "SAPI.h"
#include "ext/hash/php_hash.h"
#include "ext/hash/php_hash_xxhash.h"
#include "ext/json/php_json_parser.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/basic_functions.h"
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
#include "sharp_native.h"

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
	zval arguments;
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

/* Refuses a file with every error the compile command's checker reported for it, in the order they appear in the file.
 * The report covers every file the command compiled, lists errors by checking pass rather than by line, and counts
 * lines from 0. Returns false when the report holds no error for the file. */
static ZEND_COLD bool sharp_refuse_checked(const sharp_compiled *compiled, const char *name)
{
	zval *issues = sharp_member(&SHARP_G(compile_command_report), "issues", IS_ARRAY), *issue;
	struct { zend_long offset, line; zend_string *message; } *errors;
	uint32_t count = 0;

	if (!issues) {
		return false;
	}
	errors = safe_emalloc(zend_hash_num_elements(Z_ARRVAL_P(issues)), sizeof(*errors), 0);
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(issues), issue) {
		zval *level = sharp_member(issue, "level", IS_STRING), *text = sharp_member(issue, "message", IS_STRING);
		zval *annotations = sharp_member(issue, "annotations", IS_ARRAY), *annotation, *span = NULL, *path, *start, *offset, *start_line;
		uint32_t at;

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
		/* Inserted after every error that starts at or before it, so errors at one offset keep the report's order. */
		for (at = count++; at > 0 && errors[at - 1].offset > Z_LVAL_P(offset); at--) {
			errors[at] = errors[at - 1];
		}
		errors[at].offset = Z_LVAL_P(offset);
		errors[at].line = Z_LVAL_P(start_line) + 1;
		errors[at].message = Z_STR_P(text);
	} ZEND_HASH_FOREACH_END();

	if (count > 0) {
		smart_str refusal = {0};

		smart_str_append_printf(&refusal, "%s has %u error%s:", name, count, count == 1 ? "" : "s");
		for (uint32_t i = 0; i < count; i++) {
			smart_str_append_printf(&refusal, "\nline " ZEND_LONG_FMT ": ", errors[i].line);
			smart_str_append(&refusal, errors[i].message);
		}
		smart_str_0(&refusal);
		zend_throw_exception(zend_ce_compile_error, ZSTR_VAL(refusal.s), 0);
		smart_str_free(&refusal);
	}
	efree(errors);

	return count > 0;
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
	if (Z_TYPE_P(key) == IS_OBJECT && instanceof_function(Z_OBJCE_P(key), zend_ce_backed_enum)) {
		key = zend_enum_fetch_case_value(Z_OBJ_P(key));
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

/* The standard library's autoload.php passes the SHARP_NATIVE it was built with and its package's version, as
 * Composer's platform_check.php checks the platform before anything loads. */
static ZEND_FUNCTION(Sharp_Internal_requireNative)
{
	zend_string *fingerprint;
	zend_string *version;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(fingerprint)
		Z_PARAM_STR(version)
	ZEND_PARSE_PARAMETERS_END();

	if (!zend_string_equals_literal(fingerprint, SHARP_NATIVE)) {
		zend_throw_error(NULL, "The PHP# standard library %s needs the native bodies of PHP# engine %s, and this engine is "
			PHP_SHARP_VERSION ". Install the same PHP# version of both.", ZSTR_VAL(version), ZSTR_VAL(version));
	}
}

ZEND_METHOD(Sharp_Position, __construct)
{
	zend_string *file, *function;
	zend_long line, column;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_STR(file)
		Z_PARAM_LONG(line)
		Z_PARAM_LONG(column)
		Z_PARAM_STR(function)
	ZEND_PARSE_PARAMETERS_END();

	zend_object *position = Z_OBJ_P(ZEND_THIS);
	zend_update_property_str(position->ce, position, ZEND_STRL("file"), file);
	if (EG(exception)) {
		RETURN_THROWS();
	}

	zend_string *directory = zend_string_init(ZSTR_VAL(file), ZSTR_LEN(file), 0);
	ZSTR_LEN(directory) = zend_dirname(ZSTR_VAL(directory), ZSTR_LEN(directory));
	zend_update_property_str(position->ce, position, ZEND_STRL("directory"), directory);
	zend_string_release(directory);
	zend_update_property_long(position->ce, position, ZEND_STRL("line"), line);
	zend_update_property_long(position->ce, position, ZEND_STRL("column"), column);
	zend_update_property_str(position->ce, position, ZEND_STRL("function"), function);
}

/* Type texts, see sharp_type in php_sharp.h. A text parses in its one canonical spelling only, so equal types are
 * one pointer: a single space after each comma and nowhere else, union members sorted by their text, and `T?` in
 * place of `T|null`. Types and argument lists live in two tables, because a one-argument list spells its argument. */

zend_string *sharp_type_arguments_key;

static HashTable sharp_types;
static HashTable sharp_type_lists;
#ifdef ZTS
static MUTEX_T sharp_types_lock;
#endif

static const char *const sharp_built_in_types[] = {
	"int", "float", "bool", "string", "void", "null", "Any", "Object", "List", "Map", "Set", "Iterable", "Class",
};

typedef struct {
	const char *at;
	const char *end;
} sharp_type_reader;

typedef struct {
	const sharp_type **items;
	uint32_t count;
	uint32_t size;
} sharp_type_members;

static const sharp_type *sharp_type_read(sharp_type_reader *reader);

/* A persistent string no request frees or counts, as an interned string is. */
static zend_string *sharp_type_string(const char *text, size_t length)
{
	zend_string *string = zend_string_init(text, length, true);

	zend_string_hash_val(string);
	GC_TYPE_INFO(string) = GC_STRING | ((IS_STR_INTERNED | IS_STR_PERSISTENT | IS_STR_PERMANENT) << GC_FLAGS_SHIFT);

	return string;
}

static void sharp_type_free(zval *entry)
{
	sharp_type *type = Z_PTR_P(entry);

	if (type->class_name) {
		pefree(type->class_name, true);
	}
	pefree(type->text, true);
	pefree(type, true);
}

static void sharp_type_members_add(sharp_type_members *members, const sharp_type *member)
{
	if (members->count == members->size) {
		members->size = members->size ? members->size * 2 : 4;
		members->items = erealloc(members->items, members->size * sizeof(*members->items));
	}
	members->items[members->count++] = member;
}

static bool sharp_type_skip(sharp_type_reader *reader, const char *token)
{
	size_t length = strlen(token);

	if ((size_t) (reader->end - reader->at) < length || memcmp(reader->at, token, length) != 0) {
		return false;
	}
	reader->at += length;

	return true;
}

static bool sharp_type_identifier(sharp_type_reader *reader)
{
	const char *start = reader->at;

	while (reader->at < reader->end) {
		unsigned char c = *reader->at;

		if (c == '_' || c >= 0x80 || (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z')
			|| (reader->at > start && c >= '0' && c <= '9')) {
			reader->at++;
		} else {
			break;
		}
	}

	return reader->at > start;
}

/* The interned type of `kind` that `length` bytes at `text` spell. `name` is a class's dotted name, or NULL. */
static const sharp_type *sharp_type_intern(
	HashTable *table, sharp_type_kind kind, const char *text, size_t length, const char *name, size_t name_length,
	const sharp_type_members *members)
{
	sharp_type *type = zend_hash_str_find_ptr(table, text, length);

	if (type) {
		return type;
	}

	uint32_t count = members ? members->count : 0;
	type = pemalloc(offsetof(sharp_type, members) + MAX(count, 1) * sizeof(sharp_type *), true);
	type->text = sharp_type_string(text, length);
	type->class_name = NULL;
	if (name) {
		type->class_name = sharp_type_string(name, name_length);
		for (char *c = ZSTR_VAL(type->class_name); *c; c++) {
			if (*c == '.') {
				*c = '\\';
			}
		}
	}
	type->kind = kind;
	type->count = count;
	if (count) {
		memcpy(type->members, members->items, count * sizeof(sharp_type *));
	}
	zend_hash_add_new_ptr(table, type->text, type);

	return type;
}

/* `name`, `name<A, B>` or `Function<R(P1, P2)>`. */
static const sharp_type *sharp_type_read_atom(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_members members = {0};
	const sharp_type *type = NULL;

	if (sharp_type_skip(reader, "Function<")) {
		const sharp_type *returned = sharp_type_read(reader);

		if (!returned || !sharp_type_skip(reader, "(")) {
			goto done;
		}
		sharp_type_members_add(&members, returned);
		if (!sharp_type_skip(reader, ")")) {
			do {
				const sharp_type *parameter = sharp_type_read(reader);

				if (!parameter) {
					goto done;
				}
				sharp_type_members_add(&members, parameter);
			} while (sharp_type_skip(reader, ", "));
			if (!sharp_type_skip(reader, ")")) {
				goto done;
			}
		}
		if (sharp_type_skip(reader, ">")) {
			type = sharp_type_intern(&sharp_types, SHARP_TYPE_FUNCTION, start, reader->at - start, NULL, 0, &members);
		}
		goto done;
	}

	do {
		if (!sharp_type_identifier(reader)) {
			goto done;
		}
	} while (sharp_type_skip(reader, "."));

	size_t name_length = reader->at - start;
	bool built_in = false;
	for (size_t i = 0; i < sizeof(sharp_built_in_types) / sizeof(*sharp_built_in_types); i++) {
		if (strlen(sharp_built_in_types[i]) == name_length && memcmp(sharp_built_in_types[i], start, name_length) == 0) {
			built_in = true;
		}
	}

	if (sharp_type_skip(reader, "<")) {
		do {
			const sharp_type *argument = sharp_type_read(reader);

			if (!argument) {
				goto done;
			}
			sharp_type_members_add(&members, argument);
		} while (sharp_type_skip(reader, ", "));
		if (!sharp_type_skip(reader, ">")) {
			goto done;
		}
	}
	type = sharp_type_intern(&sharp_types, SHARP_TYPE_NAMED, start, reader->at - start,
		built_in ? NULL : start, name_length, &members);

done:
	if (members.items) {
		efree(members.items);
	}

	return type;
}

/* Union members after the first one, `|B|C`, each sorted after the one before it. `null` is never a member, because
 * a nullable union is `(A|B)?`, and neither is `Any`, which holds every other type. */
static bool sharp_type_read_union(sharp_type_reader *reader, sharp_type_members *members)
{
	while (sharp_type_skip(reader, "|")) {
		const sharp_type *member = sharp_type_read_atom(reader);
		const sharp_type *before = members->items[members->count - 1];

		if (!member || zend_binary_strcmp(ZSTR_VAL(before->text), ZSTR_LEN(before->text),
				ZSTR_VAL(member->text), ZSTR_LEN(member->text)) >= 0) {
			return false;
		}
		sharp_type_members_add(members, member);
	}

	for (uint32_t i = 0; i < members->count; i++) {
		if (zend_string_equals_literal(members->items[i]->text, "null")
			|| zend_string_equals_literal(members->items[i]->text, "Any")) {
			return false;
		}
	}

	return members->count > 1;
}

/* A type: an atom, `T?`, `A|B` or `(A|B)?`. */
static const sharp_type *sharp_type_read(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_members members = {0};
	const sharp_type *type = NULL;

#ifdef ZEND_CHECK_STACK_LIMIT
	if (UNEXPECTED(zend_call_stack_overflowed(EG(stack_limit)))) {
		return NULL;
	}
#endif

	if (sharp_type_skip(reader, "(")) {
		const sharp_type *first = sharp_type_read_atom(reader);

		if (first) {
			sharp_type_members_add(&members, first);
			if (sharp_type_read_union(reader, &members) && sharp_type_skip(reader, ")")) {
				const sharp_type *nullable[] = {sharp_type_intern(&sharp_types, SHARP_TYPE_UNION, start + 1,
					reader->at - start - 2, NULL, 0, &members)};

				if (sharp_type_skip(reader, "?")) {
					type = sharp_type_intern(&sharp_types, SHARP_TYPE_NULLABLE, start, reader->at - start, NULL, 0,
						&(sharp_type_members) {.items = nullable, .count = 1});
				}
			}
		}
	} else if ((type = sharp_type_read_atom(reader)) != NULL) {
		if (sharp_type_skip(reader, "?")) {
			const sharp_type *nullable[] = {type};

			type = zend_string_equals_literal(type->text, "null")
				? NULL
				: sharp_type_intern(&sharp_types, SHARP_TYPE_NULLABLE, start, reader->at - start, NULL, 0,
					&(sharp_type_members) {.items = nullable, .count = 1});
		} else if (reader->at < reader->end && *reader->at == '|') {
			sharp_type_members_add(&members, type);
			type = sharp_type_read_union(reader, &members)
				? sharp_type_intern(&sharp_types, SHARP_TYPE_UNION, start, reader->at - start, NULL, 0, &members)
				: NULL;
		}
	}

	if (members.items) {
		efree(members.items);
	}

	return type;
}

const sharp_type *sharp_type_list(const char *text, size_t length)
{
	const sharp_type *list;

#ifdef ZTS
	tsrm_mutex_lock(sharp_types_lock);
#endif
	list = zend_hash_str_find_ptr(&sharp_type_lists, text, length);
	if (!list) {
		sharp_type_reader reader = {text, text + length};
		sharp_type_members members = {0};

		do {
			const sharp_type *argument = sharp_type_read(&reader);

			if (!argument) {
				members.count = 0;
				break;
			}
			sharp_type_members_add(&members, argument);
		} while (sharp_type_skip(&reader, ", "));

		if (members.count && reader.at == reader.end) {
			list = sharp_type_intern(&sharp_type_lists, SHARP_TYPE_LIST, text, length, NULL, 0, &members);
		}
		if (members.items) {
			efree(members.items);
		}
	}
#ifdef ZTS
	tsrm_mutex_unlock(sharp_types_lock);
#endif

	return list;
}

const zend_property_info *sharp_type_arguments_slot(const zend_class_entry *ce)
{
	const zend_property_info *slot = zend_hash_find_ptr(&ce->properties_info, sharp_type_arguments_key);

	return slot && slot->ce == ce && (slot->flags & ZEND_ACC_SHARP_HIDDEN) ? slot : NULL;
}

/* The type arguments `ce`'s objects start with: its bounds, the default of its slot. */
static const sharp_type *sharp_type_bounds(const zend_class_entry *ce, const zend_property_info *slot)
{
	const zval *bounds = &ce->default_properties_table[OBJ_PROP_TO_NUM(slot->offset)];
	const sharp_type *list = sharp_type_list(Z_STRVAL_P(bounds), Z_STRLEN_P(bounds));

	ZEND_ASSERT(list != NULL);

	return list;
}

const sharp_type *sharp_type_arguments(zend_object *object)
{
	const zend_property_info *slot = sharp_type_arguments_slot(object->ce);

	if (!slot) {
		return NULL;
	}

	zval *value = OBJ_PROP(object, slot->offset);
	if (Z_TYPE_P(value) == IS_PTR) {
		return Z_PTR_P(value);
	}

	/* Plain PHP created the object, so its slot holds its class's bounds as a type text, or is UNDEF while the object
	 * is lazy. */
	const sharp_type *bounds = sharp_type_bounds(object->ce, slot);
	if (Z_TYPE_P(value) == IS_STRING) {
		zval_ptr_dtor_str(value);
		ZVAL_PTR(value, (void *) bounds);
	}

	return bounds;
}

void sharp_type_arguments_store(zend_object *object, const sharp_type *arguments)
{
	const zend_property_info *slot = sharp_type_arguments_slot(object->ce);
	zval *value;

	ZEND_ASSERT(slot != NULL && arguments->kind == SHARP_TYPE_LIST);
	value = OBJ_PROP(object, slot->offset);
	zval_ptr_dtor(value);
	ZVAL_PTR(value, (void *) arguments);
}

bool sharp_type_arguments_equal(zend_object *a, zend_object *b)
{
	ZEND_ASSERT(a->ce == b->ce);

	return sharp_type_arguments(a) == sharp_type_arguments(b);
}

/* Whether every class `type` names loads. */
static bool sharp_type_classes_load(const sharp_type *type)
{
	if (type->class_name && !zend_lookup_class(type->class_name)) {
		return false;
	}
	for (uint32_t i = 0; i < type->count; i++) {
		if (!sharp_type_classes_load(type->members[i])) {
			return false;
		}
	}

	return true;
}

zend_result sharp_type_arguments_unserialize(zend_object *object, const zval *text)
{
	const zend_property_info *slot = sharp_type_arguments_slot(object->ce);

	if (!slot || Z_TYPE_P(text) != IS_STRING) {
		return FAILURE;
	}

	const sharp_type *arguments = sharp_type_list(Z_STRVAL_P(text), Z_STRLEN_P(text));
	if (!arguments || arguments->count != sharp_type_bounds(object->ce, slot)->count
		|| !sharp_type_classes_load(arguments)) {
		return FAILURE;
	}
	sharp_type_arguments_store(object, arguments);

	return SUCCESS;
}

static zend_object_handlers sharp_environment_handlers;

static bool sharp_environment_is_property(const zend_string *name)
{
	return zend_string_equals_literal(name, "arguments") || zend_string_equals_literal(name, "currentDirectory");
}

static bool sharp_environment_read(const zend_string *name, zval *result)
{
	if (zend_string_equals_literal(name, "arguments")) {
		ZVAL_COPY(result, &SHARP_G(arguments));
		return true;
	}

	char directory[MAXPATHLEN];
	if (!VCWD_GETCWD(directory, MAXPATHLEN)) {
		zend_throw_error(NULL, "Cannot read the current directory: %s", strerror(errno));
		return false;
	}
	ZVAL_STRING(result, directory);

	return true;
}

static zval *sharp_environment_read_property(zend_object *object, zend_string *name, int type, void **cache_slot, zval *rv)
{
	if (!sharp_environment_is_property(name)) {
		return zend_std_read_property(object, name, type, cache_slot, rv);
	}
	if (type == BP_VAR_W || type == BP_VAR_RW || type == BP_VAR_UNSET) {
		zend_throw_error(NULL, "Indirect modification of %s::$%s is not allowed", ZSTR_VAL(object->ce->name), ZSTR_VAL(name));
		return &EG(uninitialized_zval);
	}

	return sharp_environment_read(name, rv) ? rv : &EG(uninitialized_zval);
}

static zval *sharp_environment_write_property(zend_object *object, zend_string *name, zval *value, void **cache_slot)
{
	if (!sharp_environment_is_property(name)) {
		return zend_std_write_property(object, name, value, cache_slot);
	}
	zend_throw_error(NULL, "Property %s::$%s is read-only", ZSTR_VAL(object->ce->name), ZSTR_VAL(name));

	return &EG(error_zval);
}

static zval *sharp_environment_get_property_ptr_ptr(zend_object *object, zend_string *name, int type, void **cache_slot)
{
	return NULL;
}

static int sharp_environment_has_property(zend_object *object, zend_string *name, int check_empty, void **cache_slot)
{
	if (!sharp_environment_is_property(name)) {
		return zend_std_has_property(object, name, check_empty, cache_slot);
	}
	if (check_empty != ZEND_PROPERTY_NOT_EMPTY) {
		return true;
	}

	zval value;
	if (!sharp_environment_read(name, &value)) {
		return false;
	}
	bool is_true = zend_is_true(&value);
	zval_ptr_dtor(&value);

	return is_true;
}

static void sharp_environment_unset_property(zend_object *object, zend_string *name, void **cache_slot)
{
	if (!sharp_environment_is_property(name)) {
		zend_std_unset_property(object, name, cache_slot);
		return;
	}
	zend_throw_error(NULL, "Cannot unset hooked property %s::$%s", ZSTR_VAL(object->ce->name), ZSTR_VAL(name));
}

ZEND_METHOD(Sharp_Environment, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(Sharp_Environment, variable)
{
	char *name;
	size_t name_length;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(name, name_length)
	ZEND_PARSE_PARAMETERS_END();

	char *sapi_value = sapi_getenv(name, name_length);
	if (sapi_value) {
		RETVAL_STRING(sapi_value);
		efree(sapi_value);
		return;
	}

	zend_string *value = php_getenv(name, name_length);
	if (!value) {
		RETURN_NULL();
	}
	RETURN_STR(value);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_List_wrap, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(Sharp_List, wrap)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (Z_TYPE_P(value) == IS_ARRAY) {
		RETURN_COPY(value);
	}
	array_init_size(return_value, 1);
	Z_TRY_ADDREF_P(value);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), value);
}

static const zend_function_entry class_Sharp_List_methods[] = {
	ZEND_ME(Sharp_List, wrap, arginfo_class_Sharp_List_wrap, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_List(void)
{
	zend_class_entry ce;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "List", class_Sharp_List_methods);

	return zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);
}

static PHP_MINIT_FUNCTION(sharp)
{
	REGISTER_INI_ENTRIES();
	sharp_ce_collection = register_class_Sharp_Collection();
	sharp_ce_collection->create_object = sharp_collection_create;
	memcpy(&sharp_collection_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_collection_handlers.offset = XtOffsetOf(sharp_collection, std);
	sharp_collection_handlers.dtor_obj = sharp_collection_release;
	sharp_collection_handlers.free_obj = sharp_collection_free;
	sharp_collection_handlers.clone_obj = NULL;
	sharp_ce_collection->default_object_handlers = &sharp_collection_handlers;

	register_class_Sharp_Position();
	register_class_Sharp_List();

	zend_class_entry *environment = register_class_Sharp_Environment();
	memcpy(&sharp_environment_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_environment_handlers.read_property = sharp_environment_read_property;
	sharp_environment_handlers.write_property = sharp_environment_write_property;
	sharp_environment_handlers.get_property_ptr_ptr = sharp_environment_get_property_ptr_ptr;
	sharp_environment_handlers.has_property = sharp_environment_has_property;
	sharp_environment_handlers.unset_property = sharp_environment_unset_property;
	environment->default_object_handlers = &sharp_environment_handlers;

	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_type_arguments_key = zend_string_init_interned("\0<sharp>\0types", sizeof("\0<sharp>\0types") - 1, true);
	zend_hash_init(&sharp_types, 64, NULL, sharp_type_free, true);
	zend_hash_init(&sharp_type_lists, 64, NULL, sharp_type_free, true);
#ifdef ZTS
	sharp_types_lock = tsrm_mutex_alloc();
#endif

	sharp_next_compile_file = zend_compile_file;
	zend_compile_file = sharp_compile_file;
	zend_compiled_revision = sharp_compiled_revision;

	return SUCCESS;
}

static PHP_MSHUTDOWN_FUNCTION(sharp)
{
	UNREGISTER_INI_ENTRIES();
	zend_hash_destroy(&sharp_type_lists);
	zend_hash_destroy(&sharp_types);
#ifdef ZTS
	tsrm_mutex_free(sharp_types_lock);
#endif

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

	zval *arguments = &SHARP_G(arguments);

	array_init_size(arguments, SG(request_info).argc);
	for (int i = 0; i < SG(request_info).argc; i++) {
		add_next_index_string(arguments, SG(request_info).argv[i]);
	}

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
	zval_ptr_dtor(&SHARP_G(arguments));
	ZVAL_UNDEF(&SHARP_G(arguments));

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "Version", PHP_SHARP_VERSION);
	php_info_print_table_row(2, "Mago commit", SHARP_MAGO_COMMIT);
	php_info_print_table_end();

	DISPLAY_INI_ENTRIES();
}

zend_module_entry sharp_module_entry = {
	STANDARD_MODULE_HEADER,
	"sharp",
	sharp_native_functions,
	PHP_MINIT(sharp),
	PHP_MSHUTDOWN(sharp),
	PHP_RINIT(sharp),
	PHP_RSHUTDOWN(sharp),
	PHP_MINFO(sharp),
	PHP_SHARP_VERSION,
	PHP_MODULE_GLOBALS(sharp),
	PHP_GINIT(sharp),
	PHP_GSHUTDOWN(sharp),
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};

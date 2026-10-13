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
#include "ext/json/php_json.h"
#include "ext/json/php_json_parser.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/basic_functions.h"
#include "ext/standard/info.h"
#include "zend_closures.h"
#include "zend_enum.h"
#include "zend_exceptions.h"
#include "zend_interfaces.h"
#include "zend_language_parser.h"
#include "zend_smart_str.h"
#include "zend_system_id.h"
#include "php_sharp.h"
#include "sharp_table.h"

/* ext/json exports JsonException's class entry, and php_json.h declares only JsonSerializable's. */
extern PHP_JSON_API zend_class_entry *php_json_exception_ce;
#include "sharp_arginfo.h"
#include "sharp_unit.h"
#include "sharp_build_id.h"
#include "sharp_native.h"

#define SHARP_FILE_EXTENSION ".sharp"
#define SHARP_COMPILED_FOLDER ".sharp"

#define SHARP_KIND_IS_ZEND_KIND(kind) \
	ZEND_STATIC_ASSERT((zend_ast_kind) SHARP_AST_##kind == ZEND_AST_##kind, "SHARP_AST_" #kind " differs from ZEND_AST_" #kind);
SHARP_KINDS(SHARP_KIND_IS_ZEND_KIND)
ZEND_STATIC_ASSERT(SHARP_T_FILE == T_FILE, "SHARP_T_FILE differs from T_FILE");

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
 * array where it lives: a local, a plain property or a static property through its slot, and a
 * property with hooks, or one the caller may not write in place, through write_property with a
 * changed copy. A static property the caller may not set keeps a copy, and a change throws as an
 * assignment to it does. A value that lives nowhere is a copy. An element of a List, read by index
 * from the collection that holds it, lives at that index there, so it changes through that
 * collection, as `grid[0].add(5)` does (spec section 12). It exists only as the $this of that one
 * call, or as the collection an element was read from. */
typedef struct {
	zval *slot;
	zend_object *owner;
	zend_string *property;
	const zend_property_info *static_property;
	const zend_class_entry *scope;
	zend_object *container;
	zval index;
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
	zend_object *container = collection->container;
	zval index, value;

	ZVAL_COPY_VALUE(&index, &collection->index);
	ZVAL_COPY_VALUE(&value, &collection->value);
	memset(collection, 0, XtOffsetOf(sharp_collection, std));
	if (owner) {
		OBJ_RELEASE(owner);
	}
	if (property) {
		zend_string_release(property);
	}
	if (container) {
		OBJ_RELEASE(container);
	}
	zval_ptr_dtor(&index);
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

/* result holds the array read from the static property at slot, and the caller's frame is still the
 * current one. PHP refuses hooks and readonly on a static property, so only private(set) and
 * protected(set) keep the caller from changing it in place. */
void sharp_collection_of_static_property(zval *result, zval *slot, const zend_property_info *info)
{
	zval array;
	ZVAL_COPY_VALUE(&array, result);
	sharp_collection *collection = sharp_collection_new(result);

	if (!(info->flags & ZEND_ACC_PPP_SET_MASK) || zend_asymmetric_property_has_set_access(info)) {
		collection->slot = slot;
		zval_ptr_dtor(&array);
	} else {
		collection->static_property = info;
		collection->scope = zend_get_executed_scope();
		ZVAL_COPY_VALUE(&collection->value, &array);
	}
}

/* The key a Map method or an index takes: an int or a string. */
static bool sharp_collection_key(zval *key, zend_string **string_key, zend_long *long_key)
{
	if (Z_TYPE_P(key) == IS_LONG) {
		*string_key = NULL;
		*long_key = Z_LVAL_P(key);
		return true;
	}
	if (Z_TYPE_P(key) == IS_STRING) {
		*string_key = Z_STR_P(key);
		return true;
	}

	zend_argument_type_error(1, "must be of type string|int, %s given", zend_zval_value_name(key));
	return false;
}

/* The element at index. For a missing key it is NULL after throwing OutOfRangeException, as a PHP#
 * index read does, or NULL alone when quiet, as a `?.` read is (spec section 12). */
static zval *sharp_collection_element(HashTable *array, zval *index, bool quiet)
{
	zend_string *string_key;
	zend_long long_key;

	if (!sharp_collection_key(index, &string_key, &long_key)) {
		return NULL;
	}
	zval *element = string_key ? zend_symtable_find(array, string_key) : zend_hash_index_find(array, long_key);
	if (!element && !quiet) {
		if (string_key) {
			zend_throw_exception_ex(spl_ce_OutOfRangeException, 0, "Undefined array key \"%s\"", ZSTR_VAL(string_key));
		} else {
			zend_throw_exception_ex(spl_ce_OutOfRangeException, 0, "Undefined array key " ZEND_LONG_FMT, long_key);
		}
		return NULL;
	}

	return element;
}

/* Plain PHP code may unset a property between the fetch and the call, and a function an argument
 * runs may remove the element an element collection was read from. */
static zval *sharp_collection_checked(zval *array)
{
	ZVAL_DEREF(array);
	if (UNEXPECTED(Z_TYPE_P(array) != IS_ARRAY)) {
		zend_throw_error(NULL, "Cannot call %s() on a collection that was unset", get_active_function_name());
		return NULL;
	}

	return array;
}

static HashTable *sharp_collection_change(sharp_collection *collection);

/* The array the collection reads. An element collection reads it from its container each time, so
 * it holds no copy that would make the element shared when it changes. */
static zval *sharp_collection_array(sharp_collection *collection)
{
	if (!collection->container) {
		return sharp_collection_checked(collection->slot ? collection->slot : &collection->value);
	}

	zval *container = sharp_collection_array(sharp_collection_from(collection->container));
	zval *element = container ? sharp_collection_element(Z_ARRVAL_P(container), &collection->index, false) : NULL;

	return element ? sharp_collection_checked(element) : NULL;
}

/* Where a change writes the array: what sharp_collection_array reads, but for an element collection
 * the element inside its container's changed array, so a change separates every array it is in. */
static zval *sharp_collection_place(sharp_collection *collection)
{
	if (!collection->container) {
		return sharp_collection_array(collection);
	}

	HashTable *container = sharp_collection_change(sharp_collection_from(collection->container));
	zval *element = container ? sharp_collection_element(container, &collection->index, false) : NULL;

	return element ? sharp_collection_checked(element) : NULL;
}

static HashTable *sharp_collection_change(sharp_collection *collection)
{
	zval *array = sharp_collection_place(collection);

	if (!array) {
		return NULL;
	}
	SEPARATE_ARRAY(array);

	return Z_ARRVAL_P(array);
}

/* Runs a property's set hook once with the changed array, in the caller's scope, or throws for a
 * static property the caller may not set. An element collection writes back what holds it. */
static void sharp_collection_write_back(sharp_collection *collection)
{
	if (collection->container) {
		sharp_collection_write_back(sharp_collection_from(collection->container));
		return;
	}
	if (!collection->property && !collection->static_property) {
		return;
	}

	const zend_class_entry *scope = EG(fake_scope);
	EG(fake_scope) = collection->scope;
	if (collection->static_property) {
		zend_asymmetric_visibility_property_modification_error(collection->static_property, "modify");
	} else {
		collection->owner->handlers->write_property(collection->owner, collection->property, &collection->value, NULL);
	}
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

/* Spec section 10: a struct keys a Map and joins a Set by its whole value, stored as its key text. One encoder writes
 * the text and sharp_key_read_struct is its exact inverse. The text is the struct's class name, then each stored
 * property in declaration order, as App\Spot{"x":1,"y":2}. Inside it a value is null, true, false, an int, a float in
 * its shortest round-trip form with a fraction or an exponent, so 1.0 never reads back as 1, -0.0 as 0.0, INF, -INF
 * and NAN, a string as JSON writes it with only ", \ and the bytes below 0x20 escaped, an enum case as
 * App\Status::Open, a nested struct as its own text, and an array as [v,...] when its keys in order are 0 to n-1, else
 * as {"k":v,...} with int keys first in ascending order and then string keys by their bytes. So two values == calls
 * equal share one text, and the class name first keeps PHP from reading a text as an int key. NaN is the one value
 * never == to itself, and its text is still one key. */
zend_class_entry *sharp_ce_struct;

#define SHARP_KEY_DEPTH PHP_JSON_PARSER_DEFAULT_DEPTH

/* As PHP lets only an enum implement UnitEnum, only a struct implements Sharp\Struct: the final class the bridge
 * lowers a struct to, from a .sharp file, whose checker lets no PHP# code name Sharp.Struct. A plain PHP class could
 * hold a cycle, and the encoder would never end. */
static int sharp_struct_implemented(zend_class_entry *interface, zend_class_entry *class_type)
{
	if (class_type->type == ZEND_USER_CLASS && (class_type->ce_flags & ZEND_ACC_FINAL)
			&& class_type->info.user.filename && sharp_is_sharp_file(class_type->info.user.filename)) {
		return SUCCESS;
	}

	zend_error_noreturn(E_ERROR, "Non-struct class %s cannot implement interface %s",
		ZSTR_VAL(class_type->name), ZSTR_VAL(interface->name));
	return FAILURE;
}

static bool sharp_key_write(smart_str *text, zval *value, int depth);

static void sharp_key_write_string(smart_str *text, const zend_string *string)
{
	smart_str_appendc(text, '"');
	for (size_t i = 0; i < ZSTR_LEN(string); i++) {
		unsigned char byte = (unsigned char) ZSTR_VAL(string)[i];
		if (byte == '"' || byte == '\\') {
			smart_str_appendc(text, '\\');
			smart_str_appendc(text, byte);
		} else if (byte < 0x20) {
			smart_str_append_printf(text, "\\u%04x", byte);
		} else {
			smart_str_appendc(text, byte);
		}
	}
	smart_str_appendc(text, '"');
}

static void sharp_key_write_double(smart_str *text, double number)
{
	if (zend_isnan(number)) {
		smart_str_appendl(text, "NAN", 3);
	} else if (zend_isinf(number)) {
		smart_str_appends(text, number > 0 ? "INF" : "-INF");
	} else {
		/* == calls -0.0 and 0.0 equal, so they share one text. */
		smart_str_append_double(text, number == 0 ? 0.0 : number, -1, true);
	}
}

/* Orders two entries as a key text writes an array: int keys first in ascending order, then string keys by bytes. */
static int sharp_key_compare(Bucket *a, Bucket *b)
{
	if (!a->key && !b->key) {
		return ZEND_THREEWAY_COMPARE((zend_long) a->h, (zend_long) b->h);
	}
	if (!a->key || !b->key) {
		return a->key ? 1 : -1;
	}

	return zend_binary_strcmp(ZSTR_VAL(a->key), ZSTR_LEN(a->key), ZSTR_VAL(b->key), ZSTR_LEN(b->key));
}

static bool sharp_key_write_array(smart_str *text, HashTable *array, int depth)
{
	HashTable *sorted = zend_array_dup(array);
	zend_hash_sort(sorted, sharp_key_compare, false);
	bool is_list = zend_array_is_list(sorted);
	bool written = true;
	bool first = true;
	zend_ulong index;
	zend_string *key;
	zval *element;

	smart_str_appendc(text, is_list ? '[' : '{');
	ZEND_HASH_FOREACH_KEY_VAL(sorted, index, key, element) {
		if (!first) {
			smart_str_appendc(text, ',');
		}
		first = false;
		if (!is_list) {
			if (key) {
				sharp_key_write_string(text, key);
			} else {
				smart_str_appendc(text, '"');
				smart_str_append_long(text, (zend_long) index);
				smart_str_appendc(text, '"');
			}
			smart_str_appendc(text, ':');
		}
		if (!sharp_key_write(text, element, depth)) {
			written = false;
			break;
		}
	} ZEND_HASH_FOREACH_END();
	smart_str_appendc(text, is_list ? ']' : '}');
	zend_array_release(sorted);

	return written;
}

static bool sharp_key_write_struct(smart_str *text, zend_object *object, int depth)
{
	zend_string *name;
	zend_property_info *info;
	bool first = true;

	smart_str_append(text, object->ce->name);
	smart_str_appendc(text, '{');
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&object->ce->properties_info, name, info) {
		if (info->flags & (ZEND_ACC_STATIC | ZEND_ACC_VIRTUAL)) {
			continue;
		}
		zval *value = OBJ_PROP(object, info->offset);
		if (Z_TYPE_P(value) == IS_UNDEF) {
			zend_throw_error(NULL, "%s::$%s must be set before its struct is a key", ZSTR_VAL(object->ce->name), ZSTR_VAL(name));
			return false;
		}
		if (!first) {
			smart_str_appendc(text, ',');
		}
		first = false;
		sharp_key_write_string(text, name);
		smart_str_appendc(text, ':');
		if (!sharp_key_write(text, value, depth)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();
	smart_str_appendc(text, '}');

	return true;
}

static bool sharp_key_write(smart_str *text, zval *value, int depth)
{
	if (depth > SHARP_KEY_DEPTH) {
		zend_value_error("A key text nests deeper than %d", SHARP_KEY_DEPTH);
		return false;
	}

	ZVAL_DEREF(value);
	switch (Z_TYPE_P(value)) {
		case IS_NULL:
			smart_str_appendl(text, "null", 4);
			return true;
		case IS_FALSE:
			smart_str_appendl(text, "false", 5);
			return true;
		case IS_TRUE:
			smart_str_appendl(text, "true", 4);
			return true;
		case IS_LONG:
			smart_str_append_long(text, Z_LVAL_P(value));
			return true;
		case IS_DOUBLE:
			sharp_key_write_double(text, Z_DVAL_P(value));
			return true;
		case IS_STRING:
			sharp_key_write_string(text, Z_STR_P(value));
			return true;
		case IS_ARRAY:
			return sharp_key_write_array(text, Z_ARRVAL_P(value), depth + 1);
		case IS_OBJECT:
			if (Z_OBJCE_P(value)->ce_flags & ZEND_ACC_ENUM) {
				smart_str_append(text, Z_OBJCE_P(value)->name);
				smart_str_appendl(text, "::", 2);
				smart_str_append(text, Z_STR_P(zend_enum_fetch_case_name(Z_OBJ_P(value))));
				return true;
			}
			if (instanceof_function(Z_OBJCE_P(value), sharp_ce_struct)) {
				return sharp_key_write_struct(text, Z_OBJ_P(value), depth + 1);
			}
			ZEND_FALLTHROUGH;
		default:
			zend_type_error("A struct key holds only values, %s given", zend_zval_value_name(value));
			return false;
	}
}

/* The key text of the struct object, which the caller releases, or NULL after an exception. */
static zend_string *sharp_struct_key(zend_object *object)
{
	smart_str text = {0};

	if (!sharp_key_write_struct(&text, object, 1)) {
		smart_str_free(&text);
		return NULL;
	}

	return smart_str_extract(&text);
}

typedef struct {
	const char *at;
	const char *end;
} sharp_key_reader;

static bool sharp_key_read(sharp_key_reader *reader, zval *value, int depth);

static bool sharp_key_skip(sharp_key_reader *reader, const char *expected, size_t length)
{
	if ((size_t) (reader->end - reader->at) < length || memcmp(reader->at, expected, length) != 0) {
		return false;
	}
	reader->at += length;

	return true;
}

/* Skips a word the encoder writes, such as null or INF, only when a delimiter follows it, since a class name such as
 * INFO\Spot starts as INF does. */
static bool sharp_key_skip_word(sharp_key_reader *reader, const char *word, size_t length)
{
	const char *after = reader->at + length;

	if ((size_t) (reader->end - reader->at) < length || memcmp(reader->at, word, length) != 0
			|| (after < reader->end && !strchr(",]}", *after))) {
		return false;
	}
	reader->at = after;

	return true;
}

static zend_string *sharp_key_read_name(sharp_key_reader *reader)
{
	const char *start = reader->at;

	while (reader->at < reader->end && (isalnum((unsigned char) *reader->at) || *reader->at == '_'
			|| *reader->at == '\\' || (unsigned char) *reader->at >= 0x80)) {
		reader->at++;
	}

	return reader->at > start ? zend_string_init(start, reader->at - start, false) : NULL;
}

static int sharp_key_hex(char digit)
{
	return digit >= 'a' ? digit - 'a' + 10 : digit >= 'A' ? digit - 'A' + 10 : digit - '0';
}

static zend_string *sharp_key_read_string(sharp_key_reader *reader)
{
	smart_str read = {0};

	if (!sharp_key_skip(reader, "\"", 1)) {
		return NULL;
	}
	while (reader->at < reader->end && *reader->at != '"') {
		char byte = *reader->at++;
		if (byte != '\\') {
			smart_str_appendc(&read, byte);
		} else if (reader->at < reader->end && (*reader->at == '"' || *reader->at == '\\')) {
			smart_str_appendc(&read, *reader->at++);
		} else if (reader->end - reader->at >= 5 && reader->at[0] == 'u' && reader->at[1] == '0' && reader->at[2] == '0'
				&& isxdigit((unsigned char) reader->at[3]) && isxdigit((unsigned char) reader->at[4])
				&& sharp_key_hex(reader->at[3]) < 2) {
			smart_str_appendc(&read, (char) (sharp_key_hex(reader->at[3]) * 16 + sharp_key_hex(reader->at[4])));
			reader->at += 5;
		} else {
			smart_str_free(&read);
			return NULL;
		}
	}
	if (!sharp_key_skip(reader, "\"", 1)) {
		smart_str_free(&read);
		return NULL;
	}

	return read.s ? smart_str_extract(&read) : ZSTR_EMPTY_ALLOC();
}

static bool sharp_key_read_number(sharp_key_reader *reader, zval *value)
{
	const char *start = reader->at;
	bool is_double = false;

	if (sharp_key_skip_word(reader, "-INF", 4)) {
		ZVAL_DOUBLE(value, -ZEND_INFINITY);
		return true;
	}
	if (reader->at < reader->end && *reader->at == '-') {
		reader->at++;
	}
	while (reader->at < reader->end && (isdigit((unsigned char) *reader->at) || strchr(".eE+-", *reader->at))) {
		is_double = is_double || !isdigit((unsigned char) *reader->at);
		reader->at++;
	}

	char *parsed_end;
	if (is_double) {
		ZVAL_DOUBLE(value, zend_strtod(start, (const char **) &parsed_end));
	} else {
		errno = 0;
		ZVAL_LONG(value, ZEND_STRTOL(start, &parsed_end, 10));
		if (errno == ERANGE) {
			return false;
		}
	}

	return parsed_end == reader->at && reader->at > start;
}

/* Reads one value of an array into array, and the separator after it. Returns 1 when the array goes on, 0 when it
 * ends, and -1 for a text no encoder wrote. */
static int sharp_key_read_element(sharp_key_reader *reader, HashTable *array, bool is_list, char closer, int depth)
{
	zend_string *key = NULL;
	zval element;

	if (!is_list && (!(key = sharp_key_read_string(reader)) || !sharp_key_skip(reader, ":", 1))) {
		if (key) {
			zend_string_release(key);
		}
		return -1;
	}
	if (!sharp_key_read(reader, &element, depth)) {
		if (key) {
			zend_string_release(key);
		}
		return -1;
	}
	if (key) {
		zend_symtable_update(array, key, &element);
		zend_string_release(key);
	} else {
		zend_hash_next_index_insert_new(array, &element);
	}

	return sharp_key_skip(reader, ",", 1) ? 1 : sharp_key_skip(reader, &closer, 1) ? 0 : -1;
}

/* Destroys a value a failed read built, and leaves it undefined, so the caller frees nothing twice. */
static void sharp_key_drop(zval *value)
{
	zval_ptr_dtor(value);
	ZVAL_UNDEF(value);
}

static bool sharp_key_read_array(sharp_key_reader *reader, zval *value, bool is_list, int depth)
{
	char closer = is_list ? ']' : '}';
	int more = 1;

	reader->at++;
	array_init(value);
	if (sharp_key_skip(reader, &closer, 1)) {
		return true;
	}
	while (more > 0) {
		more = sharp_key_read_element(reader, Z_ARRVAL_P(value), is_list, closer, depth);
	}
	if (more < 0) {
		sharp_key_drop(value);
		return false;
	}

	return true;
}

static bool sharp_key_read_case(sharp_key_reader *reader, zend_string *name, zval *value)
{
	zend_class_entry *ce = zend_lookup_class(name);
	if (!ce || !(ce->ce_flags & ZEND_ACC_ENUM)) {
		if (!EG(exception)) {
			zend_argument_value_error(1, "names %s, which is not an enum", ZSTR_VAL(name));
		}
		return false;
	}

	zend_string *case_name = sharp_key_read_name(reader);
	zend_class_constant *constant = case_name ? zend_hash_find_ptr(CE_CONSTANTS_TABLE(ce), case_name) : NULL;
	if (!constant || !(ZEND_CLASS_CONST_FLAGS(constant) & ZEND_CLASS_CONST_IS_CASE)) {
		if (case_name) {
			zend_string_release(case_name);
		}
		return false;
	}
	zend_object *found = zend_enum_get_case(ce, case_name);
	zend_string_release(case_name);
	ZVAL_OBJ_COPY(value, found);

	return true;
}

/* The struct the text names, built from its stored properties in declaration order without running its constructor,
 * as unserialize builds an object. Each property's type is checked as an assignment checks it. */
static bool sharp_key_read_struct(sharp_key_reader *reader, zend_string *name, zval *value, int depth)
{
	if (!sharp_key_skip(reader, "{", 1)) {
		return false;
	}
	zend_class_entry *ce = zend_lookup_class(name);
	if (!ce) {
		if (!EG(exception)) {
			zend_argument_value_error(1, "names %s, which does not exist", ZSTR_VAL(name));
		}
		return false;
	}
	if (!instanceof_function(ce, sharp_ce_struct)) {
		zend_argument_value_error(1, "names %s, which is not a struct", ZSTR_VAL(ce->name));
		return false;
	}
	if (object_init_ex(value, ce) != SUCCESS) {
		return false;
	}

	zend_string *property;
	zend_property_info *info;
	bool first = true;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&ce->properties_info, property, info) {
		if (info->flags & (ZEND_ACC_STATIC | ZEND_ACC_VIRTUAL)) {
			continue;
		}
		zend_string *written = NULL;
		zval read;
		if ((!first && !sharp_key_skip(reader, ",", 1)) || !(written = sharp_key_read_string(reader))
				|| !zend_string_equals(written, property) || !sharp_key_skip(reader, ":", 1)
				|| !sharp_key_read(reader, &read, depth)) {
			if (written) {
				zend_string_release(written);
			}
			sharp_key_drop(value);
			return false;
		}
		zend_string_release(written);
		first = false;
		if (ZEND_TYPE_IS_SET(info->type) && !zend_verify_property_type(info, &read, true)) {
			zval_ptr_dtor(&read);
			sharp_key_drop(value);
			return false;
		}

		zval *slot = OBJ_PROP(Z_OBJ_P(value), info->offset);
		zval garbage;
		ZVAL_COPY_VALUE(&garbage, slot);
		ZVAL_COPY_VALUE(slot, &read);
		Z_PROP_FLAG_P(slot) &= ~(IS_PROP_UNINIT | IS_PROP_REINITABLE);
		zval_ptr_dtor(&garbage);
	} ZEND_HASH_FOREACH_END();
	if (!sharp_key_skip(reader, "}", 1)) {
		sharp_key_drop(value);
		return false;
	}

	return true;
}

static bool sharp_key_read(sharp_key_reader *reader, zval *value, int depth)
{
	if (depth > SHARP_KEY_DEPTH) {
		zend_argument_value_error(1, "nests deeper than %d", SHARP_KEY_DEPTH);
		return false;
	}
	if (reader->at >= reader->end) {
		return false;
	}

	char first = *reader->at;
	if (sharp_key_skip_word(reader, "null", 4)) {
		ZVAL_NULL(value);
		return true;
	}
	if (sharp_key_skip_word(reader, "true", 4)) {
		ZVAL_TRUE(value);
		return true;
	}
	if (sharp_key_skip_word(reader, "false", 5)) {
		ZVAL_FALSE(value);
		return true;
	}
	if (sharp_key_skip_word(reader, "NAN", 3)) {
		ZVAL_DOUBLE(value, ZEND_NAN);
		return true;
	}
	if (sharp_key_skip_word(reader, "INF", 3)) {
		ZVAL_DOUBLE(value, ZEND_INFINITY);
		return true;
	}
	if (first == '-' || isdigit((unsigned char) first)) {
		return sharp_key_read_number(reader, value);
	}
	if (first == '"') {
		zend_string *string = sharp_key_read_string(reader);
		if (!string) {
			return false;
		}
		ZVAL_STR(value, string);
		return true;
	}
	if (first == '[' || first == '{') {
		return sharp_key_read_array(reader, value, first == '[', depth + 1);
	}

	zend_string *name = sharp_key_read_name(reader);
	if (!name) {
		return false;
	}
	bool read = sharp_key_skip(reader, "::", 2)
		? sharp_key_read_case(reader, name, value)
		: sharp_key_read_struct(reader, name, value, depth + 1);
	zend_string_release(name);

	return read;
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Map_key, 0, 1, IS_STRING, 0)
	ZEND_ARG_OBJ_INFO(0, struct, Sharp\\Struct, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Map_readKey, 0, 1, Sharp\\Struct, 0)
	ZEND_ARG_TYPE_INFO(0, text, IS_STRING, 0)
ZEND_END_ARG_INFO()

/* The key text a Map holds the struct under. */
ZEND_METHOD(Sharp_Map, key)
{
	zend_object *object;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJ_OF_CLASS(object, sharp_ce_struct)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *text = sharp_struct_key(object);
	if (!text) {
		RETURN_THROWS();
	}
	RETURN_STR(text);
}

/* The struct a key text names, which a loop over a Map reads its key back as. */
ZEND_METHOD(Sharp_Map, readKey)
{
	zend_string *text;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(text)
	ZEND_PARSE_PARAMETERS_END();

	sharp_key_reader reader = {ZSTR_VAL(text), ZSTR_VAL(text) + ZSTR_LEN(text)};
	zend_string *name = sharp_key_read_name(&reader);
	bool read = name && sharp_key_read_struct(&reader, name, return_value, 1);
	if (name) {
		zend_string_release(name);
	}
	if (read && reader.at != reader.end) {
		sharp_key_drop(return_value);
		read = false;
	}
	if (!read) {
		if (!EG(exception)) {
			zend_argument_value_error(1, "is not a key text");
		}
		RETURN_THROWS();
	}
}

/* Replaces the list at array with a renumbered copy: value goes in at index, or, with no value, the
 * element at index leaves. A List's keys are its positions. The old list is released last, so a
 * destructor an element runs sees the changed list. */
static void sharp_collection_renumber(sharp_collection *collection, zend_long index, zval *value)
{
	zval *array = sharp_collection_place(collection);
	if (!array) {
		return;
	}
	HashTable *elements = Z_ARRVAL_P(array);
	zval changed, old;
	zval *element;
	zend_long position = 0;

	array_init_size(&changed, zend_hash_num_elements(elements) + (value ? 1 : 0));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (position == index && value) {
			Z_TRY_ADDREF_P(value);
			zend_hash_next_index_insert_new(Z_ARRVAL(changed), value);
		}
		if (position != index || value) {
			Z_TRY_ADDREF_P(element);
			zend_hash_next_index_insert_new(Z_ARRVAL(changed), element);
		}
		position++;
	} ZEND_HASH_FOREACH_END();
	if (position == index && value) {
		Z_TRY_ADDREF_P(value);
		zend_hash_next_index_insert_new(Z_ARRVAL(changed), value);
	}

	ZVAL_COPY_VALUE(&old, array);
	ZVAL_COPY_VALUE(array, &changed);
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);
}

/* Kotlin's add(index, element): value goes in at index, from 0 to the count, and the elements from
 * index on move up one. Outside that range it throws as set does past the end. */
ZEND_METHOD(Sharp_Collection, insert)
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
	if (index < 0 || index > (zend_long) zend_hash_num_elements(Z_ARRVAL_P(array))) {
		zend_throw_exception_ex(spl_ce_OutOfRangeException, 0, "Undefined array key " ZEND_LONG_FMT, index);
		RETURN_THROWS();
	}

	sharp_collection_renumber(collection, index, value);
}

/* Removes the element at index and renumbers the list. A List's remove(value) finds the index and
 * calls it, so the list keeps its keys 0 to n-1, where delete would leave a gap. */
ZEND_METHOD(Sharp_Collection, removeAt)
{
	zend_long index;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_LONG(index)
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

	sharp_collection_renumber(collection, index, NULL);
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

/* `map[key] = value` and a Set's add on an element of a List, which only the element collection
 * writes where the List stores it: inserts or replaces the element under key, and gives value. The
 * old element is released last, so a destructor it runs sees the changed collection. */
ZEND_METHOD(Sharp_Collection, put)
{
	zval *key, *value;
	zend_string *string_key;
	zend_long long_key;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(key)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_collection_key(key, &string_key, &long_key)) {
		RETURN_THROWS();
	}

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	HashTable *changed = sharp_collection_change(collection);
	if (!changed) {
		RETURN_THROWS();
	}
	zval *element = string_key ? zend_symtable_find(changed, string_key) : zend_hash_index_find(changed, long_key);
	zval old;
	ZVAL_UNDEF(&old);
	if (element) {
		ZVAL_COPY_VALUE(&old, element);
		ZVAL_COPY(element, value);
	} else {
		Z_TRY_ADDREF_P(value);
		if (string_key) {
			zend_symtable_add_new(changed, string_key, value);
		} else {
			zend_hash_index_add_new(changed, long_key, value);
		}
	}
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);

	RETURN_COPY(value);
}

/* A List's or a Set's clear on an element of a List, which only the element collection writes where
 * the List stores it. The old elements are released last. */
ZEND_METHOD(Sharp_Collection, clear)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	zval *array = sharp_collection_place(collection);
	if (!array) {
		RETURN_THROWS();
	}
	zval old;
	ZVAL_COPY_VALUE(&old, array);
	ZVAL_EMPTY_ARRAY(array);
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);
}

/* A PHP# method call on an index of a List reads the element through the collection that holds it,
 * as `grid[0].add(5)` does. An element that PHP stores as an array becomes a collection that changes
 * it at that index, and any other element is read as it is. A missing index throws as a PHP# index
 * read does, and reads as null before `?.`, whose index FETCH_DIM_IS reads, so the call is skipped. */
static zval *sharp_collection_read_dimension(zend_object *object, zval *offset, int type, zval *rv)
{
	zval *array = sharp_collection_array(sharp_collection_from(object));
	ZVAL_DEREF(offset);
	zval *element = array ? sharp_collection_element(Z_ARRVAL_P(array), offset, type == BP_VAR_IS) : NULL;
	if (!element) {
		return &EG(uninitialized_zval);
	}

	ZVAL_DEREF(element);
	if (Z_TYPE_P(element) != IS_ARRAY) {
		ZVAL_COPY(rv, element);
		return rv;
	}
	sharp_collection *collection = sharp_collection_new(rv);
	GC_ADDREF(object);
	collection->container = object;
	ZVAL_COPY(&collection->index, offset);

	return rv;
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

/* The slot of a map under the key a function gave, which spec section 12 makes an int, a string or a
 * backed enum case, and section 10 a struct, held as its key text. A case goes in under its backing
 * value, as a Map index stores it, and a string of digits is the int key, as in any PHP array. */
static zval *sharp_collection_key_slot(HashTable *map, zval *key)
{
	zend_ulong index;

	if (Z_TYPE_P(key) == IS_OBJECT && (Z_OBJCE_P(key)->ce_flags & ZEND_ACC_ENUM)
		&& Z_OBJCE_P(key)->enum_backing_type != IS_UNDEF) {
		key = zend_enum_fetch_case_value(Z_OBJ_P(key));
	}
	switch (Z_TYPE_P(key)) {
		case IS_LONG:
			return zend_hash_index_lookup(map, Z_LVAL_P(key));
		case IS_STRING:
			if (ZEND_HANDLE_NUMERIC_STR(Z_STRVAL_P(key), Z_STRLEN_P(key), index)) {
				return zend_hash_index_lookup(map, index);
			}
			return zend_hash_lookup(map, Z_STR_P(key));
		case IS_OBJECT:
			if (instanceof_function(Z_OBJCE_P(key), sharp_ce_struct)) {
				zend_string *text = sharp_struct_key(Z_OBJ_P(key));
				if (!text) {
					return NULL;
				}
				zval *slot = zend_hash_lookup(map, text);
				zend_string_release(text);
				return slot;
			}
			ZEND_FALLTHROUGH;
		default:
			zend_type_error("A map key must be of type int|string or a struct, %s given", zend_zval_value_name(key));
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

/* Orders two [key, element] pairs by key. A null key sorts before every other key, as Kotlin's sortedBy puts it, since
 * PHP's <=> compares null with a number as two bools, which is no order. Two other keys order as PHP#'s <=> does: two
 * objects by the op_Comparison the left key's class declares or inherits, else the right key's, two strings by their
 * bytes, and other keys as PHP's <=> does. Equal keys keep their original order, which zend_hash_sort keeps in each
 * bucket's extra space, so the sort is stable as Kotlin's is. A call that throws leaves the exception for sortedBy to
 * throw. */
static int sharp_collection_compare_keys(Bucket *a, Bucket *b)
{
	zval *left = zend_hash_index_find(Z_ARRVAL(a->val), 0);
	zval *right = zend_hash_index_find(Z_ARRVAL(b->val), 0);
	zend_class_entry *scope = NULL;
	zend_function *comparison = NULL;
	int result;

	if (Z_TYPE_P(left) == IS_OBJECT && Z_TYPE_P(right) == IS_OBJECT) {
		scope = Z_OBJCE_P(left);
		comparison = zend_hash_str_find_ptr(&scope->function_table, ZEND_STRL("op_comparison"));
		if (!comparison) {
			scope = Z_OBJCE_P(right);
			comparison = zend_hash_str_find_ptr(&scope->function_table, ZEND_STRL("op_comparison"));
		}
	}

	if (Z_TYPE_P(left) == IS_NULL || Z_TYPE_P(right) == IS_NULL) {
		result = (Z_TYPE_P(left) != IS_NULL) - (Z_TYPE_P(right) != IS_NULL);
	} else if (comparison) {
		zval keys[2], order;
		ZVAL_COPY_VALUE(&keys[0], left);
		ZVAL_COPY_VALUE(&keys[1], right);
		ZVAL_UNDEF(&order);
		zend_call_known_function(comparison, NULL, scope, &order, 2, keys, NULL);
		result = Z_TYPE(order) == IS_LONG ? ZEND_NORMALIZE_BOOL(Z_LVAL(order)) : 0;
	} else if (Z_TYPE_P(left) == IS_STRING && Z_TYPE_P(right) == IS_STRING) {
		result = ZEND_NORMALIZE_BOOL(zend_binary_zval_strcmp(left, right));
	} else {
		result = zend_compare(left, right);
	}

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

static bool sharp_native_has_body(const zend_string *body)
{
	for (const char *const *own = sharp_native_bodies; *own; own++) {
		if (zend_string_equals_cstr(body, *own, strlen(*own))) {
			return true;
		}
	}
	return false;
}

/* The standard library's autoload.php passes the SHARP_NATIVE it was built with, the native bodies it declares and its
 * package's version when vendor/autoload.php loads it, as Composer's platform_check.php checks the platform before
 * anything loads. A body is named by its full PHP# name, as Sharp.Text.Text.slug, and a refusal shows it as Text.slug. */
static ZEND_FUNCTION(Sharp_Internal_requireNative)
{
	zend_string *fingerprint;
	HashTable *bodies;
	zend_string *version;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STR(fingerprint)
		Z_PARAM_ARRAY_HT(bodies)
		Z_PARAM_STR(version)
	ZEND_PARSE_PARAMETERS_END();

	if (zend_string_equals_literal(fingerprint, SHARP_NATIVE)) {
		return;
	}

	zval *body;
	ZEND_HASH_FOREACH_VAL(bodies, body) {
		if (Z_TYPE_P(body) != IS_STRING) {
			zend_argument_type_error(2, "must contain only strings, %s given", zend_zval_value_name(body));
			RETURN_THROWS();
		}
		if (!sharp_native_has_body(Z_STR_P(body))) {
			const char *name = Z_STRVAL_P(body);
			const char *method = zend_memrchr(name, '.', Z_STRLEN_P(body));
			const char *owner = method ? zend_memrchr(name, '.', method - name) : NULL;
			zend_throw_error(NULL, "The PHP# standard library %s declares the native body %s, which PHP# engine "
				PHP_SHARP_VERSION " does not have. Install the same PHP# version of both.",
				ZSTR_VAL(version), owner ? owner + 1 : name);
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();

	zend_throw_error(NULL, "The PHP# standard library %s was built for other native bodies than PHP# engine "
		PHP_SHARP_VERSION " has. Install the same PHP# version of both.", ZSTR_VAL(version));
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

/* Spec section 12, rulings 22 and 23 of docs/architecture/slices/10-structs.md: a Map and a Set are Sharp\Map and
 * Sharp\Set objects, each holding a sharp_table. Plain PHP reads and writes one through its index, as $map[$key],
 * $map[$key] = $value, unset($map[$key]) and $set[] = $element, which the VM runs through the dimension handlers below
 * after SHARP_SEPARATE copies a shared one, so neither class has a changing method a holder can't separate for. */
zend_class_entry *sharp_ce_map;
zend_class_entry *sharp_ce_set;
static zend_object_handlers sharp_table_handlers;

static sharp_table_object *sharp_table_object_create(zend_class_entry *ce, uint32_t size)
{
	sharp_table_object *created = zend_object_alloc(sizeof(sharp_table_object), ce);

	zend_object_std_init(&created->std, ce);
	sharp_table_init(&created->table, size);

	return created;
}

static zend_object *sharp_table_create(zend_class_entry *ce)
{
	return &sharp_table_object_create(ce, 0)->std;
}

static void sharp_table_free(zend_object *object)
{
	sharp_table_destroy(sharp_table_of(object));
	zend_object_std_dtor(object);
}

static zend_object *sharp_table_clone(zend_object *object)
{
	sharp_table_object *copy = zend_object_alloc(sizeof(sharp_table_object), object->ce);

	zend_object_std_init(&copy->std, object->ce);
	sharp_table_copy(&copy->table, sharp_table_of(object));

	return &copy->std;
}

void sharp_separate(zval *holder)
{
	zend_object *shared = Z_OBJ_P(holder);

	ZVAL_OBJ(holder, sharp_table_clone(shared));
	OBJ_RELEASE(shared);
}

static HashTable *sharp_table_get_gc(zend_object *object, zval **table, int *n)
{
	zend_get_gc_buffer *buffer = zend_get_gc_buffer_create();

	SHARP_TABLE_FOREACH(sharp_table_of(object), bucket) {
		zend_get_gc_buffer_add_zval(buffer, &bucket->key);
		zend_get_gc_buffer_add_zval(buffer, &bucket->value);
	} SHARP_TABLE_FOREACH_END();
	zend_get_gc_buffer_use(buffer, table, n);

	return NULL;
}

/* A Map as a list of [key, value] pairs, since a key need not be a PHP array key, and a Set as a list of its
 * elements. */
static HashTable *sharp_table_get_debug_info(zend_object *object, int *is_temp)
{
	sharp_table *table = sharp_table_of(object);
	HashTable *entries = zend_new_array(table->size);

	SHARP_TABLE_FOREACH(table, bucket) {
		zval entry;
		if (object->ce == sharp_ce_map) {
			array_init_size(&entry, 2);
			Z_TRY_ADDREF(bucket->key);
			Z_TRY_ADDREF(bucket->value);
			zend_hash_next_index_insert_new(Z_ARRVAL(entry), &bucket->key);
			zend_hash_next_index_insert_new(Z_ARRVAL(entry), &bucket->value);
		} else {
			ZVAL_COPY(&entry, &bucket->key);
		}
		zend_hash_next_index_insert_new(entries, &entry);
	} SHARP_TABLE_FOREACH_END();
	*is_temp = 1;

	return entries;
}

/* PHP's == on two Maps or two Sets is PHP#'s: the same entries regardless of order. */
static int sharp_table_compare(zval *a, zval *b)
{
	ZEND_COMPARE_OBJECTS_FALLBACK(a, b);

	bool equal;
	if (Z_OBJCE_P(a) != Z_OBJCE_P(b) || !sharp_equal(a, b, &equal)) {
		return ZEND_UNCOMPARABLE;
	}

	return equal ? 0 : ZEND_UNCOMPARABLE;
}

static zend_result sharp_table_count_elements(zend_object *object, zend_long *count)
{
	*count = sharp_table_of(object)->size;

	return SUCCESS;
}

/* A Map read gives the value, or null when the key is missing (spec section 12's TValue?). A Set has no index. A
 * write through the read, as $map[$key][] = $element, gets the entry's own slot, inserting null for a missing key as a
 * PHP array does: the slot comes back as a new reference, which the VM unwraps in place, as ArrayObject's does. */
static zval *sharp_table_read_dimension(zend_object *object, zval *offset, int type, zval *rv)
{
	if (object->ce == sharp_ce_set) {
		zend_throw_error(NULL, "A Set has no index: test for an element with isset($set[$element])");
		return NULL;
	}
	if (!offset) {
		zend_throw_error(NULL, "A Map read needs a key");
		return NULL;
	}

	sharp_table *table = sharp_table_of(object);
	if (type == BP_VAR_W || type == BP_VAR_RW) {
		zval null;
		ZVAL_NULL(&null);
		sharp_table_bucket *bucket = sharp_table_find(table, offset);
		if (!bucket && (EG(exception) || !sharp_table_put(table, offset, &null))) {
			return NULL;
		}
		bucket = bucket ? bucket : sharp_table_find(table, offset);
		ZVAL_MAKE_REF(&bucket->value);
		return &bucket->value;
	}

	sharp_table_bucket *bucket = sharp_table_find(table, offset);
	if (EG(exception)) {
		return NULL;
	}
	if (bucket) {
		ZVAL_COPY_DEREF(rv, &bucket->value);
	} else {
		ZVAL_NULL(rv);
	}

	return rv;
}

static void sharp_table_write_dimension(zend_object *object, zval *offset, zval *value)
{
	if (object->ce == sharp_ce_set) {
		if (offset) {
			zend_throw_error(NULL, "A Set adds an element with $set[] = $element");
			return;
		}
		zval null;
		ZVAL_NULL(&null);
		sharp_table_put(sharp_table_of(object), value, &null);
		return;
	}
	if (!offset) {
		zend_throw_error(NULL, "A Map write needs a key");
		return;
	}
	sharp_table_put(sharp_table_of(object), offset, value);
}

static int sharp_table_has_dimension(zend_object *object, zval *offset, int check_empty)
{
	sharp_table_bucket *bucket = sharp_table_find(sharp_table_of(object), offset);

	if (!bucket) {
		return 0;
	}
	zval *held = object->ce == sharp_ce_set ? &bucket->key : &bucket->value;

	return check_empty ? i_zend_is_true(held) : Z_TYPE_P(held) != IS_NULL;
}

static void sharp_table_unset_dimension(zend_object *object, zval *offset)
{
	sharp_table_remove(sharp_table_of(object), offset);
}

/* A loop over a Map gives each stored key itself, an object included, in insertion order, and a loop over a Set gives
 * each element under its position. The iterator holds the object, so a write inside the loop separates the holder,
 * and the loop reads the entries it started with. */
typedef struct {
	zend_object_iterator it;
	uint32_t index;
	uint32_t position;
} sharp_table_iterator;

static sharp_table_bucket *sharp_table_iterator_bucket(zend_object_iterator *it)
{
	sharp_table_iterator *iterator = (sharp_table_iterator *) it;
	sharp_table *table = sharp_table_of(Z_OBJ(it->data));

	while (iterator->index < table->next && SHARP_TABLE_DELETED(&table->buckets[iterator->index])) {
		iterator->index++;
	}

	return iterator->index < table->next ? &table->buckets[iterator->index] : NULL;
}

static void sharp_table_iterator_dtor(zend_object_iterator *it)
{
	zval_ptr_dtor(&it->data);
}

static zend_result sharp_table_iterator_valid(zend_object_iterator *it)
{
	return sharp_table_iterator_bucket(it) ? SUCCESS : FAILURE;
}

static zval *sharp_table_iterator_current(zend_object_iterator *it)
{
	sharp_table_bucket *bucket = sharp_table_iterator_bucket(it);

	return Z_OBJCE(it->data) == sharp_ce_set ? &bucket->key : &bucket->value;
}

static void sharp_table_iterator_key(zend_object_iterator *it, zval *key)
{
	if (Z_OBJCE(it->data) == sharp_ce_set) {
		ZVAL_LONG(key, ((sharp_table_iterator *) it)->position);
	} else {
		ZVAL_COPY(key, &sharp_table_iterator_bucket(it)->key);
	}
}

static void sharp_table_iterator_forward(zend_object_iterator *it)
{
	((sharp_table_iterator *) it)->index++;
	((sharp_table_iterator *) it)->position++;
}

static void sharp_table_iterator_rewind(zend_object_iterator *it)
{
	((sharp_table_iterator *) it)->index = 0;
	((sharp_table_iterator *) it)->position = 0;
}

static HashTable *sharp_table_iterator_get_gc(zend_object_iterator *it, zval **table, int *n)
{
	*table = &it->data;
	*n = 1;

	return NULL;
}

static const zend_object_iterator_funcs sharp_table_iterator_funcs = {
	sharp_table_iterator_dtor,
	sharp_table_iterator_valid,
	sharp_table_iterator_current,
	sharp_table_iterator_key,
	sharp_table_iterator_forward,
	sharp_table_iterator_rewind,
	NULL,
	sharp_table_iterator_get_gc,
};

static zend_object_iterator *sharp_table_get_iterator(zend_class_entry *ce, zval *object, int by_ref)
{
	if (by_ref) {
		zend_throw_error(NULL, "A %s can't be looped over by reference", ce == sharp_ce_set ? "Set" : "Map");
		return NULL;
	}

	sharp_table_iterator *iterator = emalloc(sizeof(sharp_table_iterator));
	zend_iterator_init(&iterator->it);
	ZVAL_OBJ_COPY(&iterator->it.data, Z_OBJ_P(object));
	iterator->it.funcs = &sharp_table_iterator_funcs;
	iterator->index = 0;
	iterator->position = 0;

	return &iterator->it;
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Table___construct, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Table_getIterator, 0, 0, Iterator, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_count, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_jsonSerialize, 0, 0, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_list, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Map_of, 0, 1, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, keysAndValues, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Map_from, 0, 1, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, entries, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Set_from, 0, 1, Sharp\\Set, 0)
	ZEND_ARG_TYPE_INFO(0, values, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(Sharp_Table, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(Sharp_Table, getIterator)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_create_internal_iterator_zval(return_value, ZEND_THIS);
}

ZEND_METHOD(Sharp_Table, count)
{
	ZEND_PARSE_PARAMETERS_NONE();

	RETURN_LONG(sharp_table_of(Z_OBJ_P(ZEND_THIS))->size);
}

/* The text JSON writes for a key: an int, a string or a backed enum case's value. NULL after a JsonException for any
 * other key, which has no text. */
static zend_string *sharp_table_json_key(zval *key)
{
	if (Z_TYPE_P(key) == IS_OBJECT && (Z_OBJCE_P(key)->ce_flags & ZEND_ACC_ENUM)
			&& Z_OBJCE_P(key)->enum_backing_type != IS_UNDEF) {
		key = zend_enum_fetch_case_value(Z_OBJ_P(key));
	}
	if (Z_TYPE_P(key) == IS_LONG || Z_TYPE_P(key) == IS_STRING) {
		return zval_get_string(key);
	}

	zend_throw_exception_ex(php_json_exception_ce, 0, "A JSON object key is text, and %s is not",
		Z_TYPE_P(key) == IS_OBJECT ? ZSTR_VAL(Z_OBJCE_P(key)->name) : zend_zval_value_name(key));
	return NULL;
}

/* A Map is a JSON object, {} when empty, and a Set a JSON array. */
ZEND_METHOD(Sharp_Table, jsonSerialize)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_object *object = Z_OBJ_P(ZEND_THIS);
	sharp_table *table = sharp_table_of(object);
	HashTable *encoded = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		if (object->ce == sharp_ce_set) {
			Z_TRY_ADDREF(bucket->key);
			zend_hash_next_index_insert_new(encoded, &bucket->key);
			continue;
		}
		zend_string *key = sharp_table_json_key(&bucket->key);
		if (!key) {
			zend_array_release(encoded);
			RETURN_THROWS();
		}
		Z_TRY_ADDREF(bucket->value);
		zend_hash_update(encoded, key, &bucket->value);
		zend_string_release(key);
	} SHARP_TABLE_FOREACH_END();

	if (object->ce == sharp_ce_set) {
		RETURN_ARR(encoded);
	}
	object_and_properties_init(return_value, zend_standard_class_def, encoded);
}

/* A PHP array of the entries, when every key is an int or a string, which is all a PHP array key can be. A Set's
 * array is the list of its elements. */
ZEND_METHOD(Sharp_Table, toArray)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_object *object = Z_OBJ_P(ZEND_THIS);
	sharp_table *table = sharp_table_of(object);
	HashTable *array = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		if (object->ce == sharp_ce_set) {
			Z_TRY_ADDREF(bucket->key);
			zend_hash_next_index_insert_new(array, &bucket->key);
			continue;
		}
		Z_TRY_ADDREF(bucket->value);
		if (Z_TYPE(bucket->key) == IS_LONG) {
			zend_hash_index_update(array, Z_LVAL(bucket->key), &bucket->value);
		} else if (Z_TYPE(bucket->key) == IS_STRING) {
			zend_symtable_update(array, Z_STR(bucket->key), &bucket->value);
		} else {
			zval_ptr_dtor(&bucket->value);
			zend_array_release(array);
			zend_value_error("A PHP array key is an int or a string, and this Map holds a key of type %s",
				Z_TYPE(bucket->key) == IS_OBJECT ? ZSTR_VAL(Z_OBJCE(bucket->key)->name) : zend_zval_value_name(&bucket->key));
			RETURN_THROWS();
		}
	} SHARP_TABLE_FOREACH_END();

	RETURN_ARR(array);
}

/* The List of each stored key, or of each value, in insertion order. */
static void sharp_table_list(INTERNAL_FUNCTION_PARAMETERS, bool keys)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_table *table = sharp_table_of(Z_OBJ_P(ZEND_THIS));
	HashTable *list = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *held = keys ? &bucket->key : &bucket->value;
		Z_TRY_ADDREF_P(held);
		zend_hash_next_index_insert_new(list, held);
	} SHARP_TABLE_FOREACH_END();

	RETURN_ARR(list);
}

ZEND_METHOD(Sharp_Map, keys)
{
	sharp_table_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

ZEND_METHOD(Sharp_Map, values)
{
	sharp_table_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

ZEND_METHOD(Sharp_Set, toList)
{
	sharp_table_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

/* A Map literal's entries, key then value, in the order the literal writes them, so a later key replaces an earlier
 * one. */
ZEND_METHOD(Sharp_Map, of)
{
	HashTable *keys_and_values;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(keys_and_values)
	ZEND_PARSE_PARAMETERS_END();

	uint32_t count = zend_hash_num_elements(keys_and_values);
	if (count % 2 != 0 || !zend_array_is_list(keys_and_values)) {
		zend_argument_value_error(1, "must be a list of keys each followed by its value");
		RETURN_THROWS();
	}

	sharp_table_object *map = sharp_table_object_create(sharp_ce_map, count / 2);
	RETVAL_OBJ(&map->std);
	zval *key = NULL, *element;
	ZEND_HASH_FOREACH_VAL(keys_and_values, element) {
		if (!key) {
			key = element;
		} else if (!sharp_table_put(&map->table, key, element)) {
			RETURN_THROWS();
		} else {
			key = NULL;
		}
	} ZEND_HASH_FOREACH_END();
}

/* A PHP array's entries, under the keys PHP holds them by. */
ZEND_METHOD(Sharp_Map, from)
{
	HashTable *entries;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(entries)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table_object *map = sharp_table_object_create(sharp_ce_map, zend_hash_num_elements(entries));
	RETVAL_OBJ(&map->std);
	zend_ulong index;
	zend_string *name;
	zval *element;
	ZEND_HASH_FOREACH_KEY_VAL(entries, index, name, element) {
		zval key;
		if (name) {
			ZVAL_STR(&key, name);
		} else {
			ZVAL_LONG(&key, (zend_long) index);
		}
		if (!sharp_table_put(&map->table, &key, element)) {
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();
}

/* A PHP array's elements, each once, in their first order. */
ZEND_METHOD(Sharp_Set, from)
{
	HashTable *values;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(values)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table_object *set = sharp_table_object_create(sharp_ce_set, zend_hash_num_elements(values));
	RETVAL_OBJ(&set->std);
	zval null, *element;
	ZVAL_NULL(&null);
	ZEND_HASH_FOREACH_VAL(values, element) {
		if (!sharp_table_put(&set->table, element, &null)) {
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();
}

static const zend_function_entry class_Sharp_Map_methods[] = {
	ZEND_RAW_FENTRY("__construct", zim_Sharp_Table___construct, arginfo_class_Sharp_Table___construct, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Map, of, arginfo_class_Sharp_Map_of, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Map, from, arginfo_class_Sharp_Map_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Map, key, arginfo_class_Sharp_Map_key, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Map, readKey, arginfo_class_Sharp_Map_readKey, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Map, keys, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, values, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Set_methods[] = {
	ZEND_RAW_FENTRY("__construct", zim_Sharp_Table___construct, arginfo_class_Sharp_Table___construct, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Set, from, arginfo_class_Sharp_Set_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Set, toList, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

/* As WeakMap does, the class implements IteratorAggregate and loops through its own get_iterator. */
static zend_class_entry *register_class_Sharp_Table(const char *name, const zend_function_entry *methods)
{
	zend_class_entry ce;

	INIT_CLASS_ENTRY_EX(ce, name, strlen(name), methods);
	zend_class_entry *registered = zend_register_internal_class_with_flags(&ce, NULL,
		ZEND_ACC_FINAL | ZEND_ACC_NO_DYNAMIC_PROPERTIES | ZEND_ACC_NOT_SERIALIZABLE);
	zend_class_implements(registered, 3, zend_ce_aggregate, zend_ce_countable, php_json_serializable_ce);
	registered->create_object = sharp_table_create;
	registered->get_iterator = sharp_table_get_iterator;
	registered->default_object_handlers = &sharp_table_handlers;

	return registered;
}

static void register_sharp_tables(void)
{
	memcpy(&sharp_table_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_table_handlers.offset = XtOffsetOf(sharp_table_object, std);
	sharp_table_handlers.free_obj = sharp_table_free;
	sharp_table_handlers.clone_obj = sharp_table_clone;
	sharp_table_handlers.get_gc = sharp_table_get_gc;
	sharp_table_handlers.get_debug_info = sharp_table_get_debug_info;
	sharp_table_handlers.compare = sharp_table_compare;
	sharp_table_handlers.count_elements = sharp_table_count_elements;
	sharp_table_handlers.read_dimension = sharp_table_read_dimension;
	sharp_table_handlers.write_dimension = sharp_table_write_dimension;
	sharp_table_handlers.has_dimension = sharp_table_has_dimension;
	sharp_table_handlers.unset_dimension = sharp_table_unset_dimension;

	sharp_ce_map = register_class_Sharp_Table("Sharp\\Map", class_Sharp_Map_methods);
	sharp_ce_set = register_class_Sharp_Table("Sharp\\Set", class_Sharp_Set_methods);
}

static zend_class_entry *register_class_Sharp_Struct(void)
{
	zend_class_entry ce;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Struct", NULL);
	zend_class_entry *interface = zend_register_internal_interface(&ce);
	interface->interface_gets_implemented = sharp_struct_implemented;

	return interface;
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
	sharp_collection_handlers.read_dimension = sharp_collection_read_dimension;
	sharp_ce_collection->default_object_handlers = &sharp_collection_handlers;

	register_class_Sharp_Position();
	register_class_Sharp_List();
	sharp_ce_struct = register_class_Sharp_Struct();
	register_sharp_tables();

	zend_class_entry *environment = register_class_Sharp_Environment();
	memcpy(&sharp_environment_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_environment_handlers.read_property = sharp_environment_read_property;
	sharp_environment_handlers.write_property = sharp_environment_write_property;
	sharp_environment_handlers.get_property_ptr_ptr = sharp_environment_get_property_ptr_ptr;
	sharp_environment_handlers.has_property = sharp_environment_has_property;
	sharp_environment_handlers.unset_property = sharp_environment_unset_property;
	environment->default_object_handlers = &sharp_environment_handlers;

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

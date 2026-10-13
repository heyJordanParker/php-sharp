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
#include "zend_interfaces.h"
#include "zend_language_parser.h"
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
	/* The types and argument lists first read from input this request, NULL until there is one. See sharp_types. */
	HashTable *request_types;
	HashTable *request_type_lists;
	/* The class values of request_types, NULL until there is one. See sharp_class_values. */
	HashTable *request_class_values;
	/* The lists sharp_type_list_of_frame spelled this request, keyed by a sharp_type_spelling, NULL until there is one. */
	HashTable *request_spelled_lists;
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

bool sharp_is_sharp_file(const zend_string *filename)
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
 * array where it lives: a local, a plain property or a static property through its slot, an array
 * bound to a reference through the reference it holds, and a
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

/* A reference, such as the one a generic class's static field is bound to by
 * Sharp\Internal\statics, keeps the array where it lives, so a change reaches it. */
void sharp_collection_of_reference(zval *result, zval *reference)
{
	ZVAL_COPY(&sharp_collection_new(result)->value, reference);
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
 * backed enum case. A case goes in under its backing value, as a Map index stores it, and a string of
 * digits is the int key, as in any PHP array. */
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

/* Type texts, see sharp_type in php_sharp.h. A text parses in its one canonical spelling only, so equal types are
 * one pointer: a single space after each comma and on each side of an intersection's `&` (`A & B`), and nowhere else,
 * union and intersection members sorted by their text, and `T?` in place of `T|null`. Types and argument lists live in two tables, because a one-argument list spells its argument.
 * A text parses whole into a tree of sharp_type_node in a request arena before any of it is interned, and the tables'
 * lock is held only across a lookup or an insert, so an allocation that fails never leaves it held.
 *
 * The process's tables keep the types code spells, which its source bounds. A type first read from input, or holding
 * one, interns in the request's own tables instead and is freed with them, so input never grows a worker. A lookup
 * reads the process's table, then the request's, so a type is one pointer either way. */

zend_string *sharp_type_arguments_key;
zend_string *sharp_statics_key;

static HashTable sharp_types;
static HashTable sharp_type_lists;
/* Each class with type arguments in sharp_types, keyed by the address of its interned class name, which is its class
 * value, so `new (type)(…)` finds the type arguments back from the very string `typeof` gave. Plain PHP sees the class
 * name, and any other string with that name is no key, so it names the class without type arguments. */
static HashTable sharp_class_values;
#ifdef ZTS
static MUTEX_T sharp_types_lock;
# define SHARP_TYPES_LOCK() tsrm_mutex_lock(sharp_types_lock)
# define SHARP_TYPES_UNLOCK() tsrm_mutex_unlock(sharp_types_lock)
#else
# define SHARP_TYPES_LOCK()
# define SHARP_TYPES_UNLOCK()
#endif

/* Each built-in type, with the number of type arguments it takes. */
static const struct {
	const char *name;
	uint32_t arity;
	sharp_built_in built_in;
} sharp_built_in_types[] = {
	{"int", 0, SHARP_BUILT_IN_INT}, {"float", 0, SHARP_BUILT_IN_FLOAT}, {"bool", 0, SHARP_BUILT_IN_BOOL},
	{"string", 0, SHARP_BUILT_IN_STRING}, {"void", 0, SHARP_BUILT_IN_VOID}, {"null", 0, SHARP_BUILT_IN_NULL},
	{"Any", 0, SHARP_BUILT_IN_ANY}, {"Object", 0, SHARP_BUILT_IN_OBJECT}, {"List", 1, SHARP_BUILT_IN_LIST},
	{"Map", 2, SHARP_BUILT_IN_MAP}, {"Set", 1, SHARP_BUILT_IN_SET}, {"Iterable", 1, SHARP_BUILT_IN_ITERABLE},
	{"Class", 1, SHARP_BUILT_IN_CLASS},
};

/* A type as its text spells it, before it is interned. */
typedef struct _sharp_type_node sharp_type_node;
struct _sharp_type_node {
	sharp_type_kind kind;
	const char *text;
	size_t length;
	/* A SHARP_TYPE_NAMED type's dotted name, which `name_length` measures. `name` is NULL for a built-in type. */
	const char *name;
	size_t name_length;
	/* The class `name` names, once unserialize resolved it. */
	zend_class_entry *ce;
	/* For `$i` in an open text, i + 1: it stands for this's type argument at index i. 0 for any other type. */
	uint32_t class_parameter;
	/* For `#i` in an open text, i + 1: it stands for the method's own type argument at index i. 0 for any other type. */
	uint32_t method_parameter;
	uint32_t count;
	sharp_type_node *first;
	sharp_type_node *last;
	sharp_type_node *next;
};

typedef struct {
	const char *at;
	const char *end;
	zend_arena *arena;
	/* What the text names, as SHARP_TYPE_NAMES_* bits: it is open when it read a `$i` or a `#i`. */
	uint32_t names;
	/* How many more types may nest in the one being read, each in another's `<>` or `()`. */
	uint32_t depth_left;
} sharp_type_reader;

/* The deepest a type text unserialize reads may nest, counting the outermost type. Its depth costs the request memory
 * in its square, since each nested type is interned with its whole text. The deepest text the bridge writes in
 * Zend/tests/sharp is `$0, List<$0>?`, 2 deep. */
#define SHARP_TYPE_INPUT_DEPTH 64

/* The spelling of a method's type parameter that a call from plain PHP left unresolved, in the parameter types its entry
 * check spells. It matches any type at any depth. No type text spells a bare `?`, so it is never the written `Any?`, and
 * the two intern apart. */
#define SHARP_TYPE_UNRESOLVED "?"

/* A reader at the start of the `length` bytes at `text`, whose nodes go into `arena`. */
static sharp_type_reader sharp_type_reader_of(const char *text, size_t length, zend_arena *arena)
{
	return (sharp_type_reader) {text, text + length, arena, 0, UINT32_MAX};
}

static sharp_type_node *sharp_type_read(sharp_type_reader *reader);

/* The class the dotted `name` names, as PHP writes it: `App\Order` for `App.Order`. */
static zend_string *sharp_type_class_name(const char *name, size_t length, bool persistent)
{
	zend_string *class_name = zend_string_init(name, length, persistent);

	for (char *c = ZSTR_VAL(class_name); *c; c++) {
		if (*c == '.') {
			*c = '\\';
		}
	}

	return class_name;
}

/* `string`, which no zval counts from now on, as an interned string is, for the process or for the request. */
static zend_string *sharp_type_string(zend_string *string, bool persistent)
{
	zend_string_hash_val(string);
	GC_TYPE_INFO(string) = GC_STRING
		| ((IS_STR_INTERNED | (persistent ? IS_STR_PERSISTENT | IS_STR_PERMANENT : 0)) << GC_FLAGS_SHIFT);

	return string;
}

static void sharp_type_release(sharp_type *type)
{
	bool persistent = sharp_type_is_persistent(type);

	if (type->class_name) {
		pefree(type->class_name, persistent);
	}
	pefree(type->text, persistent);
	pefree(type, persistent);
}

static void sharp_type_free(zval *entry)
{
	sharp_type_release(Z_PTR_P(entry));
}

static sharp_type_node *sharp_type_node_start(sharp_type_reader *reader, sharp_type_kind kind, const char *text)
{
	sharp_type_node *node = zend_arena_calloc(&reader->arena, 1, sizeof(sharp_type_node));

	node->kind = kind;
	node->text = text;

	return node;
}

static void sharp_type_node_add(sharp_type_node *node, sharp_type_node *member)
{
	if (node->last) {
		node->last->next = member;
	} else {
		node->first = member;
	}
	node->last = member;
	node->count++;
}

static bool sharp_type_node_is(const sharp_type_node *node, const char *text)
{
	return node->length == strlen(text) && memcmp(node->text, text, node->length) == 0;
}

/* The index in sharp_built_in_types of the built-in type the `length` bytes at `name` name, -1 when they name a class. */
static int sharp_type_built_in_index(const char *name, size_t length)
{
	for (size_t i = 0; i < sizeof(sharp_built_in_types) / sizeof(*sharp_built_in_types); i++) {
		if (strlen(sharp_built_in_types[i].name) == length && memcmp(sharp_built_in_types[i].name, name, length) == 0) {
			return (int) i;
		}
	}

	return -1;
}

/* The number of type arguments the built-in type the `length` bytes at `name` name takes, -1 when they name a
 * class. */
static int sharp_type_built_in_arity(const char *name, size_t length)
{
	int index = sharp_type_built_in_index(name, length);

	return index < 0 ? -1 : (int) sharp_built_in_types[index].arity;
}

/* Whether `token` comes next. */
static bool sharp_type_ahead(const sharp_type_reader *reader, const char *token)
{
	size_t length = strlen(token);

	return (size_t) (reader->end - reader->at) >= length && memcmp(reader->at, token, length) == 0;
}

/* Reads past `token` when it comes next. */
static bool sharp_type_skip(sharp_type_reader *reader, const char *token)
{
	if (!sharp_type_ahead(reader, token)) {
		return false;
	}
	reader->at += strlen(token);

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

/* The interned type or list `text` spells, from the process's tables or the request's. */
static const sharp_type *sharp_type_find(bool list, const char *text, size_t length)
{
	SHARP_TYPES_LOCK();
	const sharp_type *type = zend_hash_str_find_ptr(list ? &sharp_type_lists : &sharp_types, text, length);
	SHARP_TYPES_UNLOCK();

	HashTable *request = list ? SHARP_G(request_type_lists) : SHARP_G(request_types);
	if (!type && request) {
		type = zend_hash_str_find_ptr(request, text, length);
	}

	return type;
}

/* The interned type `node` spells. It lives for the process when code spells it and each member does too. */
static const sharp_type *sharp_type_intern(const sharp_type_node *node, bool from_code, zend_arena **arena)
{
	bool list = node->kind == SHARP_TYPE_LIST;
	const sharp_type *found = sharp_type_find(list, node->text, node->length);

	if (found) {
		return found;
	}

	const sharp_type **members = zend_arena_alloc(arena, MAX(node->count, 1) * sizeof(*members));
	bool persistent = from_code;
	uint32_t i = 0;
	for (const sharp_type_node *member = node->first; member; member = member->next) {
		members[i] = sharp_type_intern(member, from_code, arena);
		persistent = persistent && sharp_type_is_persistent(members[i]);
		i++;
	}

	sharp_type *type = pemalloc(offsetof(sharp_type, members) + MAX(node->count, 1) * sizeof(sharp_type *), persistent);
	type->text = sharp_type_string(zend_string_init(node->text, node->length, persistent), persistent);
	type->class_name = NULL;
	if (node->name) {
		type->class_name = sharp_type_string(sharp_type_class_name(node->name, node->name_length, persistent), persistent);
	}
	type->kind = node->kind;
	type->built_in = SHARP_BUILT_IN_NONE;
	if (node->kind == SHARP_TYPE_NAMED && !node->name) {
		/* A built-in type's name is its text before its type arguments. */
		const char *open = memchr(node->text, '<', node->length);
		int index = sharp_type_built_in_index(node->text, open ? (size_t) (open - node->text) : node->length);
		if (index >= 0) {
			type->built_in = sharp_built_in_types[index].built_in;
		}
	}
	type->count = node->count;
	memcpy(type->members, members, node->count * sizeof(*members));
	bool is_class_value = type->class_name && type->count;

	if (!persistent) {
		HashTable **request = list ? &SHARP_G(request_type_lists) : &SHARP_G(request_types);

		if (!*request) {
			ALLOC_HASHTABLE(*request);
			zend_hash_init(*request, 8, NULL, sharp_type_free, false);
		}
		zend_hash_add_new_ptr(*request, type->text, type);
		if (is_class_value) {
			if (!SHARP_G(request_class_values)) {
				ALLOC_HASHTABLE(SHARP_G(request_class_values));
				zend_hash_init(SHARP_G(request_class_values), 8, NULL, NULL, false);
			}
			zend_hash_index_add_new_ptr(SHARP_G(request_class_values), (zend_ulong) (uintptr_t) type->class_name, type);
		}

		return type;
	}

	/* Another thread may have interned the same text since the lookup above. */
	HashTable *table = list ? &sharp_type_lists : &sharp_types;
	SHARP_TYPES_LOCK();
	found = zend_hash_find_ptr(table, type->text);
	if (!found) {
		zend_hash_add_new_ptr(table, type->text, type);
		if (is_class_value) {
			zend_hash_index_add_new_ptr(&sharp_class_values, (zend_ulong) (uintptr_t) type->class_name, type);
		}
	}
	SHARP_TYPES_UNLOCK();
	if (found) {
		sharp_type_release(type);

		return found;
	}

	return type;
}

/* `name`, `name<A, B>` or `Function<R(P1, P2)>`. */
static sharp_type_node *sharp_type_read_atom(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_node *type;

	bool of_class = sharp_type_skip(reader, "$");
	if (of_class || sharp_type_skip(reader, "#")) {
		uint32_t index = 0;

		/* An index past 9999 is past any class's or method's type parameters. */
		while (reader->at < reader->end && *reader->at >= '0' && *reader->at <= '9' && reader->at - start <= 4) {
			index = index * 10 + (uint32_t) (*reader->at++ - '0');
		}
		if (reader->at == start + 1) {
			return NULL;
		}
		type = sharp_type_node_start(reader, SHARP_TYPE_NAMED, start);
		if (of_class) {
			type->class_parameter = index + 1;
		} else {
			type->method_parameter = index + 1;
		}
		type->length = reader->at - start;
		reader->names |= of_class ? SHARP_TYPE_NAMES_THIS : SHARP_TYPE_NAMES_METHOD;

		return type;
	}
	if (sharp_type_skip(reader, "Function<")) {
		type = sharp_type_node_start(reader, SHARP_TYPE_FUNCTION, start);

		sharp_type_node *returned = sharp_type_read(reader);
		if (!returned || !sharp_type_skip(reader, "(")) {
			return NULL;
		}
		sharp_type_node_add(type, returned);
		if (!sharp_type_skip(reader, ")")) {
			do {
				sharp_type_node *parameter = sharp_type_read(reader);

				if (!parameter) {
					return NULL;
				}
				sharp_type_node_add(type, parameter);
			} while (sharp_type_skip(reader, ", "));
			if (!sharp_type_skip(reader, ")")) {
				return NULL;
			}
		}
		if (!sharp_type_skip(reader, ">")) {
			return NULL;
		}
		type->length = reader->at - start;

		return type;
	}

	do {
		if (!sharp_type_identifier(reader)) {
			return NULL;
		}
	} while (sharp_type_skip(reader, "."));

	type = sharp_type_node_start(reader, SHARP_TYPE_NAMED, start);
	type->name_length = reader->at - start;
	type->name = sharp_type_built_in_arity(start, type->name_length) < 0 ? start : NULL;

	if (sharp_type_skip(reader, "<")) {
		do {
			sharp_type_node *argument = sharp_type_read(reader);

			if (!argument) {
				return NULL;
			}
			sharp_type_node_add(type, argument);
		} while (sharp_type_skip(reader, ", "));
		if (!sharp_type_skip(reader, ">")) {
			return NULL;
		}
	}
	type->length = reader->at - start;

	return type;
}

/* An intersection that starts with `first`, which `start` points at: the classes after it, ` & B & C`, each sorted after
 * the one before it. */
static sharp_type_node *sharp_type_read_intersection(sharp_type_reader *reader, sharp_type_node *first, const char *start)
{
	sharp_type_node *type = sharp_type_node_start(reader, SHARP_TYPE_INTERSECTION, start);

	sharp_type_node_add(type, first);
	while (sharp_type_skip(reader, " & ")) {
		sharp_type_node *member = sharp_type_read_atom(reader);

		if (!member || zend_binary_strcmp(type->last->text, type->last->length, member->text, member->length) >= 0) {
			return NULL;
		}
		sharp_type_node_add(type, member);
	}
	type->length = reader->at - start;

	for (const sharp_type_node *member = type->first; member; member = member->next) {
		if (member->kind != SHARP_TYPE_NAMED || !member->name) {
			return NULL;
		}
	}

	return type->count > 1 ? type : NULL;
}

/* A union member: an atom, or an intersection in parentheses, `(A & B)`, whose text leaves the parentheses out. */
static sharp_type_node *sharp_type_read_member(sharp_type_reader *reader)
{
	if (!sharp_type_skip(reader, "(")) {
		return sharp_type_read_atom(reader);
	}

	const char *start = reader->at;
	sharp_type_node *first = sharp_type_read_atom(reader);
	sharp_type_node *type = first ? sharp_type_read_intersection(reader, first, start) : NULL;

	return type && sharp_type_skip(reader, ")") ? type : NULL;
}

/* Union members after the first one, `|B|C`, each sorted after the one before it. `null` is never a member, because
 * a nullable union is `(A|B)?`, and neither is `Any`, which holds every other type. */
static bool sharp_type_read_union(sharp_type_reader *reader, sharp_type_node *type)
{
	while (sharp_type_skip(reader, "|")) {
		sharp_type_node *member = sharp_type_read_member(reader);

		if (!member || zend_binary_strcmp(type->last->text, type->last->length, member->text, member->length) >= 0) {
			return false;
		}
		sharp_type_node_add(type, member);
	}
	type->length = reader->at - type->text;

	for (const sharp_type_node *member = type->first; member; member = member->next) {
		if (sharp_type_node_is(member, "null") || sharp_type_node_is(member, "Any")) {
			return false;
		}
	}

	return type->count > 1;
}

static sharp_type_node *sharp_type_nullable(sharp_type_reader *reader, sharp_type_node *member, const char *start)
{
	sharp_type_node *type = sharp_type_node_start(reader, SHARP_TYPE_NULLABLE, start);

	sharp_type_node_add(type, member);
	type->length = reader->at - start;

	return type;
}

/* What stands in parentheses after `(`, up to the `)`: an intersection, or a union. */
static sharp_type_node *sharp_type_read_group(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_node *first = sharp_type_read_member(reader);
	sharp_type_node *group;

	if (!first) {
		return NULL;
	}
	if (first->kind == SHARP_TYPE_NAMED && sharp_type_ahead(reader, " & ")) {
		group = sharp_type_read_intersection(reader, first, start);
	} else {
		group = sharp_type_node_start(reader, SHARP_TYPE_UNION, start);
		sharp_type_node_add(group, first);
		group = sharp_type_read_union(reader, group) ? group : NULL;
	}

	return group && sharp_type_skip(reader, ")") ? group : NULL;
}

static sharp_type_node *sharp_type_read_nested(sharp_type_reader *reader);

/* A type, nested no deeper than the reader allows. */
static sharp_type_node *sharp_type_read(sharp_type_reader *reader)
{
	if (reader->depth_left == 0) {
		return NULL;
	}
	reader->depth_left--;
	sharp_type_node *type = sharp_type_read_nested(reader);
	reader->depth_left++;

	return type;
}

/* A type: an atom, `T?`, `A|B`, `A & B`, or a union or an intersection in parentheses: `(A|B)?`, `(A & B)?`, and
 * `(A & B)|C`. */
static sharp_type_node *sharp_type_read_nested(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_node *type;

#ifdef ZEND_CHECK_STACK_LIMIT
	if (UNEXPECTED(zend_call_stack_overflowed(EG(stack_limit)))) {
		return NULL;
	}
#endif

	if (sharp_type_skip(reader, "(")) {
		type = sharp_type_read_group(reader);
		if (!type) {
			return NULL;
		}
		if (sharp_type_skip(reader, "?")) {
			return sharp_type_nullable(reader, type, start);
		}
		/* Without `?`, only an intersection stands in parentheses, as the first member of a union. */
		if (type->kind != SHARP_TYPE_INTERSECTION) {
			return NULL;
		}
	} else {
		type = sharp_type_read_atom(reader);
		if (!type) {
			return NULL;
		}
		if (sharp_type_skip(reader, "?")) {
			return sharp_type_node_is(type, "null") ? NULL : sharp_type_nullable(reader, type, start);
		}
		if (sharp_type_ahead(reader, " & ")) {
			return sharp_type_read_intersection(reader, type, start);
		}
	}
	if (sharp_type_ahead(reader, "|")) {
		sharp_type_node *members = sharp_type_node_start(reader, SHARP_TYPE_UNION, start);

		sharp_type_node_add(members, type);

		return sharp_type_read_union(reader, members) ? members : NULL;
	}

	return type->kind == SHARP_TYPE_INTERSECTION ? NULL : type;
}

/* A type argument list, `A, B`, which is the whole text. */
static sharp_type_node *sharp_type_read_list(sharp_type_reader *reader)
{
	sharp_type_node *arguments = sharp_type_node_start(reader, SHARP_TYPE_LIST, reader->at);

	do {
		sharp_type_node *argument = sharp_type_read(reader);

		if (!argument) {
			return NULL;
		}
		sharp_type_node_add(arguments, argument);
	} while (sharp_type_skip(reader, ", "));
	if (reader->at != reader->end) {
		return NULL;
	}
	arguments->length = reader->at - arguments->text;

	return arguments;
}

const sharp_type *sharp_type_list(const char *text, size_t length)
{
	const sharp_type *list = sharp_type_find(true, text, length);

	if (list) {
		return list;
	}

	sharp_type_reader reader = sharp_type_reader_of(text, length, zend_arena_create(1024));
	sharp_type_node *arguments = sharp_type_read_list(&reader);
	if (arguments && !reader.names) {
		list = sharp_type_intern(arguments, true, &reader.arena);
	}
	zend_arena_destroy(reader.arena);

	return list;
}

uint32_t sharp_type_list_names(const char *text, size_t length)
{
	sharp_type_reader reader = sharp_type_reader_of(text, length, zend_arena_create(1024));
	uint32_t names = sharp_type_read_list(&reader) ? reader.names : 0;

	zend_arena_destroy(reader.arena);

	return names;
}

static void sharp_type_request_table_free(HashTable **table)
{
	if (*table) {
		zend_hash_destroy(*table);
		FREE_HASHTABLE(*table);
		*table = NULL;
	}
}

/* The hidden slot `ce` itself declares, NULL when it declares none: a class without type parameters inherits its
 * parent's slot and never fills it. */
static const zend_property_info *sharp_type_arguments_slot(const zend_class_entry *ce)
{
	const zend_property_info *slot = zend_hash_find_ptr(&ce->properties_info, sharp_type_arguments_key);

	return slot && slot->ce == ce && (slot->flags & ZEND_ACC_SHARP_HIDDEN) ? slot : NULL;
}

/* Whether PHP# declared `ce`, so its metadata says which type parameters it has. A class plain PHP declares writes its
 * type parameters in docblocks only, which the engine never reads. */
static bool sharp_class_is_sharp(const zend_class_entry *ce)
{
	return ce->type == ZEND_USER_CLASS && sharp_is_sharp_file(ce->info.user.filename);
}

/* The bounds of the type parameters of `ce`, a PHP# class-like, which its objects start with as their type arguments.
 * NULL when it declares none. */
static const sharp_type *sharp_class_bounds(const zend_class_entry *ce)
{
	const zend_string *bounds = ce->info.user.sharp_bounds;

	if (!bounds) {
		return NULL;
	}

	const sharp_type *list = sharp_type_list(ZSTR_VAL(bounds), ZSTR_LEN(bounds));
	ZEND_ASSERT(list != NULL);

	return list;
}

const sharp_type *sharp_type_arguments_of_slot(zend_object *object)
{
	/* A lazy proxy has the type arguments of its real instance, which it initializes first, as == and serialize do.
	 * NULL with an exception when the initializer fails. A real instance of a parent class has as many type arguments
	 * as the parent has type parameters, so the proxy reads its own slot, which is never lazy, as an object plain PHP
	 * creates does. */
	if (UNEXPECTED(zend_object_is_lazy_proxy(object))) {
		zend_object *instance = zend_lazy_object_init(object);

		if (!instance) {
			return NULL;
		}
		if (instance->ce == object->ce) {
			return sharp_type_arguments(instance);
		}
	}

	const zend_property_info *slot = sharp_type_arguments_slot(object->ce);

	ZEND_ASSERT(slot != NULL);

	zval *value = OBJ_PROP(object, slot->offset);
	if (Z_TYPE_P(value) == IS_PTR) {
		return Z_PTR_P(value);
	}

	/* Plain PHP created the object, so its slot holds null, which keeps its class's bounds once they are looked up, or is
	 * UNDEF when the object was created lazy. */
	const sharp_type *bounds = sharp_class_bounds(object->ce);
	if (Z_TYPE_P(value) == IS_NULL) {
		ZVAL_PTR(value, (void *) bounds);
	}

	return bounds;
}

/* How many type parameters `ce`, a PHP# class-like, declares: the members of its bounds text, separated by the commas
 * outside any `<>` or `()`. `new` counts them on each call, so it reads the text instead of looking up its interned
 * list, which hashes the text and, under ZTS, takes the process tables' lock. */
static uint32_t sharp_class_arity(const zend_class_entry *ce)
{
	const zend_string *bounds = ce->info.user.sharp_bounds;
	uint32_t count = 1;
	uint32_t depth = 0;

	if (!bounds) {
		return 0;
	}
	for (size_t i = 0; i < ZSTR_LEN(bounds); i++) {
		switch (ZSTR_VAL(bounds)[i]) {
			case '<':
			case '(':
				depth++;
				break;
			case '>':
			case ')':
				depth--;
				break;
			case ',':
				count += depth == 0;
				break;
		}
	}

	return count;
}

bool sharp_class_takes(const zend_class_entry *ce, const sharp_type *arguments)
{
	uint32_t count = sharp_class_is_sharp(ce) ? sharp_class_arity(ce) : 0;

	if (EXPECTED(arguments->count == count)) {
		return true;
	}
	if (!count) {
		zend_throw_error(NULL, "Class %s declares no type parameters, so new cannot give it <%s>",
			ZSTR_VAL(ce->name), ZSTR_VAL(arguments->text));
	} else {
		zend_throw_error(NULL, "Class %s declares %" PRIu32 " type parameter%s, so new cannot give it <%s>",
			ZSTR_VAL(ce->name), count, count == 1 ? "" : "s", ZSTR_VAL(arguments->text));
	}

	return false;
}

void sharp_type_arguments_store(zend_object *object, const sharp_type *arguments)
{
	const zend_property_info *slot = sharp_type_arguments_slot(object->ce);

	ZEND_ASSERT(slot != NULL && arguments->kind == SHARP_TYPE_LIST);
	ZVAL_PTR(OBJ_PROP(object, slot->offset), (void *) arguments);
}

/* Sorts the members of the union or intersection `node` by their text. Two members that are one type are one member
 * when `merge` holds, and make it fail otherwise. */
static bool sharp_type_node_sort(sharp_type_node *node, bool merge)
{
	sharp_type_node *sorted = NULL;

	for (sharp_type_node *member = node->first, *next; member; member = next) {
		sharp_type_node **at = &sorted;
		int order = -1;

		next = member->next;
		while (*at && (order = zend_binary_strcmp((*at)->text, (*at)->length, member->text, member->length)) < 0) {
			at = &(*at)->next;
		}
		if (*at && order == 0) {
			if (!merge) {
				return false;
			}
			node->count--;
			continue;
		}
		member->next = *at;
		*at = member;
	}
	node->first = sorted;
	for (node->last = sorted; node->last->next; node->last = node->last->next) {
	}

	return true;
}

static void sharp_type_node_join(smart_str *text, const sharp_type_node *first, const char *separator)
{
	for (const sharp_type_node *member = first; member; member = member->next) {
		if (member != first) {
			smart_str_appends(text, separator);
		}
		smart_str_appendl(text, member->text, member->length);
	}
}

/* Appends `member` as a union or a nullable type holds it, an intersection or a union in parentheses. */
static void sharp_type_node_append_grouped(smart_str *text, const sharp_type_node *member)
{
	bool grouped = member->kind == SHARP_TYPE_INTERSECTION || member->kind == SHARP_TYPE_UNION;

	if (grouped) {
		smart_str_appendc(text, '(');
	}
	smart_str_appendl(text, member->text, member->length);
	if (grouped) {
		smart_str_appendc(text, ')');
	}
}

/* Writes the text of `node` from the texts of its members, into `arena`, with a class by the name it was declared with
 * once unserialize resolved it. */
static void sharp_type_node_spell(sharp_type_node *node, zend_arena **arena)
{
	smart_str text = {0};
	switch (node->kind) {
		case SHARP_TYPE_LIST:
			sharp_type_node_join(&text, node->first, ", ");
			break;
		case SHARP_TYPE_NAMED:
			if (node->ce) {
				node->name_length = ZSTR_LEN(node->ce->name);
				for (size_t i = 0; i < node->name_length; i++) {
					smart_str_appendc(&text, ZSTR_VAL(node->ce->name)[i] == '\\' ? '.' : ZSTR_VAL(node->ce->name)[i]);
				}
			} else {
				smart_str_appendl(&text, node->text, node->name_length);
			}
			if (node->count) {
				smart_str_appendc(&text, '<');
				sharp_type_node_join(&text, node->first, ", ");
				smart_str_appendc(&text, '>');
			}
			break;
		case SHARP_TYPE_NULLABLE:
			sharp_type_node_append_grouped(&text, node->first);
			smart_str_appendc(&text, '?');
			break;
		case SHARP_TYPE_UNION:
			for (const sharp_type_node *member = node->first; member; member = member->next) {
				if (member != node->first) {
					smart_str_appendc(&text, '|');
				}
				sharp_type_node_append_grouped(&text, member);
			}
			break;
		case SHARP_TYPE_INTERSECTION:
			sharp_type_node_join(&text, node->first, " & ");
			break;
		case SHARP_TYPE_FUNCTION:
			smart_str_appends(&text, "Function<");
			smart_str_appendl(&text, node->first->text, node->first->length);
			smart_str_appendc(&text, '(');
			sharp_type_node_join(&text, node->first->next, ", ");
			smart_str_appends(&text, ")>");
			break;
	}

	char *spelling = zend_arena_alloc(arena, ZSTR_LEN(text.s));
	memcpy(spelling, ZSTR_VAL(text.s), ZSTR_LEN(text.s));
	node->text = spelling;
	node->length = ZSTR_LEN(text.s);
	if (node->name) {
		node->name = spelling;
	}
	smart_str_free(&text);
}

/* Adds `type` to the union `node` as code writes a union: the members of a union one by one, and `null`, or the null of a
 * nullable type, as `nullable`. */
static void sharp_type_union_add(sharp_type_node *node, sharp_type_node *type, bool *nullable)
{
	if (type->kind == SHARP_TYPE_UNION) {
		for (sharp_type_node *member = type->first, *next; member; member = next) {
			next = member->next;
			member->next = NULL;
			sharp_type_union_add(node, member, nullable);
		}
	} else if (type->kind == SHARP_TYPE_NULLABLE) {
		*nullable = true;
		sharp_type_union_add(node, type->first, nullable);
	} else if (sharp_type_node_is(type, "null")) {
		*nullable = true;
	} else {
		sharp_type_node_add(node, type);
	}
}

/* `node` with each `$i` in it replaced by member i of `of_class`, a list or a class type, and each `#i` by member i of
 * `of_method`, a list, or by SHARP_TYPE_UNRESOLVED when `unresolved` holds, spelled as code spells the type it becomes:
 * a union flattens a union or a nullable type it is given, `Any` takes in a whole union, an unresolved type parameter
 * takes in a whole union or nullable type, and a nullable type stays one `?`. NULL when `node` names an index the list
 * has no member at, or names one of a list that is NULL. */
static sharp_type_node *sharp_type_node_substitute(sharp_type_node *node, const sharp_type_node *of_class,
	const sharp_type_node *of_method, bool unresolved, sharp_type_reader *reader)
{
	if (node->method_parameter && unresolved) {
		sharp_type_node *wildcard = sharp_type_node_start(reader, SHARP_TYPE_NAMED, SHARP_TYPE_UNRESOLVED);
		wildcard->length = wildcard->name_length = strlen(SHARP_TYPE_UNRESOLVED);

		return wildcard;
	}
	uint32_t parameter = node->class_parameter ? node->class_parameter : node->method_parameter;
	if (parameter) {
		const sharp_type_node *arguments = node->class_parameter ? of_class : of_method;
		if (!arguments || parameter > arguments->count) {
			return NULL;
		}

		const sharp_type_node *given = arguments->first;
		for (uint32_t i = 1; i < parameter; i++) {
			given = given->next;
		}
		/* A copy, since the type it goes into is respelled in place. */
		sharp_type_reader argument = sharp_type_reader_of(given->text, given->length, reader->arena);
		sharp_type_node *type = sharp_type_read(&argument);
		reader->arena = argument.arena;
		ZEND_ASSERT(type && argument.at == argument.end);

		return type;
	}

	sharp_type_node *members = node->first;
	bool nullable = false;
	node->first = node->last = NULL;
	node->count = 0;
	for (sharp_type_node *member = members, *next; member; member = next) {
		next = member->next;
		member->next = NULL;

		sharp_type_node *type = sharp_type_node_substitute(member, of_class, of_method, unresolved, reader);
		if (!type) {
			return NULL;
		}
		if (node->kind == SHARP_TYPE_UNION) {
			sharp_type_union_add(node, type, &nullable);
		} else {
			sharp_type_node_add(node, type);
		}
	}

	if (node->kind == SHARP_TYPE_NULLABLE
		&& (node->first->kind == SHARP_TYPE_NULLABLE || sharp_type_node_is(node->first, "null")
			|| sharp_type_node_is(node->first, SHARP_TYPE_UNRESOLVED))) {
		return node->first;
	}
	if (node->kind == SHARP_TYPE_UNION) {
		for (sharp_type_node *member = node->first; member; member = member->next) {
			if (sharp_type_node_is(member, SHARP_TYPE_UNRESOLVED)) {
				member->next = NULL;

				return member;
			}
			if (sharp_type_node_is(member, "Any")) {
				member->next = NULL;
				node->first = node->last = member;
				node->count = 1;
				break;
			}
		}
		if (!node->first) {
			node = sharp_type_node_start(reader, SHARP_TYPE_NAMED, "null");
			node->length = node->name_length = strlen("null");

			return node;
		}
		sharp_type_node_sort(node, true);
		if (node->count == 1) {
			node = node->first;
		}
		if (nullable) {
			sharp_type_node *type = sharp_type_node_start(reader, SHARP_TYPE_NULLABLE, NULL);

			sharp_type_node_spell(node, &reader->arena);
			sharp_type_node_add(type, node);
			node = type;
		}
	}
	if (node->kind == SHARP_TYPE_INTERSECTION) {
		sharp_type_node_sort(node, true);
	}
	sharp_type_node_spell(node, &reader->arena);

	return node;
}

/* The type argument list `text` spells, read whole into `reader`'s arena, or an empty list when `text` is NULL. */
static sharp_type_node *sharp_type_node_list(const zend_string *text, sharp_type_reader *reader)
{
	if (!text) {
		return sharp_type_node_start(reader, SHARP_TYPE_LIST, NULL);
	}

	sharp_type_reader list = sharp_type_reader_of(ZSTR_VAL(text), ZSTR_LEN(text), reader->arena);
	sharp_type_node *node = sharp_type_read_list(&list);
	reader->arena = list.arena;
	ZEND_ASSERT(node != NULL);

	return node;
}

/* The type arguments `ancestor`, one of the classes `ce` extends or implements, takes from `ce` given `arguments`, a
 * list or a class type: the entry of `ce`'s header for the class it leads up through, with each `$i` in it replaced by
 * argument i, and so on up. A class the header gives no type arguments, or plain PHP's, starts from its bounds, as an
 * object plain PHP creates does. NULL when an entry names an index `arguments` has no member at. */
static sharp_type_node *sharp_type_node_ancestor(const zend_class_entry *ce, sharp_type_node *arguments,
	const zend_class_entry *ancestor, sharp_type_reader *reader)
{
	while (arguments && ce != ancestor) {
		const zend_class_entry *next = NULL;

		if (sharp_class_is_sharp(ce) && ce->info.user.sharp_header) {
			sharp_type_node *header = sharp_type_node_list(ce->info.user.sharp_header, reader);

			/* A loaded class has loaded each class it extends or implements. */
			for (sharp_type_node *entry = header->first; entry && !next; entry = entry->next) {
				zend_string *name = sharp_type_class_name(entry->name, entry->name_length, false);
				zend_class_entry *named = zend_lookup_class_ex(name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
				zend_string_release_ex(name, false);

				if (named && instanceof_function(named, ancestor)) {
					next = named;
					arguments = sharp_type_node_substitute(entry, arguments, NULL, false, reader);
				}
			}
		}
		if (!next) {
			next = ce->parent && instanceof_function(ce->parent, ancestor) ? ce->parent : NULL;
			for (uint32_t i = 0; !next && i < ce->num_interfaces; i++) {
				next = instanceof_function(ce->interfaces[i], ancestor) ? ce->interfaces[i] : NULL;
			}
			ZEND_ASSERT(next != NULL);
			arguments = sharp_type_node_list(sharp_class_is_sharp(next) ? next->info.user.sharp_bounds : NULL, reader);
		}
		ce = next;
	}

	return arguments;
}

/* What a list sharp_type_list_of_frame spells depends on, as the key of the request's table. Its padding is zeroed, since
 * the table hashes its bytes. */
typedef struct {
	const zend_string *text;
	const zend_class_entry *scope;
	const zend_class_entry *ce;
	const sharp_type *own;
	const sharp_type *method;
	bool unresolved;
} sharp_type_spelling;

const sharp_type *sharp_type_list_of_frame(const zval *text, zend_object *object, const sharp_type *method,
	bool unresolved, const zend_class_entry *scope, void **cache)
{
	/* Only a text that names a `$i` reads this's type arguments, so one that names none never runs a lazy proxy's
	 * initializer. A lambda bound to an object of an unrelated class has no type arguments of the class it is written
	 * in to read. */
	bool names_this = memchr(Z_STRVAL_P(text), '$', Z_STRLEN_P(text)) != NULL;
	const zend_class_entry *ce = names_this && object && instanceof_function(object->ce, scope) ? object->ce : NULL;
	const sharp_type *own = NULL;
	if (ce) {
		/* A lazy proxy's initializer runs here, and can throw. */
		own = sharp_type_arguments(object);
		if (UNEXPECTED(EG(exception))) {
			return NULL;
		}
	}
	/* The list depends only on this's class, its own type arguments and the method's, so the site keeps the last one it
	 * spelled. */
	if (cache[3] && cache[0] == ce && cache[1] == own && cache[2] == method) {
		return cache[3];
	}
	/* The request keeps every list it spelled, so a site whose this alternates between classes or type arguments, as one
	 * generic service used for several entity types does, spells each once. The text is an interned literal, and two
	 * sites write one text into one pointer, so the class each is written in tells them apart. */
	ZEND_ASSERT(ZSTR_IS_INTERNED(Z_STR_P(text)));
	sharp_type_spelling key;
	memset(&key, 0, sizeof(key));
	key.text = Z_STR_P(text);
	key.scope = scope;
	key.ce = ce;
	key.own = own;
	key.method = method;
	key.unresolved = unresolved;
	HashTable **spelled = &SHARP_G(request_spelled_lists);
	const sharp_type *substituted = *spelled ? zend_hash_str_find_ptr(*spelled, (const char *) &key, sizeof(key)) : NULL;
	if (substituted) {
		cache[0] = (void *) ce;
		cache[1] = (void *) own;
		cache[2] = (void *) method;
		cache[3] = (void *) substituted;

		return substituted;
	}
	sharp_type_reader reader = sharp_type_reader_of(Z_STRVAL_P(text), Z_STRLEN_P(text), zend_arena_create(1024));
	sharp_type_node *open = sharp_type_read_list(&reader);
	ZEND_ASSERT(open != NULL);
	ZEND_ASSERT(object || !(reader.names & SHARP_TYPE_NAMES_THIS));
	/* The indexes of `$i` count the type parameters of the class the code is written in, which a subclass's header gives
	 * their values. */
	sharp_type_node *of_class = ce
		? sharp_type_node_ancestor(ce, sharp_type_node_list(own ? own->text : NULL, &reader), scope, &reader)
		: NULL;
	sharp_type_node *of_method = method ? sharp_type_node_list(method->text, &reader) : NULL;
	sharp_type_node *list = sharp_type_node_substitute(open, of_class, of_method, unresolved, &reader);
	/* A lambda bound to an object of another class that has no type argument at an index the text names makes an object
	 * with its class's bounds, as one plain PHP creates. */
	if (!list) {
		zend_arena_destroy(reader.arena);

		return NULL;
	}
	/* A type a substitution spells is bounded by how deep the program nests it, not by its source, as in
	 * Node<List<T>>, so a new one lives for the request. One code already spells is the process's, as is a closed text
	 * code writes, and an open one that names no `$i` on a call that gives no type arguments, such as the wildcard a
	 * plain PHP call checks against, which the text alone decides. */
	substituted = sharp_type_intern(list, !reader.names || (!names_this && !method), &reader.arena);
	zend_arena_destroy(reader.arena);
	if (!*spelled) {
		ALLOC_HASHTABLE(*spelled);
		zend_hash_init(*spelled, 8, NULL, NULL, false);
	}
	zend_hash_str_add_new_ptr(*spelled, (const char *) &key, sizeof(key), (void *) substituted);

	/* Every user run-time cache lives for one request: zend_activate zeroes the dynamic map_ptr table, and accel_activate
	 * the preloaded caches. A list that lives for the request, which unserialize or a substitution makes, is freed at
	 * request end after every object, so the site keeps it as it keeps one the process holds. */
	cache[0] = (void *) ce;
	cache[1] = (void *) own;
	cache[2] = (void *) method;
	cache[3] = (void *) substituted;

	return substituted;
}

/* The class `node`, a SHARP_TYPE_NAMED type, names, once loaded, or NULL for a built-in type. */
static zend_class_entry *sharp_type_node_class(sharp_type_node *node)
{
	if (!node->ce && node->name) {
		zend_string *name = sharp_type_class_name(node->name, node->name_length, false);
		node->ce = zend_lookup_class_ex(name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
		zend_string_release_ex(name, false);
	}

	return node->ce;
}

/* The variance of the type parameter at `index` of `ce`, a class-like: `+` for `out`, `-` for `in` and `=` for neither,
 * which a plain PHP class's type parameters have. */
static char sharp_class_variance(const zend_class_entry *ce, uint32_t index)
{
	const zend_string *variance = sharp_class_is_sharp(ce) ? ce->info.user.sharp_variance : NULL;

	return variance && index < ZSTR_LEN(variance) ? ZSTR_VAL(variance)[index] : '=';
}

static bool sharp_type_fits(sharp_type_node *type, sharp_type_node *bound, sharp_type_reader *reader);

/* Whether `given`, a type argument for a type parameter of `variance`, is one the type argument `wanted` takes, spec
 * section 11.1: `wanted` itself, or for `out` a type within it, and for `in` a type it is within. */
static bool sharp_type_argument_fits(
	sharp_type_node *given, sharp_type_node *wanted, char variance, sharp_type_reader *reader)
{
	switch (variance) {
		case '+':
			return sharp_type_fits(given, wanted, reader);
		case '-':
			return sharp_type_fits(wanted, given, reader);
		default:
			return given->length == wanted->length && memcmp(given->text, wanted->text, given->length) == 0;
	}
}

/* Whether `given`, a type argument an object holds, matches `expected`: SHARP_TYPE_UNRESOLVED matches any type at any
 * depth, and every other type only itself, a written `Any?` too. */
static bool sharp_type_argument_matches(const sharp_type *expected, const sharp_type_node *given)
{
	if (zend_string_equals_literal(expected->text, SHARP_TYPE_UNRESOLVED)
		|| zend_string_equals_cstr(expected->text, given->text, given->length)) {
		return true;
	}
	if (expected->kind != given->kind || expected->count != given->count) {
		return false;
	}
	if (expected->kind == SHARP_TYPE_NAMED) {
		/* The same class or built-in type, then its type arguments. */
		size_t name_length = given->name_length;
		if (!expected->count || ZSTR_LEN(expected->text) <= name_length
			|| memcmp(ZSTR_VAL(expected->text), given->text, name_length) != 0
			|| ZSTR_VAL(expected->text)[name_length] != '<') {
			return false;
		}
	}

	uint32_t i = 0;
	for (const sharp_type_node *member = given->first; member; member = member->next, i++) {
		if (!sharp_type_argument_matches(expected->members[i], member)) {
			return false;
		}
	}

	return true;
}

/* Whether `given`, a type argument an object holds for a type parameter of `variance`, is one `expected` takes: one
 * sharp_type_argument_matches matches, or one sharp_type_argument_fits takes for `out` or `in`, spec section 11.1. */
static bool sharp_type_argument_takes(
	const sharp_type *expected, sharp_type_node *given, char variance, sharp_type_reader *reader)
{
	if (sharp_type_argument_matches(expected, given)) {
		return true;
	}
	if (variance == '=') {
		return false;
	}

	/* A text with SHARP_TYPE_UNRESOLVED in it reads as no type, so only sharp_type_argument_matches takes a value for it. */
	sharp_type_reader text = sharp_type_reader_of(ZSTR_VAL(expected->text), ZSTR_LEN(expected->text), reader->arena);
	sharp_type_node *wanted = sharp_type_read(&text);
	reader->arena = text.arena;

	return wanted && text.at == text.end && sharp_type_argument_fits(given, wanted, variance, reader);
}

/* Whether `given`, with `arguments` for its type parameters, a list or a class type, gives `ce`, a class it is or extends
 * or implements, type arguments that each take `type`'s own, as sharp_type_argument_takes takes them. */
static bool sharp_type_arguments_take(const zend_class_entry *given, sharp_type_node *arguments,
	const zend_class_entry *ce, const sharp_type *type, sharp_type_reader *reader)
{
	const sharp_type_node *of_ce = sharp_type_node_ancestor(given, arguments, ce, reader);
	if (!of_ce || of_ce->count != type->count) {
		return false;
	}
	uint32_t i = 0;
	for (sharp_type_node *argument = of_ce->first; argument; argument = argument->next, i++) {
		if (!sharp_type_argument_takes(type->members[i], argument, sharp_class_variance(ce, i), reader)) {
			return false;
		}
	}

	return true;
}

/* Whether `object` is an instance of the class `type` names whose type arguments for that class match `type`'s own, as
 * sharp_type_argument_matches matches them. Only a class PHP# declares with type parameters has type arguments to
 * compare: plain PHP's generics are erased. A loaded object's class has loaded each class it extends or implements. */
static bool sharp_object_is(zend_object *object, const sharp_type *type)
{
	/* An object of the class itself holds its type arguments for that class, interned, so equal ones are one pointer. A
	 * plain PHP class holds none. A subclass, a name written in another case, or a member that differs, as one a call
	 * from plain PHP left unresolved, takes the walk below. */
	if (zend_string_equals(object->ce->name, type->class_name)) {
		/* A lazy proxy's initializer runs here, and can throw. Its exception stands for the check. */
		const sharp_type *own = sharp_type_arguments(object);
		if (!own || !type->count || UNEXPECTED(EG(exception))) {
			return true;
		}
		bool is = own->count == type->count;
		for (uint32_t i = 0; is && i < type->count; i++) {
			is = sharp_type_equals(own->members[i], type->members[i]);
		}
		if (is) {
			return true;
		}
	}

	zend_class_entry *ce = zend_lookup_class_ex(type->class_name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
	if (!ce || !instanceof_function(object->ce, ce)) {
		return false;
	}
	if (!type->count || !sharp_class_is_sharp(ce) || !ce->info.user.sharp_bounds) {
		return true;
	}

	/* A lazy proxy's initializer runs here, and can throw. Its exception stands for the check. */
	const sharp_type *own = sharp_type_arguments(object);
	if (UNEXPECTED(EG(exception))) {
		return true;
	}
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	bool is = sharp_type_arguments_take(object->ce, sharp_type_node_list(own ? own->text : NULL, &reader), ce, type, &reader);
	zend_arena_destroy(reader.arena);

	return is;
}

/* The class with type arguments whose class value is `value`, or NULL for any other string, see sharp_class_values. */
static const sharp_type *sharp_class_value_type(const zend_string *value)
{
	if (!ZSTR_IS_INTERNED(value)) {
		return NULL;
	}
	zend_ulong address = (zend_ulong) (uintptr_t) value;

	SHARP_TYPES_LOCK();
	const sharp_type *type = zend_hash_index_find_ptr(&sharp_class_values, address);
	SHARP_TYPES_UNLOCK();
	if (!type && SHARP_G(request_class_values)) {
		type = zend_hash_index_find_ptr(SHARP_G(request_class_values), address);
	}

	return type;
}

/* Whether the string `value` is a class value of `Class<type>`, spec section 11: it names `type`'s class or a class that
 * extends or implements it, and the type arguments it carries give that class type arguments `type` takes. A class name
 * plain PHP writes carries none, so it gives its class's bounds, as an object plain PHP creates does. A `Class` of a type
 * that is no class, such as `typeof(TItem)` of an `int`, takes any string. */
static bool sharp_class_value_is(const zval *value, const sharp_type *type)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return false;
	}
	if (type->kind != SHARP_TYPE_NAMED || !type->class_name) {
		return true;
	}

	/* An autoloader runs here, and can throw. Its exception stands for the check. */
	zend_class_entry *given = zend_lookup_class(Z_STR_P(value));
	zend_class_entry *ce = given ? zend_lookup_class_ex(type->class_name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD) : NULL;
	if (!ce || !instanceof_function(given, ce)) {
		return false;
	}
	if (!type->count || !sharp_class_is_sharp(ce) || !ce->info.user.sharp_bounds) {
		return true;
	}

	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	const sharp_type *carried = sharp_class_value_type(Z_STR_P(value));
	sharp_type_node *arguments;
	if (carried) {
		sharp_type_reader text = sharp_type_reader_of(ZSTR_VAL(carried->text), ZSTR_LEN(carried->text), reader.arena);
		arguments = sharp_type_read(&text);
		reader.arena = text.arena;
	} else {
		arguments = sharp_type_node_list(sharp_class_is_sharp(given) ? given->info.user.sharp_bounds : NULL, &reader);
	}
	bool is = sharp_type_arguments_take(given, arguments, ce, type, &reader);
	zend_arena_destroy(reader.arena);

	return is;
}

/* Appends an object's class as PHP# writes it, `App.Order`, with its type arguments when its class is generic,
 * `App.Box<int>`. */
static void sharp_object_text(smart_str *text, zend_object *object)
{
	const zend_string *name = object->ce->name;
	for (size_t i = 0; i < ZSTR_LEN(name); i++) {
		smart_str_appendc(text, ZSTR_VAL(name)[i] == '\\' ? '.' : ZSTR_VAL(name)[i]);
	}

	const sharp_type *arguments = sharp_type_arguments(object);
	if (arguments) {
		smart_str_appendc(text, '<');
		smart_str_append(text, arguments->text);
		smart_str_appendc(text, '>');
	}
}

/* The longest string a problem quotes, in bytes. */
#define SHARP_QUOTED_STRING_LENGTH 40

/* Appends a value as a problem shows it: `null`, `bool true`, `int 5`, `float 1.5`, `string 'abc'` cut after
 * SHARP_QUOTED_STRING_LENGTH bytes, `List` or `Map` for an array, `object App.Order`. */
static ZEND_COLD void sharp_value_text(smart_str *text, const zval *value)
{
	switch (Z_TYPE_P(value)) {
		case IS_NULL:
			smart_str_appends(text, "null");
			return;
		case IS_FALSE:
		case IS_TRUE:
			smart_str_appends(text, Z_TYPE_P(value) == IS_TRUE ? "bool true" : "bool false");
			return;
		case IS_LONG:
			smart_str_appends(text, "int ");
			smart_str_append_long(text, Z_LVAL_P(value));
			return;
		case IS_DOUBLE:
			smart_str_appends(text, "float ");
			smart_str_append_double(text, Z_DVAL_P(value), (int) PG(serialize_precision), false);
			return;
		case IS_STRING:
			smart_str_appends(text, "string '");
			smart_str_appendl(text, Z_STRVAL_P(value), MIN(Z_STRLEN_P(value), SHARP_QUOTED_STRING_LENGTH));
			smart_str_appends(text, Z_STRLEN_P(value) > SHARP_QUOTED_STRING_LENGTH ? "…'" : "'");
			return;
		case IS_ARRAY:
			smart_str_appends(text, zend_array_is_list(Z_ARRVAL_P(value)) ? "List" : "Map");
			return;
		case IS_OBJECT:
			smart_str_appends(text, "object ");
			sharp_object_text(text, Z_OBJ_P(value));
			return;
		default:
			smart_str_appends(text, zend_zval_value_name(value));
	}
}

/* Adds the problem of `value`, which is not of `type`, at the path the walk is at: `<prefix>expected <type>, got
 * <value>`. */
static ZEND_COLD void sharp_type_problem(
	sharp_type_problems *problems, const char *prefix, const sharp_type *type, const zval *value)
{
	smart_str problem = {0};
	smart_str_appends(&problem, prefix);
	smart_str_appends(&problem, "expected ");
	smart_str_append(&problem, type->text);
	smart_str_appends(&problem, ", got ");
	sharp_value_text(&problem, value);
	smart_str_0(&problem);

	zval text;
	ZVAL_STR(&text, problem.s);
	/* The walk keeps changing the path, so the array keeps a copy of it. */
	const zend_string *path = problems->path.s ? problems->path.s : ZSTR_EMPTY_ALLOC();
	zend_symtable_str_update(Z_ARRVAL_P(problems->problems), ZSTR_VAL(path), ZSTR_LEN(path), &text);
}

/* The type of the elements of the collection `type`, or NULL when `type` is no collection whose elements are checked.
 * A `Map`'s elements are its values. */
static const sharp_type *sharp_type_elements(const sharp_type *type)
{
	switch (type->built_in) {
		case SHARP_BUILT_IN_MAP:
			return type->members[1];
		case SHARP_BUILT_IN_LIST:
		case SHARP_BUILT_IN_SET:
		case SHARP_BUILT_IN_ITERABLE:
			return type->members[0];
		default:
			return NULL;
	}
}

/* The collection whose elements the array `value` is walked by when it is of `type`, so each bad element is a problem at
 * its own path: `type` itself, the member of `T?`, or the one collection a union has. NULL for any other type, whose
 * problem is the value's own. */
static const sharp_type *sharp_type_walked(const sharp_type *type, const zval *value)
{
	if (Z_TYPE_P(value) != IS_ARRAY) {
		return NULL;
	}
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return sharp_type_elements(type) ? type : NULL;
		case SHARP_TYPE_NULLABLE:
			return sharp_type_walked(type->members[0], value);
		case SHARP_TYPE_UNION: {
			const sharp_type *walked = NULL;
			for (uint32_t i = 0; i < type->count; i++) {
				const sharp_type *member = sharp_type_walked(type->members[i], value);
				if (member && walked) {
					return NULL;
				}
				walked = member ? member : walked;
			}
			return walked;
		}
		default:
			return NULL;
	}
}

static bool sharp_type_holds(const sharp_type *type, const zval *value, bool int_as_float, sharp_type_problems *problems);

/* Whether `key`, or the int `index` when `key` is NULL, is a key of a `Map` whose keys are of `type`. PHP stores the key
 * `"5"` as the int 5, so a `string` key type takes every key, and a `Map` keyed by a backed enum holds each case's
 * backing value. */
static bool sharp_type_holds_key(const sharp_type *type, zend_ulong index, zend_string *key)
{
	if (type->built_in == SHARP_BUILT_IN_STRING) {
		return true;
	}

	zend_class_entry *ce = type->class_name
		? zend_lookup_class_ex(type->class_name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD) : NULL;
	if (ce && (ce->ce_flags & ZEND_ACC_ENUM) && ce->enum_backing_type != IS_UNDEF) {
		zend_object *found = NULL;
		if (ce->enum_backing_type == IS_LONG) {
			return !key && zend_enum_get_case_by_value(&found, ce, (zend_long) index, NULL, true) == SUCCESS && found;
		}
		zend_string *text = key ? zend_string_copy(key) : zend_long_to_str((zend_long) index);
		bool is = zend_enum_get_case_by_value(&found, ce, 0, text, true) == SUCCESS && found;
		zend_string_release(text);

		return is;
	}

	zval value;
	if (key) {
		ZVAL_STR(&value, key);
	} else {
		ZVAL_LONG(&value, (zend_long) index);
	}

	return sharp_type_holds(type, &value, false, NULL);
}

/* Whether the array `value` is of the collection `type`, each element checked by sharp_type_holds. */
static bool sharp_type_collection_holds(
	const sharp_type *type, const zval *value, bool int_as_float, sharp_type_problems *problems)
{
	if (type->built_in == SHARP_BUILT_IN_LIST && !zend_array_is_list(Z_ARRVAL_P(value))) {
		if (problems) {
			sharp_type_problem(problems, "", type, value);
		}
		return false;
	}

	const sharp_type *keys = type->built_in == SHARP_BUILT_IN_MAP ? type->members[0] : NULL;
	const sharp_type *elements = sharp_type_elements(type);
	bool holds = true;
	zend_ulong index;
	zend_string *key;
	zval *element;
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(value), index, key, element) {
		size_t length = 0;
		if (problems) {
			length = problems->path.s ? ZSTR_LEN(problems->path.s) : 0;
			if (key) {
				smart_str_appends(&problems->path, "[\"");
				smart_str_append(&problems->path, key);
				smart_str_appends(&problems->path, "\"]");
			} else {
				smart_str_appendc(&problems->path, '[');
				smart_str_append_long(&problems->path, (zend_long) index);
				smart_str_appendc(&problems->path, ']');
			}
		}

		if (keys && !sharp_type_holds_key(keys, index, key)) {
			holds = false;
			if (problems) {
				zval given;
				if (key) {
					ZVAL_STR(&given, key);
				} else {
					ZVAL_LONG(&given, (zend_long) index);
				}
				sharp_type_problem(problems, "key ", keys, &given);
			}
		}
		if ((holds || problems) && !sharp_type_holds(elements, element, int_as_float, problems)) {
			holds = false;
		}

		if (problems) {
			ZSTR_LEN(problems->path.s) = length;
		}
		if (UNEXPECTED(EG(exception)) || (!holds && !problems)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();

	return holds;
}

/* Whether `value` is of `type`, as sharp_type_accepts decides, with an int of `float` only when `int_as_float`. With
 * `problems`, it adds a problem for each bad value: an element of a collection at its own path, any other value at the
 * path the walk is at. */
static bool sharp_type_holds(const sharp_type *type, const zval *value, bool int_as_float, sharp_type_problems *problems)
{
	ZVAL_DEREF(value);
	if (problems) {
		const sharp_type *walked = sharp_type_walked(type, value);
		if (walked) {
			return sharp_type_collection_holds(walked, value, int_as_float, problems);
		}
		if (sharp_type_holds(type, value, int_as_float, NULL)) {
			return true;
		}
		if (!EG(exception)) {
			sharp_type_problem(problems, "", type, value);
		}
		return false;
	}

	switch (type->kind) {
		case SHARP_TYPE_NULLABLE:
			return Z_TYPE_P(value) == IS_NULL || sharp_type_holds(type->members[0], value, int_as_float, NULL);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, int_as_float, NULL)) {
					return true;
				}
			}
			return false;
		case SHARP_TYPE_INTERSECTION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (!sharp_type_holds(type->members[i], value, int_as_float, NULL)) {
					return false;
				}
			}
			return true;
		case SHARP_TYPE_FUNCTION:
			return true;
		case SHARP_TYPE_LIST:
			ZEND_UNREACHABLE();
		case SHARP_TYPE_NAMED:
			break;
	}
	if (type->class_name) {
		return Z_TYPE_P(value) == IS_OBJECT && sharp_object_is(Z_OBJ_P(value), type);
	}
	switch (type->built_in) {
		case SHARP_BUILT_IN_INT:
			return Z_TYPE_P(value) == IS_LONG;
		case SHARP_BUILT_IN_FLOAT:
			return Z_TYPE_P(value) == IS_DOUBLE || (int_as_float && Z_TYPE_P(value) == IS_LONG);
		case SHARP_BUILT_IN_BOOL:
			return Z_TYPE_P(value) == IS_TRUE || Z_TYPE_P(value) == IS_FALSE;
		case SHARP_BUILT_IN_STRING:
			return Z_TYPE_P(value) == IS_STRING;
		case SHARP_BUILT_IN_NULL:
			return Z_TYPE_P(value) == IS_NULL;
		case SHARP_BUILT_IN_ANY:
			return Z_TYPE_P(value) != IS_NULL;
		case SHARP_BUILT_IN_OBJECT:
			return Z_TYPE_P(value) == IS_OBJECT;
		case SHARP_BUILT_IN_LIST:
		case SHARP_BUILT_IN_MAP:
		case SHARP_BUILT_IN_SET:
			return Z_TYPE_P(value) == IS_ARRAY && sharp_type_collection_holds(type, value, int_as_float, NULL);
		case SHARP_BUILT_IN_ITERABLE:
			if (Z_TYPE_P(value) == IS_ARRAY) {
				return sharp_type_collection_holds(type, value, int_as_float, NULL);
			}
			return Z_TYPE_P(value) == IS_OBJECT && instanceof_function(Z_OBJCE_P(value), zend_ce_traversable);
		case SHARP_BUILT_IN_CLASS:
			return sharp_class_value_is(value, type->members[0]);
		default:
			return true;
	}
}

/* The member of `type` an int in `value` is converted by once sharp_type_holds took `value` with ints as floats: the type
 * itself, the member of `T?` for a value that is not null, and the first member of a union that holds the value with
 * ints as floats when none holds it without, as PHP picks a union member for an argument. NULL when nothing converts. */
static const sharp_type *sharp_type_converting(const sharp_type *type, const zval *value)
{
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return type->class_name ? NULL : type;
		case SHARP_TYPE_NULLABLE:
			return Z_TYPE_P(value) == IS_NULL ? NULL : sharp_type_converting(type->members[0], value);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, false, NULL)) {
					return NULL;
				}
			}
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, true, NULL)) {
					return sharp_type_converting(type->members[i], value);
				}
			}
			return NULL;
		default:
			return NULL;
	}
}

/* Whether converting `value` by `type` changes an int in it into a float. */
static bool sharp_type_changes(const sharp_type *type, const zval *value)
{
	ZVAL_DEREF(value);
	type = sharp_type_converting(type, value);
	if (!type) {
		return false;
	}
	if (Z_TYPE_P(value) == IS_LONG) {
		return type->built_in == SHARP_BUILT_IN_FLOAT;
	}

	const sharp_type *elements = sharp_type_elements(type);
	if (!elements || Z_TYPE_P(value) != IS_ARRAY) {
		return false;
	}
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		if (sharp_type_changes(elements, element)) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();

	return false;
}

/* Turns each int in `value` that `type` takes as a float into that float. An array is separated only when an int in it
 * changes. */
static void sharp_type_convert(const sharp_type *type, zval *value)
{
	if (!sharp_type_changes(type, value)) {
		return;
	}
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) == IS_LONG) {
		ZVAL_DOUBLE(value, (double) Z_LVAL_P(value));
		return;
	}

	const sharp_type *elements = sharp_type_elements(sharp_type_converting(type, value));
	zval *element;
	SEPARATE_ARRAY(value);
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		sharp_type_convert(elements, element);
	} ZEND_HASH_FOREACH_END();
}

bool sharp_type_accepts(const sharp_type *type, zval *value, sharp_type_problems *problems)
{
	/* A value that holds without converting an int is taken in one walk. */
	if (sharp_type_holds(type, value, false, NULL)) {
		return !EG(exception);
	}
	if (UNEXPECTED(EG(exception)) || !sharp_type_holds(type, value, true, problems) || UNEXPECTED(EG(exception))) {
		return false;
	}
	sharp_type_convert(type, value);

	return true;
}

/* The type arguments of the generic method `frame` runs, which it keeps in its hidden local, or NULL. */
static const sharp_type *sharp_frame_method_type_arguments(const zend_execute_data *frame)
{
	const zend_op_array *op_array = &frame->func->op_array;
	for (int i = 0; i < op_array->last_var; i++) {
		if (sharp_is_type_arguments_key(op_array->vars[i])) {
			const zval *local = ZEND_CALL_VAR_NUM(frame, i);

			return Z_TYPE_P(local) == IS_PTR ? Z_PTR_P(local) : NULL;
		}
	}

	return NULL;
}

/* Spec section 10: a struct's generated parse checks each parameter with the walk, as `\Sharp\Internal\accepts($type,
 * $value, $path, $problems)`. The type text is spelled for the user code that calls it, as ZEND_SHARP_VERIFY_TYPE_ARGS
 * spells its own, so a generic struct's parse can pass `List<$0>` or `List<#0>`. */
ZEND_FUNCTION(Sharp_Internal_accepts)
{
	zend_string *type;
	zval *value, *problems;
	zend_string *path;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_STR(type)
		Z_PARAM_ZVAL(value)
		Z_PARAM_STR(path)
		Z_PARAM_ARRAY_EX(problems, 0, 1)
	ZEND_PARSE_PARAMETERS_END();

	const zend_execute_data *caller = EX(prev_execute_data);
	bool is_user_code = caller && caller->func && ZEND_USER_CODE(caller->func->type);
	zend_object *object = is_user_code && Z_TYPE(caller->This) == IS_OBJECT ? Z_OBJ(caller->This) : NULL;
	const sharp_type *method = is_user_code ? sharp_frame_method_type_arguments(caller) : NULL;

	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(type), ZSTR_LEN(type), zend_arena_create(1024));
	const sharp_type_node *read = sharp_type_read_list(&reader);
	bool is_one_type = read && read->count == 1 && reader.at == reader.end;
	uint32_t names = reader.names;
	zend_arena_destroy(reader.arena);
	if (!is_one_type) {
		zend_argument_value_error(1, "must be one PHP# type");
		RETURN_THROWS();
	}
	if (((names & SHARP_TYPE_NAMES_THIS) && !object) || ((names & SHARP_TYPE_NAMES_METHOD) && !method)) {
		zend_argument_value_error(1, "must name only type parameters the calling code has type arguments for");
		RETURN_THROWS();
	}

	/* The text is a literal of the calling code, interned as code's literals are, which the list spelled for it keys. */
	zval text;
	ZVAL_INTERNED_STR(&text, ZSTR_IS_INTERNED(type) ? type : zend_new_interned_string(zend_string_copy(type)));
	void *cache[4] = {NULL};
	const sharp_type *list = sharp_type_list_of_frame(
		&text, object, method, false, is_user_code ? caller->func->common.scope : NULL, cache);
	if (!list) {
		if (!EG(exception)) {
			zend_argument_value_error(1, "must name only type parameters the calling code has type arguments for");
		}
		RETURN_THROWS();
	}

	sharp_type_problems found = {problems, {0}};
	smart_str_append(&found.path, path);
	bool accepts = sharp_type_accepts(list->members[0], value, &found);
	smart_str_free(&found.path);
	if (UNEXPECTED(EG(exception))) {
		RETURN_THROWS();
	}

	RETURN_BOOL(accepts);
}

/* Spec section 11: each type argument of a generic class has its own statics, which PHP# reads and writes as
 * `\Sharp\Internal\statics(Counter::class, 'App.Order')['count']`. Returns by reference the entry of the class's hidden
 * static sharp_statics_key for the type argument text `key`, so every write is PHP's own array write. The entry starts
 * as a copy of the defaults under the key "", as C# runs a closed type's static initializer on its first use. */
ZEND_FUNCTION(Sharp_Internal_statics)
{
	zend_class_entry *ce = NULL;
	zend_string *key;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_CLASS(ce)
		Z_PARAM_STR(key)
	ZEND_PARSE_PARAMETERS_END();

	zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, sharp_statics_key);
	if (!info || info->ce != ce || !(info->flags & ZEND_ACC_SHARP_HIDDEN)) {
		zend_argument_value_error(1, "must be a generic PHP# class that declares static fields");
		RETURN_THROWS();
	}
	if (UNEXPECTED(!(ce->ce_flags & ZEND_ACC_CONSTANTS_UPDATED)) && zend_update_class_constants(ce) != SUCCESS) {
		RETURN_THROWS();
	}
	if (UNEXPECTED(CE_STATIC_MEMBERS(ce) == NULL)) {
		zend_class_init_statics(ce);
	}

	zval *store = CE_STATIC_MEMBERS(ce) + info->offset;
	ZVAL_DEINDIRECT(store);
	SEPARATE_ARRAY(store);
	zval *entry = zend_hash_find(Z_ARRVAL_P(store), key);
	if (!entry) {
		zval defaults;
		ZVAL_COPY(&defaults, zend_hash_find_known_hash(Z_ARRVAL_P(store), ZSTR_EMPTY_ALLOC()));
		entry = zend_hash_add_new(Z_ARRVAL_P(store), key, &defaults);
	}

	ZVAL_MAKE_REF(entry);
	RETURN_COPY(entry);
}

/* Spec section 25: PHP# compares two class values with `\Sharp\Internal\equals($value, $other)`, by the whole type, as
 * C# compares two Type objects, so `typeof(Box<Order>) == typeof(Box<Invoice>)` is false. A class value with type
 * arguments is the interned class name sharp_class_values knows it by, so it equals only a class value of the same type
 * arguments, though its text is the bare class name. Any other two values compare as `===` compares them. */
ZEND_FUNCTION(Sharp_Internal_equals)
{
	zval *value, *other;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(value)
		Z_PARAM_ZVAL(other)
	ZEND_PARSE_PARAMETERS_END();

	if (Z_TYPE_P(value) != IS_STRING || Z_TYPE_P(other) != IS_STRING) {
		RETURN_BOOL(zend_is_identical(value, other));
	}
	const sharp_type *type = sharp_class_value_type(Z_STR_P(value));
	const sharp_type *other_type = sharp_class_value_type(Z_STR_P(other));
	if (type || other_type) {
		RETURN_BOOL(sharp_type_equals(type, other_type));
	}

	RETURN_BOOL(zend_string_equals(Z_STR_P(value), Z_STR_P(other)));
}

/* Throws the Error for the type arguments of a frame that ZEND_SHARP_TYPE_ARGS could not spell. */
static ZEND_COLD void sharp_type_list_unknown_error(void)
{
	zend_throw_error(NULL, "Cannot read the type arguments of the class a lambda is written in from an object of another class");
}

bool sharp_type_list_holds(const sharp_type *list, const zval *value)
{
	if (UNEXPECTED(!list)) {
		sharp_type_list_unknown_error();
		return false;
	}

	/* A lazy proxy's initializer runs to read its type arguments, and can throw. */
	return sharp_type_holds(list->members[0], value, false, NULL) && !EG(exception);
}

zend_string *sharp_type_list_class_value(const sharp_type *list, bool whole)
{
	if (UNEXPECTED(!list)) {
		sharp_type_list_unknown_error();
		return NULL;
	}
	if (whole) {
		return list->text;
	}
	const sharp_type *type = list->members[0];

	return type->class_name ? type->class_name : type->text;
}

zend_class_entry *sharp_new_class(const zval *created, const sharp_type **arguments)
{
	const sharp_type *type;

	if (Z_TYPE_P(created) == IS_STRING) {
		type = sharp_class_value_type(Z_STR_P(created));
		if (!type) {
			*arguments = NULL;

			return zend_fetch_class(Z_STR_P(created), ZEND_FETCH_CLASS_DEFAULT | ZEND_FETCH_CLASS_EXCEPTION);
		}
	} else if (Z_TYPE_P(created) == IS_PTR) {
		const sharp_type *list = Z_PTR_P(created);
		if (UNEXPECTED(!list)) {
			sharp_type_list_unknown_error();
			return NULL;
		}
		type = list->members[0];
	} else {
		zend_throw_error(NULL, "Class name must be a valid object or a string");
		return NULL;
	}
	if (UNEXPECTED(type->kind != SHARP_TYPE_NAMED || !type->class_name)) {
		zend_throw_error(NULL, "Cannot create %s with new, because it is not a class", ZSTR_VAL(type->text));
		return NULL;
	}

	zend_class_entry *ce = zend_fetch_class_by_name(type->class_name, NULL, ZEND_FETCH_CLASS_DEFAULT | ZEND_FETCH_CLASS_EXCEPTION);
	if (UNEXPECTED(!ce)) {
		return NULL;
	}

	/* The text of a class with type arguments is its dotted name, then its type arguments' text between `<` and `>`. */
	*arguments = NULL;
	const char *open = memchr(ZSTR_VAL(type->text), '<', ZSTR_LEN(type->text));
	if (open) {
		*arguments = sharp_type_list(open + 1, ZSTR_VAL(type->text) + ZSTR_LEN(type->text) - open - 2);
	}

	return ce;
}

/* The type a TypeError gives `value`, as PHP# writes the expected type: an object's dotted class name, with the type
 * arguments of a generic class's object, a class value with type arguments as its `Class` type, and PHP's name for any
 * other value. */
static ZEND_COLD void sharp_value_type_text(smart_str *given, const zval *value)
{
	const sharp_type *carried = Z_TYPE_P(value) == IS_STRING ? sharp_class_value_type(Z_STR_P(value)) : NULL;
	if (Z_TYPE_P(value) == IS_OBJECT) {
		sharp_object_text(given, Z_OBJ_P(value));
	} else if (carried) {
		smart_str_appends(given, "Class<");
		smart_str_append(given, carried->text);
		smart_str_appendc(given, '>');
	} else {
		smart_str_appends(given, zend_zval_value_name(value));
	}
	smart_str_0(given);
}

/* The first element of the array `value` that is not of `type`, as the walk that refused `value` finds it, for a
 * TypeError to name: `: [1] expected Probe.Order, got object Probe.Invoice`. Empty for any other value, and for an array
 * whose bad value is the array itself, which the TypeError already names. */
static ZEND_COLD zend_string *sharp_first_problem(const sharp_type *type, const zval *value)
{
	smart_str text = {0};
	if (Z_TYPE_P(value) != IS_ARRAY) {
		return smart_str_extract(&text);
	}

	zval problems;
	array_init(&problems);
	sharp_type_problems found = {&problems, {0}};
	sharp_type_holds(type, value, true, &found);
	smart_str_free(&found.path);

	zend_string *path;
	zval *problem;
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL(problems), path, problem) {
		if (path && ZSTR_LEN(path)) {
			smart_str_appends(&text, ": ");
			smart_str_append(&text, path);
			smart_str_appendc(&text, ' ');
			smart_str_append(&text, Z_STR_P(problem));
		}
		break;
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&problems);

	return smart_str_extract(&text);
}

/* Throws the TypeError PHP's own parameter check throws, with the PHP# type text expected, the value's type given and
 * the first bad element of an array. */
static ZEND_COLD void sharp_type_argument_error(
	const zend_execute_data *execute_data, uint32_t number, const sharp_type *expected, const zval *value)
{
	smart_str given = {0};
	sharp_value_type_text(&given, value);
	zend_string *problem = sharp_first_problem(expected, value);

	const zend_execute_data *caller = EX(prev_execute_data);
	if (caller && caller->func && ZEND_USER_CODE(caller->func->common.type)) {
		zend_argument_type_error(number, "must be of type %s, %s given%s, called in %s on line %d",
			ZSTR_VAL(expected->text), ZSTR_VAL(given.s), ZSTR_VAL(problem),
			ZSTR_VAL(caller->func->op_array.filename), caller->opline->lineno);
	} else {
		zend_argument_type_error(number, "must be of type %s, %s given%s", ZSTR_VAL(expected->text), ZSTR_VAL(given.s),
			ZSTR_VAL(problem));
	}
	zend_string_release(problem);
	smart_str_free(&given);
}

void sharp_type_check_arguments(zend_execute_data *execute_data, const zval *text, void **cache)
{
	/* Only a call from plain PHP is checked, and it gives the method no type arguments. */
	const sharp_type *expected = sharp_type_list_of_frame(text,
		Z_TYPE(EX(This)) == IS_OBJECT ? Z_OBJ(EX(This)) : NULL, NULL, true, EX(func)->common.scope, cache);
	if (!expected) {
		return;
	}

	/* A variadic parameter holds a list, whose elements are not checked. */
	uint32_t count = MIN(expected->count, EX(func)->op_array.num_args);
	for (uint32_t i = 0; i < count; i++) {
		zval *value = EX_VAR_NUM(i);

		/* A PHP# parameter is never by reference, and every call derefs a by-value argument. */
		ZEND_ASSERT(!Z_ISREF_P(value));
		if (!sharp_type_accepts(expected->members[i], value, NULL)) {
			if (!EG(exception)) {
				sharp_type_argument_error(execute_data, i + 1, expected->members[i], value);
			}
			return;
		}
	}
}

bool sharp_type_verify(zend_execute_data *execute_data, zval *value, const zval *text, void **cache)
{
	const sharp_type *expected = sharp_type_list_of_frame(text,
		Z_TYPE(EX(This)) == IS_OBJECT ? Z_OBJ(EX(This)) : NULL, NULL, true, EX(func)->common.scope, cache);
	if (!expected) {
		return !EG(exception);
	}

	const sharp_type *type = expected->members[0];
	if (!sharp_type_accepts(type, value, NULL)) {
		if (!EG(exception)) {
			/* Worded as PHP's TypeError for a function's return value. */
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *problem = sharp_first_problem(type, value);
			zend_type_error("%s: Return value must be of type %s, %s returned%s",
				Z_STRVAL_P(text + 1), ZSTR_VAL(type->text), ZSTR_VAL(given.s), ZSTR_VAL(problem));
			zend_string_release(problem);
			smart_str_free(&given);
		}
		return false;
	}

	return true;
}

/* Whether the PHP# type of the property `info` names a type parameter of its class, which only an object spells. */
static bool sharp_property_is_open(const zend_property_info *info)
{
	return info->sharp_type && memchr(ZSTR_VAL(info->sharp_type), '$', ZSTR_LEN(info->sharp_type));
}

bool sharp_property_accepts(const zend_property_info *info, zval *value, zend_object *object, bool reference)
{
	/* A hook writes its own property's storage for the code that set the property. */
	const zend_execute_data *writer = EG(current_execute_data);
	if (writer && writer->func && writer->func->common.prop_info
			&& writer->func->common.prop_info->prototype == info->prototype) {
		writer = writer->prev_execute_data;
	}
	if (writer && writer->func && ZEND_USER_CODE(writer->func->type)
			&& sharp_is_sharp_file(writer->func->op_array.filename)) {
		return true;
	}
	if (!object && sharp_property_is_open(info)) {
		return true;
	}

	/* The text is interned where the property is declared, and each check spells it for the object's class and type
	 * arguments once a request. */
	zval text;
	ZVAL_INTERNED_STR(&text, info->sharp_type);
	void *cache[4] = {NULL};
	const sharp_type *list = sharp_type_list_of_frame(&text, object, NULL, true, info->ce, cache);
	if (!list) {
		return false;
	}

	const sharp_type *type = list->members[0];
	if (sharp_type_accepts(type, value, NULL)) {
		return true;
	}
	if (!EG(exception)) {
		smart_str given = {0};
		sharp_value_type_text(&given, value);
		zend_string *problem = sharp_first_problem(type, value);
		zend_type_error("Cannot assign %s to %sproperty %s::$%s of type %s%s", ZSTR_VAL(given.s),
			reference ? "reference held by " : "", ZSTR_VAL(info->ce->name), zend_get_unmangled_property_name(info->name),
			ZSTR_VAL(type->text), ZSTR_VAL(problem));
		zend_string_release(problem);
		smart_str_free(&given);
	}

	return false;
}

bool sharp_property_refuses_reference(const zend_property_info *info)
{
	if (!sharp_property_is_open(info)) {
		return false;
	}

	zend_throw_error(NULL, "Cannot take a reference to `%s::$%s`: its type depends on the object's type arguments",
		ZSTR_VAL(info->ce->name), zend_get_unmangled_property_name(info->name));

	return true;
}

bool sharp_object_open_properties_accept(zend_object *object)
{
	for (uint32_t i = 0; i < object->ce->default_properties_count; i++) {
		zend_property_info *info = object->ce->properties_info_table[i];
		zval *slot = OBJ_PROP_NUM(object, i);
		if (info && sharp_property_is_open(info) && Z_TYPE_P(slot) != IS_UNDEF
				&& !sharp_property_accepts(info, slot, object, false)) {
			return false;
		}
	}

	return true;
}

static bool sharp_type_arguments_fit(
	const sharp_type_node *arguments, const zend_class_entry *ce, sharp_type_reader *reader);

/* Whether `type` is within `bound`: `Any?` holds every type, `Any` every type but null, a class its subclasses, a class
 * with type arguments each subclass whose header gives it type arguments its own take, an intersection what each of its
 * classes holds, and any other bound only itself. A union is within a bound when each of its members is, and an
 * intersection when one of its classes is. */
static bool sharp_type_fits(sharp_type_node *type, sharp_type_node *bound, sharp_type_reader *reader)
{
	if (sharp_type_node_is(bound, "Any?")) {
		return true;
	}
	if (type->kind == SHARP_TYPE_UNION) {
		for (sharp_type_node *member = type->first; member; member = member->next) {
			if (!sharp_type_fits(member, bound, reader)) {
				return false;
			}
		}

		return true;
	}
	if (bound->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_node_is(type, "null")
			|| sharp_type_fits(type->kind == SHARP_TYPE_NULLABLE ? type->first : type, bound->first, reader);
	}
	if (bound->kind == SHARP_TYPE_UNION) {
		for (sharp_type_node *member = bound->first; member; member = member->next) {
			if (sharp_type_fits(type, member, reader)) {
				return true;
			}
		}

		return false;
	}
	if (bound->kind == SHARP_TYPE_INTERSECTION) {
		for (sharp_type_node *member = bound->first; member; member = member->next) {
			if (!sharp_type_fits(type, member, reader)) {
				return false;
			}
		}

		return true;
	}
	if (sharp_type_node_is(bound, "Any")) {
		return type->kind != SHARP_TYPE_NULLABLE && !sharp_type_node_is(type, "null");
	}
	if (type->kind == SHARP_TYPE_INTERSECTION) {
		for (sharp_type_node *member = type->first; member; member = member->next) {
			if (sharp_type_fits(member, bound, reader)) {
				return true;
			}
		}

		return false;
	}
	if (bound->kind != SHARP_TYPE_NAMED || !bound->name) {
		return sharp_type_argument_fits(type, bound, '=', reader);
	}

	/* A loaded class has loaded each class it extends or implements. */
	zend_class_entry *ce = sharp_type_node_class(bound);
	if (type->kind != SHARP_TYPE_NAMED || !sharp_type_node_class(type) || !ce || !instanceof_function(type->ce, ce)) {
		return false;
	}
	if (!bound->count) {
		return true;
	}

	/* The type arguments must be ones the bound's take, so `IntBox : Box<int>` is within `Box<int>`. */
	const sharp_type_node *given = sharp_type_node_ancestor(type->ce, type, ce, reader);
	if (!given || given->count != bound->count) {
		return false;
	}
	uint32_t i = 0;
	sharp_type_node *wanted = bound->first;
	for (sharp_type_node *argument = given->first; argument; argument = argument->next, wanted = wanted->next) {
		if (!sharp_type_argument_fits(argument, wanted, sharp_class_variance(ce, i++), reader)) {
			return false;
		}
	}

	return true;
}

/* Whether `node`, read from input, is a type code can spell, made so in one walk of its members before it: each class
 * it names resolves through `resolve` and takes the name it was declared with, the members of a union or an
 * intersection sort by their spelling and no two are one type, and each type takes the type arguments it is given, as
 * many as it has type parameters, each within its bound. */
static bool sharp_type_node_accept(
	sharp_type_node *node, sharp_type_class_resolver resolve, void *context, sharp_type_reader *reader)
{
	for (sharp_type_node *member = node->first; member; member = member->next) {
		if (!sharp_type_node_accept(member, resolve, context, reader)) {
			return false;
		}
	}
	if (node->name) {
		zend_string *name = sharp_type_class_name(node->name, node->name_length, false);
		node->ce = resolve(name, context);
		zend_string_release_ex(name, false);
		if (!node->ce) {
			return false;
		}
	}
	if ((node->kind == SHARP_TYPE_UNION || node->kind == SHARP_TYPE_INTERSECTION) && !sharp_type_node_sort(node, false)) {
		return false;
	}
	sharp_type_node_spell(node, &reader->arena);
	if (node->kind != SHARP_TYPE_NAMED) {
		return true;
	}

	return node->ce
		? sharp_type_arguments_fit(node, node->ce, reader)
		: node->count == (uint32_t) sharp_type_built_in_arity(node->text, node->name_length);
}

/* Whether `arguments`, a type argument list or a class type, gives `ce` one type argument within each bound. Plain PHP
 * writes a class's type parameters in docblocks only, so a class it declares takes any type arguments. */
static bool sharp_type_arguments_fit(
	const sharp_type_node *arguments, const zend_class_entry *ce, sharp_type_reader *reader)
{
	if (!sharp_class_is_sharp(ce)) {
		return true;
	}

	if (!ce->info.user.sharp_bounds) {
		return arguments->count == 0;
	}

	sharp_type_node *bounds = sharp_type_node_list(ce->info.user.sharp_bounds, reader);
	if (arguments->count != bounds->count) {
		return false;
	}
	sharp_type_node *bound = bounds->first;
	for (sharp_type_node *argument = arguments->first; argument; argument = argument->next, bound = bound->next) {
		if (!sharp_type_fits(argument, bound, reader)) {
			return false;
		}
	}

	return true;
}

zend_result sharp_type_arguments_unserialize(
	zend_object *object, const zval *text, sharp_type_class_resolver resolve, void *context)
{
	if (!sharp_type_arguments_slot(object->ce) || Z_TYPE_P(text) != IS_STRING) {
		return FAILURE;
	}

	/* The text parses whole and each class it names resolves before any of it is interned. */
	sharp_type_reader reader = sharp_type_reader_of(Z_STRVAL_P(text), Z_STRLEN_P(text), zend_arena_create(1024));
	reader.depth_left = SHARP_TYPE_INPUT_DEPTH;
	sharp_type_node *arguments = sharp_type_read_list(&reader);
	const sharp_type *list = NULL;
	if (arguments && !reader.names && sharp_type_node_accept(arguments, resolve, context, &reader)
		&& sharp_type_arguments_fit(arguments, object->ce, &reader)) {
		list = sharp_type_intern(arguments, false, &reader.arena);
	}
	zend_arena_destroy(reader.arena);

	if (!list) {
		return FAILURE;
	}
	sharp_type_arguments_store(object, list);

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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Set_from, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, values, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* The key a Set holds element under, as PHP stores an array key: a backed enum case's value or the
 * element itself, with a numeric string as its int, so name is NULL for an int key. False after a
 * TypeError for any other element. */
static bool sharp_set_key(zval *element, zend_ulong *index, zend_string **name)
{
	if (Z_TYPE_P(element) == IS_OBJECT && (Z_OBJCE_P(element)->ce_flags & ZEND_ACC_ENUM)
		&& Z_OBJCE_P(element)->enum_backing_type != IS_UNDEF) {
		element = zend_enum_fetch_case_value(Z_OBJ_P(element));
	}
	if (Z_TYPE_P(element) == IS_LONG) {
		*index = (zend_ulong) Z_LVAL_P(element);
		*name = NULL;
		return true;
	}
	if (Z_TYPE_P(element) == IS_STRING) {
		*name = ZEND_HANDLE_NUMERIC(Z_STR_P(element), *index) ? NULL : Z_STR_P(element);
		return true;
	}

	zend_argument_type_error(1, "must hold only int, string or backed enum values, %s given", zend_zval_value_name(element));
	return false;
}

/* Spec section 12: the Set of values's elements in their first order, each under its key. A PHP#
 * method runs it on each Set parameter, which a List or plain PHP may pass, so an array that is
 * already a Set comes back as it is, after one pass and no copy. */
ZEND_METHOD(Sharp_Set, from)
{
	zval *values, *element;
	zend_ulong held_index, index;
	zend_string *held_name, *name;
	bool is_set = true;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY(values)
	ZEND_PARSE_PARAMETERS_END();

	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(values), held_index, held_name, element) {
		ZVAL_DEREF(element);
		if (!sharp_set_key(element, &index, &name)) {
			RETURN_THROWS();
		}
		is_set = is_set && (name ? held_name && zend_string_equals(held_name, name) : !held_name && held_index == index);
	} ZEND_HASH_FOREACH_END();
	if (is_set) {
		RETURN_COPY(values);
	}

	HashTable *set = zend_new_array(zend_hash_num_elements(Z_ARRVAL_P(values)));
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(values), element) {
		ZVAL_DEREF(element);
		sharp_set_key(element, &index, &name);
		if (name ? zend_hash_add(set, name, element) : zend_hash_index_add(set, index, element)) {
			Z_TRY_ADDREF_P(element);
		}
	} ZEND_HASH_FOREACH_END();

	RETURN_ARR(set);
}

static const zend_function_entry class_Sharp_Set_methods[] = {
	ZEND_ME(Sharp_Set, from, arginfo_class_Sharp_Set_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Set(void)
{
	zend_class_entry ce;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Set", class_Sharp_Set_methods);

	return zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);
}

static PHP_MINIT_FUNCTION(sharp)
{
	REGISTER_INI_ENTRIES();
	/* The module's own table is sharp_native_functions, which sharp/bin/native writes; sharp.stub.php declares the rest. */
	zend_register_functions(NULL, ext_functions, NULL, MODULE_PERSISTENT);
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
	register_class_Sharp_Set();

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
	sharp_statics_key = zend_string_init_interned("\0<sharp>\0statics", sizeof("\0<sharp>\0statics") - 1, true);
	zend_hash_init(&sharp_types, 64, NULL, sharp_type_free, true);
	zend_hash_init(&sharp_type_lists, 64, NULL, sharp_type_free, true);
	zend_hash_init(&sharp_class_values, 8, NULL, NULL, true);
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
	zend_hash_destroy(&sharp_class_values);
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

/* Runs after the request's objects are freed, so no slot still points into the request's types. */
static ZEND_MODULE_POST_ZEND_DEACTIVATE_D(sharp)
{
	sharp_type_request_table_free(&SHARP_G(request_spelled_lists));
	sharp_type_request_table_free(&SHARP_G(request_class_values));
	sharp_type_request_table_free(&SHARP_G(request_type_lists));
	sharp_type_request_table_free(&SHARP_G(request_types));

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "Version", PHP_SHARP_VERSION);
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
	ZEND_MODULE_POST_ZEND_DEACTIVATE_N(sharp),
	STANDARD_MODULE_PROPERTIES_EX
};

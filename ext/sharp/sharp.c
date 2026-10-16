#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include <dirent.h>
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
#include "zend_attributes.h"
#include "zend_closures.h"
#include "zend_enum.h"
#include "zend_exceptions.h"
#include "zend_generators.h"
#include "zend_interfaces.h"
#include "zend_language_parser.h"
#include "zend_observer.h"
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
/* How the path of an input that stands for the .sharp files of its folder ends: the bridge's unit::SOURCE_LISTING. */
#define SHARP_SOURCE_LISTING "/*.sharp"
#define SHARP_LISTENER_TABLE SHARP_COMPILED_FOLDER "/listeners.php"

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
	/* For the request: each listener table emit read, by its path, and each event class's listeners, by its entry. */
	HashTable listener_tables;
	HashTable event_listeners;
	/* Spec section 15: for the request, each listener Sharp\Internal\listen started whose handle lives, under the number
	 * its handle holds, in the order they started, as [the type it listens to, its function]. */
	HashTable scoped_listeners;
	zend_ulong next_scoped_listener;
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
	/* Spec section 32: Sharp\Container's bindings, under "namespace id" a list of the modules that bind it, each a
	 * list of its rows, the base modules' and the environment modules' apart; the paths of the module files it read;
	 * each test file's module, under each class the file declares, and the one of the test that runs now, or NULL;
	 * the services a singleton or a scoped binding keeps, by binding row; and the bindings and classes it is
	 * building, to refuse a cycle. */
	HashTable container_bindings;
	HashTable container_environment;
	HashTable container_modules;
	HashTable container_tests;
	HashTable *container_test;
	HashTable container_singletons;
	HashTable container_request_scope;
	HashTable *container_scope;
	HashTable container_building;
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

static int sharp_compare_names(Bucket *a, Bucket *b)
{
	return zend_binary_strcmp(ZSTR_VAL(a->key), ZSTR_LEN(a->key), ZSTR_VAL(b->key), ZSTR_LEN(b->key));
}

/* Stamps the listing of the folder that ends at length, as the bridge's unit::stamp does: the names of its regular files
 * whose name ends in .sharp, and of its folders whose name starts with an uppercase letter, as a namespace part does,
 * each with a / after it, each followed by a newline, in byte order. A folder that can't be opened lists nothing. */
static void sharp_stamp_listing(const char *path, size_t length, sharp_stamp *stamp)
{
	zend_string *folder = zend_string_init(path, length, 0), *name;
	PHP_XXH3_128_CTX context = {0};
	HashTable names;
	DIR *directory = VCWD_OPENDIR(ZSTR_VAL(folder));
	struct dirent *entry;

	zend_hash_init(&names, 8, NULL, NULL, 0);
	while (directory && (entry = readdir(directory))) {
		size_t name_length = strlen(entry->d_name);
		bool source = name_length > sizeof(SHARP_FILE_EXTENSION) - 1
			&& memcmp(entry->d_name + name_length - (sizeof(SHARP_FILE_EXTENSION) - 1), SHARP_FILE_EXTENSION, sizeof(SHARP_FILE_EXTENSION) - 1) == 0;
		zend_stat_t status;
		zend_string *file;

		if (!source && !(entry->d_name[0] >= 'A' && entry->d_name[0] <= 'Z')) {
			continue;
		}
		file = zend_string_concat2(ZSTR_VAL(folder), ZSTR_LEN(folder), entry->d_name, name_length);
		if (VCWD_STAT(ZSTR_VAL(file), &status) == 0) {
			if (source && S_ISREG(status.st_mode)) {
				zend_hash_str_add_empty_element(&names, entry->d_name, name_length);
			} else if (S_ISDIR(status.st_mode) && entry->d_name[0] >= 'A' && entry->d_name[0] <= 'Z') {
				zend_string *subfolder = zend_string_concat2(entry->d_name, name_length, "/", 1);

				zend_hash_add_empty_element(&names, subfolder);
				zend_string_release(subfolder);
			}
		}
		zend_string_release(file);
	}
	if (directory) {
		closedir(directory);
	}
	zend_hash_sort(&names, sharp_compare_names, false);

	PHP_XXH3_128_Init(&context, NULL);
	ZEND_HASH_FOREACH_STR_KEY(&names, name) {
		PHP_XXH3_128_Update(&context, (const unsigned char *) ZSTR_VAL(name), ZSTR_LEN(name));
		PHP_XXH3_128_Update(&context, (const unsigned char *) "\n", 1);
		stamp->size += ZSTR_LEN(name) + 1;
	} ZEND_HASH_FOREACH_END();
	PHP_XXH3_128_Final(stamp->hash, &context);
	stamp->exists = true;
	stamp->hashed = true;

	zend_hash_destroy(&names);
	zend_string_release(folder);
}

static sharp_stamp *sharp_stamp_of(zend_string *path)
{
	sharp_stamp *stamp = zend_hash_find_ptr(&SHARP_G(request_stamps), path);
	sharp_stamp fresh = {0};
	zend_stat_t status;
	size_t listing = sizeof(SHARP_SOURCE_LISTING) - 1;

	if (stamp) {
		return stamp;
	}
	if (ZSTR_LEN(path) >= listing && memcmp(ZSTR_VAL(path) + ZSTR_LEN(path) - listing, SHARP_SOURCE_LISTING, listing) == 0) {
		sharp_stamp_listing(ZSTR_VAL(path), ZSTR_LEN(path) - listing + 1, &fresh);
	} else if (VCWD_STAT(ZSTR_VAL(path), &status) == 0 && S_ISREG(status.st_mode)) {
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

/* Whether the folder of the file at path holds a file of exactly its name, case included. A disk that ignores case,
 * as macOS's does by default, opens environment.php for Environment.php. Linux does not, so an input is there only under
 * its exact name, as the bridge's unit::stamp stamps it. */
static bool sharp_folder_holds_name(const zend_string *path)
{
	size_t folder = sharp_parent(ZSTR_VAL(path), ZSTR_LEN(path));
	const char *name = ZSTR_VAL(path) + folder + 1;
	size_t name_length = ZSTR_LEN(path) - folder - 1;
	zend_string *folder_path = zend_string_init(ZSTR_VAL(path), folder, 0);
	DIR *directory = VCWD_OPENDIR(ZSTR_VAL(folder_path));
	struct dirent *entry;
	bool holds = false;

	while (!holds && directory && (entry = readdir(directory))) {
		holds = strlen(entry->d_name) == name_length && memcmp(entry->d_name, name, name_length) == 0;
	}
	if (directory) {
		closedir(directory);
	}
	zend_string_release(folder_path);

	return holds;
}

/* Whether the file at path still has the stamp the checker gave it: its size, modification time and hash, or all zero
 * for a file that was not there. */
static bool sharp_stamp_current(zend_string *path, uint64_t size, int64_t mtime_ns, const uint8_t hash[16])
{
	static const uint8_t absent[16] = {0};
	sharp_stamp *stamp = sharp_stamp_of(path);

	if (size == 0 && memcmp(hash, absent, sizeof(absent)) == 0) {
		/* A file that opens under another case of the name is not there, so the input stays absent. */
		return !stamp->exists || !sharp_folder_holds_name(path);
	}
	if (!stamp->exists) {
		return false;
	}
	if (!stamp->hashed && stamp->size == size && stamp->mtime_ns == mtime_ns) {
		return true;
	}
	if (!stamp->hashed) {
		stamp->hashed = sharp_hash_file(ZSTR_VAL(path), stamp->hash);
	}

	return stamp->hashed && memcmp(stamp->hash, hash, sizeof(stamp->hash)) == 0;
}

static bool sharp_input_current(const sharp_compiled *compiled, const sharp_input *input)
{
	zend_string *path = zend_string_concat3(
		ZSTR_VAL(compiled->source), compiled->root_length,
		"/", 1,
		compiled->unit.texts + input->path.offset, input->path.len);
	bool current = sharp_stamp_current(path, input->size, input->mtime_ns, input->hash);

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

/* Runs sharp.compile_command through /bin/sh in the project root, the first root_length bytes of path, and waits for
 * it however long it takes. The command runs with MAGO_REPORTING_FORMAT=json, and the checker report it prints is
 * decoded for the request's refusals. What it prints goes to a temporary file, read once it exits: a pipe would also
 * wait on every process it leaves running. */
static void sharp_run_compile_command(const char *path, size_t root_length)
{
	char *root = estrndup(path, root_length ? root_length : 1);
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
		sharp_run_compile_command(ZSTR_VAL(compiled.source), compiled.root_length);
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
 * index read does (spec section 12). */
static zval *sharp_collection_element(HashTable *array, zval *index)
{
	zend_string *string_key;
	zend_long long_key;

	if (!sharp_collection_key(index, &string_key, &long_key)) {
		return NULL;
	}
	zval *element = string_key ? zend_symtable_find(array, string_key) : zend_hash_index_find(array, long_key);
	if (!element) {
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
static zval *sharp_collection_checked(zval *held)
{
	ZVAL_DEREF(held);
	if (UNEXPECTED(!SHARP_IS_COLLECTION(held))) {
		zend_throw_error(NULL, "Cannot call %s() on a collection that was unset", get_active_function_name());
		return NULL;
	}

	return held;
}

static zval *sharp_collection_change(sharp_collection *collection);

/* The collection the receiver reads: an array, or a Sharp\Map or Sharp\Set object. An element
 * collection reads it from its container each time, so it holds no copy that would make the element
 * shared when it changes. */
static zval *sharp_collection_array(sharp_collection *collection)
{
	if (!collection->container) {
		return sharp_collection_checked(collection->slot ? collection->slot : &collection->value);
	}

	zval *container = sharp_collection_array(sharp_collection_from(collection->container));
	zval *element = container ? sharp_collection_element(Z_ARRVAL_P(container), &collection->index) : NULL;

	return element ? sharp_collection_checked(element) : NULL;
}

/* Where a change writes the collection: what sharp_collection_array reads, but for an element
 * collection the element inside its container's changed list, so a change separates every
 * collection it is in. */
static zval *sharp_collection_place(sharp_collection *collection)
{
	if (!collection->container) {
		return sharp_collection_array(collection);
	}

	zval *container = sharp_collection_change(sharp_collection_from(collection->container));
	zval *element = container ? sharp_collection_element(Z_ARRVAL_P(container), &collection->index) : NULL;

	return element ? sharp_collection_checked(element) : NULL;
}

/* The place of the collection, which no other holder shares: a shared array separates as PHP's
 * SEPARATE_ARRAY does, and a shared Map or Set as sharp_separate does. */
static zval *sharp_collection_change(sharp_collection *collection)
{
	zval *held = sharp_collection_place(collection);

	if (!held) {
		return NULL;
	}
	if (Z_TYPE_P(held) == IS_ARRAY) {
		SEPARATE_ARRAY(held);
	} else {
		SHARP_SEPARATE(held);
	}

	return held;
}

/* The table of a Map or a Set the receiver reads, or of the one a change writes when change is true,
 * or NULL after an Error for a List, which has no such method. */
static sharp_table *sharp_collection_table(zval *this_ptr, bool change)
{
	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(this_ptr));
	zval *held = change ? sharp_collection_change(collection) : sharp_collection_array(collection);

	if (!held) {
		return NULL;
	}
	if (Z_TYPE_P(held) != IS_OBJECT) {
		zend_throw_error(NULL, "%s() runs on a Map or a Set", get_active_function_name());
		return NULL;
	}

	return sharp_table_of(Z_OBJ_P(held));
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

/* A List's add appends the value. A Set's adds it when the Set holds no equal element, and gives
 * whether it did, so a Set it holds already is not changed and no shared Set separates. */
ZEND_METHOD(Sharp_Collection, add)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	zval *held = sharp_collection_array(collection);
	if (!held) {
		RETURN_THROWS();
	}
	if (Z_TYPE_P(held) == IS_OBJECT) {
		bool present = sharp_table_find(sharp_table_of(Z_OBJ_P(held)), value) != NULL;
		if (EG(exception)) {
			RETURN_THROWS();
		}
		if (present) {
			RETURN_FALSE;
		}
		zval null;
		ZVAL_NULL(&null);
		if (!sharp_table_put(sharp_table_of(Z_OBJ_P(sharp_collection_change(collection))), value, &null)) {
			RETURN_THROWS();
		}
		sharp_collection_write_back(collection);
		RETURN_TRUE;
	}

	HashTable *array = Z_ARRVAL_P(sharp_collection_change(collection));
	Z_TRY_ADDREF_P(value);
	if (!zend_hash_next_index_insert(array, value)) {
		zval_ptr_dtor(value);
		zend_cannot_add_element();
		RETURN_THROWS();
	}
	sharp_collection_write_back(collection);
	RETURN_TRUE;
}

/* `list[i] = v`, which the bridge lowers to set: v replaces the element at index, and past either end
 * set throws, where PHP's write would append or leave a gap (spec section 12). */
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
	zval *element = zend_hash_index_find(Z_ARRVAL_P(sharp_collection_change(collection)), index);
	zval old;
	ZVAL_COPY_VALUE(&old, element);
	ZVAL_COPY(element, value);
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);
	/* `list[i] = v` gives v, as a PHP assignment gives the value it stores. */
	RETURN_COPY(value);
}

zend_class_entry *sharp_ce_struct;

/* As PHP lets only an enum implement UnitEnum, only a value implements Sharp\Struct: the final class the bridge
 * lowers a struct, or an enum case that carries data, to, from a .sharp file, whose checker lets no PHP# code name
 * Sharp.Struct. A Map or a Set hashes such a key by its fields (sharp_table.c), and a plain PHP class could hold a
 * cycle, whose hash would never end. */
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

/* A Map's get: the value of key, or null when the Map holds no equal key. */
ZEND_METHOD(Sharp_Collection, get)
{
	zval *key;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *table = sharp_collection_table(ZEND_THIS, false);
	sharp_table_bucket *bucket = table ? sharp_table_find(table, key) : NULL;
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (!bucket) {
		RETURN_NULL();
	}

	RETURN_COPY_DEREF(&bucket->value);
}

/* A List's entries: the Map of each index to its element. A Map's: the Map. */
ZEND_METHOD(Sharp_Collection, entries)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zval *held = sharp_collection_array(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	if (!held) {
		RETURN_THROWS();
	}
	if (Z_TYPE_P(held) != IS_ARRAY) {
		RETURN_COPY(held);
	}

	object_init_ex(return_value, sharp_ce_map);
	sharp_table *table = sharp_table_of(Z_OBJ_P(return_value));
	zend_ulong index;
	zval *element;
	ZEND_HASH_FOREACH_NUM_KEY_VAL(Z_ARRVAL_P(held), index, element) {
		zval key;
		ZVAL_LONG(&key, (zend_long) index);
		sharp_table_put(table, &key, element);
	} ZEND_HASH_FOREACH_END();
}

/* A Map's has and a Set's contains: whether it holds a key or an element equal to value, a key whose
 * value is null included. */
static void sharp_collection_holds(INTERNAL_FUNCTION_PARAMETERS)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *table = sharp_collection_table(ZEND_THIS, false);
	bool held = table && sharp_table_find(table, value);
	if (EG(exception)) {
		RETURN_THROWS();
	}

	RETURN_BOOL(held);
}

ZEND_METHOD(Sharp_Collection, has)
{
	sharp_collection_holds(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}

ZEND_METHOD(Sharp_Collection, contains)
{
	sharp_collection_holds(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}

/* A Map's and a Set's remove: removes the key or the element equal to value, and gives whether there
 * was one. A Map or a Set without one is not changed, so a shared one does not separate. */
ZEND_METHOD(Sharp_Collection, remove)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *table = sharp_collection_table(ZEND_THIS, false);
	bool held = table && sharp_table_find(table, value);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (!held) {
		RETURN_FALSE;
	}

	if (!sharp_table_remove(sharp_collection_table(ZEND_THIS, true), value)) {
		RETURN_THROWS();
	}
	sharp_collection_write_back(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	RETURN_TRUE;
}

/* `map[key] = value` on a Map a hooked property or an element of a List holds, which only the
 * collection writes where it is stored: inserts or replaces the value of key, and gives value. */
ZEND_METHOD(Sharp_Collection, put)
{
	zval *key, *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(key)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *table = sharp_collection_table(ZEND_THIS, true);
	if (!table || !sharp_table_put(table, key, value)) {
		RETURN_THROWS();
	}
	sharp_collection_write_back(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));

	RETURN_COPY(value);
}

/* A List's or a Set's clear. The place takes an empty collection of its kind, and the old elements are
 * released last, so a destructor one runs sees the changed collection. */
ZEND_METHOD(Sharp_Collection, clear)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_collection *collection = sharp_collection_from(Z_OBJ_P(ZEND_THIS));
	zval *place = sharp_collection_place(collection);
	if (!place) {
		RETURN_THROWS();
	}
	zval old;
	ZVAL_COPY_VALUE(&old, place);
	if (Z_TYPE(old) == IS_OBJECT) {
		object_init_ex(place, Z_OBJCE(old));
	} else {
		ZVAL_EMPTY_ARRAY(place);
	}
	sharp_collection_write_back(collection);
	zval_ptr_dtor(&old);
}

ZEND_METHOD(Sharp_Collection, count)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zval *held = sharp_collection_array(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	if (!held) {
		RETURN_THROWS();
	}

	RETURN_LONG(Z_TYPE_P(held) == IS_ARRAY ? zend_hash_num_elements(Z_ARRVAL_P(held)) : sharp_table_of(Z_OBJ_P(held))->size);
}

/* A Map's keys or values and a Set's toList: the List of its keys, its values or its elements, each
 * as it was stored, in insertion order. */
static void sharp_collection_list(INTERNAL_FUNCTION_PARAMETERS, bool values)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_table *table = sharp_collection_table(ZEND_THIS, false);
	if (!table) {
		RETURN_THROWS();
	}
	array_init_size(return_value, table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *listed = values ? &bucket->value : &bucket->key;
		Z_TRY_ADDREF_P(listed);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), listed);
	} SHARP_TABLE_FOREACH_END();
}

ZEND_METHOD(Sharp_Collection, keys)
{
	sharp_collection_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

ZEND_METHOD(Sharp_Collection, values)
{
	sharp_collection_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

ZEND_METHOD(Sharp_Collection, toList)
{
	sharp_collection_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

/* A PHP# method call on an index of a List reads the element through the collection that holds it,
 * as `grid[0].add(5)` does. An element that is a collection becomes a collection that changes it at
 * that index, and any other element is read as it is. A missing index throws as a PHP# index read
 * does, before `?.` too. A Map index reads its value, or null for a missing key, and no change runs
 * through it (spec section 12). */
static zval *sharp_collection_read_dimension(zend_object *object, zval *offset, int type, zval *rv)
{
	zval *held = sharp_collection_array(sharp_collection_from(object));
	ZVAL_DEREF(offset);
	if (held && Z_TYPE_P(held) == IS_OBJECT) {
		sharp_table_bucket *bucket = sharp_table_find(sharp_table_of(Z_OBJ_P(held)), offset);
		if (EG(exception)) {
			return &EG(uninitialized_zval);
		}
		if (bucket) {
			ZVAL_COPY_DEREF(rv, &bucket->value);
		} else {
			ZVAL_NULL(rv);
		}
		return rv;
	}
	zval *element = held ? sharp_collection_element(Z_ARRVAL_P(held), offset) : NULL;
	if (!element) {
		return &EG(uninitialized_zval);
	}

	ZVAL_DEREF(element);
	if (!SHARP_IS_COLLECTION(element)) {
		ZVAL_COPY(rv, element);
		return rv;
	}
	sharp_collection *collection = sharp_collection_new(rv);
	GC_ADDREF(object);
	collection->container = object;
	ZVAL_COPY(&collection->index, offset);

	return rv;
}

/* The elements a method that takes a function reads, held while the function runs, since the function
 * may change the collection: a change then separates the collection from what this reads. A List's are
 * its array. A Set's are its elements and a Map's its values, in insertion order, in a new list. */
static zend_array *sharp_collection_read(zval *this_ptr)
{
	zval *held = sharp_collection_array(sharp_collection_from(Z_OBJ_P(this_ptr)));

	if (!held) {
		return NULL;
	}
	if (Z_TYPE_P(held) == IS_ARRAY) {
		GC_TRY_ADDREF(Z_ARRVAL_P(held));
		return Z_ARRVAL_P(held);
	}

	bool set = Z_OBJCE_P(held) == sharp_ce_set;
	sharp_table *table = sharp_table_of(Z_OBJ_P(held));
	zend_array *elements = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *element = set ? &bucket->key : &bucket->value;
		Z_TRY_ADDREF_P(element);
		zend_hash_next_index_insert_new(elements, element);
	} SHARP_TABLE_FOREACH_END();

	return elements;
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

/* A Map's or a Set's filter: a new one of its kind with the entries whose element the function keeps,
 * a Map's under their keys. The table is held while the function runs, so a change the function makes
 * to the collection separates it from this table. */
static void sharp_collection_filter_table(zend_object *object, zend_fcall_info *fci, zend_fcall_info_cache *fcc,
	zval *return_value)
{
	bool set = object->ce == sharp_ce_set;
	zval kept, keep;

	GC_ADDREF(object);
	object_init_ex(&kept, object->ce);
	sharp_table *kept_table = sharp_table_of(Z_OBJ(kept));
	SHARP_TABLE_FOREACH(sharp_table_of(object), bucket) {
		if (!sharp_collection_call(fci, fcc, set ? &bucket->key : &bucket->value, &keep)) {
			break;
		}
		bool is_kept = zend_is_true(&keep);
		zval_ptr_dtor(&keep);
		if (is_kept && !sharp_table_put(kept_table, &bucket->key, &bucket->value)) {
			break;
		}
	} SHARP_TABLE_FOREACH_END();
	OBJ_RELEASE(object);

	if (EG(exception)) {
		zval_ptr_dtor(&kept);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&kept);
}

/* Spec section 12: a List's filter renumbers what it keeps, and a Map's or a Set's gives one of its kind. */
ZEND_METHOD(Sharp_Collection, filter)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zval *held = sharp_collection_array(sharp_collection_from(Z_OBJ_P(ZEND_THIS)));
	if (!held) {
		RETURN_THROWS();
	}
	if (Z_TYPE_P(held) == IS_OBJECT) {
		sharp_collection_filter_table(Z_OBJ_P(held), &fci, &fcc, return_value);
		return;
	}

	zend_array *elements = Z_ARRVAL_P(held);
	GC_TRY_ADDREF(elements);
	zval kept, keep;
	zval *element;
	array_init(&kept);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &keep)) {
			break;
		}
		if (zend_is_true(&keep)) {
			Z_TRY_ADDREF_P(element);
			zend_hash_next_index_insert_new(Z_ARRVAL(kept), element);
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

/* Puts each element in a Map under the key the function gives it: in a list of the elements under that
 * key, or alone, where the last element with a key replaces the ones before it. Any key a Map takes
 * goes in as it is, found by PHP#'s == (spec section 12). */
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
	object_init_ex(&map, sharp_ce_map);
	sharp_table *table = sharp_table_of(Z_OBJ(map));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_collection_call(&fci, &fcc, element, &key)) {
			break;
		}
		sharp_table_bucket *bucket = group ? sharp_table_find(table, &key) : NULL;
		bool put = !EG(exception);
		if (bucket) {
			SEPARATE_ARRAY(&bucket->value);
			Z_TRY_ADDREF_P(element);
			zend_hash_next_index_insert_new(Z_ARRVAL(bucket->value), element);
		} else if (put && group) {
			zval list;
			array_init(&list);
			Z_TRY_ADDREF_P(element);
			zend_hash_next_index_insert_new(Z_ARRVAL(list), element);
			put = sharp_table_put(table, &key, &list);
			zval_ptr_dtor(&list);
		} else if (put) {
			put = sharp_table_put(table, &key, element);
		}
		zval_ptr_dtor(&key);
		if (!put) {
			break;
		}
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

static zend_class_entry *sharp_ce_sequence;
static zend_object_handlers sharp_sequence_handlers;

/* Spec section 12: what one Sequence does to each value of the one it reads. A crossing makes a check stage over a lazy
 * plain PHP source that enters an `Iterable<T>`, so each element is checked where it enters PHP#. */
typedef enum {
	SHARP_SEQUENCE_FROM,
	SHARP_SEQUENCE_FILTER,
	SHARP_SEQUENCE_MAP,
	SHARP_SEQUENCE_TAKE,
	SHARP_SEQUENCE_CHECK,
} sharp_sequence_stage;

/* One stage of a lazy chain: source is the iterable for from and check, and the Sequence before it for every other
 * stage. A check stage checks each value against `checked`, and a bad one throws a TypeError whose message starts with
 * `head`. */
typedef struct {
	sharp_sequence_stage stage;
	zend_long count;
	zval source;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	const sharp_type *checked;
	zend_string *head;
	zend_object std;
} sharp_sequence;

/* One reading of a stage: it.data holds the Sequence, and source reads the stage before it, or is NULL for an array. */
typedef struct {
	zend_object_iterator it;
	zend_object_iterator *source;
	HashPosition position;
	zend_long index;
	zval value;
} sharp_sequence_iterator;

static sharp_sequence *sharp_sequence_from(zend_object *object)
{
	return (sharp_sequence *) ((char *) object - XtOffsetOf(sharp_sequence, std));
}

static zend_object *sharp_sequence_create(zend_class_entry *ce)
{
	sharp_sequence *sequence = zend_object_alloc(sizeof(sharp_sequence), ce);

	memset(sequence, 0, XtOffsetOf(sharp_sequence, std));
	zend_object_std_init(&sequence->std, ce);

	return &sequence->std;
}

static void sharp_sequence_free(zend_object *object)
{
	sharp_sequence *sequence = sharp_sequence_from(object);

	zval_ptr_dtor(&sequence->source);
	zval_ptr_dtor(&sequence->fci.function_name);
	if (sequence->head) {
		zend_string_release(sequence->head);
	}
	zend_object_std_dtor(object);
}

static HashTable *sharp_sequence_get_gc(zend_object *object, zval **table, int *n)
{
	sharp_sequence *sequence = sharp_sequence_from(object);
	zend_get_gc_buffer *buffer = zend_get_gc_buffer_create();

	zend_get_gc_buffer_add_zval(buffer, &sequence->source);
	zend_get_gc_buffer_add_zval(buffer, &sequence->fci.function_name);
	zend_get_gc_buffer_use(buffer, table, n);

	return NULL;
}

/* Calls a stage's closure through a copy of its fci, since a closure that reads the stage again rewrites fci.retval. */
static bool sharp_sequence_call(sharp_sequence *sequence, zval *element, zval *result)
{
	zend_fcall_info fci = sequence->fci;

	return sharp_collection_call(&fci, &sequence->fcc, element, result);
}

/* Spec sections 12 and 30: the tuple `[key, value]` a Map's entry is, a PHP list, as `entry`. */
static void sharp_map_entry(zval *entry, zval *key, zval *value)
{
	zval part;
	array_init_size(entry, 2);
	ZVAL_COPY_DEREF(&part, key);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(entry), &part);
	ZVAL_COPY_DEREF(&part, value);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(entry), &part);
}

/* Moves the source to its first element or its next, and copies that element: a Sharp\Map's entry `[key, value]`, since
 * a Map is an `Iterable` of its entries. Returns false at the end and when reading threw. */
static bool sharp_sequence_iterator_read(sharp_sequence_iterator *iterator, bool rewind, zval *element)
{
	zend_object_iterator *source = iterator->source;
	zval *current;

	if (!source) {
		HashTable *array = Z_ARRVAL(sharp_sequence_from(Z_OBJ(iterator->it.data))->source);

		if (rewind) {
			zend_hash_internal_pointer_reset_ex(array, &iterator->position);
		} else {
			zend_hash_move_forward_ex(array, &iterator->position);
		}
		current = zend_hash_get_current_data_ex(array, &iterator->position);
	} else {
		if (!rewind) {
			source->funcs->move_forward(source);
		} else if (source->funcs->rewind) {
			source->funcs->rewind(source);
		}
		if (EG(exception) || source->funcs->valid(source) == FAILURE) {
			return false;
		}
		current = source->funcs->get_current_data(source);
	}
	if (!current || EG(exception)) {
		return false;
	}
	if (source && Z_OBJCE(sharp_sequence_from(Z_OBJ(iterator->it.data))->source) == sharp_ce_map) {
		zval key;
		source->funcs->get_current_key(source, &key);
		sharp_map_entry(element, &key, current);
		zval_ptr_dtor(&key);

		return true;
	}
	ZVAL_COPY_DEREF(element, current);

	return true;
}

static ZEND_COLD void sharp_sequence_refuse(const sharp_sequence *sequence, const zval *element, zend_long index);

/* Reads the stage's next value into value, which stays undefined at the end. take stops before it reads its source
 * again. A check stage converts each int its type takes as a float, as every crossing does. */
static void sharp_sequence_iterator_fetch(sharp_sequence_iterator *iterator, bool rewind, zval *value)
{
	sharp_sequence *sequence = sharp_sequence_from(Z_OBJ(iterator->it.data));
	zval element, result;

	if (sequence->stage == SHARP_SEQUENCE_TAKE && iterator->index >= sequence->count) {
		return;
	}
	while (sharp_sequence_iterator_read(iterator, rewind, &element)) {
		rewind = false;
		switch (sequence->stage) {
			case SHARP_SEQUENCE_FROM:
			case SHARP_SEQUENCE_TAKE:
				ZVAL_COPY_VALUE(value, &element);
				return;
			case SHARP_SEQUENCE_MAP:
				sharp_sequence_call(sequence, &element, value);
				zval_ptr_dtor(&element);
				return;
			case SHARP_SEQUENCE_CHECK:
				if (sharp_type_accepts(sequence->checked, &element, NULL)) {
					ZVAL_COPY_VALUE(value, &element);
					return;
				}
				if (!EG(exception)) {
					sharp_sequence_refuse(sequence, &element, iterator->index);
				}
				zval_ptr_dtor(&element);
				return;
			case SHARP_SEQUENCE_FILTER: {
				bool kept = sharp_sequence_call(sequence, &element, &result) && zend_is_true(&result);

				zval_ptr_dtor(&result);
				if (kept) {
					ZVAL_COPY_VALUE(value, &element);
					return;
				}
				zval_ptr_dtor(&element);
				if (EG(exception)) {
					return;
				}
				break;
			}
		}
	}
}

/* The old value is released last, so a destructor it runs sees the iterator on its new value. */
static void sharp_sequence_iterator_next(sharp_sequence_iterator *iterator, bool rewind)
{
	zval value, old;

	iterator->index = rewind ? 0 : iterator->index + 1;
	ZVAL_UNDEF(&value);
	sharp_sequence_iterator_fetch(iterator, rewind, &value);
	ZVAL_COPY_VALUE(&old, &iterator->value);
	ZVAL_COPY_VALUE(&iterator->value, &value);
	zval_ptr_dtor(&old);
}

static void sharp_sequence_iterator_dtor(zend_object_iterator *object)
{
	sharp_sequence_iterator *iterator = (sharp_sequence_iterator *) object;

	if (iterator->source) {
		zend_iterator_dtor(iterator->source);
	}
	zval_ptr_dtor(&iterator->value);
	zval_ptr_dtor(&iterator->it.data);
}

static zend_result sharp_sequence_iterator_valid(zend_object_iterator *object)
{
	return Z_ISUNDEF(((sharp_sequence_iterator *) object)->value) ? FAILURE : SUCCESS;
}

static zval *sharp_sequence_iterator_get_current_data(zend_object_iterator *object)
{
	sharp_sequence_iterator *iterator = (sharp_sequence_iterator *) object;

	return Z_ISUNDEF(iterator->value) ? NULL : &iterator->value;
}

/* Keys count the values this stage gave, from 0, whatever keys its source had. */
static void sharp_sequence_iterator_get_current_key(zend_object_iterator *object, zval *key)
{
	sharp_sequence_iterator *iterator = (sharp_sequence_iterator *) object;

	if (Z_ISUNDEF(iterator->value)) {
		ZVAL_NULL(key);
	} else {
		ZVAL_LONG(key, iterator->index);
	}
}

static void sharp_sequence_iterator_move_forward(zend_object_iterator *object)
{
	sharp_sequence_iterator_next((sharp_sequence_iterator *) object, false);
}

static void sharp_sequence_iterator_rewind(zend_object_iterator *object)
{
	sharp_sequence_iterator_next((sharp_sequence_iterator *) object, true);
}

static HashTable *sharp_sequence_iterator_get_gc(zend_object_iterator *object, zval **table, int *n)
{
	sharp_sequence_iterator *iterator = (sharp_sequence_iterator *) object;
	zend_get_gc_buffer *buffer = zend_get_gc_buffer_create();

	zend_get_gc_buffer_add_zval(buffer, &iterator->it.data);
	zend_get_gc_buffer_add_zval(buffer, &iterator->value);
	if (iterator->source) {
		zend_get_gc_buffer_add_obj(buffer, &iterator->source->std);
	}
	zend_get_gc_buffer_use(buffer, table, n);

	return NULL;
}

static const zend_object_iterator_funcs sharp_sequence_iterator_funcs = {
	sharp_sequence_iterator_dtor,
	sharp_sequence_iterator_valid,
	sharp_sequence_iterator_get_current_data,
	sharp_sequence_iterator_get_current_key,
	sharp_sequence_iterator_move_forward,
	sharp_sequence_iterator_rewind,
	NULL,
	sharp_sequence_iterator_get_gc,
};

/* Reads the chain for foreach, yield from, getIterator and toList, each through its own iterator of every stage. */
static zend_object_iterator *sharp_sequence_get_iterator(zend_class_entry *ce, zval *object, int by_ref)
{
	sharp_sequence *sequence = sharp_sequence_from(Z_OBJ_P(object));
	zend_object_iterator *source = NULL;

	if (by_ref) {
		zend_throw_error(NULL, "An iterator cannot be used with foreach by reference");
		return NULL;
	}
	if (Z_TYPE(sequence->source) == IS_OBJECT) {
		zend_class_entry *source_ce = Z_OBJCE(sequence->source);

		source = source_ce->get_iterator(source_ce, &sequence->source, 0);
		if (!source) {
			return NULL;
		}
	}

	sharp_sequence_iterator *iterator = emalloc(sizeof(sharp_sequence_iterator));
	zend_iterator_init(&iterator->it);
	iterator->it.funcs = &sharp_sequence_iterator_funcs;
	ZVAL_OBJ_COPY(&iterator->it.data, Z_OBJ_P(object));
	iterator->source = source;
	iterator->position = 0;
	iterator->index = 0;
	ZVAL_UNDEF(&iterator->value);

	return &iterator->it;
}

static sharp_sequence *sharp_sequence_new(zval *result, sharp_sequence_stage stage, zval *source)
{
	object_init_ex(result, sharp_ce_sequence);
	sharp_sequence *sequence = sharp_sequence_from(Z_OBJ_P(result));
	sequence->stage = stage;
	ZVAL_COPY(&sequence->source, source);

	return sequence;
}

/* A stored callable must outlive the call that passed it, so a stage takes only a Closure, which is never a
 * trampoline. */
static void sharp_sequence_closure_stage(INTERNAL_FUNCTION_PARAMETERS, sharp_sequence_stage stage)
{
	zval *closure;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJECT_OF_CLASS(closure, zend_ce_closure)
	ZEND_PARSE_PARAMETERS_END();

	sharp_sequence *sequence = sharp_sequence_new(return_value, stage, ZEND_THIS);
	zend_fcall_info_init(closure, 0, &sequence->fci, &sequence->fcc, NULL, NULL);
	Z_ADDREF(sequence->fci.function_name);
}

ZEND_METHOD(Sharp_Sequence, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* A Sequence is already one, so a local that holds a chain's result chains on without a second stage. */
ZEND_METHOD(Sharp_Sequence, from)
{
	zval *source;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ITERABLE(source)
	ZEND_PARSE_PARAMETERS_END();

	if (Z_TYPE_P(source) == IS_OBJECT && Z_OBJCE_P(source) == sharp_ce_sequence) {
		RETURN_COPY(source);
	}

	sharp_sequence_new(return_value, SHARP_SEQUENCE_FROM, source);
}

ZEND_METHOD(Sharp_Sequence, filter)
{
	sharp_sequence_closure_stage(INTERNAL_FUNCTION_PARAM_PASSTHRU, SHARP_SEQUENCE_FILTER);
}

ZEND_METHOD(Sharp_Sequence, map)
{
	sharp_sequence_closure_stage(INTERNAL_FUNCTION_PARAM_PASSTHRU, SHARP_SEQUENCE_MAP);
}

ZEND_METHOD(Sharp_Sequence, take)
{
	zend_long count;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_LONG(count)
	ZEND_PARSE_PARAMETERS_END();

	if (count < 0) {
		zend_argument_value_error(1, "must be greater than or equal to 0");
		RETURN_THROWS();
	}

	sharp_sequence_new(return_value, SHARP_SEQUENCE_TAKE, ZEND_THIS)->count = count;
}

ZEND_METHOD(Sharp_Sequence, toList)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_sequence_iterator *iterator =
		(sharp_sequence_iterator *) sharp_sequence_get_iterator(sharp_ce_sequence, ZEND_THIS, 0);
	if (!iterator) {
		RETURN_THROWS();
	}

	zval list;
	array_init(&list);
	sharp_sequence_iterator_next(iterator, true);
	while (!EG(exception) && !Z_ISUNDEF(iterator->value)) {
		Z_TRY_ADDREF(iterator->value);
		zend_hash_next_index_insert_new(Z_ARRVAL(list), &iterator->value);
		sharp_sequence_iterator_next(iterator, false);
	}
	zend_iterator_dtor(&iterator->it);

	if (EG(exception)) {
		zval_ptr_dtor(&list);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&list);
}

ZEND_METHOD(Sharp_Sequence, getIterator)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_create_internal_iterator_zval(return_value, ZEND_THIS);
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
	/* A SHARP_TYPE_FUNCTION's members are its result, its parameters, then the effect classes its `uses` lists, which
	 * are the last `effects` of them. */
	uint32_t effects;
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

/* `name`, `name<A, B>`, `Function<R(P1, P2)>`, `Function<R(P1, P2) uses E1, E2>` or the tuple `[A, B]`. */
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
		/* Each effect is a class, sorted after the one before it, as spec section 29 lists them in `uses`. */
		if (sharp_type_skip(reader, " uses ")) {
			do {
				sharp_type_node *effect = sharp_type_read_atom(reader);

				if (!effect || effect->kind != SHARP_TYPE_NAMED || !effect->name || effect->count
					|| (type->effects
						&& zend_binary_strcmp(type->last->text, type->last->length, effect->text, effect->length) >= 0)) {
					return NULL;
				}
				sharp_type_node_add(type, effect);
				type->effects++;
			} while (sharp_type_skip(reader, ", "));
		}
		if (!sharp_type_skip(reader, ">")) {
			return NULL;
		}
		type->length = reader->at - start;

		return type;
	}
	if (sharp_type_skip(reader, "[")) {
		type = sharp_type_node_start(reader, SHARP_TYPE_TUPLE, start);
		do {
			sharp_type_node *element = sharp_type_read(reader);

			if (!element) {
				return NULL;
			}
			sharp_type_node_add(type, element);
		} while (sharp_type_skip(reader, ", "));
		if (!sharp_type_skip(reader, "]")) {
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
		case SHARP_TYPE_FUNCTION: {
			smart_str_appends(&text, "Function<");
			smart_str_appendl(&text, node->first->text, node->first->length);
			smart_str_appendc(&text, '(');
			const sharp_type_node *member = node->first->next;
			for (uint32_t i = 0; i < node->count - 1 - node->effects; i++, member = member->next) {
				if (i) {
					smart_str_appends(&text, ", ");
				}
				smart_str_appendl(&text, member->text, member->length);
			}
			smart_str_appendc(&text, ')');
			if (member) {
				smart_str_appends(&text, " uses ");
				sharp_type_node_join(&text, member, ", ");
			}
			smart_str_appendc(&text, '>');
			break;
		}
		case SHARP_TYPE_TUPLE:
			smart_str_appendc(&text, '[');
			sharp_type_node_join(&text, node->first, ", ");
			smart_str_appendc(&text, ']');
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

/* The class whose type parameters the `$i` of code `scope` holds count, of `func` when the code is a function's. A
 * method a class took from a trait is the trait's code, whose op array it shares. The trait of a PHP# interface's
 * default bodies counts its interface's, which its header names, spec section 22. */
static const zend_class_entry *sharp_type_scope(const zend_class_entry *scope, const zend_function *func)
{
	if (scope && func && func->type == ZEND_USER_FUNCTION && (func->common.fn_flags & ZEND_ACC_TRAIT_CLONE)) {
		const zend_class_entry *user = scope;

		/* A loaded class has loaded each trait it uses. */
		for (uint32_t i = 0; scope == user && i < user->num_traits; i++) {
			const zend_class_entry *trait = zend_lookup_class_ex(
				user->trait_names[i].name, user->trait_names[i].lc_name, ZEND_FETCH_CLASS_NO_AUTOLOAD);
			const zend_function *original;

			ZEND_ASSERT(trait != NULL);
			ZEND_HASH_MAP_FOREACH_PTR(&trait->function_table, original) {
				if (original->type == ZEND_USER_FUNCTION && original->op_array.opcodes == func->op_array.opcodes) {
					scope = trait;
					break;
				}
			} ZEND_HASH_FOREACH_END();
		}
	}
	if (scope && (scope->ce_flags & ZEND_ACC_TRAIT) && sharp_class_is_sharp(scope) && scope->info.user.sharp_header) {
		const zend_string *header = scope->info.user.sharp_header;
		zend_string *name = sharp_type_class_name(ZSTR_VAL(header), strcspn(ZSTR_VAL(header), "<"), false);
		const zend_class_entry *interface = zend_lookup_class_ex(name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
		zend_string_release_ex(name, false);

		/* The trait is declared after its interface, in the interface's file. */
		ZEND_ASSERT(interface != NULL);
		scope = interface;
	}

	return scope;
}

const sharp_type *sharp_type_list_of_frame(const zval *text, zend_object *object, const sharp_type *method,
	bool unresolved, const zend_class_entry *scope, void **cache)
{
	scope = sharp_type_scope(scope, NULL);
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

/* Whether `ce` or a class it extends uses the trait `trait`, itself or through a trait it uses, as class_uses() lists
 * them on each class up the chain. No object is instanceof a trait, and spec section 22 makes a plain PHP trait a type.
 * A linked class has loaded each trait it uses. */
static bool sharp_class_uses_trait(const zend_class_entry *ce, const zend_class_entry *trait)
{
	for (; ce; ce = ce->parent) {
		for (uint32_t i = 0; i < ce->num_traits; i++) {
			const zend_class_entry *used = zend_lookup_class_ex(
				ce->trait_names[i].name, ce->trait_names[i].lc_name, ZEND_FETCH_CLASS_NO_AUTOLOAD);
			if (used && (used == trait || sharp_class_uses_trait(used, trait))) {
				return true;
			}
		}
	}

	return false;
}

/* Whether `object` is an instance of the class `type` names, or uses the trait it names, whose type arguments for that
 * class match `type`'s own, as sharp_type_argument_matches matches them. Only a class PHP# declares with type
 * parameters has type arguments to compare: plain PHP's generics are erased. A loaded object's class has loaded each
 * class it extends or implements. */
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
	if (!ce) {
		return false;
	}
	if (ce->ce_flags & ZEND_ACC_TRAIT) {
		return sharp_class_uses_trait(object->ce, ce);
	}
	if (!instanceof_function(object->ce, ce)) {
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

/* Whether the string `value` is a class value of `Class<type>`, for a `type` that is no class, spec section 25: the
 * class value of a type that fits `type`, as the checker lets `Class<X>` fit `Class<Y>` when `X` fits `Y`, so a
 * `Class<int|string>` takes `int`, `string` and `int|string`. The class value of such a type is the text `typeof` gives
 * it, which reads only as typeof spells it, and that of a class is its name, `App\Order`, which reads as `App.Order`. */
static bool sharp_class_value_fits(const zend_string *value, const sharp_type *type)
{
	zend_string *text = zend_string_init(ZSTR_VAL(value), ZSTR_LEN(value), false);
	for (size_t i = 0; i < ZSTR_LEN(text); i++) {
		if (ZSTR_VAL(text)[i] == '\\') {
			ZSTR_VAL(text)[i] = '.';
		}
	}

	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(text), ZSTR_LEN(text), zend_arena_create(1024));
	reader.depth_left = SHARP_TYPE_INPUT_DEPTH;
	sharp_type_node *given = sharp_type_read(&reader);
	bool fits = given && reader.at == reader.end && !reader.names
		&& sharp_type_argument_takes(type, given, '+', &reader);
	zend_arena_destroy(reader.arena);
	zend_string_release_ex(text, false);

	return fits;
}

/* Whether the string `value` is a class value of `Class<type>`, spec section 11: it names `type`'s class or a class that
 * extends or implements it, and the type arguments it carries give that class type arguments `type` takes. A class name
 * plain PHP writes carries none, so it gives its class's bounds, as an object plain PHP creates does. A `Class` of a type
 * that is no class, as `Class<int>` or `Class<int|string>`, takes what sharp_class_value_fits takes. `Class<Any?>`,
 * `Class<Object>` and a `Class` of a type parameter no type argument resolves take any string. */
static bool sharp_class_value_is(const zval *value, const sharp_type *type)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return false;
	}
	const sharp_type *held = type->kind == SHARP_TYPE_NULLABLE ? type->members[0] : type;
	if (held->kind == SHARP_TYPE_NAMED
		&& (held->built_in == SHARP_BUILT_IN_ANY || held->built_in == SHARP_BUILT_IN_OBJECT
			|| zend_string_equals_literal(held->text, SHARP_TYPE_UNRESOLVED))) {
		return true;
	}
	if (type->kind != SHARP_TYPE_NAMED || !type->class_name) {
		return sharp_class_value_fits(Z_STR_P(value), type);
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

/* The type of the element at `position` of a value of `type`: a tuple's element type there, a collection's one element
 * type, or NULL for any other type. */
static const sharp_type *sharp_type_element_at(const sharp_type *type, uint32_t position)
{
	return type->kind == SHARP_TYPE_TUPLE ? type->members[position] : sharp_type_elements(type);
}

/* Whether `value` is walked entry by entry as a collection of `type`: an array where `type` is a collection whose
 * elements are checked, the Sharp\Map or Sharp\Set object of a `Map` or a `Set`, or a Sharp\Map or a Sharp\Set held
 * as an `Iterable`. */
static bool sharp_type_collects(const sharp_type *type, const zval *value)
{
	if (Z_TYPE_P(value) == IS_ARRAY) {
		return sharp_type_elements(type) != NULL;
	}
	if (Z_TYPE_P(value) != IS_OBJECT) {
		return false;
	}

	return ((type->built_in == SHARP_BUILT_IN_MAP || type->built_in == SHARP_BUILT_IN_ITERABLE)
			&& Z_OBJCE_P(value) == sharp_ce_map)
		|| ((type->built_in == SHARP_BUILT_IN_SET || type->built_in == SHARP_BUILT_IN_ITERABLE)
			&& Z_OBJCE_P(value) == sharp_ce_set);
}

/* The collection whose elements `value`, an array, a Sharp\Map or a Sharp\Set, is walked by when it is of `type`, so each
 * bad entry is a problem at its own path: `type` itself, the member of `T?`, or the one collection a union has. NULL for
 * any other type, whose problem is the value's own. */
static const sharp_type *sharp_type_walked(const sharp_type *type, const zval *value)
{
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return sharp_type_collects(type, value) ? type : NULL;
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

/* How sharp_type_holds takes a value. */
typedef enum {
	/* As `is` tests it: a closure plain PHP made, with no PHP# type, is of no function type. */
	SHARP_HOLDS_TEST,
	/* As a value from plain PHP enters PHP#: such a closure is of every function type, since PHP's own check of
	 * `Closure` is all plain PHP gives it, as a raw generic object has its bounds. */
	SHARP_HOLDS_ENTRY,
	/* As SHARP_HOLDS_ENTRY, with an int of `float`, which entry then converts. */
	SHARP_HOLDS_ENTRY_INT_AS_FLOAT,
} sharp_holds_mode;

static bool sharp_type_holds(
	const sharp_type *type, const zval *value, sharp_holds_mode mode, sharp_type_problems *problems);
static bool sharp_closure_is(const zval *value, const sharp_type *type, sharp_holds_mode mode);
static bool sharp_iterable_object_holds(const sharp_type *iterable, zend_object *object);
static const sharp_type *sharp_type_iterable_member(const sharp_type *type);
static const sharp_type *sharp_type_lazily_checked(const sharp_type *type, const zval *value);
static void sharp_sequence_check(zval *value, const sharp_type *iterable, zend_string *head);

/* Whether `key`, or the int `index` when `key` is NULL, is a key of the PHP array a plain PHP caller gives for a `Map`
 * whose keys are of `type`. PHP stores the key `"5"` as the int 5, so a `string` key type takes every key, a backed enum
 * takes each case's backing value, and `T?` and a union take what any of their members takes. */
static bool sharp_type_holds_key(const sharp_type *type, zend_ulong index, zend_string *key)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_holds_key(type->members[0], index, key);
	}
	if (type->kind == SHARP_TYPE_UNION) {
		for (uint32_t i = 0; i < type->count; i++) {
			if (sharp_type_holds_key(type->members[i], index, key)) {
				return true;
			}
		}
		return false;
	}
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

	return sharp_type_holds(type, &value, SHARP_HOLDS_ENTRY, NULL);
}

/* Whether the entry `element` under `key` of a collection holds, where `key_holds` says whether the key is of `keys`,
 * the key type of a `Map`, and `elements` is the type of its elements. With `problems`, the entry's path goes on the
 * walk's path while it is checked: `["name"]` for a string key, `[5]` for an int, and the key's own text for any other
 * key a Sharp\Map holds, as `[object Lib\Standing::Active]`. Without `problems`, a bad key stops the check. */
static bool sharp_type_entry_holds(const sharp_type *keys, bool key_holds, const zval *key, const sharp_type *elements,
	const zval *element, sharp_holds_mode mode, sharp_type_problems *problems)
{
	size_t length = 0;
	if (problems) {
		length = problems->path.s ? ZSTR_LEN(problems->path.s) : 0;
		smart_str_appendc(&problems->path, '[');
		if (Z_TYPE_P(key) == IS_STRING) {
			smart_str_appendc(&problems->path, '"');
			smart_str_append(&problems->path, Z_STR_P(key));
			smart_str_appendc(&problems->path, '"');
		} else if (Z_TYPE_P(key) == IS_LONG) {
			smart_str_append_long(&problems->path, Z_LVAL_P(key));
		} else {
			sharp_value_text(&problems->path, key);
		}
		smart_str_appendc(&problems->path, ']');
	}

	bool holds = key_holds;
	if (!key_holds && problems) {
		sharp_type_problem(problems, "key ", keys, key);
	}
	if ((holds || problems) && !sharp_type_holds(elements, element, mode, problems)) {
		holds = false;
	}

	if (problems) {
		ZSTR_LEN(problems->path.s) = length;
	}

	return holds;
}

/* Whether the Sharp\Map or Sharp\Set `table` is of the collection `type`: each key of a Map is checked against its key
 * type as the value it is, and each value, or each element of a Set, as sharp_type_holds checks it. A Map held as an
 * `Iterable` is checked by the `[key, value]` tuple of each entry, as it gives them. A Set's entry is at its position in
 * the Set's order. An int it accepts for a float stays an int, since the table may be shared. The table is held while
 * it is walked, so a write that a check runs through a holder copies it first. */
static bool sharp_type_table_holds(
	const sharp_type *type, zend_object *table, sharp_holds_mode mode, sharp_type_problems *problems)
{
	const sharp_type *keys = type->built_in == SHARP_BUILT_IN_MAP ? type->members[0] : NULL;
	const sharp_type *elements = sharp_type_elements(type);
	/* Spec section 12: a `Map` held as an `Iterable` is the `[key, value]` tuple of each entry. */
	bool entries = !keys && table->ce == sharp_ce_map;
	bool holds = true;
	zend_long position = 0;
	GC_ADDREF(table);
	SHARP_TABLE_FOREACH(sharp_table_of(table), bucket) {
		zval at, entry;
		ZVAL_LONG(&at, position++);
		if (entries) {
			sharp_map_entry(&entry, &bucket->key, &bucket->value);
		}
		const zval *key = keys ? &bucket->key : &at;
		const zval *element = entries ? &entry : keys ? &bucket->value : &bucket->key;
		bool key_holds = !keys || sharp_type_holds(keys, &bucket->key, mode, NULL);
		if (!sharp_type_entry_holds(keys, key_holds, key, elements, element, mode, problems)) {
			holds = false;
		}
		if (entries) {
			zval_ptr_dtor(&entry);
		}
		if (UNEXPECTED(EG(exception)) || (!holds && !problems)) {
			break;
		}
	} SHARP_TABLE_FOREACH_END();
	OBJ_RELEASE(table);

	return holds;
}

/* Whether `value`, an array, or the Sharp\Map or Sharp\Set of a `Map` or a `Set`, is of the collection `type`, each
 * element checked by sharp_type_holds. */
static bool sharp_type_collection_holds(
	const sharp_type *type, const zval *value, sharp_holds_mode mode, sharp_type_problems *problems)
{
	if (Z_TYPE_P(value) == IS_OBJECT) {
		return sharp_type_table_holds(type, Z_OBJ_P(value), mode, problems);
	}
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
	zend_string *name;
	zval *element;
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(value), index, name, element) {
		zval key;
		if (name) {
			ZVAL_STR(&key, name);
		} else {
			ZVAL_LONG(&key, (zend_long) index);
		}
		bool key_holds = !keys || sharp_type_holds_key(keys, index, name);
		if (!sharp_type_entry_holds(keys, key_holds, &key, elements, element, mode, problems)) {
			holds = false;
		}
		if (UNEXPECTED(EG(exception)) || (!holds && !problems)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();

	return holds;
}

/* Whether `value` has the shape of the tuple `type`, spec section 30: a PHP list of exactly its count of elements. */
static bool sharp_type_tuple_shaped(const sharp_type *type, const zval *value)
{
	return Z_TYPE_P(value) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL_P(value)) == type->count
		&& zend_array_is_list(Z_ARRVAL_P(value));
}

/* Whether `value`, of the shape of the tuple `type`, holds the element type at each position. With `problems`, each bad
 * element is a problem at its position's path, `[1]`. */
static bool sharp_type_tuple_holds(
	const sharp_type *type, const zval *value, sharp_holds_mode mode, sharp_type_problems *problems)
{
	bool holds = true;
	zend_long position = 0;
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		zval at;
		ZVAL_LONG(&at, position);
		if (!sharp_type_entry_holds(NULL, true, &at, type->members[position++], element, mode, problems)) {
			holds = false;
		}
		if (UNEXPECTED(EG(exception)) || (!holds && !problems)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();

	return holds;
}

/* Whether `value` is of `type`, as `mode` takes it. With `problems`, it adds a problem for each bad value: an element of
 * a collection or a tuple at its own path, any other value at the path the walk is at. */
static bool sharp_type_holds(
	const sharp_type *type, const zval *value, sharp_holds_mode mode, sharp_type_problems *problems)
{
	ZVAL_DEREF(value);
	if (problems) {
		if (type->kind == SHARP_TYPE_TUPLE && sharp_type_tuple_shaped(type, value)) {
			return sharp_type_tuple_holds(type, value, mode, problems);
		}
		const sharp_type *walked = sharp_type_walked(type, value);
		if (walked) {
			return sharp_type_collection_holds(walked, value, mode, problems);
		}
		if (sharp_type_holds(type, value, mode, NULL)) {
			return true;
		}
		if (!EG(exception)) {
			sharp_type_problem(problems, "", type, value);
		}
		return false;
	}

	switch (type->kind) {
		case SHARP_TYPE_NULLABLE:
			return Z_TYPE_P(value) == IS_NULL || sharp_type_holds(type->members[0], value, mode, NULL);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, mode, NULL)) {
					return true;
				}
			}
			return false;
		case SHARP_TYPE_INTERSECTION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (!sharp_type_holds(type->members[i], value, mode, NULL)) {
					return false;
				}
			}
			return true;
		case SHARP_TYPE_FUNCTION:
			return sharp_closure_is(value, type, mode);
		case SHARP_TYPE_TUPLE:
			return sharp_type_tuple_shaped(type, value) && sharp_type_tuple_holds(type, value, mode, NULL);
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
			return Z_TYPE_P(value) == IS_DOUBLE || (mode == SHARP_HOLDS_ENTRY_INT_AS_FLOAT && Z_TYPE_P(value) == IS_LONG);
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
			return sharp_type_collects(type, value) && sharp_type_collection_holds(type, value, mode, NULL);
		case SHARP_BUILT_IN_ITERABLE:
			if (sharp_type_collects(type, value)) {
				return sharp_type_collection_holds(type, value, mode, NULL);
			}
			if (Z_TYPE_P(value) != IS_OBJECT || !instanceof_function(Z_OBJCE_P(value), zend_ce_traversable)) {
				return false;
			}
			/* A crossing takes any other Traversable, and checks each element as it is read. */
			return mode != SHARP_HOLDS_TEST || sharp_iterable_object_holds(type, Z_OBJ_P(value));
		case SHARP_BUILT_IN_CLASS:
			return sharp_class_value_is(value, type->members[0]);
		default:
			return true;
	}
}

/* The member of `type` an int in `value` is converted by once sharp_type_holds took `value` with ints as floats: the type
 * itself, a tuple too, the member of `T?` for a value that is not null, and the first member of a union that holds the
 * value with ints as floats when none holds it without, as PHP picks a union member for an argument. NULL when nothing
 * converts. */
static const sharp_type *sharp_type_converting(const sharp_type *type, const zval *value)
{
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return type->class_name ? NULL : type;
		case SHARP_TYPE_TUPLE:
			return type;
		case SHARP_TYPE_NULLABLE:
			return Z_TYPE_P(value) == IS_NULL ? NULL : sharp_type_converting(type->members[0], value);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, SHARP_HOLDS_ENTRY, NULL)) {
					return NULL;
				}
			}
			for (uint32_t i = 0; i < type->count; i++) {
				if (sharp_type_holds(type->members[i], value, SHARP_HOLDS_ENTRY_INT_AS_FLOAT, NULL)) {
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

	if (!sharp_type_element_at(type, 0) || Z_TYPE_P(value) != IS_ARRAY) {
		return false;
	}
	uint32_t position = 0;
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		if (sharp_type_changes(sharp_type_element_at(type, position++), element)) {
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

	const sharp_type *converting = sharp_type_converting(type, value);
	uint32_t position = 0;
	zval *element;
	SEPARATE_ARRAY(value);
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		sharp_type_convert(sharp_type_element_at(converting, position++), element);
	} ZEND_HASH_FOREACH_END();
}

bool sharp_type_accepts(const sharp_type *type, zval *value, sharp_type_problems *problems)
{
	/* A value that holds without converting an int is taken in one walk. */
	if (sharp_type_holds(type, value, SHARP_HOLDS_ENTRY, NULL)) {
		return !EG(exception);
	}
	if (UNEXPECTED(EG(exception))
		|| !sharp_type_holds(type, value, SHARP_HOLDS_ENTRY_INT_AS_FLOAT, problems) || UNEXPECTED(EG(exception))) {
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
	const sharp_type *iterable = accepts ? sharp_type_lazily_checked(list->members[0], value) : NULL;
	if (iterable) {
		/* A bad element is named at its own path, as `rows[3] expected int, got string 'x'`. */
		sharp_sequence_check(value, iterable, zend_string_copy(path));
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
	return sharp_type_holds(list->members[0], value, SHARP_HOLDS_TEST, NULL) && !EG(exception);
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

/* The first entry of the array, Sharp\Map or Sharp\Set `value` that is not of `type`, as the walk that refused `value`
 * finds it, for a TypeError to name: `: [1] expected Probe.Order, got object Probe.Invoice`. Empty for any other value,
 * and for a collection whose bad value is the collection itself, which the TypeError already names. */
static ZEND_COLD zend_string *sharp_first_problem(const sharp_type *type, const zval *value)
{
	smart_str text = {0};
	if (Z_TYPE_P(value) != IS_ARRAY && !SHARP_IS_TABLE(value)) {
		return smart_str_extract(&text);
	}

	zval problems;
	array_init(&problems);
	sharp_type_problems found = {&problems, {0}};
	sharp_type_holds(type, value, SHARP_HOLDS_ENTRY_INT_AS_FLOAT, &found);
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
		Z_TYPE(EX(This)) == IS_OBJECT ? Z_OBJ(EX(This)) : NULL, NULL, true, sharp_type_scope(EX(func)->common.scope, EX(func)), cache);
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
		const sharp_type *iterable = sharp_type_lazily_checked(expected->members[i], value);
		if (iterable) {
			/* Worded as the TypeError above, which a bad element throws where a loop reads it. */
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *function = get_function_or_method_name(EX(func));
			const char *name = get_function_arg_name(EX(func), i + 1);
			zend_string *head = zend_strpprintf(0, "%s(): Argument #%u%s%s%s must be of type %s, %s given: ",
				ZSTR_VAL(function), i + 1, name ? " ($" : "", name ? name : "", name ? ")" : "",
				ZSTR_VAL(expected->members[i]->text), ZSTR_VAL(given.s));
			zend_string_release(function);
			smart_str_free(&given);
			sharp_sequence_check(value, iterable, head);
		}
	}
}

bool sharp_type_verify(zend_execute_data *execute_data, zval *value, const zval *text, void **cache)
{
	const sharp_type *expected = sharp_type_list_of_frame(text,
		Z_TYPE(EX(This)) == IS_OBJECT ? Z_OBJ(EX(This)) : NULL, NULL, true, sharp_type_scope(EX(func)->common.scope, EX(func)), cache);
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
	const sharp_type *iterable = sharp_type_lazily_checked(type, value);
	if (iterable) {
		smart_str given = {0};
		sharp_value_type_text(&given, value);
		zend_string *head = zend_strpprintf(0, "%s: Return value must be of type %s, %s returned: ",
			Z_STRVAL_P(text + 1), ZSTR_VAL(type->text), ZSTR_VAL(given.s));
		smart_str_free(&given);
		sharp_sequence_check(value, iterable, head);
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
		const sharp_type *iterable = sharp_type_lazily_checked(type, value);
		if (iterable) {
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *head = zend_strpprintf(0, "Cannot assign %s to %sproperty %s::$%s of type %s: ", ZSTR_VAL(given.s),
				reference ? "reference held by " : "", ZSTR_VAL(info->ce->name),
				zend_get_unmangled_property_name(info->name), ZSTR_VAL(type->text));
			smart_str_free(&given);
			sharp_sequence_check(value, iterable, head);
		}
		return Z_TYPE_P(value) != IS_ARRAY || !sharp_crosses_property(info) || sharp_cross_property(info, value);
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

/* Whether a function of the type `given` can be assigned to the function type `wanted`, spec section 21, by the rules the
 * checker assigns one by: `wanted` lists each effect `given` has, each parameter type of `wanted` is within `given`'s at
 * its place, and `given`'s result is within `wanted`'s unless that is `void` or `Any?`. A function may take fewer
 * parameters than `wanted` passes, and more only from the index `required` on, where its own parameters are optional. */
static bool sharp_type_function_fits(
	sharp_type_node *given, sharp_type_node *wanted, uint32_t required, sharp_type_reader *reader)
{
	uint32_t given_parameters = given->count - 1 - given->effects;
	uint32_t wanted_parameters = wanted->count - 1 - wanted->effects;

	sharp_type_node *allowed = wanted->first;
	for (uint32_t i = 0; i <= wanted_parameters; i++) {
		allowed = allowed->next;
	}
	sharp_type_node *effect = given->first;
	for (uint32_t i = 0; i <= given_parameters; i++) {
		effect = effect->next;
	}
	/* Spec section 29 compares effects by their class names, which PHP compares without case. */
	for (; effect; effect = effect->next) {
		sharp_type_node *listed = allowed;
		while (listed && zend_binary_strcasecmp(effect->text, effect->length, listed->text, listed->length) != 0) {
			listed = listed->next;
		}
		if (!listed) {
			return false;
		}
	}

	sharp_type_node *parameter = given->first->next;
	sharp_type_node *passed = wanted->first->next;
	for (uint32_t i = 0; i < given_parameters; i++, parameter = parameter->next) {
		if (i >= wanted_parameters) {
			if (i < required) {
				return false;
			}
			break;
		}
		if (!sharp_type_fits(passed, parameter, reader)) {
			return false;
		}
		passed = passed->next;
	}

	return sharp_type_node_is(wanted->first, "void") || sharp_type_node_is(wanted->first, "Any?")
		|| sharp_type_fits(given->first, wanted->first, reader);
}

/* Whether `type` is within `bound`: `Any?` holds every type, `Any` every type but null, a class its subclasses, a class
 * with type arguments each subclass whose header gives it type arguments its own take, a function type each function
 * type that can be assigned to it, a tuple each tuple whose element types are within its own, an intersection what
 * each of its classes holds, and any other bound only itself. A
 * union is within a bound when each of its members is, and an intersection when one of its classes is. */
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
	/* A function type has no default for a parameter, so each it takes is one a caller passes. */
	if (bound->kind == SHARP_TYPE_FUNCTION) {
		return type->kind == SHARP_TYPE_FUNCTION && sharp_type_function_fits(type, bound, UINT32_MAX, reader);
	}
	/* A tuple never changes, so each element type need only be within the bound's at its position. */
	if (bound->kind == SHARP_TYPE_TUPLE) {
		if (type->kind != SHARP_TYPE_TUPLE || type->count != bound->count) {
			return false;
		}
		for (sharp_type_node *element = type->first, *wanted = bound->first; element;
			element = element->next, wanted = wanted->next) {
			if (!sharp_type_fits(element, wanted, reader)) {
				return false;
			}
		}

		return true;
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

/* The type the PHP# function `func` carries, spelled for `object`, its `this`, and `method`, the type arguments of its
 * own type parameters: its function type, spec section 21, or its result type when no function type can say its
 * effects, as for one that calls plain PHP no `extern` declares. NULL for a plain PHP function, for a method that
 * carries no type, and for one whose type names a type parameter it has no type argument for. */
static const sharp_type *sharp_function_carried(const zend_function *func, zend_object *object, const sharp_type *method)
{
	if (func->type != ZEND_USER_FUNCTION || !func->op_array.sharp_type) {
		return NULL;
	}

	zval text;
	ZVAL_INTERNED_STR(&text, func->op_array.sharp_type);
	if (!object && memchr(Z_STRVAL(text), '$', Z_STRLEN(text))) {
		return NULL;
	}
	void *cache[4] = {NULL};
	const sharp_type *own = sharp_type_list_of_frame(&text, object, method, false, func->common.scope, cache);

	return own ? own->members[0] : NULL;
}

/* The function type of `carried`, a type sharp_function_carried read, or NULL when it is a result type alone. */
static const sharp_type *sharp_carried_function(const sharp_type *carried)
{
	return carried && carried->kind == SHARP_TYPE_FUNCTION ? carried : NULL;
}

/* The result type of `carried`, a type sharp_function_carried read: its function type's result, or itself. */
static const sharp_type *sharp_carried_result(const sharp_type *carried)
{
	return carried && carried->kind == SHARP_TYPE_FUNCTION ? carried->members[0] : carried;
}

/* The type the closure `value` carries, as sharp_function_carried spells it for the object the closure is bound to and
 * the method type arguments it captured. */
static const sharp_type *sharp_closure_carried(const zval *value)
{
	const zend_function *func = zend_get_closure_method_def(Z_OBJ_P(value));
	zval *this_ptr = zend_get_closure_this_ptr((zval *) value);
	zend_object *object = Z_TYPE_P(this_ptr) == IS_OBJECT ? Z_OBJ_P(this_ptr) : NULL;
	/* A lambda that names its method's own type parameters captures the method's type arguments, as its frame keeps
	 * them. A closure made from a method owns no statics, as zend_closure_get_gc says. */
	HashTable *captured = func->type == ZEND_USER_FUNCTION && !(func->common.fn_flags & ZEND_ACC_FAKE_CLOSURE)
		? ZEND_MAP_PTR_GET(func->op_array.static_variables_ptr) : NULL;
	zval *arguments = captured ? zend_hash_find(captured, sharp_type_arguments_key) : NULL;
	const sharp_type *method = arguments && Z_TYPE_P(arguments) == IS_PTR ? Z_PTR_P(arguments) : NULL;

	return sharp_function_carried(func, object, method);
}

/* The function type the closure `value` declares, as sharp_closure_carried spells it. */
static const sharp_type *sharp_closure_type(const zval *value)
{
	return sharp_carried_function(sharp_closure_carried(value));
}

/* The element type the PHP# generator `object` yields, spec section 12: the `T` of the `Iterable<T>` its method
 * returns, spelled for the frame it runs in, whatever its body calls. NULL for a plain PHP generator and for one that
 * has finished, whose frame is gone. */
static const sharp_type *sharp_generator_yields(zend_object *object)
{
	const zend_execute_data *frame = ((zend_generator *) object)->execute_data;
	if (!frame) {
		return NULL;
	}
	zend_object *self = Z_TYPE(frame->This) == IS_OBJECT ? Z_OBJ(frame->This) : NULL;
	const sharp_type *result =
		sharp_carried_result(sharp_function_carried(frame->func, self, sharp_frame_method_type_arguments(frame)));
	const sharp_type *iterable = result ? sharp_type_iterable_member(result) : NULL;

	return iterable ? iterable->members[0] : NULL;
}

/* Whether `value` is a closure whose function's type can be assigned to the function type `type`, spec section 21, as
 * sharp_type_function_fits decides. A closure with no type sharp_closure_type finds has no type to compare, so `mode`
 * decides. */
static bool sharp_closure_is(const zval *value, const sharp_type *type, sharp_holds_mode mode)
{
	if (Z_TYPE_P(value) != IS_OBJECT || Z_OBJCE_P(value) != zend_ce_closure) {
		return false;
	}
	const sharp_type *own = sharp_closure_type(value);
	if (!own) {
		return !EG(exception) && mode != SHARP_HOLDS_TEST;
	}

	const zend_function *func = zend_get_closure_method_def(Z_OBJ_P(value));
	zend_string *spelled = own->text;
	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(spelled), ZSTR_LEN(spelled), zend_arena_create(1024));
	sharp_type_node *given = sharp_type_read(&reader);
	sharp_type_reader tested = sharp_type_reader_of(ZSTR_VAL(type->text), ZSTR_LEN(type->text), reader.arena);
	sharp_type_node *wanted = sharp_type_read(&tested);
	reader.arena = tested.arena;
	ZEND_ASSERT(given && given->kind == SHARP_TYPE_FUNCTION && wanted);
	bool is = sharp_type_function_fits(given, wanted, func->common.required_num_args, &reader);
	zend_arena_destroy(reader.arena);

	return is;
}

/* Whether every value of `given` is a value of `wanted`, as sharp_type_fits decides, NULL standing for `Any?`, the type
 * of what a plain PHP source gives. */
static bool sharp_type_within(const sharp_type *given, const sharp_type *wanted)
{
	const char *text = given ? ZSTR_VAL(given->text) : "Any?";
	size_t length = given ? ZSTR_LEN(given->text) : sizeof("Any?") - 1;
	if (zend_string_equals_cstr(wanted->text, text, length)) {
		return true;
	}

	sharp_type_reader reader = sharp_type_reader_of(text, length, zend_arena_create(1024));
	sharp_type_node *node = sharp_type_read(&reader);
	sharp_type_reader tested = sharp_type_reader_of(ZSTR_VAL(wanted->text), ZSTR_LEN(wanted->text), reader.arena);
	sharp_type_node *bound = sharp_type_read(&tested);
	reader.arena = tested.arena;
	bool within = node && bound && sharp_type_fits(node, bound, &reader);
	zend_arena_destroy(reader.arena);

	return within;
}

/* Whether the Traversable `object`, held as `iterable`, gives only elements of its element type, read from the type the
 * object carries and none of its elements, spec section 12: a PHP# generator the element type of the `Iterable<T>` its
 * method returns, a check stage the type it checks each value against, a map stage the return type of its PHP#
 * function, and every other stage the type of the source it reads. A plain PHP Traversable carries none, so it is only
 * an `Iterable<Any?>`. */
static bool sharp_iterable_object_holds(const sharp_type *iterable, zend_object *object)
{
	const sharp_type *element = iterable->members[0];
	if (object->ce == zend_ce_generator) {
		const sharp_type *yields = sharp_generator_yields(object);

		return !EG(exception) && sharp_type_within(yields, element);
	}
	if (object->ce != sharp_ce_sequence) {
		return sharp_type_within(NULL, element);
	}

	const sharp_sequence *sequence = sharp_sequence_from(object);
	switch (sequence->stage) {
		case SHARP_SEQUENCE_CHECK:
			return sharp_type_within(sequence->checked, element);
		case SHARP_SEQUENCE_MAP: {
			const sharp_type *result = sharp_carried_result(sharp_closure_carried(&sequence->fci.function_name));

			return !EG(exception) && sharp_type_within(result, element);
		}
		default:
			return sharp_type_holds(iterable, &sequence->source, SHARP_HOLDS_TEST, NULL);
	}
}

/* The `Iterable<T>` that `type` is: `type` itself, the member of `T?`, or the first member of a union that is one. NULL
 * for any other type. */
static const sharp_type *sharp_type_iterable_member(const sharp_type *type)
{
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return type->built_in == SHARP_BUILT_IN_ITERABLE ? type : NULL;
		case SHARP_TYPE_NULLABLE:
			return sharp_type_iterable_member(type->members[0]);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				const sharp_type *member = sharp_type_iterable_member(type->members[i]);
				if (member) {
					return member;
				}
			}
			return NULL;
		default:
			return NULL;
	}
}

/* The `Iterable<T>` a crossing checks `value`, a value sharp_type_accepts took for `type`, against as it is read, decision
 * 7 of the collections Slice: a Traversable object other than a Sharp\Set, which is checked whole, that carries no type
 * within `type`. A Sharp\Map is checked whole too, and an `Iterable<T>` holds its entries, so it is read through one as
 * well. NULL for any other value. */
static const sharp_type *sharp_type_lazily_checked(const sharp_type *type, const zval *value)
{
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) == IS_OBJECT && Z_OBJCE_P(value) == sharp_ce_map) {
		return sharp_type_iterable_member(type);
	}
	if (Z_TYPE_P(value) != IS_OBJECT || Z_OBJCE_P(value) == sharp_ce_set
		|| !instanceof_function(Z_OBJCE_P(value), zend_ce_traversable)
		|| sharp_type_holds(type, value, SHARP_HOLDS_TEST, NULL) || EG(exception)) {
		return NULL;
	}

	return sharp_type_iterable_member(type);
}

/* Puts in place of `value` a check stage over it, which checks each element against `iterable`'s element type as a
 * loop reads it, and takes `head` for the start of the TypeError a bad one throws. A Sharp\Map, checked whole, takes a
 * from stage, which reads its entries. */
static void sharp_sequence_check(zval *value, const sharp_type *iterable, zend_string *head)
{
	ZVAL_DEREF(value);
	zval checked;
	if (Z_TYPE_P(value) == IS_OBJECT && Z_OBJCE_P(value) == sharp_ce_map) {
		sharp_sequence_new(&checked, SHARP_SEQUENCE_FROM, value);
		zend_string_release(head);
		zval_ptr_dtor(value);
		ZVAL_COPY_VALUE(value, &checked);
		return;
	}
	sharp_sequence *sequence = sharp_sequence_new(&checked, SHARP_SEQUENCE_CHECK, value);
	sequence->checked = iterable->members[0];
	sequence->head = head;
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &checked);
}

/* Throws the TypeError for `element`, the value at `index` of a check stage's source that is not of its type: the
 * stage's head, then the first bad value the walk finds at its path, as `[3] expected App.Order, got string 'x'`. */
static ZEND_COLD void sharp_sequence_refuse(const sharp_sequence *sequence, const zval *element, zend_long index)
{
	zval problems;
	array_init(&problems);
	sharp_type_problems found = {&problems, {0}};
	smart_str_appendc(&found.path, '[');
	smart_str_append_long(&found.path, index);
	smart_str_appendc(&found.path, ']');
	sharp_type_holds(sequence->checked, element, SHARP_HOLDS_ENTRY_INT_AS_FLOAT, &found);
	smart_str_free(&found.path);

	zend_string *path;
	zval *problem;
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL(problems), path, problem) {
		if (!EG(exception)) {
			zend_type_error("%s%s %s", ZSTR_VAL(sequence->head), path ? ZSTR_VAL(path) : "", Z_STRVAL_P(problem));
		}
		break;
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&problems);
	if (!EG(exception)) {
		zend_type_error("%s[" ZEND_LONG_FMT "] is not of type %s", ZSTR_VAL(sequence->head), index,
			ZSTR_VAL(sequence->checked->text));
	}
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

/* The native body of `Sharp.Environment.commandLine`: the command-line arguments as PHP's `$argv` holds them, starting
 * with the script's name. Only the SAPI holds them, so this body is C behind the prototype sharp/bin/native writes. */
void sharp_native_Environment_commandLine(zval *result)
{
	ZVAL_COPY(result, &SHARP_G(arguments));
}

/* The environment variable name, from the SAPI first as getenv() reads it, or NULL when it is not set. */
static zend_string *sharp_environment_variable(const char *name, size_t name_length)
{
	char *sapi_value = sapi_getenv(name, name_length);
	if (sapi_value) {
		zend_string *value = zend_string_init(sapi_value, strlen(sapi_value), 0);
		efree(sapi_value);
		return value;
	}

	return php_getenv(name, name_length);
}

/* Spec section 32: Sharp\Container builds the app's objects from the bindings its modules' files return, as
 * .NET's IServiceProvider does, and resolves them as the checker does. Each binding row is [namespace, service id,
 * class it serves or null, lifetime, value]. PHP# creates only the services a module binds. A class's services
 * resolve from the modules of its namespace, then of each namespace above it, and the standard library's module
 * serves the root namespace. The first module with a binding wins: the module of the test that runs now, or else an
 * environment module, then the base module. In one module a binding for the class wins over one for any class.
 * The container throws its own failures as Sharp\ContainerError, and a constructor's exception unchanged. */

static zend_class_entry *sharp_ce_container_error;

/* The key the bindings of id in the namespace's first length bytes are kept under. */
static zend_string *sharp_container_key(const zend_string *namespace, size_t length, const zend_string *id)
{
	zend_string *key = zend_strpprintf(0, "%.*s %s", (int) length, ZSTR_VAL(namespace), ZSTR_VAL(id));
	zend_str_tolower(ZSTR_VAL(key), ZSTR_LEN(key));

	return key;
}

/* Adds rows, one module's binding rows, to module: under each namespace and service id, a list of its rows. */
static void sharp_container_group(HashTable *rows, HashTable *module)
{
	zval *row;
	ZEND_HASH_FOREACH_VAL(rows, row) {
		zend_string *namespace = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 0));
		zend_string *key = sharp_container_key(namespace, ZSTR_LEN(namespace), Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 1)));
		zval *list = zend_hash_find(module, key);
		if (!list) {
			zval empty;
			array_init(&empty);
			list = zend_hash_add_new(module, key, &empty);
		}
		Z_TRY_ADDREF_P(row);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(list), row);
		zend_string_release(key);
	} ZEND_HASH_FOREACH_END();
}

/* Runs a module file's op_array for the rows it returns, as zend_execute does, but in a symbol table of its own.
 * zend_execute would give the PHP code that called the engine a symbol table, which a tracing-JIT frame never frees. */
static void sharp_container_execute(zend_op_array *op_array, zval *rows)
{
	HashTable symbols;
	zend_execute_data *execute_data;

	zend_hash_init(&symbols, 8, NULL, ZVAL_PTR_DTOR, false);
	execute_data = zend_vm_stack_push_call_frame(ZEND_CALL_TOP_CODE | ZEND_CALL_HAS_SYMBOL_TABLE,
		(zend_function *) op_array, 0, NULL);
	execute_data->symbol_table = &symbols;
	zend_init_code_execute_data(execute_data, op_array, rows);
	ZEND_OBSERVER_FCALL_BEGIN(execute_data);
	zend_execute_ex(execute_data);
	zend_vm_stack_free_call_frame(execute_data);
	zend_hash_destroy(&symbols);
}

/* Adds the module whose file is at path, if one is, to bindings once: under each namespace and service id, a list of
 * its rows after those of the modules read before it. A module that throws as it loads is read again next time. */
static void sharp_container_read(const char *path, HashTable *bindings)
{
	size_t length = strlen(path);
	if (zend_hash_str_exists(&SHARP_G(container_modules), path, length)) {
		return;
	}

	zval rows;
	ZVAL_UNDEF(&rows);
	zend_stat_t status = {0};
	if (VCWD_STAT(path, &status) == 0) {
		zend_file_handle handle;
		zend_stream_init_filename(&handle, path);
		zend_op_array *op_array = zend_compile_file(&handle, ZEND_REQUIRE);
		zend_destroy_file_handle(&handle);
		if (op_array && !EG(exception)) {
			sharp_container_execute(op_array, &rows);
		}
		if (op_array) {
			zend_destroy_static_vars(op_array);
			destroy_op_array(op_array);
			efree_size(op_array, sizeof(zend_op_array));
		}
	}
	if (!EG(exception)) {
		zend_hash_str_add_empty_element(&SHARP_G(container_modules), path, length);
	}
	if (EG(exception) || Z_TYPE(rows) != IS_ARRAY) {
		zval_ptr_dtor(&rows);
		return;
	}

	HashTable module;
	zend_hash_init(&module, 8, NULL, ZVAL_PTR_DTOR, false);
	sharp_container_group(Z_ARRVAL(rows), &module);
	zval_ptr_dtor(&rows);

	zend_string *key;
	zval *list;
	ZEND_HASH_FOREACH_STR_KEY_VAL(&module, key, list) {
		zval *modules = zend_hash_find(bindings, key);
		if (!modules) {
			zval empty;
			array_init(&empty);
			modules = zend_hash_add_new(bindings, key, &empty);
		}
		Z_ADDREF_P(list);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(modules), list);
	} ZEND_HASH_FOREACH_END();
	zend_hash_destroy(&module);
}

/* Reads the modules of file's folder and of each folder above it up to its project's root: a folder's
 * Module.<SHARP_ENV>.sharp among the environment modules, and its Module.sharp among the base modules. It stops at
 * the first module that throws. */
static void sharp_container_read_folders(const zend_string *file)
{
	size_t root;
	if (!sharp_find_root(file, &root)) {
		return;
	}

	zend_string *environment = sharp_environment_variable("SHARP_ENV", sizeof("SHARP_ENV") - 1);
	const char *path = ZSTR_VAL(file);
	for (size_t folder = sharp_parent(path, ZSTR_LEN(file)); folder >= root && !EG(exception);
			folder = sharp_parent(path, folder)) {
		char module[MAXPATHLEN];
		if (environment && ZSTR_LEN(environment) > 0) {
			snprintf(module, sizeof(module), "%.*s/Module.%s.sharp", (int) folder, path, ZSTR_VAL(environment));
			sharp_container_read(module, &SHARP_G(container_environment));
		}
		snprintf(module, sizeof(module), "%.*s/Module.sharp", (int) folder, path);
		if (!EG(exception)) {
			sharp_container_read(module, &SHARP_G(container_bindings));
		}
		if (folder == root) {
			break;
		}
	}

	if (environment) {
		zend_string_release(environment);
	}
}

/* The namespace of the class or service named name. */
static zend_string *sharp_container_namespace(const zend_string *name)
{
	const char *end = zend_memrchr(ZSTR_VAL(name), '\\', ZSTR_LEN(name));

	return end ? zend_string_init(ZSTR_VAL(name), end - ZSTR_VAL(name), 0) : ZSTR_EMPTY_ALLOC();
}

/* The row of list, one module's rows of a service, that serves requester or NULL: a row for the class before a row
 * for any class. */
static zval *sharp_container_row(zval *list, const zend_class_entry *requester)
{
	zval *row, *any = NULL;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(list), row) {
		zval *target = zend_hash_index_find(Z_ARRVAL_P(row), 2);
		if (Z_TYPE_P(target) == IS_NULL) {
			any = any ? any : row;
		} else if (requester && zend_string_equals_ci(Z_STR_P(target), requester->name)) {
			return row;
		}
	} ZEND_HASH_FOREACH_END();

	return any;
}

/* The row of the first of modules, a list of modules' rows of a service or NULL, that serves requester, or NULL. */
static zval *sharp_container_first_row(zval *modules, const zend_class_entry *requester)
{
	zval *list, *row;
	if (modules) {
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(modules), list) {
			row = sharp_container_row(list, requester);
			if (row) {
				return row;
			}
		} ZEND_HASH_FOREACH_END();
	}

	return NULL;
}

/* The binding row that serves id to requester, a class of namespace or NULL, or NULL when no module binds it. While a
 * test runs, its module replaces the environment modules, as the checker checks a test module over the base modules
 * alone. */
static zval *sharp_container_binding(const zend_string *id, const zend_string *namespace, const zend_class_entry *requester)
{
	size_t length = ZSTR_LEN(namespace);

	while (true) {
		zend_string *key = sharp_container_key(namespace, length, id);
		zval *test = SHARP_G(container_test) ? zend_hash_find(SHARP_G(container_test), key) : NULL;
		zval *environment = SHARP_G(container_test) ? NULL : zend_hash_find(&SHARP_G(container_environment), key);
		zval *modules = zend_hash_find(&SHARP_G(container_bindings), key);
		zend_string_release(key);
		zval *row = test ? sharp_container_row(test, requester) : NULL;
		if (!row) {
			row = sharp_container_first_row(environment, requester);
		}
		if (!row) {
			row = sharp_container_first_row(modules, requester);
		}
		if (row) {
			return row;
		}

		if (length == 0) {
			return NULL;
		}
		const char *end = zend_memrchr(ZSTR_VAL(namespace), '\\', length);
		length = end ? (size_t) (end - ZSTR_VAL(namespace)) : 0;
	}
}

static bool sharp_container_service(zend_string *id, const zend_string *namespace, const zend_class_entry *requester, zval *service);

/* Fills arguments with the service of each parameter of function: the id its Sharp\Internal\Service attribute
 * names, or its class. A parameter with neither ends the arguments when it is optional. On failure arguments is
 * undefined. */
static bool sharp_container_arguments(const zend_function *function, const zend_string *namespace, const zend_class_entry *requester, zval *arguments)
{
	array_init(arguments);
	for (uint32_t i = 0; i < function->common.num_args; i++) {
		zend_arg_info *argument = &function->common.arg_info[i];
		zend_string *id = NULL;
		zend_attribute *attribute = function->common.attributes
			? zend_get_parameter_attribute_str(function->common.attributes, "sharp\\internal\\service", sizeof("sharp\\internal\\service") - 1, i)
			: NULL;
		zval named;
		if (attribute && zend_get_attribute_value(&named, attribute, 0, function->common.scope) == SUCCESS) {
			id = Z_STR(named);
		} else if (ZEND_TYPE_HAS_NAME(argument->type)) {
			id = zend_string_copy(ZEND_TYPE_NAME(argument->type));
		} else if (i >= function->common.required_num_args) {
			break;
		} else {
			zend_throw_error(sharp_ce_container_error, "The container can't fill parameter $%s of %s: give it a class type",
				ZSTR_VAL(argument->name), requester ? ZSTR_VAL(requester->name) : "a binding");
			zval_ptr_dtor(arguments);
			ZVAL_UNDEF(arguments);
			return false;
		}

		zval service;
		bool built = sharp_container_service(id, namespace, requester, &service);
		zend_string_release(id);
		if (!built) {
			zval_ptr_dtor(arguments);
			ZVAL_UNDEF(arguments);
			return false;
		}
		zend_hash_next_index_insert_new(Z_ARRVAL_P(arguments), &service);
	}

	return true;
}

/* Builds ce through its constructor, each parameter filled from the modules of its namespace. */
static bool sharp_container_build(zend_class_entry *ce, zval *object)
{
	if (!zend_hash_index_add_empty_element(&SHARP_G(container_building), (zend_ulong) (uintptr_t) ce)) {
		zend_throw_error(sharp_ce_container_error, "%s needs itself, so the container can't build it", ZSTR_VAL(ce->name));
		return false;
	}
	if (ce->type == ZEND_USER_CLASS) {
		sharp_container_read_folders(ce->info.user.filename);
	}

	zval arguments;
	ZVAL_UNDEF(&arguments);
	bool built = true;
	if (ce->constructor) {
		zend_string *namespace = sharp_container_namespace(ce->name);
		built = sharp_container_arguments(ce->constructor, namespace, ce, &arguments);
		zend_string_release(namespace);
	}
	if (built && object_init_ex(object, ce) == SUCCESS) {
		if (ce->constructor) {
			zend_call_known_instance_method(ce->constructor, Z_OBJ_P(object), NULL,
				zend_hash_num_elements(Z_ARRVAL(arguments)), Z_ARRVAL(arguments)->arPacked);
		}
		if (EG(exception)) {
			zval_ptr_dtor(object);
			ZVAL_NULL(object);
			built = false;
		}
	} else {
		built = false;
	}

	zval_ptr_dtor(&arguments);
	zend_hash_index_del(&SHARP_G(container_building), (zend_ulong) (uintptr_t) ce);

	return built;
}

/* Builds the class named name. */
static bool sharp_container_build_named(zend_string *name, zval *object)
{
	zend_class_entry *ce = zend_lookup_class(name);
	if (!ce) {
		if (!EG(exception)) {
			zend_throw_error(sharp_ce_container_error, "Class \"%s\" not found", ZSTR_VAL(name));
		}
		return false;
	}

	return sharp_container_build(ce, object);
}

/* The service row binds: a class, a list of classes, or the result of its closure over its parameters, which
 * resolve from the row's namespace. A singleton is kept for the request and a scoped service for the scope. */
static bool sharp_container_bound(zval *row, zval *service)
{
	zend_string *namespace = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 0));
	zend_string *lifetime = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 3));
	zval *value = zend_hash_index_find(Z_ARRVAL_P(row), 4);
	zend_ulong key = (zend_ulong) (uintptr_t) Z_ARRVAL_P(row);
	HashTable *kept = zend_string_equals_literal(lifetime, "singleton") ? &SHARP_G(container_singletons)
		: zend_string_equals_literal(lifetime, "scoped") ? SHARP_G(container_scope)
		: NULL;
	zval *found = kept ? zend_hash_index_find(kept, key) : NULL;
	if (found) {
		ZVAL_COPY(service, found);
		return true;
	}

	if (!zend_hash_index_add_empty_element(&SHARP_G(container_building), key)) {
		/* A class the row binds is named as it is written, and any other service by its id. */
		zend_throw_error(sharp_ce_container_error, "%s needs itself, so the container can't build it",
			Z_STRVAL_P(Z_TYPE_P(value) == IS_STRING ? value : zend_hash_index_find(Z_ARRVAL_P(row), 1)));
		return false;
	}

	bool built = true;
	if (Z_TYPE_P(value) == IS_STRING) {
		built = sharp_container_build_named(Z_STR_P(value), service);
	} else if (Z_TYPE_P(value) == IS_ARRAY) {
		array_init(service);
		zval *class;
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), class) {
			zval object;
			if (!sharp_container_build_named(Z_STR_P(class), &object)) {
				built = false;
				break;
			}
			zend_hash_next_index_insert_new(Z_ARRVAL_P(service), &object);
		} ZEND_HASH_FOREACH_END();
		if (!built) {
			zval_ptr_dtor(service);
			ZVAL_NULL(service);
		}
	} else {
		zval arguments;
		built = sharp_container_arguments(zend_get_closure_method_def(Z_OBJ_P(value)), namespace, NULL, &arguments);
		if (built) {
			built = call_user_function(NULL, NULL, value, service, zend_hash_num_elements(Z_ARRVAL(arguments)),
				Z_ARRVAL(arguments)->arPacked) == SUCCESS && !EG(exception);
			zval_ptr_dtor(&arguments);
			if (!built) {
				zval_ptr_dtor(service);
				ZVAL_NULL(service);
			}
		}
	}

	zend_hash_index_del(&SHARP_G(container_building), key);
	if (built && kept) {
		Z_TRY_ADDREF_P(service);
		zend_hash_index_add_new(kept, key, service);
	}

	return built;
}

/* The service id names for requester, a class of namespace or NULL. */
static bool sharp_container_service(zend_string *id, const zend_string *namespace, const zend_class_entry *requester, zval *service)
{
	zval *row = sharp_container_binding(id, namespace, requester);
	if (row) {
		return sharp_container_bound(row, service);
	}

	if (!EG(exception)) {
		zend_throw_error(sharp_ce_container_error, "No module binds %s%s%s", ZSTR_VAL(id), requester ? " for " : "",
			requester ? ZSTR_VAL(requester->name) : "");
	}

	return false;
}

/* The id of the service named name, and its namespace, after reading the modules of its class's folders. */
static zend_string *sharp_container_requested(zend_string *name, zend_string **namespace)
{
	zend_string *id = ZSTR_LEN(name) > 0 && ZSTR_VAL(name)[0] == '\\'
		? zend_string_init(ZSTR_VAL(name) + 1, ZSTR_LEN(name) - 1, 0)
		: zend_string_copy(name);
	zend_class_entry *ce = memchr(ZSTR_VAL(id), '<', ZSTR_LEN(id)) ? NULL : zend_lookup_class(id);
	if (ce && ce->type == ZEND_USER_CLASS) {
		sharp_container_read_folders(ce->info.user.filename);
	}
	*namespace = sharp_container_namespace(id);

	return id;
}

ZEND_METHOD(Sharp_Container, get)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *namespace;
	zend_string *id = sharp_container_requested(name, &namespace);
	sharp_container_service(id, namespace, NULL, return_value);
	zend_string_release(namespace);
	zend_string_release(id);
}

ZEND_METHOD(Sharp_Container, has)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *namespace;
	zend_string *id = sharp_container_requested(name, &namespace);
	bool has = sharp_container_binding(id, namespace, NULL) != NULL;
	zend_string_release(namespace);
	zend_string_release(id);

	RETURN_BOOL(has);
}

/* Spec section 32: calls run in a new scope, and releases the scoped services it built when run returns. */
ZEND_METHOD(Sharp_Container, scope)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	HashTable *outer = SHARP_G(container_scope);
	HashTable scope;
	zend_hash_init(&scope, 8, NULL, ZVAL_PTR_DTOR, 0);
	SHARP_G(container_scope) = &scope;

	fci.retval = return_value;
	zend_call_function(&fci, &fcc);

	SHARP_G(container_scope) = outer;
	zend_hash_destroy(&scope);
}

/* Spec section 32: the standard library's autoloader adds the library's module, whose file is at path, as the
 * container adds the module of a folder. */
ZEND_FUNCTION(Sharp_Internal_readModule)
{
	zend_string *path;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_PATH_STR(path)
	ZEND_PARSE_PARAMETERS_END();

	zend_stat_t status = {0};
	if (VCWD_STAT(ZSTR_VAL(path), &status) != 0) {
		zend_throw_error(NULL, "Sharp\\Internal\\readModule: no module file at %s", ZSTR_VAL(path));
		RETURN_THROWS();
	}
	sharp_container_read(ZSTR_VAL(path), &SHARP_G(container_bindings));
}

/* Spec section 32: a test file's module, the rows bindings holds, applies while a test of a class of classes runs.
 * The compiler calls this from the test file. */
ZEND_FUNCTION(Sharp_Internal_testModule)
{
	HashTable *classes, *bindings;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_HT(classes)
		Z_PARAM_ARRAY_HT(bindings)
	ZEND_PARSE_PARAMETERS_END();

	zval module;
	array_init(&module);
	sharp_container_group(bindings, Z_ARRVAL(module));
	zval *class;
	ZEND_HASH_FOREACH_VAL(classes, class) {
		zend_string *name = zend_string_tolower(Z_STR_P(class));
		Z_ADDREF(module);
		zend_hash_update(&SHARP_G(container_tests), name, &module);
		zend_string_release(name);
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&module);
}

/* Spec section 6.5: a `static lazy` property computes its value on its first read and keeps it for a singleton's
 * lifetime, section 32, as the container keeps a singleton service, so a test's start and end forget it too. PHP#
 * reads `Class.name` as `Class::{'name#get'}()`, which returns `\Sharp\Internal\keep('Class::name', static fn () =>
 * body)`. The value is kept under the key `key` beside the singleton services. A throw in `compute` keeps nothing, so
 * the next read runs it again. */
ZEND_FUNCTION(Sharp_Internal_keep)
{
	zend_string *key;
	zval *compute;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(key)
		Z_PARAM_OBJECT_OF_CLASS(compute, zend_ce_closure)
	ZEND_PARSE_PARAMETERS_END();

	zval *kept = zend_hash_find(&SHARP_G(container_singletons), key);
	if (kept) {
		RETURN_COPY(kept);
	}

	zval value;
	ZVAL_UNDEF(&value);
	if (call_user_function(NULL, NULL, compute, &value, 0, NULL) != SUCCESS || EG(exception)) {
		zval_ptr_dtor(&value);
		RETURN_THROWS();
	}

	/* A body that read the property again kept a value already, and the outer read keeps its own. */
	Z_TRY_ADDREF(value);
	zend_hash_update(&SHARP_G(container_singletons), key, &value);
	RETURN_COPY_VALUE(&value);
}

/* The container forgets every service it kept, so the next test builds its own. */
static void sharp_container_forget(void)
{
	zend_hash_clean(&SHARP_G(container_singletons));
	zend_hash_clean(&SHARP_G(container_request_scope));
}

/* Spec section 32: a test of class starts, so the container uses the module of class's test file before every
 * other module, and builds every service again. The method the compiler gives each class of a test file, which
 * PHPUnit runs before each of its tests, calls this. */
ZEND_FUNCTION(Sharp_Internal_startTest)
{
	zend_string *class;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(class)
	ZEND_PARSE_PARAMETERS_END();

	sharp_container_forget();
	zend_string *name = zend_string_tolower(class);
	zval *module = zend_hash_find(&SHARP_G(container_tests), name);
	zend_string_release(name);
	SHARP_G(container_test) = module ? Z_ARRVAL_P(module) : NULL;
}

/* Spec section 32: the test that runs ends, so the container forgets its module and the services it built. */
ZEND_FUNCTION(Sharp_Internal_endTest)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_container_forget();
	SHARP_G(container_test) = NULL;
}

/* Spec section 6.2: a property's observers run only once its object is fully built. The object is still being built
 * while one of its constructors runs, a parent's included, and plain PHP's `new` runs them too. It is also being built
 * while it carries IS_OBJ_SHARP_BUILDING: while Sharp\Internal\build runs a named constructor's body or an object
 * initializer on it, and while clone() writes a `with` copy's new values. A method a constructor calls runs before the
 * constructor returns, so the object is still being built. */
ZEND_FUNCTION(Sharp_Internal_building)
{
	zend_object *object;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJ(object)
	ZEND_PARSE_PARAMETERS_END();

	if (OBJ_EXTRA_FLAGS(object) & IS_OBJ_SHARP_BUILDING) {
		RETURN_TRUE;
	}
	for (const zend_execute_data *frame = EX(prev_execute_data); frame; frame = frame->prev_execute_data) {
		if (frame->func
		 && (frame->func->common.fn_flags & ZEND_ACC_CTOR)
		 && (ZEND_CALL_INFO(frame) & ZEND_CALL_HAS_THIS)
		 && Z_OBJ(frame->This) == object) {
			RETURN_TRUE;
		}
	}

	RETURN_FALSE;
}

/* Spec sections 6.2, 9.1 and 16: runs `body` on `object` as Closure::call does, with the arguments after it, while
 * the object carries IS_OBJ_SHARP_BUILDING, so the writes of a named constructor's body or an object initializer run
 * no observer. It puts back the mark it found, whether the body returns or throws, so an initializer inside a named
 * constructor leaves the outer object marked until its own body ends. */
ZEND_FUNCTION(Sharp_Internal_build)
{
	zend_object *object;
	zend_object *body;
	zval *arguments = NULL;
	uint32_t count = 0;

	ZEND_PARSE_PARAMETERS_START(2, -1)
		Z_PARAM_OBJ(object)
		Z_PARAM_OBJ_OF_CLASS(body, zend_ce_closure)
		Z_PARAM_VARIADIC('*', arguments, count)
	ZEND_PARSE_PARAMETERS_END();

	zend_function *call = zend_hash_str_find_ptr(&zend_ce_closure->function_table, ZEND_STRL("call"));
	zval *parameters = safe_emalloc(count + 1, sizeof(zval), 0);
	ZVAL_OBJ(&parameters[0], object);
	memcpy(&parameters[1], arguments, count * sizeof(zval));

	uint32_t found = OBJ_EXTRA_FLAGS(object) & IS_OBJ_SHARP_BUILDING;
	OBJ_EXTRA_FLAGS(object) |= IS_OBJ_SHARP_BUILDING;
	zend_call_known_function(call, body, zend_ce_closure, return_value, count + 1, parameters, NULL);
	OBJ_EXTRA_FLAGS(object) = (OBJ_EXTRA_FLAGS(object) & ~IS_OBJ_SHARP_BUILDING) | found;
	efree(parameters);
}

/* Spec section 15: emit hands an event, and one function per listener of it, to the app's dispatcher. mago compile
 * lists every listener an `on` clause declares in the listener table, and Sharp\Internal\listen starts one for as long
 * as its handle lives. Each function takes the event, as PSR-14 calls a listener, and creates its listener's object
 * through the container when it runs, so every event gets a new one. */

static zend_class_entry *sharp_ce_listener;
static zend_class_entry *sharp_ce_listening;
static zend_object_handlers sharp_listener_handlers;
static zend_object_handlers sharp_listening_handlers;
/* Sharp\Internal\Listener::hear, the function each listener's closure calls. */
static zend_function *sharp_listener_hear;

/* Returns the element of a list at the index when it has the given type, or NULL. */
static zval *sharp_element(const zval *list, zend_ulong index, uint8_t type)
{
	zval *element = Z_TYPE_P(list) == IS_ARRAY ? zend_hash_index_find(Z_ARRVAL_P(list), index) : NULL;

	return element && Z_TYPE_P(element) == type ? element : NULL;
}

/* mago compile writes each listener as [class, method, [listed type, ...], whether the method takes the event]. */
static bool sharp_listener_fits(const zval *row)
{
	const zval *types = sharp_element(row, 2, IS_ARRAY), *type;
	const zval *takes_event = types ? zend_hash_index_find(Z_ARRVAL_P(row), 3) : NULL;

	if (!sharp_element(row, 0, IS_STRING) || !sharp_element(row, 1, IS_STRING)
			|| !takes_event || (Z_TYPE_P(takes_event) != IS_TRUE && Z_TYPE_P(takes_event) != IS_FALSE)) {
		return false;
	}
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types), type) {
		if (Z_TYPE_P(type) != IS_STRING) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* mago compile writes each input as [workspace-relative path, size, modification time, hash in 32 hex digits]. */
static bool sharp_listener_input_fits(const zval *input)
{
	const zval *hash = sharp_element(input, 3, IS_STRING);

	return sharp_element(input, 0, IS_STRING) && sharp_element(input, 1, IS_LONG) && sharp_element(input, 2, IS_LONG)
		&& hash && Z_STRLEN_P(hash) == 32 && strspn(Z_STRVAL_P(hash), "0123456789abcdef") == 32;
}

/* Whether table is a table mago compile wrote: ['inputs' => [input, ...], 'listeners' => [row, ...], 'dispatcher' =>
 * null or [the workspace-relative path of the app's module that binds the dispatcher, its namespace in lowercase]]. */
static bool sharp_listener_table_fits(const zval *table)
{
	const zval *inputs = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "inputs", sizeof("inputs") - 1) : NULL;
	const zval *listeners = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "listeners", sizeof("listeners") - 1) : NULL;
	const zval *dispatcher = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "dispatcher", sizeof("dispatcher") - 1) : NULL;
	const zval *element;

	if (!inputs || Z_TYPE_P(inputs) != IS_ARRAY || !listeners || Z_TYPE_P(listeners) != IS_ARRAY || !dispatcher
			|| (Z_TYPE_P(dispatcher) != IS_NULL && (!sharp_element(dispatcher, 0, IS_STRING) || !sharp_element(dispatcher, 1, IS_STRING)))) {
		return false;
	}
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(inputs), element) {
		if (!sharp_listener_input_fits(element)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(listeners), element) {
		if (!sharp_listener_fits(element)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* Whether every input of a fitting table, under the root of the first root_length bytes of path, still has the stamp
 * mago compile gave it. */
static bool sharp_listener_inputs_current(const char *path, size_t root_length, const zval *table)
{
	const zval *input;

	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(zend_hash_str_find(Z_ARRVAL_P(table), "inputs", sizeof("inputs") - 1)), input) {
		const zval *name = sharp_element(input, 0, IS_STRING);
		const char *digits = Z_STRVAL_P(sharp_element(input, 3, IS_STRING));
		zend_string *input_path = zend_string_concat3(path, root_length, "/", 1, Z_STRVAL_P(name), Z_STRLEN_P(name));
		uint8_t hash[16];
		bool current;

		for (size_t i = 0; i < sizeof(hash); i++) {
			char high = digits[2 * i], low = digits[2 * i + 1];

			hash[i] = (uint8_t) (((high <= '9' ? high - '0' : high - 'a' + 10) << 4) | (low <= '9' ? low - '0' : low - 'a' + 10));
		}
		current = sharp_stamp_current(input_path, (uint64_t) Z_LVAL_P(sharp_element(input, 1, IS_LONG)),
			(int64_t) Z_LVAL_P(sharp_element(input, 2, IS_LONG)), hash);
		zend_string_release(input_path);
		if (!current) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* Reads the table at path as the constant the file returns, without running the file: code run from here would give
 * the caller a symbol table its JIT-compiled frame never frees. Leaves table undefined when no file is there, and
 * anything but an array when the file returns no constant. past_opcache reads the file as it is now, where opcache
 * may keep a table the compile command just replaced. */
static void sharp_read_listener_table(zend_string *path, bool past_opcache, zval *table)
{
	zend_stat_t status;
	zend_file_handle handle;
	zend_op_array *op_array;

	ZVAL_UNDEF(table);
	/* Not sharp_stamp_of, whose stamp the request keeps: the compile command may write the table after it. */
	if (VCWD_STAT(ZSTR_VAL(path), &status) != 0) {
		return;
	}
	ZVAL_NULL(table);
	zend_stream_init_filename_ex(&handle, path);
	op_array = (past_opcache ? sharp_next_compile_file : zend_compile_file)(&handle, ZEND_REQUIRE);
	zend_destroy_file_handle(&handle);
	if (op_array) {
		const zend_op *opline = op_array->opcodes;

		while (opline->opcode == ZEND_EXT_STMT || opline->opcode == ZEND_EXT_NOP || opline->opcode == ZEND_NOP) {
			opline++;
		}
		if (opline->opcode == ZEND_RETURN && opline->op1_type == IS_CONST) {
			ZVAL_COPY(table, RT_CONSTANT(opline, opline->op1));
		}
		destroy_op_array(op_array);
		efree_size(op_array, sizeof(zend_op_array));
	}
}

/* The listener table in the .sharp folder at the root, the first root_length bytes of path, read once per request
 * through zend_compile_file, so opcache serves it. A missing table lists no listener and no dispatcher. With
 * sharp.compile_command set, the table is read past opcache, since this request or an earlier one may have rewritten
 * it, and a table whose inputs changed since mago compile wrote it makes the request run the command, as an
 * out-of-date compiled file does, and the table it writes is read instead. Throws and returns NULL when the table is
 * damaged. */
static HashTable *sharp_listener_table(const char *path, size_t root_length)
{
	zend_string *table_path = zend_string_concat3(path, root_length, "/", 1, SHARP_LISTENER_TABLE, sizeof(SHARP_LISTENER_TABLE) - 1);
	zval *known = zend_hash_find(&SHARP_G(listener_tables), table_path);
	bool developing = SHARP_G(compile_command) && *SHARP_G(compile_command);
	zval table;

	if (known) {
		zend_string_release(table_path);
		return Z_ARRVAL_P(known);
	}
	sharp_read_listener_table(table_path, developing, &table);
	if (!Z_ISUNDEF(table) && sharp_listener_table_fits(&table) && !EG(exception) && developing
			&& Z_ISUNDEF(SHARP_G(compile_command_report)) && !sharp_listener_inputs_current(path, root_length, &table)) {
		zval_ptr_dtor(&table);
		sharp_run_compile_command(path, root_length);
		sharp_read_listener_table(table_path, true, &table);
	}
	if (Z_ISUNDEF(table)) {
		zval none;

		ZVAL_EMPTY_ARRAY(&none);
		array_init(&table);
		add_assoc_zval(&table, "listeners", &none);
		add_assoc_null(&table, "dispatcher");
	} else if (EG(exception) || !sharp_listener_table_fits(&table)) {
		zval_ptr_dtor(&table);
		zend_string_release(table_path);
		if (!EG(exception)) {
			zend_throw_exception(zend_ce_compile_error, SHARP_LISTENER_TABLE " is damaged. Run vendor/bin/mago compile.", 0);
		}
		return NULL;
	}

	known = zend_hash_add_new(&SHARP_G(listener_tables), table_path, &table);
	zend_string_release(table_path);

	return Z_ARRVAL_P(known);
}

/* Whether a listener of the type named type hears an event of the class event: the class itself, or a class or
 * interface it extends. A class the event extends is loaded with it, so a type that isn't loaded is none of them. */
static bool sharp_hears(zend_class_entry *event, zend_string *type)
{
	zend_class_entry *listed = zend_lookup_class_ex(type, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);

	return listed && instanceof_function(event, listed);
}

/* The listeners of an event class, in the table's order: each row that lists a type the class is. The table is the one
 * in the .sharp folder above the file that declares the class, so an extern event in vendor/ finds its project's table.
 * The rows are kept for the request, since the table and every class it names are fixed for it. Throws and returns
 * NULL when the table is damaged. */
static HashTable *sharp_listeners_of(zend_class_entry *event)
{
	zend_ulong key = (zend_ulong) (uintptr_t) event;
	zval *known = zend_hash_index_find(&SHARP_G(event_listeners), key);
	zval listeners, *row, *type;
	HashTable *table;
	size_t root_length;

	if (known) {
		return Z_ARRVAL_P(known);
	}
	array_init(&listeners);
	if (event->type == ZEND_USER_CLASS && sharp_find_root(event->info.user.filename, &root_length)) {
		table = sharp_listener_table(ZSTR_VAL(event->info.user.filename), root_length);
		if (!table) {
			zval_ptr_dtor(&listeners);
			return NULL;
		}
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(zend_hash_str_find(table, "listeners", sizeof("listeners") - 1)), row) {
			ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(sharp_element(row, 2, IS_ARRAY)), type) {
				if (sharp_hears(event, Z_STR_P(type))) {
					Z_TRY_ADDREF_P(row);
					zend_hash_next_index_insert_new(Z_ARRVAL(listeners), row);
					break;
				}
			} ZEND_HASH_FOREACH_END();
		} ZEND_HASH_FOREACH_END();
	}

	return Z_ARRVAL_P(zend_hash_index_add_new(&SHARP_G(event_listeners), key, &listeners));
}

/* A Sharp\Listening is the handle of a scoped listener, which stops when the handle is destroyed. It owns the
 * listener's function and reports it to the cycle collector, so a handle kept in a property of an object the function
 * uses is collected with that object, and its listener stops. SHARP_G(scoped_listeners) points at each live handle by
 * its number and holds no reference. */
typedef struct {
	zend_ulong number;
	zend_string *type;
	zval function;
	zend_object std;
} sharp_listening;

static sharp_listening *sharp_listening_from(zend_object *object)
{
	return (sharp_listening *) ((char *) object - XtOffsetOf(sharp_listening, std));
}

/* A Sharp\Internal\Listener runs one listener: a row of the listener table, or the scoped listener of a number, which
 * runs only while its handle lives. */
typedef struct {
	zval row;
	zend_ulong scoped;
	zend_object std;
} sharp_listener;

static sharp_listener *sharp_listener_from(zend_object *object)
{
	return (sharp_listener *) ((char *) object - XtOffsetOf(sharp_listener, std));
}

static zend_object *sharp_listener_create(zend_class_entry *ce)
{
	sharp_listener *listener = zend_object_alloc(sizeof(sharp_listener), ce);

	ZVAL_UNDEF(&listener->row);
	listener->scoped = 0;
	zend_object_std_init(&listener->std, ce);

	return &listener->std;
}

static void sharp_listener_free(zend_object *object)
{
	zval_ptr_dtor(&sharp_listener_from(object)->row);
	zend_object_std_dtor(object);
}

/* Adds to listeners the function that runs a listener on the event it is given, as emit hands it to the dispatcher
 * and PSR-14 calls it: a row of the listener table, or the scoped listener numbered scoped. */
static void sharp_add_listener(zval *listeners, zval *row, zend_ulong scoped)
{
	zval object, function;
	sharp_listener *listener;

	object_init_ex(&object, sharp_ce_listener);
	listener = sharp_listener_from(Z_OBJ(object));
	if (row) {
		ZVAL_COPY(&listener->row, row);
	}
	listener->scoped = scoped;
	zend_create_fake_closure(&function, sharp_listener_hear, sharp_ce_listener, sharp_ce_listener, &object);
	zval_ptr_dtor(&object);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(listeners), &function);
}

/* Fills listeners with one function per listener of the event: the table's, in its order, then the scoped listeners,
 * in the order they started. A listener started or stopped later doesn't change the list, though a stopped one's
 * function runs nothing. Throws and returns false when the table is damaged. */
static bool sharp_listeners_for(zval *event, zval *listeners)
{
	HashTable *rows = sharp_listeners_of(Z_OBJCE_P(event));
	zval *row;
	sharp_listening *listening;
	zend_ulong number;

	if (!rows) {
		return false;
	}
	array_init(listeners);
	ZEND_HASH_FOREACH_VAL(rows, row) {
		sharp_add_listener(listeners, row, 0);
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_NUM_KEY_PTR(&SHARP_G(scoped_listeners), number, listening) {
		if (sharp_hears(Z_OBJCE_P(event), listening->type)) {
			sharp_add_listener(listeners, NULL, number);
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* Throws that the listener table names name, which the code no longer has, as an out-of-date compiled file does. */
static void sharp_listener_table_out_of_date(const zend_string *name)
{
	zend_string *written = zend_string_init(ZSTR_VAL(name), ZSTR_LEN(name), 0);

	for (size_t i = 0; i < ZSTR_LEN(written); i++) {
		if (ZSTR_VAL(written)[i] == '\\') {
			ZSTR_VAL(written)[i] = '.';
		}
	}
	zend_throw_exception_ex(zend_ce_compile_error, 0,
		SHARP_LISTENER_TABLE " is out of date: it lists %s, which no longer exists. Run vendor/bin/mago compile.",
		ZSTR_VAL(written));
	zend_string_release(written);
}

/* Runs a listener on event. A table listener runs on a new object of its class, which the container builds with the
 * services of its namespace's modules, so every event gets a new one whatever lifetime a module gives the class. A
 * scoped listener whose handle is gone runs nothing. */
static void sharp_listener_call(sharp_listener *listener, zval *event)
{
	zend_class_entry *ce;
	zend_string *class, *name;
	zend_function *method;
	zval object;

	if (listener->scoped) {
		sharp_listening *listening = zend_hash_index_find_ptr(&SHARP_G(scoped_listeners), listener->scoped);
		zval function, result;

		if (listening) {
			/* The listener may stop itself as it runs, so the call holds its function. */
			ZVAL_COPY(&function, &listening->function);
			call_user_function(NULL, NULL, &function, &result, 1, event);
			zval_ptr_dtor(&result);
			zval_ptr_dtor(&function);
		}
		return;
	}
	if (Z_TYPE(listener->row) != IS_ARRAY) {
		return;
	}

	class = Z_STR_P(sharp_element(&listener->row, 0, IS_STRING));
	ce = zend_fetch_class_by_name(class, NULL, ZEND_FETCH_CLASS_SILENT);
	if (!ce) {
		if (!EG(exception)) {
			sharp_listener_table_out_of_date(class);
		}
		return;
	}
	name = Z_STR_P(sharp_element(&listener->row, 1, IS_STRING));
	method = zend_hash_find_ptr_lc(&ce->function_table, name);
	if (!method) {
		zend_string *member = zend_string_concat3(ZSTR_VAL(ce->name), ZSTR_LEN(ce->name), "\\", 1, ZSTR_VAL(name), ZSTR_LEN(name));

		sharp_listener_table_out_of_date(member);
		zend_string_release(member);
		return;
	}
	if (!sharp_container_build(ce, &object)) {
		return;
	}
	zend_call_known_instance_method(method, Z_OBJ(object), NULL,
		Z_TYPE_P(zend_hash_index_find(Z_ARRVAL(listener->row), 3)) == IS_TRUE ? 1 : 0, event);
	zval_ptr_dtor(&object);
}

ZEND_METHOD(Sharp_Internal_Listener, hear)
{
	zval *event;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJECT(event)
	ZEND_PARSE_PARAMETERS_END();

	sharp_listener_call(sharp_listener_from(Z_OBJ_P(ZEND_THIS)), event);
}

ZEND_METHOD(Sharp_Internal_Listener, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* The namespace whose binding of the dispatcher serves the whole app, as spec section 15's "An app replaces the
 * dispatcher once at startup" says: the running test module's, whatever namespace its header names; else that of the
 * app's top module that binds it, which mago compile writes in the listener table of the event's project, read here
 * with the modules above it; else the root namespace, which the standard library's module serves. Returns NULL and
 * throws when the table is damaged. */
static zend_string *sharp_dispatcher_namespace(zend_class_entry *event)
{
	static const char id[] = " sharp\\dispatcher";
	zend_string *key;
	size_t root_length;

	if (SHARP_G(container_test)) {
		ZEND_HASH_FOREACH_STR_KEY(SHARP_G(container_test), key) {
			if (ZSTR_LEN(key) >= sizeof(id) - 1
					&& memcmp(ZSTR_VAL(key) + ZSTR_LEN(key) - (sizeof(id) - 1), id, sizeof(id) - 1) == 0) {
				return zend_string_init(ZSTR_VAL(key), ZSTR_LEN(key) - (sizeof(id) - 1), 0);
			}
		} ZEND_HASH_FOREACH_END();
	}
	if (event->type == ZEND_USER_CLASS && sharp_find_root(event->info.user.filename, &root_length)) {
		HashTable *table = sharp_listener_table(ZSTR_VAL(event->info.user.filename), root_length);
		zval *module;

		if (!table) {
			return NULL;
		}
		module = zend_hash_str_find(table, "dispatcher", sizeof("dispatcher") - 1);
		if (Z_TYPE_P(module) == IS_ARRAY) {
			zend_string *path = Z_STR_P(sharp_element(module, 0, IS_STRING));
			zend_string *file = zend_string_concat3(ZSTR_VAL(event->info.user.filename), root_length, "/", 1,
				ZSTR_VAL(path), ZSTR_LEN(path));

			sharp_container_read_folders(file);
			zend_string_release(file);

			return EG(exception) ? NULL : zend_string_copy(Z_STR_P(sharp_element(module, 1, IS_STRING)));
		}
	}

	return ZSTR_EMPTY_ALLOC();
}

/* emit hands the event to the app's dispatcher with one function per listener. The dispatcher sees every event, one
 * no listener hears included, whoever emits it: an app class, a PHP# package in vendor/ or plain PHP. */
ZEND_FUNCTION(Sharp_Internal_emit)
{
	zval *event, listeners, dispatcher;
	zend_string *id, *namespace;
	bool found;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJECT(event)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_listeners_for(event, &listeners)) {
		RETURN_THROWS();
	}
	namespace = sharp_dispatcher_namespace(Z_OBJCE_P(event));
	if (!namespace) {
		zval_ptr_dtor(&listeners);
		RETURN_THROWS();
	}
	id = zend_string_init("Sharp\\Dispatcher", sizeof("Sharp\\Dispatcher") - 1, 0);
	found = sharp_container_service(id, namespace, NULL, &dispatcher);
	zend_string_release(id);
	zend_string_release(namespace);

	if (found) {
		zend_string *name = zend_string_init("dispatch", sizeof("dispatch") - 1, 0);
		zend_function *dispatch = zend_hash_find_ptr_lc(&Z_OBJCE(dispatcher)->function_table, name);
		zval arguments[2];

		ZVAL_COPY_VALUE(&arguments[0], event);
		ZVAL_COPY_VALUE(&arguments[1], &listeners);
		if (dispatch) {
			zend_call_known_instance_method(dispatch, Z_OBJ(dispatcher), NULL, 2, arguments);
		} else {
			zend_undefined_method(Z_OBJCE(dispatcher), name);
		}
		zend_string_release(name);
		zval_ptr_dtor(&dispatcher);
	}
	zval_ptr_dtor(&listeners);
}

/* The listeners of an event for a PSR-14 dispatcher, which Sharp\Psr\ListenerProvider returns: the functions emit
 * hands its dispatcher. */
ZEND_FUNCTION(Sharp_Internal_listenersFor)
{
	zval *event;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJECT(event)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_listeners_for(event, return_value)) {
		RETURN_THROWS();
	}
}

static zend_object *sharp_listening_create(zend_class_entry *ce)
{
	sharp_listening *listening = zend_object_alloc(sizeof(sharp_listening), ce);

	listening->number = 0;
	listening->type = NULL;
	ZVAL_UNDEF(&listening->function);
	zend_object_std_init(&listening->std, ce);

	return &listening->std;
}

/* Stops the listener: the scoped listeners no longer reach it. */
static void sharp_listening_stop(sharp_listening *listening)
{
	if (listening->number) {
		zend_hash_index_del(&SHARP_G(scoped_listeners), listening->number);
		listening->number = 0;
	}
}

/* The handle's destructor stops its listener, when its last reference goes or the cycle collector collects it. */
static void sharp_listening_destroy(zend_object *object)
{
	sharp_listening_stop(sharp_listening_from(object));
	zend_objects_destroy_object(object);
}

/* A handle freed without its destructor, as at the end of a request, stops its listener too. */
static void sharp_listening_free(zend_object *object)
{
	sharp_listening *listening = sharp_listening_from(object);

	sharp_listening_stop(listening);
	if (listening->type) {
		zend_string_release(listening->type);
	}
	zval_ptr_dtor(&listening->function);
	zend_object_std_dtor(object);
}

static HashTable *sharp_listening_gc(zend_object *object, zval **table, int *count)
{
	/* The class declares no property, and @strict-properties allows no other. */
	*table = &sharp_listening_from(object)->function;
	*count = 1;

	return NULL;
}

ZEND_METHOD(Sharp_Listening, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* Spec section 15: Events.on starts a listener of the type named type, which hears every event of that type until the
 * handle it returns is destroyed. */
ZEND_FUNCTION(Sharp_Internal_listen)
{
	zend_string *type;
	zval *function;
	sharp_listening *listening;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(type)
		Z_PARAM_OBJECT_OF_CLASS(function, zend_ce_closure)
	ZEND_PARSE_PARAMETERS_END();

	object_init_ex(return_value, sharp_ce_listening);
	listening = sharp_listening_from(Z_OBJ_P(return_value));
	listening->type = zend_string_copy(type);
	ZVAL_COPY(&listening->function, function);
	listening->number = ++SHARP_G(next_scoped_listener);
	zend_hash_index_add_new_ptr(&SHARP_G(scoped_listeners), listening->number, listening);
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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Class_of, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

/* Spec section 25: the class of the object a value holds, which may be a subclass of its type. A class value is its
 * class name, so plain PHP receives it as the name it takes. An object of a generic class gives the interned class
 * value of its class with the object's type arguments, the one `typeof(Box<Order>)` gives, so `==` and `new (…)()`
 * read them. */
ZEND_METHOD(Sharp_Class, of)
{
	zend_object *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJ(value)
	ZEND_PARSE_PARAMETERS_END();

	/* A lazy proxy's initializer runs to read its type arguments, and can throw. */
	const sharp_type *arguments = sharp_type_arguments(value);
	if (UNEXPECTED(EG(exception))) {
		RETURN_THROWS();
	}
	if (!arguments) {
		RETURN_STR_COPY(value->ce->name);
	}

	smart_str text = {0};
	sharp_object_text(&text, value);
	smart_str_0(&text);
	const sharp_type *type = sharp_type_list(ZSTR_VAL(text.s), ZSTR_LEN(text.s));
	smart_str_free(&text);
	ZEND_ASSERT(type != NULL);

	RETURN_STR_COPY(sharp_type_list_class_value(type, false));
}

static const zend_function_entry class_Sharp_Class_methods[] = {
	ZEND_ME(Sharp_Class, of, arginfo_class_Sharp_Class_of, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Class(void)
{
	zend_class_entry ce;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Class", class_Sharp_Class_methods);

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

/* The key a Map takes for the key PHP holds under index or name, as the plan's key says: the key as it is, a string
 * for an all-digit one PHP holds as an int, or the case of the backed enum the plan names. */
static bool sharp_crossed_key(zval *key, const zval *plan_key, zend_ulong index, zend_string *name)
{
	if (Z_TYPE_P(plan_key) != IS_STRING) {
		if (name) {
			ZVAL_STR_COPY(key, name);
		} else {
			ZVAL_LONG(key, (zend_long) index);
		}
		return true;
	}
	if (zend_string_equals_literal(Z_STR_P(plan_key), "string")) {
		ZVAL_STR(key, name ? zend_string_copy(name) : zend_long_to_str((zend_long) index));
		return true;
	}

	zend_class_entry *backed = zend_lookup_class(Z_STR_P(plan_key));
	if (!backed) {
		return false;
	}
	if (name && backed->enum_backing_type == IS_LONG) {
		zend_type_error("%s::from(): Argument #1 ($value) must be of type int, string given", ZSTR_VAL(backed->name));
		return false;
	}
	zend_object *found;
	zend_string *text = name ? zend_string_copy(name) : NULL;
	if (!name && backed->enum_backing_type == IS_STRING) {
		text = zend_long_to_str((zend_long) index);
	}
	zend_result result = zend_enum_get_case_by_value(&found, backed, (zend_long) index, text, false);
	if (text) {
		zend_string_release(text);
	}
	if (result == FAILURE) {
		return false;
	}
	ZVAL_OBJ_COPY(key, found);

	return true;
}

/* The value a PHP array crosses into, as `plan` describes the PHP# type it enters: ['map', key, values],
 * ['set', elements] or ['list', elements], each inner plan null when that part holds no Map or Set. A value that is
 * not a PHP array is the object already, as the bridge's own crossing leaves it. */
static bool sharp_crossed(zval *result, zval *value, const zval *plan)
{
	if (Z_TYPE_P(value) != IS_ARRAY || Z_TYPE_P(plan) != IS_ARRAY) {
		ZVAL_COPY(result, value);
		return true;
	}

	HashTable *parts = Z_ARRVAL_P(plan);
	const zval *kind = zend_hash_index_find(parts, 0);
	ZEND_ASSERT(kind && Z_TYPE_P(kind) == IS_STRING);
	const zval *inner = zend_hash_index_find(parts, zend_hash_num_elements(parts) - 1);
	HashTable *entries = Z_ARRVAL_P(value);
	zend_ulong index;
	zend_string *name;
	zval *element, crossed, key;

	if (zend_string_equals_literal(Z_STR_P(kind), "list")) {
		array_init_size(result, zend_hash_num_elements(entries));
		ZEND_HASH_FOREACH_KEY_VAL(entries, index, name, element) {
			if (!sharp_crossed(&crossed, element, inner)) {
				zval_ptr_dtor(result);
				return false;
			}
			if (name) {
				zend_hash_update(Z_ARRVAL_P(result), name, &crossed);
			} else {
				zend_hash_index_update(Z_ARRVAL_P(result), index, &crossed);
			}
		} ZEND_HASH_FOREACH_END();
		return true;
	}

	bool is_set = zend_string_equals_literal(Z_STR_P(kind), "set");
	sharp_table_object *table =
		sharp_table_object_create(is_set ? sharp_ce_set : sharp_ce_map, zend_hash_num_elements(entries));
	ZVAL_OBJ(result, &table->std);
	ZEND_HASH_FOREACH_KEY_VAL(entries, index, name, element) {
		if (!sharp_crossed(&crossed, element, inner)) {
			zval_ptr_dtor(result);
			return false;
		}
		bool put;
		if (is_set) {
			zval null;
			ZVAL_NULL(&null);
			put = sharp_table_put(&table->table, &crossed, &null);
		} else {
			put = sharp_crossed_key(&key, zend_hash_index_find(parts, 1), index, name);
			if (put) {
				put = sharp_table_put(&table->table, &key, &crossed);
				zval_ptr_dtor(&key);
			}
		}
		zval_ptr_dtor(&crossed);
		if (!put) {
			zval_ptr_dtor(result);
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

static zend_attribute *sharp_crossing_of(const zend_property_info *info)
{
	return info->attributes
		? zend_get_attribute_str(info->attributes, "sharp\\crossing", sizeof("sharp\\crossing") - 1)
		: NULL;
}

bool sharp_crosses_property(const zend_property_info *info)
{
	return sharp_crossing_of(info) != NULL;
}

bool sharp_cross_property(const zend_property_info *info, zval *value)
{
	zend_attribute *crossing = sharp_crossing_of(info);
	zval plan, crossed;
	if (!crossing || zend_get_attribute_value(&plan, crossing, 0, info->ce) == FAILURE) {
		return false;
	}

	bool is_crossed = sharp_crossed(&crossed, value, &plan);
	zval_ptr_dtor(&plan);
	if (!is_crossed) {
		return false;
	}
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &crossed);

	return true;
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

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Map___construct, 0, 0, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, keysAndValues, IS_ARRAY, 0, "[]")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Set___construct, 0, 0, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, values, IS_ARRAY, 0, "[]")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Table_getIterator, 0, 0, Iterator, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_count, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_jsonSerialize, 0, 0, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_list, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_offsetExists, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, offset, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_offsetGet, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, offset, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_offsetSet, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, offset, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table_offsetUnset, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, offset, IS_MIXED, 0)
ZEND_END_ARG_INFO()


ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Map_from, 0, 1, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, entries, IS_ARRAY, 0)
ZEND_END_ARG_INFO()



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

/* ArrayAccess's reads, which run as the index does: $map[$key] and isset($map[$key]), $set[$element] and
 * isset($set[$element]). */
ZEND_METHOD(Sharp_Table, offsetExists)
{
	zval *offset;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(offset)
	ZEND_PARSE_PARAMETERS_END();

	bool exists = sharp_table_has_dimension(Z_OBJ_P(ZEND_THIS), offset, 0);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	RETURN_BOOL(exists);
}

ZEND_METHOD(Sharp_Table, offsetGet)
{
	zval *offset;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(offset)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_table_read_dimension(Z_OBJ_P(ZEND_THIS), offset, BP_VAR_R, return_value)) {
		RETURN_THROWS();
	}
}

/* ArrayAccess's writes refuse a call. A Map and a Set are values: the index opcodes copy a shared one into the holder
 * they write through before the write, and a method call has no holder to copy into, so its write would reach every
 * holder of the shared one. */
ZEND_METHOD(Sharp_Table, offsetSet)
{
	zval *offset, *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(offset)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	zend_throw_error(NULL, "A %s is written through its index, which copies a shared one first", ZSTR_VAL(Z_OBJCE_P(ZEND_THIS)->name));
}

ZEND_METHOD(Sharp_Table, offsetUnset)
{
	zval *offset;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(offset)
	ZEND_PARSE_PARAMETERS_END();

	zend_throw_error(NULL, "A %s is written through its index, which copies a shared one first", ZSTR_VAL(Z_OBJCE_P(ZEND_THIS)->name));
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
 * one. A PHP# Map literal lowers to `new \Sharp\Map([$k1, $v1, ...])`, which a .sharp property default may hold. */
ZEND_METHOD(Sharp_Map, __construct)
{
	HashTable *keys_and_values = NULL;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_ARRAY_HT(keys_and_values)
	ZEND_PARSE_PARAMETERS_END();

	if (!keys_and_values) {
		return;
	}
	if (zend_hash_num_elements(keys_and_values) % 2 != 0 || !zend_array_is_list(keys_and_values)) {
		zend_argument_value_error(1, "must be a list of keys each followed by its value");
		RETURN_THROWS();
	}

	sharp_table *table = sharp_table_of(Z_OBJ_P(ZEND_THIS));
	zval *key = NULL, *element;
	ZEND_HASH_FOREACH_VAL(keys_and_values, element) {
		if (!key) {
			key = element;
		} else if (!sharp_table_put(table, key, element)) {
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

/* An array's elements, each once, in their first order: a PHP# Set literal's, as `new \Sharp\Set([...])` lowers it,
 * and a PHP array's where plain PHP passes one for a Set. */
ZEND_METHOD(Sharp_Set, __construct)
{
	HashTable *values = NULL;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_ARRAY_HT(values)
	ZEND_PARSE_PARAMETERS_END();

	if (!values) {
		return;
	}

	sharp_table *table = sharp_table_of(Z_OBJ_P(ZEND_THIS));
	zval null, *element;
	ZVAL_NULL(&null);
	ZEND_HASH_FOREACH_VAL(values, element) {
		if (!sharp_table_put(table, element, &null)) {
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();
}

static const zend_function_entry class_Sharp_Map_methods[] = {
	ZEND_ME(Sharp_Map, __construct, arginfo_class_Sharp_Map___construct, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, from, arginfo_class_Sharp_Map_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Map, keys, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, values, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("offsetExists", zim_Sharp_Table_offsetExists, arginfo_class_Sharp_Table_offsetExists, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetGet", zim_Sharp_Table_offsetGet, arginfo_class_Sharp_Table_offsetGet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetSet", zim_Sharp_Table_offsetSet, arginfo_class_Sharp_Table_offsetSet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetUnset", zim_Sharp_Table_offsetUnset, arginfo_class_Sharp_Table_offsetUnset, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Set_methods[] = {
	ZEND_ME(Sharp_Set, __construct, arginfo_class_Sharp_Set___construct, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Set, toList, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("offsetExists", zim_Sharp_Table_offsetExists, arginfo_class_Sharp_Table_offsetExists, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetGet", zim_Sharp_Table_offsetGet, arginfo_class_Sharp_Table_offsetGet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetSet", zim_Sharp_Table_offsetSet, arginfo_class_Sharp_Table_offsetSet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetUnset", zim_Sharp_Table_offsetUnset, arginfo_class_Sharp_Table_offsetUnset, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_FE_END
};

/* As WeakMap does, the class implements ArrayAccess and IteratorAggregate through its own handlers: the index runs
 * through read_dimension and the others, and a loop through get_iterator. */
static zend_class_entry *register_class_Sharp_Table(const char *name, const zend_function_entry *methods)
{
	zend_class_entry ce;

	INIT_CLASS_ENTRY_EX(ce, name, strlen(name), methods);
	zend_class_entry *registered = zend_register_internal_class_with_flags(&ce, NULL,
		ZEND_ACC_FINAL | ZEND_ACC_NO_DYNAMIC_PROPERTIES | ZEND_ACC_NOT_SERIALIZABLE);
	zend_class_implements(registered, 4, zend_ce_arrayaccess, zend_ce_aggregate, zend_ce_countable, php_json_serializable_ce);
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

/* #[\Sharp\Crossing(plan)], which the bridge puts on each property PHP# declares a Map or a Set: the plan
 * sharp_cross_property crosses a PHP array plain PHP writes there by. */
ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Crossing___construct, 0, 0, 1)
	ZEND_ARG_TYPE_INFO(0, plan, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(Sharp_Crossing, __construct)
{
	zval *plan;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY(plan)
	ZEND_PARSE_PARAMETERS_END();

	zend_update_property(Z_OBJCE_P(ZEND_THIS), Z_OBJ_P(ZEND_THIS), "plan", sizeof("plan") - 1, plan);
}

static const zend_function_entry class_Sharp_Crossing_methods[] = {
	ZEND_ME(Sharp_Crossing, __construct, arginfo_class_Sharp_Crossing___construct, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static void register_class_Sharp_Crossing(void)
{
	zend_class_entry ce;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Crossing", class_Sharp_Crossing_methods);
	zend_class_entry *crossing = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL);

	zval undefined;
	ZVAL_UNDEF(&undefined);
	zend_string *plan = zend_string_init_interned("plan", sizeof("plan") - 1, true);
	zend_declare_typed_property(crossing, plan, &undefined, ZEND_ACC_PUBLIC | ZEND_ACC_READONLY, NULL,
		(zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_ARRAY));
	zend_string_release(plan);

	zend_string *attribute_name = zend_string_init_interned("Attribute", sizeof("Attribute") - 1, true);
	zend_attribute *attribute = zend_add_class_attribute(crossing, attribute_name, 1);
	zend_string_release(attribute_name);
	ZVAL_LONG(&attribute->args[0].value, ZEND_ATTRIBUTE_TARGET_PROPERTY);
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

	sharp_ce_sequence = register_class_Sharp_Sequence(zend_ce_aggregate);
	sharp_ce_sequence->create_object = sharp_sequence_create;
	sharp_ce_sequence->get_iterator = sharp_sequence_get_iterator;
	memcpy(&sharp_sequence_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_sequence_handlers.offset = XtOffsetOf(sharp_sequence, std);
	sharp_sequence_handlers.free_obj = sharp_sequence_free;
	sharp_sequence_handlers.get_gc = sharp_sequence_get_gc;
	sharp_sequence_handlers.clone_obj = NULL;
	sharp_ce_sequence->default_object_handlers = &sharp_sequence_handlers;

	register_class_Sharp_Position();
	register_class_Sharp_List();
	register_class_Sharp_Class();
	sharp_ce_struct = register_class_Sharp_Struct();
	register_sharp_tables();
	register_class_Sharp_Crossing();

	register_class_Sharp_Container();
	sharp_ce_container_error = register_class_Sharp_ContainerError(zend_ce_error);

	sharp_ce_listener = register_class_Sharp_Internal_Listener();
	sharp_ce_listener->create_object = sharp_listener_create;
	memcpy(&sharp_listener_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_listener_handlers.offset = XtOffsetOf(sharp_listener, std);
	sharp_listener_handlers.free_obj = sharp_listener_free;
	sharp_listener_handlers.clone_obj = NULL;
	sharp_ce_listener->default_object_handlers = &sharp_listener_handlers;
	sharp_listener_hear = zend_hash_str_find_ptr(&sharp_ce_listener->function_table, "hear", sizeof("hear") - 1);

	sharp_ce_listening = register_class_Sharp_Listening();
	sharp_ce_listening->create_object = sharp_listening_create;
	memcpy(&sharp_listening_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_listening_handlers.offset = XtOffsetOf(sharp_listening, std);
	sharp_listening_handlers.dtor_obj = sharp_listening_destroy;
	sharp_listening_handlers.free_obj = sharp_listening_free;
	sharp_listening_handlers.get_gc = sharp_listening_gc;
	sharp_listening_handlers.clone_obj = NULL;
	sharp_ce_listening->default_object_handlers = &sharp_listening_handlers;
	/* The module's own table is sharp/bin/native's, so the stub's Sharp\Internal functions register here, as
	 * ext/zend_test registers its second table. */
	zend_register_functions(NULL, ext_functions, NULL, type);

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
	zend_hash_init(&SHARP_G(listener_tables), 0, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(event_listeners), 0, NULL, ZVAL_PTR_DTOR, false);
	/* Each entry points at a live Sharp\Listening, which removes it when it stops, and holds no reference. */
	zend_hash_init(&SHARP_G(scoped_listeners), 0, NULL, NULL, false);
	SHARP_G(next_scoped_listener) = 0;
	ZVAL_UNDEF(&SHARP_G(compile_command_report));
	/* Spec section 32: the request is the host's scope, as each CLI run is. */
	zend_hash_init(&SHARP_G(container_bindings), 8, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(container_environment), 8, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(container_modules), 8, NULL, NULL, false);
	zend_hash_init(&SHARP_G(container_tests), 8, NULL, ZVAL_PTR_DTOR, false);
	SHARP_G(container_test) = NULL;
	zend_hash_init(&SHARP_G(container_singletons), 8, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(container_request_scope), 8, NULL, ZVAL_PTR_DTOR, false);
	SHARP_G(container_scope) = &SHARP_G(container_request_scope);
	zend_hash_init(&SHARP_G(container_building), 8, NULL, NULL, false);

	/* The CLI names the script in argv[0] only while the request starts, as PHP's $argv does. */
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
	zend_hash_destroy(&SHARP_G(container_request_scope));
	zend_hash_destroy(&SHARP_G(container_singletons));
	zend_hash_destroy(&SHARP_G(container_bindings));
	zend_hash_destroy(&SHARP_G(container_environment));
	zend_hash_destroy(&SHARP_G(container_modules));
	SHARP_G(container_test) = NULL;
	zend_hash_destroy(&SHARP_G(container_tests));
	zend_hash_destroy(&SHARP_G(container_building));
	zend_hash_destroy(&SHARP_G(listener_tables));
	zend_hash_destroy(&SHARP_G(event_listeners));
	/* A handle whose destructor didn't run, as after a fatal error, is freed after this, with no table to leave. */
	sharp_listening *listening;
	ZEND_HASH_FOREACH_PTR(&SHARP_G(scoped_listeners), listening) {
		listening->number = 0;
	} ZEND_HASH_FOREACH_END();
	zend_hash_destroy(&SHARP_G(scoped_listeners));
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

#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include <arpa/inet.h>
#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <netdb.h>
#include <signal.h>
#include <sys/file.h>
#include <sys/socket.h>
#include <sys/wait.h>
#include <unistd.h>
#ifdef __linux__
# include <sys/prctl.h>
#endif

#include "php.h"
#include "php_ini.h"
#include "SAPI.h"
#include "ext/hash/php_hash.h"
#include "ext/hash/php_hash_xxhash.h"
#include "ext/json/php_json.h"
#include "ext/json/php_json_encoder.h"
#include "ext/json/php_json_parser.h"
#include "ext/random/php_random_csprng.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/base64.h"
#include "ext/standard/basic_functions.h"
#include "ext/standard/html.h"
#include "ext/standard/info.h"
#include "zend_attributes.h"
#include "zend_bitset.h"
#include "zend_closures.h"
#include "zend_enum.h"
#include "zend_exceptions.h"
#include "zend_extensions.h"
#include "zend_fibers.h"
#include "zend_generators.h"
#include "zend_interfaces.h"
#include "zend_language_parser.h"
#include "zend_lazy_objects.h"
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
#include "sharp_version.h"

#define SHARP_COMPILED_FOLDER ".sharp"
/* How the path of an input that stands for the .sharp files of its folder ends: the bridge's unit::SOURCE_LISTING. */
#define SHARP_SOURCE_LISTING "/*.sharp"
#define SHARP_LISTENERS "listeners.php"
#define SHARP_LISTENER_TABLE SHARP_COMPILED_FOLDER "/" SHARP_LISTENERS
/* The script mago compile writes that requires the project's Composer autoloader, spec section 27. */
#define SHARP_AUTOLOAD SHARP_COMPILED_FOLDER "/autoload.php"

#define SHARP_KIND_IS_ZEND_KIND(kind) \
	ZEND_STATIC_ASSERT((zend_ast_kind) SHARP_AST_##kind == ZEND_AST_##kind, "SHARP_AST_" #kind " differs from ZEND_AST_" #kind);
SHARP_KINDS(SHARP_KIND_IS_ZEND_KIND)
ZEND_STATIC_ASSERT(SHARP_T_FILE == T_FILE, "SHARP_T_FILE differs from T_FILE");

ZEND_STATIC_ASSERT(sizeof(sharp_unit_header) == 160, "a .sharpc header is 160 bytes");
ZEND_STATIC_ASSERT(sizeof(sharp_input) == 56, "a .sharpc input is 56 bytes");
ZEND_STATIC_ASSERT(sizeof(sharp_node) == 56, "a .sharpc node is 56 bytes");

/* Spec section 32: a scope keeps the scoped services built in it, by binding row. Sharp\Internal\runScoped starts one
 * and ends it when its body returns or throws, so it is empty and ended from then on, and is freed once nothing holds
 * it: each fiber holds the scope it started in. */
typedef struct {
	uint32_t references;
	bool ended;
	HashTable services;
} sharp_scope;

/* A fiber's scopes, in the reserved slot sharp_fiber_slot of its zend_fiber_context: the scope it started in, the scope
 * it runs in now, the bindings and classes it is building, to refuse a cycle, and, while sharp.effect_report is set,
 * the calls a built-in it runs counts against, innermost on top, each a declared method's run, or NULL for PHP# code.
 * The request's main context has them too. A fiber may suspend in the middle of a build or of a declared call, so
 * each fiber builds and counts on its own, and starts counting against nothing. See sharp_effect_observer. */
typedef struct {
	sharp_scope *started_in;
	sharp_scope *current;
	HashTable building;
	zend_stack effect_calls;
} sharp_fiber_scopes;

ZEND_BEGIN_MODULE_GLOBALS(sharp)
	HashTable folder_roots;
	HashTable request_stamps;
	/* For the request: each listener table emit read, by its path, and each event class's listeners, by its entry. */
	HashTable listener_tables;
	HashTable event_listeners;
	/* Spec section 29: for the request, the attributes reflection reads of each class, method and function, by the
	 * key sharp_extern_key gives it, or NULL for one whose own attributes are all it has. See
	 * sharp_extern_attributes. */
	HashTable class_attributes;
	/* The key of the declaration whose `extern` lines sharp_parse_extern_attributes compiles, or NULL. */
	zend_string *extern_key;
	/* Spec section 15: for the request, each listener Sharp\Internal\listen started whose handle lives, under the number
	 * its handle holds, in the order they started, as [the type it listens to, its function]. */
	HashTable scoped_listeners;
	zend_ulong next_scoped_listener;
	char *compile_command;
	/* Undefined until the request runs its compile command, then the checker report the command printed, or null. */
	zval compile_command_report;
	/* NULL, or why the request's compile command failed: how it ended and the last lines it wrote to stderr. */
	zend_string *compile_command_failure;
	/* The descriptor of the project's compile lock while the request holds it, else -1. See sharp_lock_compiles. */
	int compile_lock;
	zval arguments;
	/* The types and argument lists first read from input this request, NULL until there is one. See sharp_types. */
	HashTable *request_types;
	HashTable *request_type_lists;
	/* How many of them a text read from input made. See SHARP_TYPE_INPUT_LIMIT. */
	uint32_t request_input_types;
	/* The class values of request_types, NULL until there is one. See sharp_class_values. */
	HashTable *request_class_values;
	/* The lists sharp_type_list_of_frame spelled this request, under their text an array keyed by a sharp_type_spelling,
	 * NULL until there is one. */
	HashTable *request_spelled_lists;
	/* Spec section 25: what reflection read this request, under a key sharp_reflected spells the rows it gave, so a
	 * class's declarations are read once. NULL until there is one. */
	HashTable *request_reflected;
	/* Spec section 32: Sharp\Container's bindings, under "namespace id" a list of the modules that bind it, each a
	 * list of its rows, the base modules' and the environment modules' apart; the paths of the module files it read;
	 * each test file's module, under the exact name of the one PHP# class the file declares, and the one of the test
	 * that runs now, or NULL; the services each singleton keeps, by binding row; and the main context's scopes, which
	 * started in the request's scope. */
	HashTable container_bindings;
	HashTable container_environment;
	HashTable container_modules;
	/* The file of the standard library's module, which Sharp\Internal\readModule names, or NULL. */
	zend_string *container_library;
	HashTable container_tests;
	HashTable *container_test;
	HashTable container_singletons;
	sharp_fiber_scopes container_main_scopes;
	/* The sort that runs now, which sharp_compare_sort_keys reads, or NULL. See sharp_sort. */
	struct sharp_sort *sort;
	/* See sharp_equality_operators, NULL until the request compares an object. */
	HashTable *equality_operators;
	/* The text of the error the last call inside a ZEND_SHARP_SILENCE raised in this request, or NULL. See
	 * sharp_observe_error. */
	zend_string *silenced_warning;
	/* The field whose value Sharp_Internal_seal is writing, which each Secret in it is encrypted for, or NULL. */
	zend_string *seal_path;
	/* Spec section 29, while sharp.effect_report is set: for the request, what the calls of each plain PHP method an
	 * `extern` line declares ran, a sharp_effect_run by the method's name. See sharp_effect_observer. */
	HashTable effect_runs;
	/* Spec section 29: the emits each open Database.transaction holds, outermost first under 0, each a list of
	 * [event, dispatcher service] in emit order. See Sharp_Internal_holdEmits. */
	HashTable held_emits;
ZEND_END_MODULE_GLOBALS(sharp)

ZEND_DECLARE_MODULE_GLOBALS(sharp)

#define SHARP_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(sharp, v)

PHP_INI_BEGIN()
	STD_PHP_INI_ENTRY("sharp.compile_command", "", PHP_INI_SYSTEM, OnUpdateString, compile_command, zend_sharp_globals, sharp_globals)
	PHP_INI_ENTRY("sharp.effect_report", "", PHP_INI_SYSTEM, NULL)
PHP_INI_END()

static zend_op_array *(*sharp_next_compile_file)(zend_file_handle *file_handle, int type);

/* Whether the request ran past memory_limit, after which it throws the fatal error at once, as zend_call_function does.
 * An allocation past memory_limit succeeds, and the VM raises the error at its next safe point (GHSA-w8hw-w9fx-j79w), but
 * one past the hard limit above it, twice memory_limit by default, ends the process. So a loop that allocates for each
 * element of a value, and runs no PHP code between them, checks before each one and stops through its exception path. */
static bool sharp_out_of_memory(void)
{
	if (EXPECTED(!ZEND_HAS_OOM())) {
		return false;
	}
	zend_handle_delayed_oom();

	return true;
}

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

/* The attr of a node of `kind` whose unit gives it `attr`: an operator's with the syntax PHP# flags it with, and a
 * member's with ZEND_SHARP_INTERNAL_MEMBER for its ZEND_ACC_SHARP_INTERNAL and ZEND_SHARP_INTERNAL_SET_MEMBER for its
 * ZEND_ACC_SHARP_INTERNAL_SET, which the 16 bits of an attr can't hold. */
static zend_ast_attr sharp_operator_attr(zend_ast_kind kind, uint32_t attr)
{
	switch (kind) {
		case ZEND_AST_PROP_GROUP:
		case ZEND_AST_CLASS_CONST_GROUP:
		case ZEND_AST_PARAM:
			return (attr & ~(ZEND_ACC_SHARP_INTERNAL|ZEND_ACC_SHARP_INTERNAL_SET))
				| (attr & ZEND_ACC_SHARP_INTERNAL ? ZEND_SHARP_INTERNAL_MEMBER : 0)
				| (attr & ZEND_ACC_SHARP_INTERNAL_SET ? ZEND_SHARP_INTERNAL_SET_MEMBER : 0);
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
		case ZEND_AST_SILENCE:
			return attr | ZEND_SHARP_SILENCE;
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

/* sharp_check proved the tree is one: each child comes before its parent, and each node has one parent. A tree deeper
 * than the stack stops as zend_compile stops on a deep expression. */
static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index)
{
	const sharp_node *node;
	zend_ast_kind kind;
	zend_ast *ast;

	if (index == UINT32_MAX) {
		return NULL;
	}
#ifdef ZEND_CHECK_STACK_LIMIT
	if (UNEXPECTED(zend_call_stack_overflowed(EG(stack_limit)))) {
		zend_error_noreturn(E_COMPILE_ERROR, "Maximum call stack size of %zu bytes (zend.max_allowed_stack_size"
			" - zend.reserved_stack_size) reached during compilation. Try splitting expression",
			(size_t) ((uintptr_t) EG(stack_base) - (uintptr_t) EG(stack_limit)));
	}
#endif

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
	SHARP_OTHER_LIBRARY,
	SHARP_OUT_OF_DATE,
} sharp_state;

typedef struct {
	bool exists;
	bool hashed;
	uint64_t size;
	int64_t mtime_ns;
	uint8_t hash[16];
	/* Whether declarations_hash holds the one the header of the file's compiled file names, or all zero when it has
	 * no compiled file this engine reads. */
	bool declarations_read;
	uint8_t declarations_hash[16];
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

static void sharp_free_pointer(zval *pointer)
{
	efree(Z_PTR_P(pointer));
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

static zend_string *sharp_environment_variable(const char *name, size_t name_length);

/* The folder an extension host's worker reads the project's compiled files from: mago starts each worker of a host with
 * SHARP_ROOT, the project's root, and SHARP_COMPILED, the folder outside the project where it compiled the project for
 * its hosts, so a host loads the project's classes on a fresh or read-only checkout, and a file `mago compile` refused
 * never runs from the project's .sharp folder. Returns the folder, and the root's length in root_length, when the path
 * of length bytes is in that project, or NULL. */
static zend_string *sharp_host_compiled(const char *path, size_t length, size_t *root_length)
{
	zend_string *root = sharp_environment_variable("SHARP_ROOT", sizeof("SHARP_ROOT") - 1);
	zend_string *folder = NULL;

	if (root && ZSTR_LEN(root) > 0 && ZSTR_LEN(root) < length && path[ZSTR_LEN(root)] == '/'
			&& memcmp(path, ZSTR_VAL(root), ZSTR_LEN(root)) == 0) {
		folder = sharp_environment_variable("SHARP_COMPILED", sizeof("SHARP_COMPILED") - 1);
		*root_length = ZSTR_LEN(root);
	}
	if (root) {
		zend_string_release(root);
	}

	return folder;
}

/* The folder that holds the compiled files of the project whose root is the first root_length bytes of the path of
 * length bytes: the host's folder for a worker of an extension host, else the project's .sharp folder. */
static zend_string *sharp_compiled_folder(const char *path, size_t length, size_t root_length)
{
	size_t host_root_length;
	zend_string *host = sharp_host_compiled(path, length, &host_root_length);

	if (host && host_root_length == root_length) {
		return host;
	}
	if (host) {
		zend_string_release(host);
	}

	return zend_string_concat3(path, root_length, "/", 1, SHARP_COMPILED_FOLDER, sizeof(SHARP_COMPILED_FOLDER) - 1);
}

/* The root of the source's project: the nearest folder above it that holds a .sharp folder. For a worker of an extension
 * host, a source in the host's project is rooted at SHARP_ROOT, whose files the host's folder holds, unless a folder
 * between them holds its own .sharp folder, as the standard library's package holds the files PHP# compiled at its
 * release. */
static bool sharp_find_root(const zend_string *source, size_t *root_length)
{
	const char *path = ZSTR_VAL(source);
	size_t folder = ZSTR_LEN(source);
	size_t host_root_length = 0;
	zend_string *host = sharp_host_compiled(path, folder, &host_root_length);
	bool hosted = host != NULL;
	zval root;

	if (host) {
		zend_string_release(host);
	}

	do {
		zval *known;

		folder = sharp_parent(path, folder);
		if (hosted && folder <= host_root_length) {
			*root_length = host_root_length;
			break;
		}
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
	zend_string *folder = sharp_compiled_folder(source, ZSTR_LEN(compiled->source), compiled->root_length);
	zend_string *path = zend_strpprintf(0, "%s%.*sc", ZSTR_VAL(folder),
		(int) (ZSTR_LEN(compiled->source) - compiled->root_length), source + compiled->root_length);
	php_stream *stream;
	zend_string *bytes;

	zend_string_release(folder);
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

/* The hash of the declarations of the file at path, under the root of the first root_length bytes of path, that the
 * header of its compiled file names, read once per request from that header alone. All zero when the file has no
 * compiled file this engine reads, which no recorded hash equals. */
static const uint8_t *sharp_compiled_declarations(const zend_string *path, size_t root_length, sharp_stamp *stamp)
{
	char real[MAXPATHLEN];
	size_t length;

	if (stamp->declarations_read) {
		return stamp->declarations_hash;
	}
	stamp->declarations_read = true;
	if (!VCWD_REALPATH(ZSTR_VAL(path), real)) {
		return stamp->declarations_hash;
	}
	length = strlen(real);
	if (length > root_length && real[root_length] == '/' && memcmp(real, ZSTR_VAL(path), root_length) == 0) {
		zend_string *folder = sharp_compiled_folder(real, length, root_length);
		zend_string *compiled = zend_strpprintf(0, "%s%sc", ZSTR_VAL(folder), real + root_length);
		int file = VCWD_OPEN(ZSTR_VAL(compiled), O_RDONLY);
		sharp_unit_header header;

		if (file >= 0) {
			if (read(file, &header, sizeof(header)) == (ssize_t) sizeof(header)
					&& memcmp(header.magic, SHARP_UNIT_MAGIC, sizeof(header.magic)) == 0
					&& memcmp(header.abi, SHARP_UNIT_ABI, sizeof(header.abi)) == 0) {
				memcpy(stamp->declarations_hash, header.declarations_hash, sizeof(stamp->declarations_hash));
			}
			close(file);
		}
		zend_string_release(compiled);
		zend_string_release(folder);
	}

	return stamp->declarations_hash;
}

/* Whether an input of a file under the root of the first root_length bytes of path is as it was compiled. One whose
 * declarations_hash is all zero holds while the file at path has its stamp. One that names the hash of the declarations
 * of a .sharp file the compiled file imports holds while that file is there with its stamp, or while the file's own
 * compiled file names that hash, so a body edit to an import leaves its importers current. The edited import is
 * refused itself until it compiles, so no importer runs its new code. */
static bool sharp_input_holds(zend_string *path, size_t root_length, uint64_t size, int64_t mtime_ns,
	const uint8_t hash[16], const uint8_t declarations_hash[16])
{
	static const uint8_t none[16] = {0};
	sharp_stamp *stamp;

	if (memcmp(declarations_hash, none, sizeof(none)) == 0) {
		return sharp_stamp_current(path, size, mtime_ns, hash);
	}
	stamp = sharp_stamp_of(path);
	if (!stamp->exists) {
		return false;
	}
	if (stamp->hashed ? memcmp(stamp->hash, hash, sizeof(stamp->hash)) == 0
			: stamp->size == size && stamp->mtime_ns == mtime_ns) {
		return true;
	}

	return memcmp(sharp_compiled_declarations(path, root_length, stamp), declarations_hash, sizeof(none)) == 0;
}

/* Whether an input is as it was compiled. mago compile records an input inside the project by its path under the root,
 * and one outside it, as a file of an includes folder that is a symlink to a folder elsewhere, by its absolute path,
 * which it reads as is, so the engine does too. */
static bool sharp_input_current(const sharp_compiled *compiled, const sharp_input *input)
{
	const char *recorded = compiled->unit.texts + input->path.offset;
	zend_string *path = IS_ABSOLUTE_PATH(recorded, input->path.len)
		? zend_string_init(recorded, input->path.len, 0)
		: zend_string_concat3(ZSTR_VAL(compiled->source), compiled->root_length, "/", 1, recorded, input->path.len);
	bool current = sharp_input_holds(path, compiled->root_length, input->size, input->mtime_ns, input->hash,
		input->declarations_hash);

	zend_string_release(path);

	return current;
}

/* Whether a compiled file was compiled against a standard library built for this engine's native bodies: its native
 * fingerprint is SHARP_NATIVE, or all zero, as a project without the standard library compiles. Every loader reaches
 * this through sharp_check, opcache's revalidation too, through sharp_compiled_revision. */
static bool sharp_native_current(const uint8_t fingerprint[16])
{
	static const char digits[] = "0123456789abcdef";
	static const uint8_t none[16] = {0};
	char hex[32];

	if (memcmp(fingerprint, none, sizeof(none)) == 0) {
		return true;
	}
	for (int i = 0; i < 16; i++) {
		hex[2 * i] = digits[fingerprint[i] >> 4];
		hex[2 * i + 1] = digits[fingerprint[i] & 15];
	}

	return memcmp(hex, SHARP_NATIVE, sizeof(hex)) == 0;
}

/* The refusal of a standard library built for other native bodies than this engine's, which names the version Composer
 * installed it at, without a leading v, and the two fixes. version is empty when Composer reports none. */
static zend_string *sharp_native_refusal(const char *version, size_t length)
{
	if (length > 1 && version[0] == 'v' && version[1] >= '0' && version[1] <= '9') {
		version++;
		length--;
	}
	if (length == 0) {
		return zend_string_init(ZEND_STRL("This project's PHP# library was built for another PHP# engine than "
			PHP_SHARP_VERSION ". Require heyjordanparker/php-sharp-composer " PHP_SHARP_VERSION
			", or install the PHP# engine the library was built for."), 0);
	}
	if (zend_binary_strcmp(version, length, ZEND_STRL(PHP_SHARP_VERSION)) == 0) {
		return zend_string_init(ZEND_STRL("This project's PHP# library and the PHP# engine are both " PHP_SHARP_VERSION
			", but were built from different sources. Rebuild the PHP# engine, or reinstall"
			" heyjordanparker/php-sharp-composer."), 0);
	}

	return zend_strpprintf(0, "This project's PHP# library is %.*s, and the PHP# engine is " PHP_SHARP_VERSION
		". Install PHP# %.*s, or require heyjordanparker/php-sharp-composer " PHP_SHARP_VERSION ".",
		(int) length, version, (int) length, version);
}

static void sharp_hash_bytes(const char *bytes, size_t length, uint8_t hash[16])
{
	PHP_XXH3_128_CTX context = {0};

	PHP_XXH3_128_Init(&context, NULL);
	PHP_XXH3_128_Update(&context, (const unsigned char *) bytes, length);
	PHP_XXH3_128_Final(hash, &context);
}

/* Whether the file's inputs, tree and texts, every byte after its header, are the ones mago wrote. */
static bool sharp_sections_intact(const zend_string *bytes)
{
	const sharp_unit_header *header = (const sharp_unit_header *) ZSTR_VAL(bytes);
	uint8_t hash[16];

	sharp_hash_bytes(ZSTR_VAL(bytes) + sizeof(sharp_unit_header), ZSTR_LEN(bytes) - sizeof(sharp_unit_header), hash);

	return memcmp(hash, header->sections_hash, sizeof(hash)) == 0;
}

static bool sharp_kind_declared(sharp_kind kind)
{
	switch (kind) {
#define SHARP_KIND_CASE(name) case SHARP_AST_##name:
		SHARP_KINDS(SHARP_KIND_CASE)
#undef SHARP_KIND_CASE
			return true;
		default:
			return false;
	}
}

/* Whether sharp_translate builds the node: a value with a tag sharp_value names, a declaration with its 5 children, a
 * list, or another kind php-src declares with as many children as the kind takes. ZEND_AST_CONSTANT, ZEND_AST_OP_ARRAY
 * and ZEND_AST_ZNODE are no nodes the parser makes. */
static bool sharp_node_fits(const sharp_node *node)
{
	zend_ast_kind kind = (zend_ast_kind) node->kind;

	if (!sharp_kind_declared(node->kind)) {
		return false;
	}
	if (kind == ZEND_AST_ZVAL) {
		return node->value <= SHARP_STRING;
	}
	if ((kind >> ZEND_AST_IS_LIST_SHIFT) & 1) {
		return true;
	}
	if (kind >= ZEND_AST_FUNC_DECL && kind <= ZEND_AST_PROPERTY_HOOK) {
		return node->child_count == 5;
	}

	return !((kind >> ZEND_AST_SPECIAL_SHIFT) & 1) && node->child_count == kind >> ZEND_AST_NUM_CHILDREN_SHIFT;
}

/* Whether sharp_translate turns the tree into a zend_ast: the root and every child name a node or none, each text and
 * child range lies inside its section, each node fits its kind, and each child comes before its parent, as the bridge
 * writes them, so no node holds itself. Each node the root reaches has one parent, so the nodes it reaches are a tree,
 * which sharp_translate builds once per node, where a node two parents share would be built once per path to it. */
static bool sharp_tree_fits(const sharp_unit_header *header, const sharp_unit *unit)
{
	if (header->root >= header->node_count) {
		return false;
	}
	for (uint32_t i = 0; i < header->node_count; i++) {
		const sharp_node *node = &unit->nodes[i];

		if (!sharp_text_fits(header, node->text)
				|| (uint64_t) node->first_child + node->child_count > header->children_count
				|| !sharp_node_fits(node)) {
			return false;
		}
		for (uint32_t c = 0; c < node->child_count; c++) {
			uint32_t child = unit->children[node->first_child + c];

			if (child != UINT32_MAX && child >= i) {
				return false;
			}
		}
	}

	/* A parent comes after each of its children, so a walk down from the root meets every parent of a node before
	 * the node, and a node it has reached already has a second parent. */
	zend_bitset reached = ecalloc(zend_bitset_len(header->root + 1), ZEND_BITSET_ELM_SIZE);
	bool tree = true;
	zend_bitset_incl(reached, header->root);
	for (uint32_t i = header->root + 1; tree && i-- > 0;) {
		if (!zend_bitset_in(reached, i)) {
			continue;
		}
		const sharp_node *node = &unit->nodes[i];
		for (uint32_t c = 0; tree && c < node->child_count; c++) {
			uint32_t child = unit->children[node->first_child + c];
			if (child == UINT32_MAX) {
				continue;
			}
			tree = !zend_bitset_in(reached, child);
			zend_bitset_incl(reached, child);
		}
	}
	efree(reached);

	return tree;
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
			+ header->texts_size != ZSTR_LEN(compiled->bytes)) {
		return SHARP_NOT_COMPILED;
	}

	inputs = (const sharp_input *) (bytes + sizeof(sharp_unit_header));
	compiled->unit.nodes = (const sharp_node *) (inputs + header->input_count);
	compiled->unit.children = (const uint32_t *) (compiled->unit.nodes + header->node_count);
	compiled->unit.texts = (const char *) (compiled->unit.children + header->children_count);
	compiled->unit.root = header->root;

	if (!sharp_text_fits(header, header->library_version)) {
		return SHARP_NOT_COMPILED;
	}
	if (!sharp_native_current(header->native_fingerprint)) {
		return SHARP_OTHER_LIBRARY;
	}
	if (source) {
		uint8_t hash[16];

		sharp_hash_bytes(source, source_length, hash);
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
	/* The revision opcache asks for reads no node, so only a compile pays for these. */
	if (source && (!sharp_sections_intact(compiled->bytes) || !sharp_tree_fits(header, &compiled->unit))) {
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

/* Refuses a file with every error the compile command's checker reported for it, in the order they appear in the file,
 * each with its notes and its help, as mago compile prints them. The report covers every file the command compiled,
 * lists errors by checking pass rather than by line, and counts lines from 0. Returns false when the report holds no
 * error for the file. */
static ZEND_COLD bool sharp_refuse_checked(const sharp_compiled *compiled, const char *name)
{
	zval *issues = sharp_member(&SHARP_G(compile_command_report), "issues", IS_ARRAY), *issue;
	struct { zend_long offset, line; zval *issue; } *errors;
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
		errors[at].issue = issue;
	} ZEND_HASH_FOREACH_END();

	if (count > 0) {
		smart_str refusal = {0};

		smart_str_append_printf(&refusal, "%s has %u error%s:", name, count, count == 1 ? "" : "s");
		for (uint32_t i = 0; i < count; i++) {
			zval *notes = sharp_member(errors[i].issue, "notes", IS_ARRAY), *note;
			zval *help = sharp_member(errors[i].issue, "help", IS_STRING);

			smart_str_append_printf(&refusal, "\nline " ZEND_LONG_FMT ": ", errors[i].line);
			smart_str_append(&refusal, Z_STR_P(sharp_member(errors[i].issue, "message", IS_STRING)));
			if (notes) {
				ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(notes), note) {
					if (Z_TYPE_P(note) == IS_STRING) {
						smart_str_appends(&refusal, "\n  ");
						smart_str_append(&refusal, Z_STR_P(note));
					}
				} ZEND_HASH_FOREACH_END();
			}
			if (help) {
				smart_str_appends(&refusal, "\n  help: ");
				smart_str_append(&refusal, Z_STR_P(help));
			}
		}
		smart_str_0(&refusal);
		zend_throw_exception(zend_ce_compile_error, ZSTR_VAL(refusal.s), 0);
		smart_str_free(&refusal);
	}
	efree(errors);

	return count > 0;
}

/* Refuses a file the checker didn't refuse with what is wrong with its compiled file, and, when the request's compile
 * command failed, with how it failed. */
static ZEND_COLD void sharp_refuse(const sharp_compiled *compiled, sharp_state state)
{
	const char *name = ZSTR_VAL(compiled->source) + (compiled->rooted ? compiled->root_length + 1 : 0);
	zend_string *refusal = NULL;

	if (sharp_refuse_checked(compiled, name)) {
		return;
	}
	switch (state) {
		case SHARP_NOT_COMPILED: {
			/* A worker of an extension host loads its file before mago compiled the files outside the host's code for
			 * the hosts, so the compile the refusal would name is the one running. */
			size_t root_length;
			zend_string *hosts = sharp_host_compiled(ZSTR_VAL(compiled->source), ZSTR_LEN(compiled->source), &root_length);

			refusal = hosts
				? zend_strpprintf(0, "%s isn't compiled for the extension hosts yet: a host's file runs as its worker "
					"starts, before mago compiles the files outside the host's code, which is the folder of the host's "
					"file and every file named from there. Move it under that folder or name it from the host's code.",
					name)
				: zend_strpprintf(0, "%s isn't compiled. Run vendor/bin/mago compile.", name);
			if (hosts) {
				zend_string_release(hosts);
			}
			break;
		}
		case SHARP_OTHER_ENGINE:
			refusal = zend_strpprintf(0,
				"%s was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.", name);
			break;
		case SHARP_OTHER_LIBRARY: {
			const sharp_unit_header *header = (const sharp_unit_header *) ZSTR_VAL(compiled->bytes);

			refusal = sharp_native_refusal(
				compiled->unit.texts + header->library_version.offset, header->library_version.len);
			break;
		}
		case SHARP_OUT_OF_DATE:
			refusal = zend_strpprintf(0, "%s is out of date (%.*s changed). Run vendor/bin/mago compile.",
				name, (int) compiled->changed_length, compiled->changed);
			break;
		EMPTY_SWITCH_DEFAULT_CASE();
	}
	if (SHARP_G(compile_command_failure)) {
		zend_string *failure = SHARP_G(compile_command_failure);
		zend_string *explained = zend_string_concat3(
			ZSTR_VAL(refusal), ZSTR_LEN(refusal), " ", 1, ZSTR_VAL(failure), ZSTR_LEN(failure));

		zend_string_release(refusal);
		refusal = explained;
	}
	zend_throw_exception(zend_ce_compile_error, ZSTR_VAL(refusal), 0);
	zend_string_release(refusal);
}

/* Decodes the checker report stream holds into the request's report for its refusals, and closes stream. A report that
 * is no JSON leaves null. */
static void sharp_decode_report(php_stream *stream)
{
	zend_string *printed = php_stream_copy_to_mem(stream, PHP_STREAM_COPY_ALL, false);

	php_stream_close(stream);
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

/* The variable sharp.compile_command runs with, and that mago sets on every process it starts. */
#define SHARP_COMPILING "SHARP_COMPILING"
/* How much of what a failed compile command wrote to stderr its refusal shows: its last lines, of its last bytes.
 * mago's own failures write one line, as the 140 bytes of "ERROR can't write the file `…`: Permission denied" for a
 * project its user can't write. */
#define SHARP_FAILURE_LINES 3
#define SHARP_FAILURE_BYTES 1024

/* Whether a file that isn't current runs sharp.compile_command: when it is set, and this process doesn't run inside a
 * compile, as the command itself and every process mago starts do, with SHARP_COMPILING set. mago compile starts the
 * extension hosts, which are php too, so a host that loads a file the checker refused would otherwise compile again,
 * and that compile's hosts again, with no end. The process's own environment decides, never a request's. */
static bool sharp_compiles_on_demand(void)
{
	return SHARP_G(compile_command) && *SHARP_G(compile_command) && !getenv(SHARP_COMPILING);
}

/* The descriptor of a temporary file, or -1. */
static int sharp_temporary_fd(php_stream *stream)
{
	int fd = -1;

	if (!stream || php_stream_cast(stream, PHP_STREAM_AS_FD, (void **) &fd, false) != SUCCESS) {
		return -1;
	}

	return fd;
}

/* Appends to failure the last SHARP_FAILURE_LINES lines of the last SHARP_FAILURE_BYTES bytes of the file stream holds,
 * each trimmed, after ": " and joined by an indented line break, and closes stream. A line cut by the byte limit shows
 * its end. */
static void sharp_append_stderr(smart_str *failure, php_stream *stream)
{
	char tail[SHARP_FAILURE_BYTES];
	const char *lines[SHARP_FAILURE_LINES];
	size_t lengths[SHARP_FAILURE_LINES];
	uint32_t count = 0;
	zend_off_t size;
	ssize_t kept;
	size_t end;

	php_stream_seek(stream, 0, SEEK_END);
	size = php_stream_tell(stream);
	php_stream_seek(stream, size > SHARP_FAILURE_BYTES ? size - SHARP_FAILURE_BYTES : 0, SEEK_SET);
	kept = php_stream_read(stream, tail, sizeof(tail));
	php_stream_close(stream);
	end = kept > 0 ? (size_t) kept : 0;
	while (count < SHARP_FAILURE_LINES) {
		size_t start;

		while (end > 0 && isspace((unsigned char) tail[end - 1])) {
			end--;
		}
		if (end == 0) {
			break;
		}
		for (start = end; start > 0 && tail[start - 1] != '\n'; start--);
		lines[count] = tail + start;
		lengths[count] = end - start;
		while (lengths[count] > 0 && isspace((unsigned char) *lines[count])) {
			lines[count]++;
			lengths[count]--;
		}
		count++;
		end = start;
	}
	for (uint32_t i = count; i-- > 0;) {
		smart_str_appends(failure, i + 1 == count ? ": " : "\n  ");
		smart_str_appendl(failure, lines[i], lengths[i]);
	}
}

/* Why the compile command failed, or NULL when it exited 0: it could not start, a signal killed it, or it exited with
 * another status, each with what it wrote to stderr last. Closes errors. */
static zend_string *sharp_command_failure(pid_t child, int start_error, int status, php_stream *errors)
{
	const char *command = SHARP_G(compile_command);
	smart_str failure = {0};

	if (child < 0) {
		smart_str_append_printf(&failure, "`%s` could not start: %s", command, strerror(start_error));
	} else if (WIFSIGNALED(status)) {
		smart_str_append_printf(&failure, "`%s` was killed by signal %d", command, WTERMSIG(status));
	} else if (WIFEXITED(status) && WEXITSTATUS(status) != 0) {
		smart_str_append_printf(&failure, "`%s` failed with exit %d", command, WEXITSTATUS(status));
	}
	if (errors && failure.s) {
		sharp_append_stderr(&failure, errors);
	} else if (errors) {
		php_stream_close(errors);
	}
	smart_str_0(&failure);

	return failure.s;
}

/* The file in a project's .sharp folder whose lock a request holds while it checks its file again and runs
 * sharp.compile_command, so the project compiles once at a time. mago compile keeps it. */
#define SHARP_COMPILE_LOCK "compile.lock"

/* Whether the wait of a request ended because the request did: it timed out, or FrankenPHP stops its worker. Either
 * interrupts a wait on Linux, where the timer and FrankenPHP's stop signal the thread. */
static bool sharp_request_ending(void)
{
	return zend_atomic_bool_load_ex(&EG(timed_out));
}

static void sharp_unlock_compiles(void)
{
	if (SHARP_G(compile_lock) >= 0) {
		close(SHARP_G(compile_lock));
		SHARP_G(compile_lock) = -1;
	}
}

/* Waits until the request holds the compile lock of the project whose root is the first root_length bytes of path, a
 * compile another request runs there first included, and keeps its descriptor in compile_lock. Returns false, holding
 * nothing, when the request ends while it waits, and the request then runs no compile. A lock that can't be taken, as
 * in a folder the request can't write, leaves the request to run the command, which reports the folder. */
static bool sharp_lock_compiles(const char *path, size_t root_length)
{
	zend_string *lock = zend_strpprintf(0, "%.*s/" SHARP_COMPILED_FOLDER "/" SHARP_COMPILE_LOCK, (int) root_length, path);
	int fd = open(ZSTR_VAL(lock), O_RDWR | O_CREAT | O_CLOEXEC, 0666);

	zend_string_release(lock);
	while (fd >= 0 && flock(fd, LOCK_EX) != 0) {
		if (errno != EINTR || sharp_request_ending()) {
			close(fd);
			fd = -1;
		}
	}
	SHARP_G(compile_lock) = fd;
	if (sharp_request_ending()) {
		sharp_unlock_compiles();
		return false;
	}

	return true;
}

/* The script of the process that ends a compile's process group when the process that started the compile ends first,
 * as a FrankenPHP killed mid-compile does: its stdin is a pipe only the engine writes to, which reads its end once no
 * process holds it open. It sends SIGTERM to the group, which mago passes on to the groups it started its own processes
 * in, then SIGKILL to what is left a second later, itself included. */
#define SHARP_COMPILE_WATCHER "read -r _; trap '' TERM; kill -s TERM 0; sleep 1; kill -s KILL 0"

/* Starts the process that ends the process group of the compile leader when the pipe it reads from closes, and
 * returns it, or -1. It joins the group, or exits at once, so its signals never reach the engine's own group. */
static pid_t sharp_watch_compile(pid_t leader, int pipe_read)
{
	pid_t watcher = fork();

	if (watcher == 0) {
		int nowhere = open("/dev/null", O_RDWR);
		sigset_t none;

		sigemptyset(&none);
#ifdef __linux__
		/* FrankenPHP kills each child its thread forks when the thread ends, which would end the watcher first. */
		prctl(PR_SET_PDEATHSIG, 0);
#endif
		if (setpgid(0, leader) != 0 || sigprocmask(SIG_SETMASK, &none, NULL) != 0 || nowhere < 0
				|| dup2(pipe_read, STDIN_FILENO) < 0 || dup2(nowhere, STDOUT_FILENO) < 0
				|| dup2(nowhere, STDERR_FILENO) < 0) {
			_exit(127);
		}
		execl("/bin/sh", "sh", "-c", SHARP_COMPILE_WATCHER, (char *) NULL);
		_exit(127);
	}

	return watcher;
}

/* Waits for the compile leader to exit, and leaves it to be reaped, so its id still names its group. Returns false when
 * the request ends first. */
static bool sharp_wait_for_exit(pid_t leader)
{
	siginfo_t exited;

	while (waitid(P_PID, leader, &exited, WEXITED | WNOWAIT) != 0) {
		if (errno != EINTR || sharp_request_ending()) {
			return false;
		}
	}

	return true;
}

static void sharp_reap(pid_t child, int *status)
{
	while (waitpid(child, status, 0) < 0 && errno == EINTR);
}

/* Runs sharp.compile_command through /bin/sh in the project root, the first root_length bytes of path, and waits for
 * it however long it takes. The command runs with MAGO_REPORTING_FORMAT=json, and the checker report it prints is
 * decoded for the request's refusals, and with SHARP_COMPILING=1, so nothing it starts compiles again. What it prints
 * goes to temporary files, read once it exits: a pipe would also wait on every process it leaves running. When it
 * fails, the request's refusals say how.
 * The command runs in a process group of its own, which ends with the request that started it: once the command
 * exits, every process it left running is killed. A request that ends first, by its timeout or as FrankenPHP stops its
 * worker, and a process that ends first, as one killed mid-compile, leave the group to the watcher, which ends it. */
static void sharp_run_compile_command(const char *path, size_t root_length)
{
	char *root = estrndup(path, root_length ? root_length : 1);
	php_stream *output = php_stream_fopen_tmpfile();
	php_stream *errors = php_stream_fopen_tmpfile();
	int output_fd = sharp_temporary_fd(output);
	int errors_fd = sharp_temporary_fd(errors);
	int watch[2] = {-1, -1};
	int start_error = 0;
	int status = 0;
	pid_t child;

	ZVAL_NULL(&SHARP_G(compile_command_report));
	if (pipe(watch) != 0 || fcntl(watch[0], F_SETFD, FD_CLOEXEC) != 0 || fcntl(watch[1], F_SETFD, FD_CLOEXEC) != 0) {
		start_error = errno;
		child = -1;
	} else {
		child = fork();
	}
	if (child == 0) {
		int nowhere = open("/dev/null", O_RDWR);
		sigset_t none;

		sigemptyset(&none);
		if (setpgid(0, 0) != 0 || sigprocmask(SIG_SETMASK, &none, NULL) != 0 || chdir(root) != 0 || nowhere < 0
				|| dup2(nowhere, STDIN_FILENO) < 0
				|| dup2(output_fd >= 0 ? output_fd : nowhere, STDOUT_FILENO) < 0
				|| dup2(errors_fd >= 0 ? errors_fd : nowhere, STDERR_FILENO) < 0) {
			_exit(127);
		}
		execl("/usr/bin/env", "env", "MAGO_REPORTING_FORMAT=json", SHARP_COMPILING "=1", "/bin/sh", "-c",
			SHARP_G(compile_command), (char *) NULL);
		_exit(127);
	}
	if (child < 0) {
		start_error = start_error ? start_error : errno;
	} else {
		/* Also here, so the group exists before the watcher joins it, whichever process runs first. */
		setpgid(child, child);
		pid_t watcher = sharp_watch_compile(child, watch[0]);

		if (sharp_wait_for_exit(child) || watcher < 0) {
			kill(-child, SIGKILL);
		}
		close(watch[1]);
		watch[1] = -1;
		if (watcher > 0) {
			int ended;

			sharp_reap(watcher, &ended);
		}
		sharp_reap(child, &status);
	}
	for (int i = 0; i < 2; i++) {
		if (watch[i] >= 0) {
			close(watch[i]);
		}
	}
	efree(root);
	if (output) {
		php_stream_rewind(output);
		sharp_decode_report(output);
	}
	SHARP_G(compile_command_failure) = sharp_command_failure(child, start_error, status, errors);
}

/* For a worker of an extension host, reads report.json, the checker's report mago wrote beside the files it compiled
 * for the hosts, for the refusal of each file it could not compile. */
static void sharp_read_host_report(const sharp_compiled *compiled)
{
	size_t root_length;
	zend_string *folder = sharp_host_compiled(ZSTR_VAL(compiled->source), ZSTR_LEN(compiled->source), &root_length);
	zend_string *path;
	php_stream *report;

	if (!folder) {
		return;
	}
	ZVAL_NULL(&SHARP_G(compile_command_report));
	path = zend_string_concat2(ZSTR_VAL(folder), ZSTR_LEN(folder), "/report.json", sizeof("/report.json") - 1);
	report = php_stream_open_wrapper(ZSTR_VAL(path), "rb", STREAM_DISABLE_OPEN_BASEDIR, NULL);
	zend_string_release(path);
	zend_string_release(folder);
	if (report) {
		sharp_decode_report(report);
	}
}

/* Reads the compiled file of filename again, as a compile may have rewritten it, and checks it against source. */
static sharp_state sharp_check_again(sharp_compiled *compiled, const char *filename, const char *source,
	size_t source_length)
{
	sharp_release(compiled);
	sharp_locate(compiled, filename);

	return sharp_check(compiled, source, source_length);
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
	/* A file with no root has no project to compile, and the current folder is no guess for one. A compile another
	 * request ran while this one waited for the lock may have compiled the file. */
	if (state != SHARP_CURRENT && compiled.rooted
			&& sharp_compiles_on_demand() && Z_ISUNDEF(SHARP_G(compile_command_report))
			&& sharp_lock_compiles(ZSTR_VAL(compiled.source), compiled.root_length)) {
		state = sharp_check_again(&compiled, filename, source, source_length);
		if (state != SHARP_CURRENT) {
			sharp_run_compile_command(ZSTR_VAL(compiled.source), compiled.root_length);
			state = sharp_check_again(&compiled, filename, source, source_length);
		}
		sharp_unlock_compiles();
	}
	if (state != SHARP_CURRENT && compiled.rooted && Z_ISUNDEF(SHARP_G(compile_command_report))) {
		sharp_read_host_report(&compiled);
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

HashTable *sharp_equality_operators(void)
{
	if (!SHARP_G(equality_operators)) {
		ALLOC_HASHTABLE(SHARP_G(equality_operators));
		zend_hash_init(SHARP_G(equality_operators), 8, NULL, sharp_free_pointer, false);
	}

	return SHARP_G(equality_operators);
}

static zend_op_array *sharp_compile_file(zend_file_handle *file_handle, int type)
{
	if (!file_handle->filename || !sharp_is_sharp_file(file_handle->filename)) {
		return sharp_next_compile_file(file_handle, type);
	}

	zend_op_array *op_array = zend_compile_file_with(file_handle, type, sharp_parse);

	/* A file the engine refuses was never included, so a later require_once compiles it again and gives the same
	 * refusal, where PHP would skip it and leave its classes undeclared. */
	if (!op_array && file_handle->opened_path) {
		zend_hash_del(&EG(included_files), file_handle->opened_path);
	}

	return op_array;
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

/* The table of the Map or the Set `object` whose entries the running code reads. Spec section 12: PHP# code reads a
 * Map's entries only after each one plain PHP wrote into, as `$map["k"][] = $element` does, is checked against the
 * Map's type, so this throws the TypeError and gives NULL for one that isn't of it. Each entry it takes is no reference
 * any more, unless plain PHP still holds that reference. Plain PHP code reads the entries as they are, so it never
 * throws. Every read of a table's entries, by an index, a loop, a method of the table, a Sharp\Internal function, a
 * crossing, json_encode() or serialize(), takes the table here. sharp_table_of gives it to a write, and to the engine's
 * own bookkeeping, which reads no entry for the running code. */
static sharp_table *sharp_table_entries(zend_object *object);

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

/* Appends the value `element` holds to the List `list` being built, never a reference: a List holds values, spec
 * section 12, and a `ref` change through an element that threw leaves the element a reference, which the new List
 * would share otherwise. */
static zend_always_inline void sharp_list_add_value(HashTable *list, zval *element)
{
	zval value;
	ZVAL_COPY_DEREF(&value, element);
	zend_hash_next_index_insert_new(list, &value);
}

/* Replaces the list at array with a renumbered copy: value goes in at index, or, with no value, the
 * element at index leaves. A List's keys are its positions. It gives the old list in `old`, which the
 * caller releases last, so a destructor an element runs sees the changed list. */
static void sharp_list_renumber(zval *array, zend_long index, zval *value, zval *old)
{
	HashTable *elements = Z_ARRVAL_P(array);
	zval changed;
	zval *element;
	zend_long position = 0;

	array_init_size(&changed, zend_hash_num_elements(elements) + (value ? 1 : 0));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (position == index && value) {
			sharp_list_add_value(Z_ARRVAL(changed), value);
		}
		if (position != index || value) {
			sharp_list_add_value(Z_ARRVAL(changed), element);
		}
		position++;
	} ZEND_HASH_FOREACH_END();
	if (position == index && value) {
		sharp_list_add_value(Z_ARRVAL(changed), value);
	}

	ZVAL_COPY_VALUE(old, array);
	ZVAL_COPY_VALUE(array, &changed);
}

static bool sharp_table_made_with(zend_execute_data *execute_data, zend_object *table, zend_string *text, uint32_t argument);

/* A List's entries: the Map of each index to its element, of the type arguments `type_arguments`, which `entries()`
 * takes as its second argument, spells. */
static void sharp_list_entries(zend_execute_data *execute_data, HashTable *list, zend_string *type_arguments,
	zval *return_value)
{
	object_init_ex(return_value, sharp_ce_map);
	if (!sharp_table_made_with(execute_data, Z_OBJ_P(return_value), type_arguments, 2)) {
		RETURN_THROWS();
	}
	sharp_table *table = sharp_table_of(Z_OBJ_P(return_value));
	zend_ulong index;
	zval *element;
	ZEND_HASH_FOREACH_NUM_KEY_VAL(list, index, element) {
		if (sharp_out_of_memory()) {
			RETURN_THROWS();
		}
		zval key;
		ZVAL_LONG(&key, (zend_long) index);
		sharp_table_put(table, &key, element);
	} ZEND_HASH_FOREACH_END();
}

/* A Map's keys or values, or a Set's elements, which are its keys: the List of them, each as it was stored, in
 * insertion order. */
static void sharp_table_list(sharp_table *table, bool values, zval *return_value)
{
	array_init_size(return_value, table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *listed = values ? &bucket->value : &bucket->key;
		ZVAL_DEREF(listed);
		Z_TRY_ADDREF_P(listed);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), listed);
	} SHARP_TABLE_FOREACH_END();
}

/* The List a Map's or a Set's toList gives, which var_dump shows too: a Map's entries, each the tuple
 * [key, value] (spec sections 12 and 30), since a key need not be a PHP array key, and a Set's
 * elements, in insertion order. */
static HashTable *sharp_table_to_list(zend_object *object)
{
	sharp_table *table = sharp_table_of(object);
	HashTable *list = zend_new_array(table->size);

	SHARP_TABLE_FOREACH(table, bucket) {
		if (sharp_out_of_memory()) {
			break;
		}
		zval listed;
		if (object->ce == sharp_ce_map) {
			array_init_size(&listed, 2);
			zval value;
			Z_TRY_ADDREF(bucket->key);
			ZVAL_COPY_DEREF(&value, &bucket->value);
			zend_hash_next_index_insert_new(Z_ARRVAL(listed), &bucket->key);
			zend_hash_next_index_insert_new(Z_ARRVAL(listed), &value);
		} else {
			ZVAL_COPY(&listed, &bucket->key);
		}
		zend_hash_next_index_insert_new(list, &listed);
	} SHARP_TABLE_FOREACH_END();

	return list;
}

/* The elements of the Map or the Set `object` a function reads, in a new list, in insertion order: a Set's elements and
 * a Map's values. */
static zend_array *sharp_table_elements(zend_object *object)
{
	bool set = object->ce == sharp_ce_set;
	sharp_table *table = sharp_table_of(object);
	zend_array *elements = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *element = set ? &bucket->key : &bucket->value;
		ZVAL_DEREF(element);
		Z_TRY_ADDREF_P(element);
		zend_hash_next_index_insert_new(elements, element);
	} SHARP_TABLE_FOREACH_END();

	return elements;
}

/* Calls the function a member takes with one element. Returns false when the function threw. */
static bool sharp_call_on_element(zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *element, zval *result)
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
static void sharp_table_filter(zend_object *object, zend_fcall_info *fci, zend_fcall_info_cache *fcc,
	zval *return_value)
{
	bool set = object->ce == sharp_ce_set;
	zval kept, keep;

	GC_ADDREF(object);
	object_init_ex(&kept, object->ce);
	/* The entries a filter keeps are of the type the Map or the Set has. */
	sharp_table_object_of(Z_OBJ(kept))->arguments = sharp_table_object_of(object)->arguments;
	sharp_table *kept_table = sharp_table_of(Z_OBJ(kept));
	SHARP_TABLE_FOREACH(sharp_table_of(object), bucket) {
		zval *value = &bucket->value;
		ZVAL_DEREF(value);
		if (!sharp_call_on_element(fci, fcc, set ? &bucket->key : value, &keep)) {
			break;
		}
		bool is_kept = zend_is_true(&keep);
		zval_ptr_dtor(&keep);
		if (is_kept && !sharp_table_put(kept_table, &bucket->key, value)) {
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

/* Spec section 12: a List's filter renumbers what it keeps. */
static void sharp_filter(zval *held, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	zend_array *elements = Z_ARRVAL_P(held);
	GC_TRY_ADDREF(elements);
	zval kept, keep;
	zval *element;
	array_init(&kept);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &keep)) {
			break;
		}
		if (zend_is_true(&keep)) {
			sharp_list_add_value(Z_ARRVAL(kept), element);
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

/* What the function gives for each of `elements`, which it releases. */
static void sharp_map(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	zval mapped, value;
	zval *element;
	array_init_size(&mapped, zend_hash_num_elements(elements));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &value)) {
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

/* Adds what the function gives for each of `elements`, which it releases, as PHP#'s + does, which throws where an int
 * sum overflows. */
static void sharp_sum_of(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	zval sum, value;
	zval *element;
	ZVAL_LONG(&sum, 0);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &value)) {
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

/* The first of `elements` the function returns true for, or NULL when none is or the function threw. */
static zval *sharp_find(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc)
{
	zval found;
	zval *element;
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &found)) {
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

/* Kotlin's first: the first of `elements`, which it releases, the function returns true for, and an exception when
 * there is none, as a bare index read throws for a missing index. */
static void sharp_first(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	zval *element = sharp_find(elements, fci, fcc);

	if (element) {
		ZVAL_COPY_DEREF(return_value, element);
	} else if (!EG(exception)) {
		zend_throw_exception(spl_ce_OutOfRangeException, "`first` finds an element the predicate matches, and none does.", 0);
	}
	zend_array_release(elements);
}

/* Whether the function returns true for any of `elements`, which it releases. */
static void sharp_any(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	bool found = sharp_find(elements, fci, fcc) != NULL;

	zend_array_release(elements);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	RETURN_BOOL(found);
}

/* Puts each element in a Map under the key the function gives it: in a list of the elements under that
 * key, or alone, where the last element with a key replaces the ones before it. Any key a Map takes
 * goes in as it is, found by PHP#'s == (spec section 12). The Map has the type arguments PHP# code passes. */
static void sharp_key_by(zend_execute_data *execute_data, zend_array *elements, zend_fcall_info *fci,
	zend_fcall_info_cache *fcc, zend_string *type_arguments, uint32_t type_argument, bool group, zval *return_value)
{
	zval map, key;
	zval *element;
	object_init_ex(&map, sharp_ce_map);
	if (!sharp_table_made_with(execute_data, Z_OBJ(map), type_arguments, type_argument)) {
		zval_ptr_dtor(&map);
		zend_array_release(elements);
		RETURN_THROWS();
	}
	sharp_table *table = sharp_table_of(Z_OBJ(map));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &key)) {
			break;
		}
		sharp_table_bucket *bucket = group ? sharp_table_find(table, &key) : NULL;
		bool put = !EG(exception);
		if (bucket) {
			SEPARATE_ARRAY(&bucket->value);
			sharp_list_add_value(Z_ARRVAL(bucket->value), element);
		} else if (put && group) {
			zval list;
			array_init(&list);
			sharp_list_add_value(Z_ARRVAL(list), element);
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

/* Orders two keys as PHP#'s < on two keys of the key type does, spec sections 12 and 19, -1, 0 or 1. A null key sorts
 * before every other key, as Kotlin's sortedBy puts it, since PHP's <=> compares null with a number as two bools, which
 * is no order. `order` is what the bridge passes for the key type: the operator <=> the checker chose for it, a list of
 * one order per element for a tuple key, or NULL. Two tuples order element by element, as Rust orders tuples, two other
 * keys by the operator, two strings by their bytes, and the rest as PHP's <=> does. A call that throws leaves the
 * exception for the caller to throw. */
static int sharp_order_keys(zval *left, zval *right, zval *order)
{
	if (Z_TYPE_P(left) == IS_NULL || Z_TYPE_P(right) == IS_NULL) {
		return (Z_TYPE_P(left) != IS_NULL) - (Z_TYPE_P(right) != IS_NULL);
	}
	if (Z_TYPE_P(left) == IS_ARRAY && Z_TYPE_P(right) == IS_ARRAY) {
		HashTable *orders = order && Z_TYPE_P(order) == IS_ARRAY ? Z_ARRVAL_P(order) : NULL;
		uint32_t left_count = zend_hash_num_elements(Z_ARRVAL_P(left));
		uint32_t right_count = zend_hash_num_elements(Z_ARRVAL_P(right));
		for (uint32_t index = 0; index < MIN(left_count, right_count); index++) {
			int result = sharp_order_keys(zend_hash_index_find(Z_ARRVAL_P(left), index),
				zend_hash_index_find(Z_ARRVAL_P(right), index), orders ? zend_hash_index_find(orders, index) : NULL);
			if (result || EG(exception)) {
				return result;
			}
		}

		return ZEND_THREEWAY_COMPARE(left_count, right_count);
	}
	if (order && Z_TYPE_P(order) == IS_OBJECT) {
		zend_function *operator = (zend_function *) zend_get_closure_method_def(Z_OBJ_P(order));
		zval keys[2], result;
		ZVAL_COPY_VALUE(&keys[0], left);
		ZVAL_COPY_VALUE(&keys[1], right);
		ZVAL_UNDEF(&result);
		zend_call_known_function(operator, NULL, operator->common.scope, &result, 2, keys, NULL);

		return Z_TYPE(result) == IS_LONG ? ZEND_NORMALIZE_BOOL(Z_LVAL(result)) : 0;
	}
	if (Z_TYPE_P(left) == IS_STRING && Z_TYPE_P(right) == IS_STRING) {
		return ZEND_NORMALIZE_BOOL(zend_binary_zval_strcmp(left, right));
	}

	return zend_compare(left, right);
}

/* What one sort orders by, spec section 12: by its keys, as sharp_order_keys orders them with `order`, or, for
 * sortedWith, by `comparison`, the function it takes, called on two elements. `descending` sorts the largest first. */
typedef struct sharp_sort {
	zval *order;
	zend_fcall_info *comparison;
	zend_fcall_info_cache *comparison_cache;
	bool descending;
} sharp_sort;

/* Orders two [key, element] pairs by key, as SHARP_G(sort) says. Equal keys keep their original order, which
 * zend_hash_sort keeps in each bucket's extra space, so the sort is stable as Kotlin's is, descending too. A call that
 * throws leaves the exception for the sort to throw. */
static int sharp_compare_sort_keys(Bucket *a, Bucket *b)
{
	zval *left = zend_hash_index_find(Z_ARRVAL(a->val), 0);
	zval *right = zend_hash_index_find(Z_ARRVAL(b->val), 0);
	sharp_sort *sort = SHARP_G(sort);
	int result;

	if (sort->comparison) {
		zval keys[2], order;
		ZVAL_COPY_VALUE(&keys[0], left);
		ZVAL_COPY_VALUE(&keys[1], right);
		sort->comparison->retval = &order;
		sort->comparison->params = keys;
		sort->comparison->param_count = 2;
		zend_call_function(sort->comparison, sort->comparison_cache);
		result = Z_TYPE(order) == IS_LONG ? ZEND_NORMALIZE_BOOL(Z_LVAL(order)) : 0;
		zval_ptr_dtor(&order);
	} else {
		result = sharp_order_keys(left, right, sort->order);
	}

	if (result) {
		return sort->descending ? -result : result;
	}

	return Z_EXTRA(a->val) < Z_EXTRA(b->val) ? -1 : Z_EXTRA(a->val) > Z_EXTRA(b->val);
}

/* `elements`, which it releases, ordered as `sort` says, by the key the function gives each, or by each element itself
 * where `fci` is NULL. */
static void sharp_sorted_by(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, sharp_sort *sort,
	zval *return_value)
{
	zval pairs, pair, key;
	zval *element;
	array_init_size(&pairs, zend_hash_num_elements(elements));
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!fci) {
			ZVAL_COPY_DEREF(&key, element);
		} else if (!sharp_call_on_element(fci, fcc, element, &key)) {
			break;
		}
		array_init_size(&pair, 2);
		zend_hash_next_index_insert_new(Z_ARRVAL(pair), &key);
		sharp_list_add_value(Z_ARRVAL(pair), element);
		zend_hash_next_index_insert_new(Z_ARRVAL(pairs), &pair);
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (!EG(exception)) {
		/* A key's operator may sort a list of its own, so the outer sort comes back after it. */
		sharp_sort *outer = SHARP_G(sort);
		SHARP_G(sort) = sort;
		zend_hash_sort(Z_ARRVAL(pairs), sharp_compare_sort_keys, true);
		SHARP_G(sort) = outer;
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
	/* What plain PHP's foreach reads of a class that is an `Iterable<T>`, spec section 12: each element as plain PHP
	 * receives it, under the keys 0, 1, 2 and on, or, where `count` is 1, each `[key, value]` tuple as its key and
	 * value. */
	SHARP_SEQUENCE_PLAIN,
} sharp_sequence_stage;

/* One stage of a lazy chain: source is the iterable for from and check, and the Sequence before it for every other
 * stage. A check stage checks each value against `checked`, and a bad one throws a TypeError whose message starts with
 * `head`. It makes each PHP array it takes the Map or the Set `checked` names, as `crossing`, its plan, says. */
typedef struct {
	sharp_sequence_stage stage;
	zend_long count;
	zval source;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	const sharp_type *checked;
	zend_string *head;
	zval crossing;
	zend_object std;
} sharp_sequence;

static bool sharp_crossed(zval *result, zval *value, const zval *plan, const sharp_type *type);
static void sharp_type_plan(zval *plan, const sharp_type *type);

/* One reading of a stage: it.data holds the Sequence, and source reads the stage before it, or is NULL for an array.
 * key is the key a plain stage of pairs gives, and is undefined for every other stage, whose keys count its values. */
typedef struct {
	zend_object_iterator it;
	zend_object_iterator *source;
	HashPosition position;
	zend_long index;
	zval value;
	zval key;
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
	zval_ptr_dtor(&sequence->crossing);
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

	return sharp_call_on_element(&fci, &sequence->fcc, element, result);
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
static void sharp_type_renumber(const sharp_type *type, zval *value);
static void sharp_closure_check(const sharp_type *type, zval *value);

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
			case SHARP_SEQUENCE_PLAIN: {
				zval *part = &element;
				/* A tuple is a PHP list of its parts, so a pair's key is its first part. */
				if (sequence->count && Z_TYPE(element) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL(element)) == 2) {
					zval *key = zend_hash_index_find(Z_ARRVAL(element), 0);
					zval *second = zend_hash_index_find(Z_ARRVAL(element), 1);
					if (key && second) {
						ZVAL_COPY_DEREF(&iterator->key, key);
						part = second;
					}
				}
				sharp_array_of(value, part);
				zval_ptr_dtor(&element);
				return;
			}
			case SHARP_SEQUENCE_CHECK:
				sharp_type_renumber(sequence->checked, &element);
				if (!EG(exception) && sharp_type_accepts(sequence->checked, &element, NULL)) {
					/* Spec section 12: a PHP array a plain PHP source gives enters as the Map or the Set it is. */
					if (Z_TYPE(sequence->crossing) == IS_ARRAY && Z_TYPE(element) == IS_ARRAY) {
						zval crossed;
						bool is_crossed = sharp_crossed(&crossed, &element, &sequence->crossing, sequence->checked);
						if (!is_crossed && !EG(exception)) {
							sharp_sequence_refuse(sequence, &element, iterator->index);
						}
						zval_ptr_dtor(&element);
						if (!is_crossed) {
							return;
						}
						ZVAL_COPY_VALUE(&element, &crossed);
					}
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
	zval_ptr_dtor(&iterator->key);
	ZVAL_UNDEF(&iterator->key);
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
	zval_ptr_dtor(&iterator->key);
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

/* Keys count the values this stage gave, from 0, whatever keys its source had, except a pair's own key. */
static void sharp_sequence_iterator_get_current_key(zend_object_iterator *object, zval *key)
{
	sharp_sequence_iterator *iterator = (sharp_sequence_iterator *) object;

	if (Z_ISUNDEF(iterator->value)) {
		ZVAL_NULL(key);
	} else if (!Z_ISUNDEF(iterator->key)) {
		ZVAL_COPY(key, &iterator->key);
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
	zend_get_gc_buffer_add_zval(buffer, &iterator->key);
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
	ZVAL_UNDEF(&iterator->key);

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

ZEND_METHOD(Sharp_Internal_Sequence, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* A Sequence is already one, so a local that holds a chain's result chains on without a second stage. */
ZEND_METHOD(Sharp_Internal_Sequence, from)
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

ZEND_METHOD(Sharp_Internal_Sequence, filter)
{
	sharp_sequence_closure_stage(INTERNAL_FUNCTION_PARAM_PASSTHRU, SHARP_SEQUENCE_FILTER);
}

ZEND_METHOD(Sharp_Internal_Sequence, map)
{
	sharp_sequence_closure_stage(INTERNAL_FUNCTION_PARAM_PASSTHRU, SHARP_SEQUENCE_MAP);
}

ZEND_METHOD(Sharp_Internal_Sequence, take)
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

/* Appends each value the Sequence `sequence` gives to the array `list`. Returns false when reading threw. */
static bool sharp_sequence_append(zval *sequence, zval *list)
{
	sharp_sequence_iterator *iterator =
		(sharp_sequence_iterator *) sharp_sequence_get_iterator(sharp_ce_sequence, sequence, 0);
	if (!iterator) {
		return false;
	}

	sharp_sequence_iterator_next(iterator, true);
	while (!EG(exception) && !Z_ISUNDEF(iterator->value) && !sharp_out_of_memory()) {
		Z_TRY_ADDREF(iterator->value);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(list), &iterator->value);
		sharp_sequence_iterator_next(iterator, false);
	}
	zend_iterator_dtor(&iterator->it);

	return !EG(exception);
}

ZEND_METHOD(Sharp_Internal_Sequence, toList)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zval list;
	array_init(&list);
	if (!sharp_sequence_append(ZEND_THIS, &list)) {
		zval_ptr_dtor(&list);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&list);
}

/* Spec section 12: a List's flatMap, the elements of each Iterable the function gives, in order, each read as a
 * Sequence reads its source. */
static void sharp_flat_map(zend_array *elements, zend_fcall_info *fci, zend_fcall_info_cache *fcc, zval *return_value)
{
	zval flat, part, sequence;
	zval *element;
	array_init(&flat);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(fci, fcc, element, &part)) {
			break;
		}
		if (Z_TYPE(part) != IS_ARRAY
			&& (Z_TYPE(part) != IS_OBJECT || !instanceof_function(Z_OBJCE(part), zend_ce_traversable))) {
			zend_type_error(
				"flatMap() takes a function that gives an Iterable, and it gave %s", zend_zval_value_name(&part));
			zval_ptr_dtor(&part);
			break;
		}
		sharp_sequence_new(&sequence, SHARP_SEQUENCE_FROM, &part);
		zval_ptr_dtor(&part);
		bool appended = sharp_sequence_append(&sequence, &flat);
		zval_ptr_dtor(&sequence);
		if (!appended) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	zend_array_release(elements);

	if (EG(exception)) {
		zval_ptr_dtor(&flat);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&flat);
}

/* Spec section 12: the standard library's members of a List, a Map and a Set run these, each with the collection first.
 * A member that changes the collection takes it by reference, from the place a `ref` receiver passes, and changes it
 * there. It finds the change first and separates a shared one only then, a List as SEPARATE_ARRAY does for a write and
 * a Map or a Set as SHARP_SEPARATE does, so a `remove` that finds nothing and an index outside the List leave it shared
 * (design section 5). One that renumbers builds the changed List anew in sharp_list_renumber, so it separates nothing.
 * One that reads it takes it by value. The functions of a table, a Map's or a Set's, are named for its keys, which are
 * a Set's elements. */

static ZEND_COLD void sharp_throw_empty_list(void)
{
	zend_throw_exception(spl_ce_OutOfRangeException, "The list is empty.", 0);
}

static ZEND_COLD void sharp_throw_missing_index(zend_long index)
{
	zend_throw_exception_ex(spl_ce_OutOfRangeException, 0, "Undefined array key " ZEND_LONG_FMT, index);
}

/* Whether `list`, the first argument of each function here, is a list, and a ValueError that names the function when it
 * is not. Plain PHP can pass any array, and in one whose keys are not its positions, an element found by key would be
 * removed or renumbered by position. A packed array without holes is a list at once. */
static bool sharp_list_argument(const HashTable *list)
{
	if (EXPECTED(zend_array_is_list(list))) {
		return true;
	}
	zend_argument_value_error(1, "must be a list");

	return false;
}

/* `add`: the value goes after the last element. */
ZEND_FUNCTION(Sharp_Internal_add)
{
	zval *list, *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	SEPARATE_ARRAY(list);
	Z_TRY_ADDREF_P(value);
	if (!zend_hash_next_index_insert(Z_ARRVAL_P(list), value)) {
		zval_ptr_dtor(value);
		zend_cannot_add_element();
		RETURN_THROWS();
	}
}

/* Kotlin's add(index, element): the value goes in at index, from 0 to the count, and the elements from index on move up
 * one. Outside that range it throws as `set` does past the end. */
ZEND_FUNCTION(Sharp_Internal_insert)
{
	zval *list, *value;
	zend_long index;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
		Z_PARAM_LONG(index)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	if (index < 0 || index > (zend_long) zend_hash_num_elements(Z_ARRVAL_P(list))) {
		sharp_throw_missing_index(index);
		RETURN_THROWS();
	}
	zval old;
	sharp_list_renumber(list, index, value, &old);
	zval_ptr_dtor(&old);
}

/* Kotlin's set(index, element), which `list[i] = v` runs: the value replaces the element at index, and past either end
 * it throws, where PHP's write would append or leave a gap (spec section 12). It gives the element it replaced, as
 * Kotlin's does. */
ZEND_FUNCTION(Sharp_Internal_set)
{
	zval *list, *value;
	zend_long index;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
		Z_PARAM_LONG(index)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	if ((zend_ulong) index >= zend_hash_num_elements(Z_ARRVAL_P(list))) {
		sharp_throw_missing_index(index);
		RETURN_THROWS();
	}
	SEPARATE_ARRAY(list);
	zval *element = zend_hash_index_find(Z_ARRVAL_P(list), index);
	/* The old element leaves the list before it is given, so a destructor it runs sees the changed list. */
	zval old;
	ZVAL_COPY_VALUE(&old, element);
	ZVAL_COPY(element, value);
	RETVAL_COPY_DEREF(&old);
	zval_ptr_dtor(&old);
}

/* The index of the first element of `list` equal to `value` by PHP#'s ==, as sharp_equal compares them, or -1 when
 * none is, or -2 after an exception an operator == threw. */
static zend_long sharp_list_index_of(HashTable *list, zval *value)
{
	zend_ulong index;
	zval *element;
	ZEND_HASH_FOREACH_NUM_KEY_VAL(list, index, element) {
		bool equal;
		if (!sharp_equal(element, value, &equal)) {
			return -2;
		}
		if (equal) {
			return (zend_long) index;
		}
	} ZEND_HASH_FOREACH_END();

	return -1;
}

/* A List's remove(value): removes the first element equal to the value by PHP#'s ==, renumbers the elements after it,
 * and gives whether it removed one (decision 047). A List without one is not changed. */
ZEND_FUNCTION(Sharp_Internal_remove)
{
	zval *list, *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	zend_long index = sharp_list_index_of(Z_ARRVAL_P(list), value);
	if (index == -2) {
		RETURN_THROWS();
	}
	if (index == -1) {
		RETURN_FALSE;
	}
	zval old;
	sharp_list_renumber(list, index, NULL, &old);
	zval_ptr_dtor(&old);
	RETURN_TRUE;
}

/* Kotlin's removeAt: removes the element at index, renumbers the ones after it and gives the removed one. An empty List
 * throws as `last` does, and an index outside a List that holds elements as `set` does. */
ZEND_FUNCTION(Sharp_Internal_removeAt)
{
	zval *list;
	zend_long index;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
		Z_PARAM_LONG(index)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	uint32_t count = zend_hash_num_elements(Z_ARRVAL_P(list));
	if (count == 0) {
		sharp_throw_empty_list();
		RETURN_THROWS();
	}
	if ((zend_ulong) index >= count) {
		sharp_throw_missing_index(index);
		RETURN_THROWS();
	}
	ZVAL_COPY_DEREF(return_value, zend_hash_index_find(Z_ARRVAL_P(list), index));
	zval old;
	sharp_list_renumber(list, index, NULL, &old);
	zval_ptr_dtor(&old);
}

/* Kotlin's removeLast: removes the last element and gives it, as array_pop does, and an empty List throws as `last`
 * does. */
ZEND_FUNCTION(Sharp_Internal_removeLast)
{
	zval *list;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_EX2(list, 0, 1, 0)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	uint32_t count = zend_hash_num_elements(Z_ARRVAL_P(list));
	if (count == 0) {
		sharp_throw_empty_list();
		RETURN_THROWS();
	}
	SEPARATE_ARRAY(list);
	HashTable *elements = Z_ARRVAL_P(list);
	zend_long last = (zend_long) count - 1;
	ZVAL_COPY_DEREF(return_value, zend_hash_index_find(elements, last));
	zend_hash_index_del(elements, last);
	elements->nNextFreeElement = last;
	zend_hash_internal_pointer_reset(elements);
}

/* The table a function of a Map or a Set reads, `collection` its argument `argument`, with each entry plain PHP wrote
 * checked as sharp_table_entries checks it, or NULL after the error for any other value. */
static sharp_table *sharp_table_argument(zval *collection, uint32_t argument)
{
	if (UNEXPECTED(!SHARP_IS_TABLE(collection))) {
		zend_argument_type_error(argument, "must be of type Sharp\\Map|Sharp\\Set, %s given",
			zend_zval_value_name(collection));
		return NULL;
	}

	return sharp_table_entries(Z_OBJ_P(collection));
}

/* `clear`: the List, the Map or the Set becomes an empty one of its kind, and a Map or a Set keeps its type. The old
 * elements are released last, so a destructor one runs sees the empty collection. */
ZEND_FUNCTION(Sharp_Internal_clear)
{
	zval *collection;

	/* The reference is followed, and a shared collection is left to its other holders unseparated. */
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(collection)
	ZEND_PARSE_PARAMETERS_END();

	ZVAL_DEREF(collection);
	zval old;
	if (Z_TYPE_P(collection) == IS_ARRAY) {
		if (!sharp_list_argument(Z_ARRVAL_P(collection))) {
			RETURN_THROWS();
		}
		ZVAL_COPY_VALUE(&old, collection);
		ZVAL_EMPTY_ARRAY(collection);
	} else {
		if (!sharp_table_argument(collection, 1)) {
			RETURN_THROWS();
		}
		ZVAL_COPY_VALUE(&old, collection);
		object_init_ex(collection, Z_OBJCE(old));
		sharp_table_object_of(Z_OBJ_P(collection))->arguments = sharp_table_object_of(Z_OBJ(old))->arguments;
	}
	zval_ptr_dtor(&old);
}

/* A List's `getOrNull(i)`: the element at index, or null past either end. A Map's `get(key)`: the value of the key, or
 * null when the Map holds no equal key. */
ZEND_FUNCTION(Sharp_Internal_get)
{
	zval *collection, *key;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	if (Z_TYPE_P(collection) == IS_ARRAY) {
		if (!sharp_list_argument(Z_ARRVAL_P(collection))) {
			RETURN_THROWS();
		}
		if (UNEXPECTED(Z_TYPE_P(key) != IS_LONG)) {
			zend_argument_type_error(2, "must be of type int for a List, %s given", zend_zval_value_name(key));
			RETURN_THROWS();
		}
		zval *element = zend_hash_index_find(Z_ARRVAL_P(collection), Z_LVAL_P(key));
		if (element) {
			RETURN_COPY_DEREF(element);
		}
		return;
	}
	if (UNEXPECTED(!SHARP_IS_TABLE(collection) || Z_OBJCE_P(collection) != sharp_ce_map)) {
		zend_argument_type_error(1, "must be of type Sharp\\Map|array, %s given", zend_zval_value_name(collection));
		RETURN_THROWS();
	}
	sharp_table *table = sharp_table_entries(Z_OBJ_P(collection));
	sharp_table_bucket *bucket = table ? sharp_table_find(table, key) : NULL;
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (bucket) {
		RETURN_COPY_DEREF(&bucket->value);
	}
}

/* A Map's and a Set's `count()`: how many keys it holds, which the table keeps. */
ZEND_FUNCTION(Sharp_Internal_size)
{
	zval *table;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(table)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *entries = sharp_table_argument(table, 1);
	if (!entries) {
		RETURN_THROWS();
	}
	RETURN_LONG(entries->size);
}

/* A Map's `has(key)` and a Set's `contains(element)`: whether it holds a key equal to the value, a key whose value is
 * null included. */
ZEND_FUNCTION(Sharp_Internal_containsKey)
{
	zval *table, *key;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(table)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *entries = sharp_table_argument(table, 1);
	bool held = entries && sharp_table_find(entries, key);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	RETURN_BOOL(held);
}

/* A Map's and a Set's `remove`: removes the key equal to the value, and gives whether there was one. One without it is
 * not changed, so a shared one does not separate. */
ZEND_FUNCTION(Sharp_Internal_removeKey)
{
	zval *table, *key;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(table)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	ZVAL_DEREF(table);
	sharp_table *entries = sharp_table_argument(table, 1);
	bool held = entries && sharp_table_find(entries, key);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (!held) {
		RETURN_FALSE;
	}
	SHARP_SEPARATE(table);
	if (!sharp_table_remove(sharp_table_of(Z_OBJ_P(table)), key)) {
		RETURN_THROWS();
	}
	RETURN_TRUE;
}

/* A Set's `add`: adds the element when the Set holds no equal one, and gives whether it did, so a Set that holds it
 * already is not changed and does not separate. */
ZEND_FUNCTION(Sharp_Internal_addKey)
{
	zval *set, *element;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_OBJECT_OF_CLASS_EX(set, sharp_ce_set, 0, 1)
		Z_PARAM_ZVAL(element)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *entries = sharp_table_entries(Z_OBJ_P(set));
	bool held = entries && sharp_table_find(entries, element);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (held) {
		RETURN_FALSE;
	}
	SHARP_SEPARATE(set);
	zval null;
	ZVAL_NULL(&null);
	if (!sharp_table_put(sharp_table_of(Z_OBJ_P(set)), element, &null)) {
		RETURN_THROWS();
	}
	RETURN_TRUE;
}

/* A Map's `set`, which `map[key] = value` runs: inserts the key with the value, or replaces the value of an equal key. */
ZEND_FUNCTION(Sharp_Internal_put)
{
	zval *map, *key, *value;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_OBJECT_OF_CLASS_EX(map, sharp_ce_map, 0, 1)
		Z_PARAM_ZVAL(key)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	SHARP_SEPARATE(map);
	if (!sharp_table_put(sharp_table_of(Z_OBJ_P(map)), key, value)) {
		RETURN_THROWS();
	}
}

/* `getOrPut`: the value of the key, or, when the Map holds no equal key, what the function gives, put under the key
 * first, as Kotlin's getOrPut. */
ZEND_FUNCTION(Sharp_Internal_getOrPut)
{
	zval *map, *key;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_OBJECT_OF_CLASS_EX(map, sharp_ce_map, 0, 1)
		Z_PARAM_ZVAL(key)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *table = sharp_table_entries(Z_OBJ_P(map));
	sharp_table_bucket *bucket = table ? sharp_table_find(table, key) : NULL;
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (bucket) {
		RETURN_COPY_DEREF(&bucket->value);
	}

	zval value;
	fci.retval = &value;
	fci.params = NULL;
	fci.param_count = 0;
	zend_call_function(&fci, &fcc);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	/* The function may have changed the Map, so it separates only now. */
	SHARP_SEPARATE(map);
	if (!sharp_table_put(sharp_table_of(Z_OBJ_P(map)), key, &value)) {
		zval_ptr_dtor(&value);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&value);
}

/* A Map's `keys()` and a Set's `toList()`: the List of its keys, which are a Set's elements. */
ZEND_FUNCTION(Sharp_Internal_keys)
{
	zval *table;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(table)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *entries = sharp_table_argument(table, 1);
	if (!entries) {
		RETURN_THROWS();
	}
	sharp_table_list(entries, false, return_value);
}

/* A Map's `values()`: the List of its values. */
ZEND_FUNCTION(Sharp_Internal_values)
{
	zend_object *map;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJ_OF_CLASS(map, sharp_ce_map)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table *entries = sharp_table_entries(map);
	if (!entries) {
		RETURN_THROWS();
	}
	sharp_table_list(entries, true, return_value);
}

/* A Map's `toList()`: the List of its entries, each the tuple [key, value], as sharp_table_to_list gives it. */
ZEND_FUNCTION(Sharp_Internal_pairs)
{
	zend_object *map;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_OBJ_OF_CLASS(map, sharp_ce_map)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_table_entries(map)) {
		RETURN_THROWS();
	}
	RETURN_ARR(sharp_table_to_list(map));
}

/* Whether an element equals the value by PHP#'s ==. */
ZEND_FUNCTION(Sharp_Internal_contains)
{
	HashTable *list;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_HT(list)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(list)) {
		RETURN_THROWS();
	}
	zend_long index = sharp_list_index_of(list, value);
	if (index == -2) {
		RETURN_THROWS();
	}
	RETURN_BOOL(index >= 0);
}

/* The index of the first element equal to the value by PHP#'s ==, or null when none is. */
ZEND_FUNCTION(Sharp_Internal_indexOf)
{
	HashTable *list;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY_HT(list)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(list)) {
		RETURN_THROWS();
	}
	zend_long index = sharp_list_index_of(list, value);
	if (index == -2) {
		RETURN_THROWS();
	}
	if (index >= 0) {
		RETURN_LONG(index);
	}
}

/* The List itself, or OutOfRangeException when it is empty: `last`, `max` and `min` read their element after it. */
ZEND_FUNCTION(Sharp_Internal_nonEmpty)
{
	zval *list;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY(list)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	if (zend_hash_num_elements(Z_ARRVAL_P(list)) == 0) {
		sharp_throw_empty_list();
		RETURN_THROWS();
	}
	RETURN_COPY(list);
}

/* `entries()`: the Map of each index to its element, of the type arguments the bridge passes for the call's result. */
ZEND_FUNCTION(Sharp_Internal_entries)
{
	HashTable *list;
	zend_string *type_arguments = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ARRAY_HT(list)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(type_arguments)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(list)) {
		RETURN_THROWS();
	}
	sharp_list_entries(execute_data, list, type_arguments, return_value);
}

static bool sharp_set_fill(zend_object *set, HashTable *values);

/* `toSet()`: the Set of the elements, each once, in the order they first appear, of the type arguments the bridge passes
 * for the call's result. */
ZEND_FUNCTION(Sharp_Internal_toSet)
{
	HashTable *list;
	zend_string *type_arguments = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ARRAY_HT(list)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(type_arguments)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(list)) {
		RETURN_THROWS();
	}
	object_init_ex(return_value, sharp_ce_set);
	if (!sharp_table_made_with(execute_data, Z_OBJ_P(return_value), type_arguments, 2)
			|| !sharp_set_fill(Z_OBJ_P(return_value), list)) {
		RETURN_THROWS();
	}
}

/* The elements a member that takes a function reads, held while the function runs, since the function may change the
 * List: a change then separates the List from what this reads. */
static zend_array *sharp_list_elements(zval *list)
{
	GC_TRY_ADDREF(Z_ARRVAL_P(list));

	return Z_ARRVAL_P(list);
}

/* The elements a member of any collection that takes a function reads, `collection` its first argument: a List's, as
 * sharp_list_elements holds them, or a Map's or a Set's, as sharp_table_elements lists them. NULL after the error for
 * any other value. */
static zend_array *sharp_elements_of(zval *collection)
{
	if (Z_TYPE_P(collection) == IS_ARRAY) {
		return sharp_list_argument(Z_ARRVAL_P(collection)) ? sharp_list_elements(collection) : NULL;
	}
	if (UNEXPECTED(!SHARP_IS_TABLE(collection))) {
		zend_argument_type_error(1, "must be of type Sharp\\Map|Sharp\\Set|array, %s given",
			zend_zval_value_name(collection));
		return NULL;
	}

	return sharp_table_entries(Z_OBJ_P(collection)) ? sharp_table_elements(Z_OBJ_P(collection)) : NULL;
}

ZEND_FUNCTION(Sharp_Internal_filter)
{
	zval *list;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY(list)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	sharp_filter(list, &fci, &fcc, return_value);
}

/* A Map's `filter`: the Map of the entries whose value the function keeps, of the Map's type. */
ZEND_FUNCTION(Sharp_Internal_filterEntries)
{
	zend_object *map;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_OBJ_OF_CLASS(map, sharp_ce_map)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_table_entries(map)) {
		RETURN_THROWS();
	}
	sharp_table_filter(map, &fci, &fcc, return_value);
}

/* A Set's `filter`: the Set of the elements the function keeps, of the Set's type. */
ZEND_FUNCTION(Sharp_Internal_filterElements)
{
	zend_object *set;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_OBJ_OF_CLASS(set, sharp_ce_set)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_table_entries(set)) {
		RETURN_THROWS();
	}
	sharp_table_filter(set, &fci, &fcc, return_value);
}

ZEND_FUNCTION(Sharp_Internal_map)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_map(elements, &fci, &fcc, return_value);
}

ZEND_FUNCTION(Sharp_Internal_flatMap)
{
	zval *list;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY(list)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	sharp_flat_map(sharp_list_elements(list), &fci, &fcc, return_value);
}

ZEND_FUNCTION(Sharp_Internal_sumOf)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_sum_of(elements, &fci, &fcc, return_value);
}

ZEND_FUNCTION(Sharp_Internal_first)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_first(elements, &fci, &fcc, return_value);
}

ZEND_FUNCTION(Sharp_Internal_any)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_any(elements, &fci, &fcc, return_value);
}

static void sharp_internal_key_by(INTERNAL_FUNCTION_PARAMETERS, bool group)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	zend_string *type_arguments = NULL;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(type_arguments)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_key_by(execute_data, elements, &fci, &fcc, type_arguments, 3, group, return_value);
}

ZEND_FUNCTION(Sharp_Internal_groupBy)
{
	sharp_internal_key_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

ZEND_FUNCTION(Sharp_Internal_associateBy)
{
	sharp_internal_key_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

/* `firstOrNull`: the first element the function returns true for, or null when none is, as Kotlin's. */
ZEND_FUNCTION(Sharp_Internal_firstOrNull)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	zval *element = sharp_find(elements, &fci, &fcc);
	if (element) {
		ZVAL_COPY_DEREF(return_value, element);
	}
	zend_array_release(elements);
}

/* The key order a sorting function takes last, which the bridge passes, spec section 12: the operator <=> of the key
 * type as a Closure, a list of the orders of a tuple key's elements, or null. False after the error for any other
 * value. */
static bool sharp_key_order_argument(zval *order)
{
	if (Z_TYPE_P(order) == IS_NULL || Z_TYPE_P(order) == IS_ARRAY
			|| (Z_TYPE_P(order) == IS_OBJECT && Z_OBJCE_P(order) == zend_ce_closure)) {
		return true;
	}
	zend_argument_type_error(3, "must be of type Closure|array|null, %s given", zend_zval_value_name(order));

	return false;
}

/* `sortedBy` and `sortedByDescending`: the elements in the order of the key the function gives each, by the key order
 * the bridge passes, equal keys in their order. */
static void sharp_internal_sorted_by(INTERNAL_FUNCTION_PARAMETERS, bool descending)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	zval *order = NULL;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(order)
	ZEND_PARSE_PARAMETERS_END();

	if (order && !sharp_key_order_argument(order)) {
		RETURN_THROWS();
	}
	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	sharp_sort sort = {.order = order, .descending = descending};
	sharp_sorted_by(elements, &fci, &fcc, &sort, return_value);
}

ZEND_FUNCTION(Sharp_Internal_sortedBy)
{
	sharp_internal_sorted_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

ZEND_FUNCTION(Sharp_Internal_sortedByDescending)
{
	sharp_internal_sorted_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

/* `sortedWith`: the elements of a List in the order the comparison gives two of them, equal ones in their order, as
 * Kotlin's sortedWith and Rust's sort_by. */
ZEND_FUNCTION(Sharp_Internal_sortedWith)
{
	zval *list;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ARRAY(list)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_list_argument(Z_ARRVAL_P(list))) {
		RETURN_THROWS();
	}
	sharp_sort sort = {.comparison = &fci, .comparison_cache = &fcc};
	sharp_sorted_by(sharp_list_elements(list), NULL, NULL, &sort, return_value);
}

/* `minBy` and `maxBy`: the first element whose key is the smallest, or the largest where `sign` is -1, by the key order
 * the bridge passes, and OutOfRangeException for an empty collection, as `max()` throws. */
static void sharp_internal_extreme_by(INTERNAL_FUNCTION_PARAMETERS, int sign)
{
	zval *collection;
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	zval *order = NULL;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_ZVAL(collection)
		Z_PARAM_FUNC(fci, fcc)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(order)
	ZEND_PARSE_PARAMETERS_END();

	if (order && !sharp_key_order_argument(order)) {
		RETURN_THROWS();
	}
	zend_array *elements = sharp_elements_of(collection);
	if (!elements) {
		RETURN_THROWS();
	}
	if (zend_hash_num_elements(elements) == 0) {
		zend_array_release(elements);
		sharp_throw_empty_list();
		RETURN_THROWS();
	}

	zval best_key, key;
	zval *element, *best = NULL;
	ZVAL_UNDEF(&best_key);
	ZEND_HASH_FOREACH_VAL(elements, element) {
		if (!sharp_call_on_element(&fci, &fcc, element, &key)) {
			break;
		}
		if (best && sharp_order_keys(&key, &best_key, order) * sign >= 0) {
			zval_ptr_dtor(&key);
		} else {
			zval_ptr_dtor(&best_key);
			ZVAL_COPY_VALUE(&best_key, &key);
			best = element;
		}
		if (EG(exception)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	if (!EG(exception)) {
		ZVAL_COPY_DEREF(return_value, best);
	}
	zval_ptr_dtor(&best_key);
	zend_array_release(elements);
}

ZEND_FUNCTION(Sharp_Internal_minBy)
{
	sharp_internal_extreme_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, 1);
}

ZEND_FUNCTION(Sharp_Internal_maxBy)
{
	sharp_internal_extreme_by(INTERNAL_FUNCTION_PARAM_PASSTHRU, -1);
}

ZEND_METHOD(Sharp_Internal_Sequence, getIterator)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zend_create_internal_iterator_zval(return_value, ZEND_THIS);
}

/* Spec section 12: the `getIterator()` of a class whose header names `Iterable<T>` returns
 * `\Sharp\Internal\elements($this, $pairs)`, the Sequence over `$object->elements()` the code that reads the object
 * reads: PHP# code each element, and plain PHP each element as it receives one, a Map and a Set as their PHP arrays,
 * under the keys 0, 1, 2 and on, or, with `$pairs`, each `[key, value]` tuple as its key and value. The code that reads
 * is the code before `getIterator()`'s frame, past the internal functions it called, as iterator_to_array(). */
ZEND_FUNCTION(Sharp_Internal_elements)
{
	zend_object *object;
	bool pairs;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_OBJ(object)
		Z_PARAM_BOOL(pairs)
	ZEND_PARSE_PARAMETERS_END();

	zval elements;
	zend_call_method_with_0_params(object, object->ce, NULL, "elements", &elements);
	if (UNEXPECTED(EG(exception))) {
		zval_ptr_dtor(&elements);
		RETURN_THROWS();
	}
	if (Z_TYPE(elements) != IS_ARRAY
		&& (Z_TYPE(elements) != IS_OBJECT || !instanceof_function(Z_OBJCE(elements), zend_ce_traversable))) {
		zend_type_error("%s::elements(): Return value must be of type Iterable, %s returned",
			ZSTR_VAL(object->ce->name), zend_zval_value_name(&elements));
		zval_ptr_dtor(&elements);
		RETURN_THROWS();
	}

	const zend_execute_data *iterated = EX(prev_execute_data);
	bool from_sharp = iterated && sharp_code_at(iterated->prev_execute_data);
	sharp_sequence *sequence = sharp_sequence_new(return_value,
		from_sharp ? SHARP_SEQUENCE_FROM : SHARP_SEQUENCE_PLAIN, &elements);
	sequence->count = !from_sharp && pairs;
	zval_ptr_dtor(&elements);
}

/* The standard library's autoload.php passes the SHARP_NATIVE it was built with, the native bodies it declares and its
 * package's version when vendor/autoload.php loads it, as Composer's platform_check.php checks the platform before
 * anything loads, and its plugin passes them after each install. Every version of the library calls it with these three
 * arguments, so an engine of any version refuses a library of any other with its own message. SHARP_NATIVE covers the
 * native bodies, so this engine reads none of them. */
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
	(void) bodies;

	if (!zend_string_equals_literal(fingerprint, SHARP_NATIVE)) {
		zend_string *refusal = sharp_native_refusal(ZSTR_VAL(version), ZSTR_LEN(version));

		zend_throw_error(NULL, "%s", ZSTR_VAL(refusal));
		zend_string_release(refusal);
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
 * one pointer: a single space after each comma and on each side of an intersection's `&` (`A & B`), and nowhere else,
 * union and intersection members sorted by their text, and `T?` in place of `T|null`. Types and argument lists live in two tables, because a one-argument list spells its argument.
 * A text parses whole into a tree of sharp_type_node in a request arena before any of it is interned, and the tables'
 * lock is held only across a lookup or an insert, so an allocation that fails never leaves it held.
 *
 * The process's tables keep the types code spells, which its source bounds. A type first read from input, or holding
 * one, interns in the request's own tables instead and is freed with them. A lookup reads the process's table, then
 * the request's, so a type is one pointer either way.
 *
 * The PHP request is the app: one HTTP request under php-fpm, the whole worker under a long-running host. So the
 * request's tables keep the app's run-time types, as C# keeps each generic instantiation it makes at run time for the
 * process's life. The program bounds the types its own substitutions make, as in Node<List<T>>, and a worker reuses
 * them on its next request. Only input that invents new type texts could grow the tables without limit, so they hold
 * at most SHARP_TYPE_INPUT_LIMIT types read from input. Past it, a new type from input fails as other bad input does,
 * while a substitution and every type they hold keep working. */

/* The most types the request's two tables hold that a text read from input made. */
#define SHARP_TYPE_INPUT_LIMIT 65536

/* Where the text of a type to intern comes from: code, which its source bounds, a substitution the program makes as it
 * runs, which the program bounds, or input, which nothing bounds. */
typedef enum {
	SHARP_TYPE_FROM_CODE,
	SHARP_TYPE_FROM_SUBSTITUTION,
	SHARP_TYPE_FROM_INPUT,
} sharp_type_source;

zend_string *sharp_type_arguments_key;
zend_string *sharp_statics_key;
zend_string *sharp_static_constructor_key;

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
	{"Class", 1, SHARP_BUILT_IN_CLASS}, {"never", 0, SHARP_BUILT_IN_NEVER}, {"callable", 0, SHARP_BUILT_IN_CALLABLE},
};

/* A type as its text spells it, before it is interned. */
typedef struct _sharp_type_node sharp_type_node;
struct _sharp_type_node {
	sharp_type_kind kind;
	const char *text;
	size_t length;
	/* A SHARP_TYPE_NAMED type's dotted name, or a SHARP_TYPE_PARAMETER's name, which `name_length` measures. `name` is
	 * NULL for a built-in type. */
	const char *name;
	size_t name_length;
	/* The name a tuple element is written with, `method` in `[string method, int count]`, which `label_length`
	 * measures, or NULL. */
	const char *label;
	size_t label_length;
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

/* The interned type `node` spells. It lives for the process when code spells it and each member does too. NULL when it
 * is new, read from input, and the request's tables already hold SHARP_TYPE_INPUT_LIMIT types input made. A type code
 * or a substitution spells always interns, since the source and the program bound those. */
static const sharp_type *sharp_type_intern(const sharp_type_node *node, sharp_type_source source, zend_arena **arena)
{
	bool list = node->kind == SHARP_TYPE_LIST;
	const sharp_type *found = sharp_type_find(list, node->text, node->length);

	if (found) {
		return found;
	}

	const sharp_type **members = zend_arena_alloc(arena, MAX(node->count, 1) * sizeof(*members));
	bool persistent = source == SHARP_TYPE_FROM_CODE;
	uint32_t i = 0;
	for (const sharp_type_node *member = node->first; member; member = member->next) {
		members[i] = sharp_type_intern(member, source, arena);
		if (!members[i]) {
			return NULL;
		}
		persistent = persistent && sharp_type_is_persistent(members[i]);
		i++;
	}
	if (source == SHARP_TYPE_FROM_INPUT) {
		if (SHARP_G(request_input_types) >= SHARP_TYPE_INPUT_LIMIT) {
			return NULL;
		}
		SHARP_G(request_input_types)++;
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

	/* Every node of a text starts here, so a long one read from input stops once it runs out of memory. */
	if (sharp_out_of_memory()) {
		return NULL;
	}

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
			/* An element's name follows its type after a space, where a text reflection reads writes it. */
			if (sharp_type_skip(reader, " ")) {
				const char *label = reader->at;
				if (!sharp_type_identifier(reader)) {
					return NULL;
				}
				element->label = label;
				element->label_length = reader->at - label;
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

	/* A type parameter as reflection reads it: its name, then its bound after ` : `. */
	if (sharp_type_skip(reader, " : ")) {
		type = sharp_type_node_start(reader, SHARP_TYPE_PARAMETER, start);
		type->name = start;
		type->name_length = reader->at - 3 - start;
		sharp_type_node *bound = sharp_type_read(reader);
		if (!bound || memchr(start, '.', type->name_length)) {
			return NULL;
		}
		sharp_type_node_add(type, bound);
		type->length = reader->at - start;

		return type;
	}

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
	sharp_type_node *type = first && first->kind != SHARP_TYPE_PARAMETER
		? sharp_type_read_intersection(reader, first, start)
		: first;

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
	/* A type parameter stands in parentheses alone, as a union's member or a nullable type's. */
	if (first->kind == SHARP_TYPE_PARAMETER && sharp_type_ahead(reader, ")")) {
		group = first;
	} else if (first->kind == SHARP_TYPE_NAMED && sharp_type_ahead(reader, " & ")) {
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
		/* Without `?`, only an intersection or a type parameter stands in parentheses, as the first member of a
		 * union. */
		if (type->kind != SHARP_TYPE_INTERSECTION && type->kind != SHARP_TYPE_PARAMETER) {
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

/* A type argument list, `A, B`, which is the whole text, or the one SHARP_TYPE_RESULT `: R`. */
static sharp_type_node *sharp_type_read_list(sharp_type_reader *reader)
{
	const char *start = reader->at;
	sharp_type_node *arguments = sharp_type_node_start(reader, SHARP_TYPE_LIST, start);

	if (sharp_type_skip(reader, ": ")) {
		sharp_type_node *result = sharp_type_node_start(reader, SHARP_TYPE_RESULT, start);
		sharp_type_node *returned = sharp_type_read(reader);

		if (!returned) {
			return NULL;
		}
		sharp_type_node_add(result, returned);
		result->length = reader->at - start;
		sharp_type_node_add(arguments, result);
	} else {
		do {
			sharp_type_node *argument = sharp_type_read(reader);

			if (!argument) {
				return NULL;
			}
			sharp_type_node_add(arguments, argument);
		} while (sharp_type_skip(reader, ", "));
	}
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
		list = sharp_type_intern(arguments, SHARP_TYPE_FROM_CODE, &reader.arena);
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
	/* A Map literal takes its key and value types, and a Set literal its element type, as `Map<K, V>` and `Set<T>`. */
	uint32_t count = ce == sharp_ce_map ? 2
		: ce == sharp_ce_set ? 1
		: sharp_class_is_sharp(ce) ? sharp_class_arity(ce) : 0;

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
	if (object->ce == sharp_ce_map || object->ce == sharp_ce_set) {
		ZEND_ASSERT(arguments->kind == SHARP_TYPE_LIST);
		sharp_table_object_of(object)->arguments = arguments;
		return;
	}
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
	bool grouped = member->kind == SHARP_TYPE_INTERSECTION || member->kind == SHARP_TYPE_UNION
		|| member->kind == SHARP_TYPE_PARAMETER;

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
			for (const sharp_type_node *member = node->first; member; member = member->next) {
				if (member != node->first) {
					smart_str_appends(&text, ", ");
				}
				smart_str_appendl(&text, member->text, member->length);
				if (member->label) {
					smart_str_appendc(&text, ' ');
					smart_str_appendl(&text, member->label, member->label_length);
				}
			}
			smart_str_appendc(&text, ']');
			break;
		case SHARP_TYPE_PARAMETER:
			smart_str_appendl(&text, node->name, node->name_length);
			smart_str_appends(&text, " : ");
			smart_str_appendl(&text, node->first->text, node->first->length);
			break;
		case SHARP_TYPE_RESULT:
			smart_str_appends(&text, ": ");
			smart_str_appendl(&text, node->first->text, node->first->length);
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

		const char *label = member->label;
		size_t label_length = member->label_length;
		sharp_type_node *type = sharp_type_node_substitute(member, of_class, of_method, unresolved, reader);
		if (!type) {
			return NULL;
		}
		/* A tuple element keeps its name whatever type it becomes. */
		type->label = label;
		type->label_length = label_length;
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

/* What a list sharp_type_list_of_frame spells depends on besides its text, as the key of the text's table of lists. Its
 * padding is zeroed, since the table hashes its bytes. */
typedef struct {
	const zend_class_entry *scope;
	const zend_class_entry *ce;
	const sharp_type *own;
	const sharp_type *method;
	bool unresolved;
} sharp_type_spelling;

/* The method of the trait `func` came from, when `func` is a method `scope` takes from a trait, which PHP copies into
 * the class with its opcodes, and NULL for a method `scope` declares itself. A trait that takes the method from
 * another trait holds a copy of its own, so a caller follows the copies to the trait that declares it. */
static const zend_function *sharp_trait_method(const zend_class_entry *scope, const zend_function *func)
{
	if (!scope || !func || func->type != ZEND_USER_FUNCTION || !(func->common.fn_flags & ZEND_ACC_TRAIT_CLONE)) {
		return NULL;
	}
	/* A loaded class has loaded each trait it uses. */
	for (uint32_t i = 0; i < scope->num_traits; i++) {
		const zend_class_entry *trait = zend_lookup_class_ex(
			scope->trait_names[i].name, scope->trait_names[i].lc_name, ZEND_FETCH_CLASS_NO_AUTOLOAD);
		const zend_function *original;

		ZEND_ASSERT(trait != NULL);
		ZEND_HASH_MAP_FOREACH_PTR(&trait->function_table, original) {
			if (original->type == ZEND_USER_FUNCTION && original->op_array.opcodes == func->op_array.opcodes) {
				return original;
			}
		} ZEND_HASH_FOREACH_END();
	}

	return NULL;
}

const zend_class_entry *sharp_type_scope(const zend_class_entry *scope, const zend_function *func)
{
	const zend_function *original = sharp_trait_method(scope, func);
	if (original) {
		scope = original->common.scope;
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
	 * generic service used for several entity types does, spells each once. Two sites may write one text, so the class
	 * each is written in tells them apart. The lists are kept under the text, found by its contents: a script opcache
	 * preloads runs before opcache interns its literals, so one text may be several strings. The table holds the text,
	 * so its address is never reused this request, and a lookup reuses its hash. */
	sharp_type_spelling key;
	memset(&key, 0, sizeof(key));
	key.scope = scope;
	key.ce = ce;
	key.own = own;
	key.method = method;
	key.unresolved = unresolved;
	HashTable **spelled = &SHARP_G(request_spelled_lists);
	zval *of_text = *spelled ? zend_hash_find(*spelled, Z_STR_P(text)) : NULL;
	const sharp_type *substituted = of_text ? zend_hash_str_find_ptr(Z_ARRVAL_P(of_text), (const char *) &key, sizeof(key)) : NULL;
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
	substituted = sharp_type_intern(list,
		!reader.names || (!names_this && !method) ? SHARP_TYPE_FROM_CODE : SHARP_TYPE_FROM_SUBSTITUTION, &reader.arena);
	zend_arena_destroy(reader.arena);
	if (!*spelled) {
		ALLOC_HASHTABLE(*spelled);
		zend_hash_init(*spelled, 8, NULL, ZVAL_PTR_DTOR, false);
	}
	if (!of_text) {
		zval lists;
		array_init(&lists);
		of_text = zend_hash_add_new(*spelled, Z_STR_P(text), &lists);
	}
	zend_hash_str_add_new_ptr(Z_ARRVAL_P(of_text), (const char *) &key, sizeof(key), (void *) substituted);

	/* Every user run-time cache lives for one request: zend_activate zeroes the dynamic map_ptr table, and accel_activate
	 * the preloaded caches. A list that lives for the request, which unserialize or a substitution makes, is freed at
	 * request end after every object, so the site keeps it as it keeps one the process holds. */
	cache[0] = (void *) ce;
	cache[1] = (void *) own;
	cache[2] = (void *) method;
	cache[3] = (void *) substituted;

	return substituted;
}

/* The class `name` names, for a check that needs the class's declaration, loaded through the autoloader the first time,
 * as Java and C# load a class on first use. A type argument is text, so `Crate<Apple>` exists before any code loads
 * Apple, and whether it fits `Harvest<Fruit>` depends on what Apple extends. A loaded class stays in the class table,
 * so the autoloader runs once for it. NULL when the autoloader finds no such class, and NULL with the autoloader's
 * exception left for the check's caller when it throws, which every check then stops for. Once an exception is
 * pending, nothing more runs: the class is only looked up. */
static zend_class_entry *sharp_type_class(zend_string *name)
{
	return zend_lookup_class_ex(name, NULL, UNEXPECTED(EG(exception)) ? ZEND_FETCH_CLASS_NO_AUTOLOAD : 0);
}

/* The class `node`, a SHARP_TYPE_NAMED type, names, as sharp_type_class finds it, or NULL for a built-in type. */
static zend_class_entry *sharp_type_node_class(sharp_type_node *node)
{
	if (!node->ce && node->name) {
		zend_string *name = sharp_type_class_name(node->name, node->name_length, false);
		node->ce = sharp_type_class(name);
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

const sharp_type *sharp_class_value_type(const zend_string *value)
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

/* The class the string `value` names, as PHP writes its name, `App\Order`, or as PHP# does, `App.Order`, loaded as
 * sharp_type_class loads it. NULL for a string that names no class. */
static zend_class_entry *sharp_class_value_class(const zend_string *value)
{
	zend_string *name = sharp_type_class_name(ZSTR_VAL(value), ZSTR_LEN(value), false);
	zend_class_entry *ce = ZSTR_LEN(name) ? sharp_type_class(name) : NULL;
	zend_string_release_ex(name, false);

	return ce;
}

/* The type the string `value` is the class value of, read with `reader`, whose arena holds it, or NULL for a string that
 * reads as no whole type. The class value of a type that is no class is the text `typeof` gives it, which reads only as
 * typeof spells it, and that of a class is its name, `App\Order`, which reads as `App.Order`. */
static sharp_type_node *sharp_class_value_read(const zend_string *value, sharp_type_reader *reader)
{
	char *text = zend_arena_alloc(&reader->arena, ZSTR_LEN(value));
	for (size_t i = 0; i < ZSTR_LEN(value); i++) {
		text[i] = ZSTR_VAL(value)[i] == '\\' ? '.' : ZSTR_VAL(value)[i];
	}
	*reader = sharp_type_reader_of(text, ZSTR_LEN(value), reader->arena);
	reader->depth_left = SHARP_TYPE_INPUT_DEPTH;
	sharp_type_node *given = sharp_type_read(reader);

	return given && reader->at == reader->end && !reader->names ? given : NULL;
}

/* The class value of `type`, as typeof gives it: a class's name, or the text of any other type. */
static zend_string *sharp_type_class_value(const sharp_type *type)
{
	return type->class_name ? type->class_name : type->text;
}

static int sharp_text_compare(const void *left, const void *right)
{
	const zend_string *a = *(const zend_string *const *) left;
	const zend_string *b = *(const zend_string *const *) right;

	return zend_binary_strcmp(ZSTR_VAL(a), ZSTR_LEN(a), ZSTR_VAL(b), ZSTR_LEN(b));
}

/* Appends `parts`, the texts of a union's or an intersection's members, as PHP# writes the type: in order, as
 * sharp_type_read_union and sharp_type_read_intersection read them, each between `separator`s, and an intersection in
 * a union of several members in parentheses. Releases each part. */
static void sharp_php_type_join(smart_str *text, zend_string **parts, uint32_t count, const char *separator)
{
	qsort(parts, count, sizeof(*parts), sharp_text_compare);
	for (uint32_t i = 0; i < count; i++) {
		bool grouped = count > 1 && *separator == '|' && memchr(ZSTR_VAL(parts[i]), '&', ZSTR_LEN(parts[i]));
		if (i) {
			smart_str_appends(text, separator);
		}
		if (grouped) {
			smart_str_appendc(text, '(');
		}
		smart_str_append(text, parts[i]);
		if (grouped) {
			smart_str_appendc(text, ')');
		}
		zend_string_release(parts[i]);
	}
}

/* The PHP# text of the member of a PHP union that is `length` bytes at `at`: `App\Order` as `App.Order`, `object` as
 * `Object`, `false` and `true` as the `bool` the checker reads them as, since PHP# writes no type of one value, spec
 * section 24, `array` as the `Map<int|string, Any?>` a plain PHP array arrives as, spec section 12, an intersection
 * `(A&B)` as `A & B`, without the parentheses a union holds it in, and `self`, `parent` and `static` as the class each
 * names: `self` the class `self`, `parent` its parent, and `static` the class `called` the code runs on. PHP writes the
 * class itself for `self` and `parent` outside a trait, so they reach here from a trait, naming the class that uses it. */
static zend_string *sharp_php_type_member(
	const char *at, size_t length, const zend_class_entry *self, const zend_class_entry *called)
{
	const zend_class_entry *named = NULL;
	if (length == 4 && memcmp(at, "self", 4) == 0) {
		named = self;
	} else if (length == 6 && memcmp(at, "parent", 6) == 0) {
		named = self ? self->parent : NULL;
	} else if (length == 6 && memcmp(at, "static", 6) == 0) {
		named = called;
	}
	if (named) {
		return sharp_php_type_member(ZSTR_VAL(named->name), ZSTR_LEN(named->name), NULL, NULL);
	}

	smart_str text = {0};
	if (length == 6 && memcmp(at, "object", 6) == 0) {
		smart_str_appends(&text, "Object");
	} else if ((length == 5 && memcmp(at, "false", 5) == 0) || (length == 4 && memcmp(at, "true", 4) == 0)) {
		smart_str_appends(&text, "bool");
	} else if (length == 5 && memcmp(at, "array", 5) == 0) {
		smart_str_appends(&text, "Map<int|string, Any?>");
	} else if (memchr(at, '&', length)) {
		if (*at == '(') {
			at++;
			length -= 2;
		}
		zend_string **classes = safe_emalloc(length, sizeof(*classes), 0);
		uint32_t count = 0;
		for (const char *end = at + length, *amp; at < end; at = amp + 1) {
			amp = memchr(at, '&', end - at);
			amp = amp ? amp : end;
			classes[count++] = sharp_php_type_member(at, amp - at, self, called);
		}
		sharp_php_type_join(&text, classes, count, " & ");
		efree(classes);
	} else {
		for (size_t i = 0; i < length; i++) {
			smart_str_appendc(&text, at[i] == '\\' ? '.' : at[i]);
		}
	}
	smart_str_0(&text);

	return text.s;
}

/* Appends the PHP type `type` as PHP# writes it, for a member that carries no PHP# type: each member as
 * sharp_php_type_member writes it, a union's members in order, `?int` and `int|null` as `int?`, `int|string|null` as
 * `(int|string)?`, and `mixed` and no type as `Any?`, with `self`, `parent` and `static` the classes of `self` and
 * `called`. */
static void sharp_php_type_text(
	smart_str *text, zend_type type, const zend_class_entry *self, const zend_class_entry *called)
{
	if (!ZEND_TYPE_IS_SET(type) || ZEND_TYPE_PURE_MASK(type) == MAY_BE_ANY) {
		smart_str_appends(text, "Any?");
		return;
	}
	zend_string *written = zend_type_to_string(type);
	const char *at = ZSTR_VAL(written);
	const char *end = at + ZSTR_LEN(written);
	bool nullable = *at == '?';
	at += nullable;
	if (end - at > 5 && memcmp(end - 5, "|null", 5) == 0) {
		nullable = true;
		end -= 5;
	}
	zend_string **members = safe_emalloc(end - at, sizeof(*members), 0);
	uint32_t count = 0;
	while (at < end) {
		/* A `|` inside an intersection's parentheses is no union's. */
		const char *bar = at;
		while (bar < end && *bar != '|') {
			bar = *bar == '(' ? memchr(bar, ')', end - bar) + 1 : bar + 1;
		}
		members[count++] = sharp_php_type_member(at, bar - at, self, called);
		at = bar + 1;
	}
	/* A trait's `self|Order` names one class twice once `Order` uses the trait, which PHP keeps as two members. */
	qsort(members, count, sizeof(*members), sharp_text_compare);
	uint32_t distinct = 1;
	for (uint32_t i = 1; i < count; i++) {
		if (zend_string_equals(members[i], members[distinct - 1])) {
			zend_string_release(members[i]);
		} else {
			members[distinct++] = members[i];
		}
	}
	count = distinct;
	bool grouped = nullable && (count > 1 || memchr(ZSTR_VAL(members[0]), '&', ZSTR_LEN(members[0])));
	if (grouped) {
		smart_str_appendc(text, '(');
	}
	sharp_php_type_join(text, members, count, "|");
	efree(members);
	if (grouped) {
		smart_str_appendc(text, ')');
	}
	if (nullable) {
		smart_str_appendc(text, '?');
	}
	zend_string_release(written);
}

/* A line of sharp_declared, zend.h: the type after `: `, and the words between the member's name and the `: `: what
 * writes a property, `set private` or `init protected`, and `virtual` and `override` as the declaration states them. */
typedef struct {
	const char *type;
	size_t type_length;
	const char *words;
	size_t words_length;
} sharp_declared_member;

/* The line of sharp_declared of `ce` for the member `key` names: `$tags` a property and `first` a method, by its name in
 * any case, as PHP finds a method. The first line, of the type parameters' names, has the empty key, and its names are
 * its type. False for a class-like that declares no such member, or that plain PHP declares. */
static bool sharp_declared_line(
	const zend_class_entry *ce, const char *key, size_t key_length, sharp_declared_member *member)
{
	const zend_string *declared = sharp_class_is_sharp(ce) ? ce->info.user.sharp_declared : NULL;
	if (!declared) {
		return false;
	}
	const char *end = ZSTR_VAL(declared) + ZSTR_LEN(declared);
	const char *line = ZSTR_VAL(declared);
	const char *line_end = memchr(line, '\n', end - line);
	if (!key_length) {
		*member = (sharp_declared_member) {line, (line_end ? line_end : end) - line, NULL, 0};
		return true;
	}
	while (line_end) {
		line = line_end + 1;
		line_end = memchr(line, '\n', end - line);
		const char *stop = line_end ? line_end : end;
		const char *colon = zend_memnstr(line, ": ", 2, stop);
		const char *after = line + key_length;
		if (colon && colon >= after && (after == colon || *after == ' ')
			&& zend_binary_strncasecmp(line, key_length, key, key_length, key_length) == 0) {
			*member = (sharp_declared_member) {
				colon + 2, stop - colon - 2, after == colon ? after : after + 1, after == colon ? 0 : colon - after - 1
			};
			return true;
		}
	}

	return false;
}

/* Appends the type parameters of `ce`, a PHP# class-like that declares some, as reflection reads them on its class value
 * without type arguments, spec section 25: each by its name and its bound, `TItem : App.Mark, TOther : Any?`. False for
 * any other class-like. */
static bool sharp_class_parameters(smart_str *text, const zend_class_entry *ce)
{
	sharp_declared_member line;
	const sharp_type *bounds = sharp_class_is_sharp(ce) ? sharp_class_bounds(ce) : NULL;
	if (!bounds || !sharp_declared_line(ce, NULL, 0, &line)) {
		return false;
	}
	const char *names = line.type;
	const char *end = names + line.type_length;
	for (uint32_t i = 0; i < bounds->count; i++) {
		const char *comma = memchr(names, ',', end - names);
		const char *name_end = comma ? comma : end;
		if (i) {
			smart_str_appends(text, ", ");
		}
		smart_str_appendl(text, names, name_end - names);
		smart_str_appends(text, " : ");
		smart_str_append(text, bounds->members[i]->text);
		names = comma ? comma + 2 : end;
	}

	return true;
}

/* sharp_declared_types of a text in which no `Self` stands. */
static const sharp_type *sharp_declared_types_of(const char *written, size_t length, const zend_class_entry *declaring,
	const zend_class_entry *ce, const sharp_type *arguments)
{
	sharp_type_reader reader = sharp_type_reader_of(written, length, zend_arena_create(1024));
	sharp_type_node *open = sharp_type_read_list(&reader);
	sharp_type_node *of_class = NULL;
	if (open && (reader.names & SHARP_TYPE_NAMES_THIS)) {
		smart_str own = {0};
		if (arguments) {
			smart_str_append(&own, arguments->text);
		} else {
			sharp_class_parameters(&own, ce);
		}
		smart_str_0(&own);
		sharp_type_node *given = sharp_type_node_list(own.s, &reader);
		/* The list reads the text in place, which the reader's nodes point into until the arena goes. */
		of_class = sharp_type_node_ancestor(ce, given, declaring, &reader);
		sharp_type_node *list = of_class ? sharp_type_node_substitute(open, of_class, NULL, false, &reader) : NULL;
		const sharp_type *types = list ? sharp_type_intern(list, SHARP_TYPE_FROM_SUBSTITUTION, &reader.arena) : NULL;
		smart_str_free(&own);
		zend_arena_destroy(reader.arena);

		return types;
	}
	const sharp_type *types = open && !reader.names
		? sharp_type_intern(open, SHARP_TYPE_FROM_SUBSTITUTION, &reader.arena)
		: NULL;
	zend_arena_destroy(reader.arena);

	return types;
}

/* Whether `at`, inside `text`, of `end`, starts the word `Self`, which no dotted class name and no other word holds. */
static bool sharp_text_names_self(const char *text, const char *at, const char *end)
{
	const char *after = at + strlen("Self");
	bool before_ok = at == text || !(isalnum((unsigned char) at[-1]) || at[-1] == '_' || at[-1] == '.' || at[-1] == '\\');

	return before_ok && after <= end && memcmp(at, "Self", strlen("Self")) == 0
		&& (after == end || !(isalnum((unsigned char) *after) || *after == '_'));
}

/* `written`, of `length` bytes, with each `Self` that a line of sharp_declared writes for the class a member runs on
 * replaced by the type of the class value it is read on, spec section 25, as PHP binds `static`: `ce`'s dotted name
 * with `arguments`, or with its type parameters, as sharp_class_parameters spells them, when the class value gives
 * none. NULL when `written` holds no `Self`. */
static zend_string *sharp_declared_self(
	const char *written, size_t length, const zend_class_entry *ce, const sharp_type *arguments)
{
	const char *end = written + length;
	const char *at = written;
	while (at < end && !sharp_text_names_self(written, at, end)) {
		at++;
	}
	if (at == end) {
		return NULL;
	}
	smart_str own = {0};
	for (const char *name = ZSTR_VAL(ce->name); *name; name++) {
		smart_str_appendc(&own, *name == '\\' ? '.' : *name);
	}
	smart_str parameters = {0};
	if (arguments) {
		smart_str_append(&parameters, arguments->text);
	} else {
		sharp_class_parameters(&parameters, ce);
	}
	if (parameters.s) {
		smart_str_appendc(&own, '<');
		smart_str_append_smart_str(&own, &parameters);
		smart_str_appendc(&own, '>');
	}
	smart_str_free(&parameters);
	smart_str_0(&own);
	smart_str text = {0};
	const char *from = written;
	for (; at < end; at++) {
		if (sharp_text_names_self(written, at, end)) {
			smart_str_appendl(&text, from, at - from);
			smart_str_append(&text, own.s);
			at += strlen("Self") - 1;
			from = at + 1;
		}
	}
	smart_str_appendl(&text, from, end - from);
	smart_str_free(&own);

	return smart_str_extract(&text);
}

/* The types the type list `written`, of `length` bytes, spells, which a member of `declaring` declares, read on a class
 * value of `ce` whose type arguments are `arguments`, spec section 25: with each type parameter of its class the type
 * argument the class value gives it, or, on a class value without type arguments, the type parameter itself, as
 * sharp_class_parameters spells it, and `Self` the class value's own type, as sharp_declared_self reads it. */
static const sharp_type *sharp_declared_types(const char *given, size_t given_length, const zend_class_entry *declaring,
	const zend_class_entry *ce, const sharp_type *arguments)
{
	zend_string *own = sharp_declared_self(given, given_length, ce, arguments);
	const sharp_type *types = own
		? sharp_declared_types_of(ZSTR_VAL(own), ZSTR_LEN(own), declaring, ce, arguments)
		: sharp_declared_types_of(given, given_length, declaring, ce, arguments);
	if (own) {
		zend_string_release_ex(own, false);
	}

	return types;
}

/* The access modifier PHP's flags `flags` give a member, as sharp_add_visibility adds it, without `internal`. */
static const char *sharp_php_visibility(uint32_t flags)
{
	return (flags & ZEND_ACC_PRIVATE) ? "private" : (flags & ZEND_ACC_PROTECTED) ? "protected" : "public";
}

/* Appends `word` to the words of a member's modifiers, `words`, each after a space. */
static void sharp_add_word(smart_str *words, const char *word)
{
	if (words->s && ZSTR_LEN(words->s)) {
		smart_str_appendc(words, ' ');
	}
	smart_str_appends(words, word);
}

/* Appends to `words` the modifiers of a member of the PHP flags `flags`, spec section 25: `static` and `abstract` as
 * PHP marks them, and for a plain PHP member, whose declaration has no line of sharp_declared, `virtual` for one a
 * subclass may replace, which PHP's own rule decides, as spec section 22 says, so none of a final class `declaring`,
 * an enum's included, and `override` for one that replaces a member of its parent class, as `replaces` says. A PHP#
 * member's line states its `virtual` and `override`. */
static void sharp_add_modifiers(
	smart_str *words, uint32_t flags, const zend_class_entry *declaring, bool plain, bool replaces)
{
	if (flags & ZEND_ACC_STATIC) {
		sharp_add_word(words, "static");
	}
	if (flags & ZEND_ACC_ABSTRACT) {
		sharp_add_word(words, "abstract");
	}
	if (plain && !(declaring->ce_flags & ZEND_ACC_FINAL)
		&& !(flags & (ZEND_ACC_FINAL | ZEND_ACC_PRIVATE | ZEND_ACC_CTOR))) {
		sharp_add_word(words, "virtual");
	}
	if (plain && replaces && !(flags & ZEND_ACC_CTOR)) {
		sharp_add_word(words, "override");
	}
}

/* The type the property `info` declares, read on a class value of `ce` whose type arguments are `arguments`, as
 * sharp_declared_types reads it, with what writes it and its modifiers in `words`, as a line of sharp_declared spells
 * them and sharp_add_modifiers adds them: its PHP# declaration as its class writes it, or its PHP declaration, whose
 * `readonly` has no writer, and whose write takes its set visibility. A PHP# field's words end with `field`, by which
 * `Class<T>.fields()` and `properties()` tell it from a property, and a plain PHP property's with `plain`, by which
 * `fields()` lists one that declares no hook whether or not a subclass may replace it, spec section 25. */
static const sharp_type *sharp_property_declared_type(
	const zend_property_info *info, const zend_class_entry *ce, const sharp_type *arguments, smart_str *words)
{
	const char *name = zend_get_unmangled_property_name(info->name);
	smart_str key = {0};
	smart_str_appendc(&key, '$');
	smart_str_appends(&key, name);
	smart_str_0(&key);
	sharp_declared_member line;
	smart_str written = {0};
	bool plain = !sharp_declared_line(info->ce, ZSTR_VAL(key.s), ZSTR_LEN(key.s), &line);
	bool replaces = false;
	if (!plain) {
		smart_str_appendl(words, line.words, line.words_length);
	} else {
		sharp_php_type_text(&written, info->type, info->ce, ce);
		smart_str_0(&written);
		line.type = ZSTR_VAL(written.s);
		line.type_length = ZSTR_LEN(written.s);
		if (!(info->flags & ZEND_ACC_READONLY)) {
			smart_str_appends(words, "set ");
			smart_str_appends(words, sharp_php_visibility((info->flags & ZEND_ACC_PPP_SET_MASK)
				? ((info->flags & ZEND_ACC_PRIVATE_SET) ? ZEND_ACC_PRIVATE : ZEND_ACC_PROTECTED)
				: info->flags));
		}
		const zend_property_info *parent = info->ce->parent
			? zend_hash_str_find_ptr(&info->ce->parent->properties_info, name, strlen(name))
			: NULL;
		replaces = parent && !(parent->flags & ZEND_ACC_PRIVATE);
	}
	sharp_add_modifiers(words, info->flags, info->ce, plain, replaces);
	if (info->flags & ZEND_ACC_FIELD) {
		sharp_add_word(words, "field");
	}
	if (plain) {
		sharp_add_word(words, "plain");
	}
	const sharp_type *types = sharp_declared_types(line.type, line.type_length, info->ce, ce, arguments);
	smart_str_free(&written);
	smart_str_free(&key);

	return types ? types->members[0] : NULL;
}

/* Appends the function type the PHP declaration of `func` gives it, spec section 21: its PHP result type, `void` for a
 * constructor, then each parameter's PHP type, a variadic one's last, each as sharp_php_type_text writes it, so a type
 * it leaves out is `Any?`, `self` its class and `static` the class `called` it runs on. It lists no effects, as the
 * checker gives a PHP signature none, which fits every function type's `uses`. */
static void sharp_php_function_type_text(smart_str *text, const zend_function *func, const zend_class_entry *called)
{
	const zend_class_entry *self = func->common.scope;
	smart_str_appends(text, "Function<");
	if (func->common.fn_flags & ZEND_ACC_CTOR) {
		smart_str_appends(text, "void");
	} else if (func->common.fn_flags & ZEND_ACC_HAS_RETURN_TYPE) {
		sharp_php_type_text(text, func->common.arg_info[-1].type, self, called);
	} else {
		smart_str_appends(text, "Any?");
	}
	smart_str_appendc(text, '(');
	uint32_t count = func->common.num_args + !!(func->common.fn_flags & ZEND_ACC_VARIADIC);
	for (uint32_t i = 0; i < count; i++) {
		if (i) {
			smart_str_appends(text, ", ");
		}
		sharp_php_type_text(text, func->common.arg_info[i].type, self, called);
	}
	smart_str_appends(text, ")>");
}

/* The function type the method `func` declares, read on a class value of `ce` whose type arguments are `arguments`, as
 * sharp_declared_types reads it, with its modifiers in `words`, as sharp_property_declared_type gives a property's: its
 * PHP# type as the class-like that declares it writes it, the interface whose default body a trait copied into the
 * class included, or else its PHP declaration's. Its members are its result type, then its parameters' types. */
static const sharp_type *sharp_method_declared_types(
	const zend_function *func, const zend_class_entry *ce, const sharp_type *arguments, smart_str *words)
{
	const zend_class_entry *declaring = sharp_type_scope(func->common.scope, func);
	const zend_string *name = func->common.function_name;
	sharp_declared_member line;
	smart_str written = {0};
	bool plain = !sharp_declared_line(declaring, ZSTR_VAL(name), ZSTR_LEN(name), &line);
	bool replaces = false;
	if (!plain) {
		smart_str_appendl(words, line.words, line.words_length);
	} else {
		declaring = func->common.scope;
		sharp_php_function_type_text(&written, func, ce);
		smart_str_0(&written);
		line.type = ZSTR_VAL(written.s);
		line.type_length = ZSTR_LEN(written.s);
		const zend_function *parent = declaring->parent
			? zend_hash_find_ptr_lc(&declaring->parent->function_table, func->common.function_name)
			: NULL;
		replaces = parent && !(parent->common.fn_flags & ZEND_ACC_PRIVATE);
	}
	sharp_add_modifiers(words, func->common.fn_flags, declaring, plain, replaces);
	const sharp_type *types = sharp_declared_types(line.type, line.type_length, declaring, ce, arguments);
	smart_str_free(&written);

	return types ? types->members[0] : NULL;
}

/* The class a class value `value` names, with the type arguments it carries in `arguments`, or NULL with an exception
 * for one that names no class. */
static zend_class_entry *sharp_class_value_declaring(const zend_string *value, const sharp_type **arguments)
{
	const sharp_type *carried = sharp_class_value_type(value);
	*arguments = NULL;
	if (carried && carried->kind == SHARP_TYPE_NAMED && carried->class_name) {
		const char *open = memchr(ZSTR_VAL(carried->text), '<', ZSTR_LEN(carried->text));
		*arguments = open ? sharp_type_list(open + 1, ZSTR_VAL(carried->text) + ZSTR_LEN(carried->text) - open - 2) : NULL;

		return zend_fetch_class_by_name(carried->class_name, NULL, ZEND_FETCH_CLASS_DEFAULT | ZEND_FETCH_CLASS_EXCEPTION);
	}

	zend_string *name = sharp_type_class_name(ZSTR_VAL(value), ZSTR_LEN(value), false);
	zend_class_entry *ce = zend_fetch_class(name, ZEND_FETCH_CLASS_DEFAULT | ZEND_FETCH_CLASS_EXCEPTION);
	zend_string_release_ex(name, false);

	return ce;
}

/* Adds the class value of `type`, the whole declared type, to `entry`. */
static void sharp_add_declared_type(zval *entry, const sharp_type *type)
{
	add_next_index_str(entry, zend_string_copy(sharp_type_class_value(type)));
}

/* What reflection read this request of `ce` with the type arguments `arguments` for the function `kind` names, see
 * request_reflected, or NULL, with its key in `key`, which the caller releases. */
static zval *sharp_reflected(char kind, const zend_class_entry *ce, const sharp_type *arguments, zend_string **key)
{
	struct {
		const zend_class_entry *ce;
		const sharp_type *arguments;
		char kind;
	} read;
	memset(&read, 0, sizeof(read));
	read.ce = ce;
	read.arguments = arguments;
	read.kind = kind;
	*key = zend_string_init((const char *) &read, sizeof(read), false);
	HashTable *reflected = SHARP_G(request_reflected);

	return reflected ? zend_hash_find(reflected, *key) : NULL;
}

/* Keeps `rows` under `key` for the rest of the request, see sharp_reflected. Releases `key`. */
static void sharp_reflected_keep(zend_string *key, zval *rows)
{
	HashTable **reflected = &SHARP_G(request_reflected);
	if (!*reflected) {
		ALLOC_HASHTABLE(*reflected);
		zend_hash_init(*reflected, 8, NULL, ZVAL_PTR_DTOR, false);
	}
	Z_TRY_ADDREF_P(rows);
	zend_hash_add_new(*reflected, key, rows);
	zend_string_release_ex(key, false);
}

/* The name of the parameter at `offset` of `func`, as get_function_arg_name reads it, and also a variadic one's, which
 * PHP keeps after the others, outside num_args, where get_function_arg_name gives NULL. */
static const char *sharp_parameter_name(const zend_function *func, uint32_t offset)
{
	if (func->type == ZEND_USER_FUNCTION || (func->common.fn_flags & ZEND_ACC_USER_ARG_INFO)) {
		return ZSTR_VAL(func->common.arg_info[offset].name);
	}

	return ((const zend_internal_arg_info *) func->common.arg_info)[offset].name;
}

/* Whether PHP runs code when it evaluates the constant expression `ast`: a `new`, a call, a closure or an arrow
 * function, compiled or not, as a .sharp file's computed value, `(static function () { return …; })()`, is. A constant,
 * a class constant, an enum case and an operator on them run none. */
static bool sharp_ast_runs_code(zend_ast *ast)
{
	if (!ast) {
		return false;
	}
	switch (ast->kind) {
		case ZEND_AST_ZVAL:
		case ZEND_AST_CONSTANT:
			return false;
		case ZEND_AST_OP_ARRAY:
		case ZEND_AST_NEW:
		case ZEND_AST_CALL:
		case ZEND_AST_STATIC_CALL:
		case ZEND_AST_METHOD_CALL:
		case ZEND_AST_NULLSAFE_METHOD_CALL:
			return true;
	}
	if (zend_ast_is_special(ast)) {
		return zend_ast_is_decl(ast);
	}
	if (zend_ast_is_list(ast)) {
		const zend_ast_list *list = zend_ast_get_list(ast);
		for (uint32_t i = 0; i < list->children; i++) {
			if (sharp_ast_runs_code(list->child[i])) {
				return true;
			}
		}
		return false;
	}
	for (uint32_t i = 0; i < zend_ast_get_num_children(ast); i++) {
		if (sharp_ast_runs_code(ast->child[i])) {
			return true;
		}
	}

	return false;
}

/* The default of the parameter at `offset` of `func` into `value`, which the caller releases, as PHP's reflection
 * reads it before it evaluates it: a user function's from its RECV_INIT, and a PHP function's from its arginfo. False
 * for a parameter with none, a variadic one included. */
static bool sharp_parameter_default(const zend_function *func, uint32_t offset, zval *value)
{
	if (offset >= func->common.num_args) {
		return false;
	}
	if (func->type == ZEND_INTERNAL_FUNCTION) {
		return !(func->common.fn_flags & ZEND_ACC_USER_ARG_INFO)
			&& zend_get_default_from_internal_arg_info(
				value, (zend_internal_arg_info *) &func->internal_function.arg_info[offset]) == SUCCESS;
	}
	const zend_op *op = func->op_array.opcodes;
	for (const zend_op *end = op + func->op_array.last; op < end; op++) {
		if (op->opcode == ZEND_RECV_INIT && op->op1.num == offset + 1) {
			ZVAL_COPY(value, RT_CONSTANT(op, op->op2));
			return true;
		}
	}

	return false;
}

/* Whether the parameter at `offset` of `func` has a default that runs no code when PHP evaluates it, spec section 25,
 * which `Parameter.hasConstantDefault` reads. */
static bool sharp_has_constant_default(const zend_function *func, uint32_t offset)
{
	zval value;
	if (!sharp_parameter_default(func, offset, &value)) {
		return false;
	}
	bool constant = Z_TYPE(value) != IS_CONSTANT_AST || !sharp_ast_runs_code(Z_ASTVAL(value));
	zval_ptr_dtor(&value);

	return constant;
}

/* Adds to `entry` the access modifier that declares a member of the PHP flags `flags`, spec section 5, an `internal`
 * one by its ZEND_ACC_SHARP_INTERNAL. */
static void sharp_add_visibility(zval *entry, uint32_t flags)
{
	if (flags & ZEND_ACC_PRIVATE) {
		add_next_index_string(entry, "private");
	} else if (flags & ZEND_ACC_PROTECTED) {
		add_next_index_string(entry, "protected");
	} else if (flags & ZEND_ACC_SHARP_INTERNAL) {
		add_next_index_string(entry, "internal");
	} else {
		add_next_index_string(entry, "public");
	}
}

/* Makes `method` the list reflection reads of the method `func`, read on a class value of `ce` whose type arguments are
 * `arguments`, spec section 25: first the list of the class that declares it, its name, its result type as
 * sharp_add_declared_type adds it, its modifiers as sharp_method_declared_types gives them, and its access modifier as
 * sharp_add_visibility adds it, then one list per parameter: its name, its type, "optional" for one a call may leave
 * out or "", "variadic" for the one that takes the rest of a call's arguments or "", and "constant" for one whose
 * default runs no code, see sharp_has_constant_default, or "". False with an exception when no type reads. */
static bool sharp_method_row(zval *method, const zend_function *func, const zend_class_entry *ce,
	const sharp_type *arguments)
{
	smart_str words = {0};
	const sharp_type *types = sharp_method_declared_types(func, ce, arguments, &words);
	if (!types) {
		smart_str_free(&words);
		zend_throw_error(NULL, "PHP# reads no type for %s::%s(). This is a bug in PHP#",
			ZSTR_VAL(func->common.scope->name), ZSTR_VAL(func->common.function_name));
		return false;
	}
	uint32_t count = func->common.num_args + !!(func->common.fn_flags & ZEND_ACC_VARIADIC);
	zval declared;
	array_init_size(method, count + 1);
	array_init_size(&declared, 5);
	add_next_index_str(&declared, zend_string_copy(func->common.scope->name));
	add_next_index_str(&declared, zend_string_copy(func->common.function_name));
	sharp_add_declared_type(&declared, types->members[0]);
	smart_str_0(&words);
	add_next_index_str(&declared, words.s ? words.s : ZSTR_EMPTY_ALLOC());
	sharp_add_visibility(&declared, func->common.fn_flags);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(method), &declared);
	for (uint32_t i = 0; i < count; i++) {
		zval parameter;
		array_init_size(&parameter, 5);
		add_next_index_string(&parameter, sharp_parameter_name(func, i));
		sharp_add_declared_type(&parameter, types->members[i + 1]);
		add_next_index_string(&parameter, i >= func->common.required_num_args ? "optional" : "");
		/* PHP keeps a variadic parameter after the others, outside num_args. */
		add_next_index_string(&parameter, i == func->common.num_args ? "variadic" : "");
		add_next_index_string(&parameter, sharp_has_constant_default(func, i) ? "constant" : "");
		zend_hash_next_index_insert_new(Z_ARRVAL_P(method), &parameter);
	}

	return true;
}

/* The method `name` of `ce`, by the key the engine gives it: as written in a class with exact names, spec section 24,
 * and in lowercase in any other. */
static zend_function *sharp_method_named(const zend_class_entry *ce, zend_string *name)
{
	if (ce->sharp_flags & SHARP_CLASS_EXACT_NAMES) {
		return zend_hash_find_ptr(&ce->function_table, name);
	}
	zend_string *key = zend_string_tolower(name);
	zend_function *func = zend_hash_find_ptr(&ce->function_table, key);
	zend_string_release(key);

	return func;
}

/* The hidden static method `name#get` or `name#set`, by `kind`, of the static property `name` of the PHP# class `ce`,
 * whose accessors run as its class's hidden static methods, which alone reach its storage, as decision 3 lowers a
 * static property with accessors. NULL for a property of none, and for the `set` of a get-only one. */
static zend_function *sharp_static_accessor(const zend_class_entry *ce, const char *name, int kind)
{
	if (!sharp_class_is_sharp(ce)) {
		return NULL;
	}
	zend_string *method = zend_strpprintf(0, "%s#%s", name, kind == ZEND_PROPERTY_HOOK_GET ? "get" : "set");
	zend_function *func = sharp_method_named(ce, method);
	zend_string_release_ex(method, false);

	return func && (func->common.fn_flags & ZEND_ACC_STATIC) ? func : NULL;
}

/* The type the static property `name`, whose accessors run as the hidden static methods of `get`'s class, see
 * sharp_static_accessor, declares, read on a class value of `ce` whose type arguments are `arguments`, with what writes
 * it and its modifiers in `words`, as sharp_property_declared_type gives an instance property's. Its PHP# line holds
 * them, since its class may keep no storage for it: a computed one keeps none, and a generic class keeps each type
 * argument's in its entry of `statics_store`. NULL when no line does. */
static const sharp_type *sharp_static_accessor_declared_type(const zend_function *get, const char *name,
	const zend_class_entry *ce, const sharp_type *arguments, smart_str *words)
{
	smart_str key = {0};
	smart_str_appendc(&key, '$');
	smart_str_appends(&key, name);
	smart_str_0(&key);
	sharp_declared_member line;
	bool found = sharp_declared_line(get->common.scope, ZSTR_VAL(key.s), ZSTR_LEN(key.s), &line);
	smart_str_free(&key);
	if (!found) {
		return NULL;
	}
	smart_str_appendl(words, line.words, line.words_length);
	sharp_add_modifiers(words, ZEND_ACC_STATIC, get->common.scope, false, false);
	const sharp_type *types = sharp_declared_types(line.type, line.type_length, get->common.scope, ce, arguments);

	return types ? types->members[0] : NULL;
}

/* Adds to `properties` the row reflection reads of the static property `name`, whose accessors run as the hidden static
 * methods of `get`'s class, read on a class value of `ce` whose type arguments are `arguments`, as
 * Sharp_Internal_properties makes a row: its access modifier is its `get`'s, and `internal` when its storage, `storage`
 * or NULL, which the bridge marks, or its `get` is marked so. False with an Error when no type reads. */
static bool sharp_add_static_accessor_row(zval *properties, const zend_function *get, const char *name,
	const zend_property_info *storage, const zend_class_entry *ce, const sharp_type *arguments)
{
	smart_str writer = {0};
	const sharp_type *type = sharp_static_accessor_declared_type(get, name, ce, arguments, &writer);
	if (!type) {
		smart_str_free(&writer);
		zend_throw_error(NULL, "PHP# reads no type for %s::$%s. This is a bug in PHP#", ZSTR_VAL(get->common.scope->name),
			name);
		return false;
	}
	zval property;
	array_init_size(&property, 5);
	add_next_index_str(&property, zend_string_copy(get->common.scope->name));
	add_next_index_string(&property, name);
	sharp_add_declared_type(&property, type);
	sharp_add_visibility(&property, get->common.fn_flags | (storage ? storage->flags & ZEND_ACC_SHARP_INTERNAL : 0));
	smart_str_0(&writer);
	add_next_index_str(&property, writer.s ? writer.s : ZSTR_EMPTY_ALLOC());
	zend_hash_next_index_insert_new(Z_ARRVAL_P(properties), &property);

	return true;
}

/* Whether `info` is the hidden static the bridge declares for an `extern` property, spec section 14.3, named `name#extern`:
 * it holds the key its plain PHP parent's `__get` and `__set` receive, and carries its access and its attributes, since
 * PHP declares no property of its name. */
static bool sharp_is_extern_carrier(const zend_property_info *info)
{
	const char *name = zend_get_unmangled_property_name(info->name);
	size_t length = strlen(name);

	return (info->flags & ZEND_ACC_STATIC) && sharp_class_is_sharp(info->ce) && length > strlen("#extern")
		&& memcmp(name + length - strlen("#extern"), "#extern", strlen("#extern")) == 0;
}

/* The hidden static of the `extern` property `name` of `ce`, see sharp_is_extern_carrier, or NULL for any other name. */
static const zend_property_info *sharp_extern_carrier(const zend_class_entry *ce, const zend_string *name)
{
	zend_string *carried = zend_strpprintf(0, "%s#extern", ZSTR_VAL(name));
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, carried);
	zend_string_release_ex(carried, false);

	return info && sharp_is_extern_carrier(info) ? info : NULL;
}

/* The line `$name` of sharp_declared that states the type of the `extern` property whose hidden static is `carrier`, and
 * what writes it, `set private extern`, into `line`, with the property's name in `name`, which the caller releases. */
static bool sharp_extern_line(const zend_property_info *carrier, zend_string **name, sharp_declared_member *line)
{
	const char *carried = zend_get_unmangled_property_name(carrier->name);
	*name = zend_string_init(carried, strlen(carried) - strlen("#extern"), false);
	zend_string *key = zend_strpprintf(0, "$%s", ZSTR_VAL(*name));
	bool found = sharp_declared_line(carrier->ce, ZSTR_VAL(key), ZSTR_LEN(key), line);
	zend_string_release_ex(key, false);

	return found;
}

/* The type of the `extern` property whose hidden static is `carrier`, read on a class value of `ce` whose type arguments
 * are `arguments`, as sharp_declared_types reads it, with its line in `line` and its name in `name`, which the caller
 * releases, see sharp_extern_line. NULL with an Error when no type reads. */
static const sharp_type *sharp_extern_type(const zend_property_info *carrier, const zend_class_entry *ce,
	const sharp_type *arguments, zend_string **name, sharp_declared_member *line)
{
	const sharp_type *types = sharp_extern_line(carrier, name, line)
		? sharp_declared_types(line->type, line->type_length, carrier->ce, ce, arguments)
		: NULL;
	if (!types) {
		zend_throw_error(NULL, "PHP# reads no type for %s::$%s. This is a bug in PHP#", ZSTR_VAL(carrier->ce->name),
			ZSTR_VAL(*name));
		return NULL;
	}

	return types->members[0];
}

/* Adds to `properties` the row reflection reads of the `extern` property whose hidden static is `carrier`, read on a class
 * value of `ce` whose type arguments are `arguments`, as Sharp_Internal_properties makes a row: its access and its
 * attributes are its hidden static's, and its type and what writes it its line, see sharp_extern_type. False with an
 * Error when no type reads. */
static bool sharp_add_extern_row(
	zval *properties, const zend_property_info *carrier, const zend_class_entry *ce, const sharp_type *arguments)
{
	zend_string *name;
	sharp_declared_member line;
	const sharp_type *type = sharp_extern_type(carrier, ce, arguments, &name, &line);
	if (!type) {
		zend_string_release_ex(name, false);
		return false;
	}
	zval property;
	array_init_size(&property, 5);
	add_next_index_str(&property, zend_string_copy(carrier->ce->name));
	add_next_index_str(&property, name);
	sharp_add_declared_type(&property, type);
	sharp_add_visibility(&property, carrier->flags);
	add_next_index_stringl(&property, line.words, line.words_length);
	zend_hash_next_index_insert_new(Z_ARRVAL_P(properties), &property);

	return true;
}

/* Spec section 25: what `Class<T>.properties()` reads, the properties and fields of the class `type` names, its own
 * first and then those it inherits, in the order they are declared, at every visibility, its statics included. Each is
 * the list of the class that declares it, its name, its type as sharp_add_declared_type adds it, its access modifier as
 * sharp_add_visibility adds it, and what writes it and its modifiers as sharp_property_declared_type gives them. A
 * static whose accessors run as hidden static methods, see sharp_static_accessor, is read from them in its storage's
 * place. An `extern` property, which PHP declares no property for, follows the stored ones, in the order it is
 * declared, as sharp_add_extern_row reads it, and a static with no storage of the class, as a computed one, follows
 * them, in the order its `get` is declared. A class's are read once a request, see request_reflected. */
ZEND_FUNCTION(Sharp_Internal_properties)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	zend_string *key;
	zval *read = sharp_reflected('p', ce, arguments, &key);
	if (read) {
		zend_string_release_ex(key, false);
		RETURN_COPY(read);
	}

	array_init(return_value);
	zend_property_info *info;
	ZEND_HASH_FOREACH_PTR(&ce->properties_info, info) {
		if ((info->flags & ZEND_ACC_SHARP_HIDDEN) || memchr(ZSTR_VAL(info->name), '#', ZSTR_LEN(info->name))) {
			continue;
		}
		const char *unmangled = zend_get_unmangled_property_name(info->name);
		const zend_function *get = (info->flags & ZEND_ACC_STATIC)
			? sharp_static_accessor(info->ce, unmangled, ZEND_PROPERTY_HOOK_GET)
			: NULL;
		if (get) {
			if (!sharp_add_static_accessor_row(return_value, get, unmangled, info, ce, arguments)) {
				zend_string_release_ex(key, false);
				zval_ptr_dtor(return_value);
				RETVAL_NULL();
				RETURN_THROWS();
			}
			continue;
		}
		smart_str writer = {0};
		const sharp_type *type = sharp_property_declared_type(info, ce, arguments, &writer);
		if (!type) {
			smart_str_free(&writer);
			zend_string_release_ex(key, false);
			zend_throw_error(NULL, "PHP# reads no type for %s::$%s. This is a bug in PHP#",
				ZSTR_VAL(info->ce->name), zend_get_unmangled_property_name(info->name));
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
		zval property;
		array_init_size(&property, 5);
		add_next_index_str(&property, zend_string_copy(info->ce->name));
		add_next_index_string(&property, zend_get_unmangled_property_name(info->name));
		sharp_add_declared_type(&property, type);
		sharp_add_visibility(&property, info->flags);
		smart_str_0(&writer);
		add_next_index_str(&property, writer.s ? writer.s : ZSTR_EMPTY_ALLOC());
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &property);
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_PTR(&ce->properties_info, info) {
		if (sharp_is_extern_carrier(info) && !sharp_add_extern_row(return_value, info, ce, arguments)) {
			zend_string_release_ex(key, false);
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();
	const zend_function *func;
	ZEND_HASH_FOREACH_PTR(&ce->function_table, func) {
		const zend_string *method = func->common.function_name;
		if (!(func->common.fn_flags & ZEND_ACC_STATIC) || !sharp_class_is_sharp(func->common.scope)
			|| ZSTR_LEN(method) <= strlen("#get")
			|| memcmp(ZSTR_VAL(method) + ZSTR_LEN(method) - strlen("#get"), "#get", strlen("#get")) != 0) {
			continue;
		}
		zend_string *name = zend_string_init(ZSTR_VAL(method), ZSTR_LEN(method) - strlen("#get"), false);
		bool stored = zend_hash_exists(&ce->properties_info, name);
		bool added = stored || sharp_add_static_accessor_row(return_value, func, ZSTR_VAL(name), NULL, ce, arguments);
		zend_string_release_ex(name, false);
		if (!added) {
			zend_string_release_ex(key, false);
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
	} ZEND_HASH_FOREACH_END();
	sharp_reflected_keep(key, return_value);
}

/* Spec section 25: what `Property.getter()` and `Property.setter()` read, the accessors of the property `name` of the
 * class `type` names, as PHP 8.4's ReflectionProperty::getHook gives a hook: a list of the `get` one and the `set` one,
 * each null for an accessor the property runs none of, as a plain stored property runs none. Each is a method row, as
 * sharp_method_row makes one, named `$name::get` or `$name::set` as PHP names a hook, whose result is the property's
 * type read on the class value, as sharp_property_declared_type reads it, or `void`, whose one parameter, `value`, a
 * `set` takes of that type, with the property's modifiers, and the access modifier that reaches its read or its
 * write. A static's are its hidden static methods, see sharp_static_accessor. */
ZEND_FUNCTION(Sharp_Internal_accessors)
{
	zend_string *value;
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(value)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, name);
	const zend_function *get = !info || (info->flags & ZEND_ACC_STATIC)
		? sharp_static_accessor(ce, ZSTR_VAL(name), ZEND_PROPERTY_HOOK_GET)
		: NULL;
	/* An `extern` property runs no accessor of its own: PHP runs its plain PHP parent's `__get` and `__set`. */
	if (!info && !get && sharp_extern_carrier(ce, name)) {
		array_init_size(return_value, 2);
		add_next_index_null(return_value);
		add_next_index_null(return_value);
		return;
	}
	if (!info && !get) {
		zend_throw_error(NULL, "Property %s::$%s does not exist", ZSTR_VAL(ce->name), ZSTR_VAL(name));
		RETURN_THROWS();
	}
	const zend_class_entry *declaring = get ? get->common.scope : info->ce;
	smart_str words = {0};
	const sharp_type *type = get ? sharp_static_accessor_declared_type(get, ZSTR_VAL(name), ce, arguments, &words)
		: sharp_property_declared_type(info, ce, arguments, &words);
	smart_str_0(&words);
	if (!type) {
		smart_str_free(&words);
		zend_throw_error(NULL, "PHP# reads no type for %s::$%s. This is a bug in PHP#", ZSTR_VAL(declaring->name),
			ZSTR_VAL(name));
		RETURN_THROWS();
	}
	/* The words open with what writes the property, as `set private`, whose access modifier reaches the `set`, and end
	 * with its modifiers. */
	const char *modifiers = words.s ? ZSTR_VAL(words.s) : "";
	const char *setter = NULL;
	size_t setter_length = 0;
	if (strncmp(modifiers, "set ", 4) == 0 || strncmp(modifiers, "init ", 5) == 0) {
		setter = strchr(modifiers, ' ') + 1;
		const char *end = strchr(setter, ' ');
		setter_length = end ? (size_t) (end - setter) : strlen(setter);
		modifiers = end ? end + 1 : setter + setter_length;
	}

	array_init_size(return_value, 2);
	for (int kind = ZEND_PROPERTY_HOOK_GET; kind <= ZEND_PROPERTY_HOOK_SET; kind++) {
		if (get ? !sharp_static_accessor(ce, ZSTR_VAL(name), kind) : !info->hooks || !info->hooks[kind]) {
			add_next_index_null(return_value);
			continue;
		}
		zval row;
		zval declared;
		array_init_size(&row, 2);
		array_init_size(&declared, 5);
		add_next_index_str(&declared, zend_string_copy(declaring->name));
		add_next_index_str(&declared, zend_strpprintf(0, "$%s::%s", ZSTR_VAL(name),
			kind == ZEND_PROPERTY_HOOK_GET ? "get" : "set"));
		sharp_add_declared_type(&declared,
			kind == ZEND_PROPERTY_HOOK_GET ? type : sharp_type_list(ZEND_STRL("void"))->members[0]);
		add_next_index_string(&declared, modifiers);
		if (kind == ZEND_PROPERTY_HOOK_SET && setter) {
			add_next_index_stringl(&declared, setter, setter_length);
		} else if (get) {
			sharp_add_visibility(&declared, get->common.fn_flags | (info ? info->flags & ZEND_ACC_SHARP_INTERNAL : 0));
		} else {
			sharp_add_visibility(&declared, info->flags);
		}
		zend_hash_next_index_insert_new(Z_ARRVAL(row), &declared);
		if (kind == ZEND_PROPERTY_HOOK_SET) {
			zval parameter;
			array_init_size(&parameter, 5);
			add_next_index_string(&parameter, "value");
			sharp_add_declared_type(&parameter, type);
			add_next_index_string(&parameter, "");
			add_next_index_string(&parameter, "");
			add_next_index_string(&parameter, "");
			zend_hash_next_index_insert_new(Z_ARRVAL(row), &parameter);
		}
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &row);
	}
	smart_str_free(&words);
}

/* Spec section 25: what `Class<T>.methods()` reads, the instance and static methods of the class `type` names, its own
 * first and then those it inherits, in the order they are declared, without its constructor, the magic methods PHP
 * names with `__`, and the methods PHP# runs a member's accessors as, whose names hold `#`, at every visibility. Each is
 * a list of string lists, as sharp_method_row makes it. A class's are read once a request, see request_reflected. */
ZEND_FUNCTION(Sharp_Internal_methods)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}

	zend_string *key;
	zval *read = sharp_reflected('m', ce, arguments, &key);
	if (read) {
		zend_string_release_ex(key, false);
		RETURN_COPY(read);
	}

	array_init(return_value);
	zend_function *func;
	ZEND_HASH_MAP_FOREACH_PTR(&ce->function_table, func) {
		const zend_string *name = func->common.function_name;
		if ((ZSTR_LEN(name) > 1 && ZSTR_VAL(name)[0] == '_' && ZSTR_VAL(name)[1] == '_')
			|| memchr(ZSTR_VAL(name), '#', ZSTR_LEN(name))) {
			continue;
		}
		zval method;
		if (!sharp_method_row(&method, func, ce, arguments)) {
			zend_string_release_ex(key, false);
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &method);
	} ZEND_HASH_FOREACH_END();
	sharp_reflected_keep(key, return_value);
}

/* Spec section 25: what `Class<T>.constructor()` reads, the constructor of the class `type` names, its own or the one
 * it inherits, as sharp_method_row gives a method, or null for a class that has none. */
ZEND_FUNCTION(Sharp_Internal_constructor)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	if (ce->constructor && !sharp_method_row(return_value, ce->constructor, ce, arguments)) {
		RETURN_THROWS();
	}
}

/* Spec section 25: the method `name` of `class` that the compile made, whose name holds `#`, as a closure, or null for
 * a class without it. PHP's reflection finds no such method, so the standard library reaches one here: `name#initial`,
 * which `Property.defaultValue()` runs, and a static accessor's `name#get` and `name#set`, whose attributes
 * `Method.attributes<T>()` reads. A name without `#` is a ValueError, since PHP's reflection reaches that method. */
ZEND_FUNCTION(Sharp_Internal_hiddenMethod)
{
	zend_string *class_name;
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	if (!memchr(ZSTR_VAL(name), '#', ZSTR_LEN(name))) {
		zend_argument_value_error(2, "must name a method the compile made, whose name holds \"#\", \"%s\" given",
			ZSTR_VAL(name));
		RETURN_THROWS();
	}
	zend_class_entry *ce = zend_lookup_class(class_name);
	if (!ce) {
		if (!EG(exception)) {
			zend_throw_error(NULL, "Class \"%s\" not found", ZSTR_VAL(class_name));
		}
		RETURN_THROWS();
	}
	zend_function *method = sharp_method_named(ce, name);
	if (!method) {
		RETURN_NULL();
	}
	zend_create_fake_closure(return_value, method, method->common.scope, ce, NULL);
}

/* The frame of the code that called the standard library member whose body called this function, as
 * zend_get_executed_scope finds the code that runs: the first frame below that member's that runs user code or a
 * method. */
static const zend_execute_data *sharp_reflection_caller(const zend_execute_data *execute_data)
{
	const zend_execute_data *frame = EX(prev_execute_data) ? EX(prev_execute_data)->prev_execute_data : NULL;
	while (frame && !(frame->func && (ZEND_USER_CODE(frame->func->type) || frame->func->common.scope))) {
		frame = frame->prev_execute_data;
	}

	return frame;
}

/* Whether the code `caller` runs reaches the method `func`, named `name`, as a direct call would, by its access
 * modifier, spec section 5: a private one only from its class, and an `internal` one only from its namespace and those
 * below it. False with the Error PHP gives a call it can't make. */
static bool sharp_reaches_method(const zend_execute_data *caller, const zend_function *func, const zend_string *name)
{
	zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	uint32_t flags = func->common.fn_flags;
	if (((flags & ZEND_ACC_PRIVATE) && func->common.scope != scope)
			|| ((flags & ZEND_ACC_PROTECTED) && !zend_check_protected(zend_get_function_root_class(func), scope))) {
		zend_bad_method_call(func, name, scope);
		return false;
	}
	const zend_string *code = zend_sharp_frame_code_name(caller);
	if ((flags & ZEND_ACC_SHARP_INTERNAL) && !zend_sharp_code_reaches_internal(func->common.scope, code)) {
		zend_string *outside = zend_sharp_code_outside_namespace(func->common.scope, code);
		zend_throw_error(NULL, "Call to internal method %s::%s()%s", ZSTR_VAL(func->common.scope->name),
			ZSTR_VAL(name), ZSTR_VAL(outside));
		zend_string_release_ex(outside, false);
		return false;
	}

	return true;
}

/* The class `name` names, of which `receiver` must be an instance for `member`, or NULL with a TypeError. */
static zend_class_entry *sharp_reflected_class(zend_object *receiver, zend_string *name, const char *member)
{
	zend_class_entry *ce = zend_lookup_class(name);
	if (!ce) {
		zend_throw_error(NULL, "Class \"%s\" not found", ZSTR_VAL(name));
		return NULL;
	}
	if (!instanceof_function(receiver->ce, ce)) {
		zend_type_error("`%s` takes a receiver of `%s`, and was given `%s`.", member, ZSTR_VAL(ce->name),
			ZSTR_VAL(receiver->ce->name));
		return NULL;
	}

	return ce;
}

/* The kind of accessor the name `name` gives, `$total::get` or `$total::set` as PHP 8.4 names a property hook, with its
 * property's name, `total`, in `property`, which the caller releases: ZEND_PROPERTY_HOOK_GET, ZEND_PROPERTY_HOOK_SET,
 * or -1 for the name of a method. */
static int sharp_accessor_kind(const zend_string *name, zend_string **property)
{
	size_t length = ZSTR_LEN(name);
	const char *text = ZSTR_VAL(name);
	if (length < 7 || text[0] != '$' || memcmp(text + length - 5, "::", 2) != 0) {
		return -1;
	}
	int kind = memcmp(text + length - 3, "get", 3) == 0 ? ZEND_PROPERTY_HOOK_GET
		: memcmp(text + length - 3, "set", 3) == 0 ? ZEND_PROPERTY_HOOK_SET
		: -1;
	if (kind >= 0) {
		*property = zend_string_init(text + 1, length - 6, false);
	}

	return kind;
}

/* The property `name` of `ce`, which the reflection functions read and write, with the Error PHP gives a direct use of
 * an `internal` property from a namespace outside its class's when `caller` can't reach it, an `extern` one's by its
 * hidden static, see sharp_is_extern_carrier. A private or protected property is checked by the object's handlers,
 * which run in the caller's scope, and an `extern` one by sharp_extern_call. */
static bool sharp_reaches_property(const zend_execute_data *caller, const zend_class_entry *ce, zend_string *name)
{
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, name);
	if (!info) {
		info = sharp_extern_carrier(ce, name);
	}
	const zend_string *code = zend_sharp_frame_code_name(caller);
	if (info && (info->flags & ZEND_ACC_SHARP_INTERNAL) && !zend_sharp_code_reaches_internal(info->ce, code)) {
		zend_string *outside = zend_sharp_code_outside_namespace(info->ce, code);
		zend_throw_error(NULL, "Cannot access internal property %s::$%s%s", ZSTR_VAL(info->ce->name), ZSTR_VAL(name),
			ZSTR_VAL(outside));
		zend_string_release_ex(outside, false);
		return false;
	}

	return true;
}

/* The class `class_name` that declares the property `name` the reflection function `member` uses, with whether the
 * property is static in `is_static`. An instance property's `receiver` must be of the class, and a static one's is
 * ignored, as .NET's PropertyInfo.GetValue ignores it. NULL with an exception for a missing class, a receiver of
 * another class, and an instance property given no receiver, InvalidArgumentException. */
static zend_class_entry *sharp_property_class(
	zend_object *receiver, zend_string *class_name, zend_string *name, const char *member, bool *is_static)
{
	zend_class_entry *ce = zend_lookup_class(class_name);
	if (!ce) {
		zend_throw_error(NULL, "Class \"%s\" not found", ZSTR_VAL(class_name));
		return NULL;
	}
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, name);
	*is_static = info ? (info->flags & ZEND_ACC_STATIC)
		: sharp_static_accessor(ce, ZSTR_VAL(name), ZEND_PROPERTY_HOOK_GET) != NULL;
	if (*is_static) {
		return ce;
	}
	if (!receiver) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0,
			"`%s` uses the instance property %s::$%s of a receiver, and was given null.", member, ZSTR_VAL(ce->name),
			ZSTR_VAL(name));
		return NULL;
	}

	return sharp_reflected_class(receiver, class_name, member);
}

/* The call by code in `scope` of `accessor`, the hidden `get` or `set` of the static property `name` of `ce`, see
 * sharp_static_accessor, with `value` for a `set` or NULL, into `return_value`. The code reaches it as a direct use of
 * the property would, by the accessor's access modifier, with the Error PHP gives a property it can't reach. */
static void sharp_static_accessor_call(const zend_class_entry *scope, zend_function *accessor, zend_class_entry *ce,
	zend_string *name, zval *value, zval *return_value)
{
	uint32_t flags = accessor->common.fn_flags;
	if (((flags & ZEND_ACC_PRIVATE) && accessor->common.scope != scope)
			|| ((flags & ZEND_ACC_PROTECTED) && !zend_check_protected(accessor->common.scope, scope))) {
		const char *visibility = zend_visibility_string(flags);
		if (value) {
			zend_throw_error(NULL, "Cannot modify %s(set) property %s::$%s from %s%s", visibility, ZSTR_VAL(ce->name),
				ZSTR_VAL(name), scope ? "scope " : "global scope", scope ? ZSTR_VAL(scope->name) : "");
		} else {
			zend_throw_error(NULL, "Cannot access %s property %s::$%s", visibility, ZSTR_VAL(ce->name), ZSTR_VAL(name));
		}
		return;
	}
	zend_call_known_function(accessor, NULL, ce, return_value, value ? 1 : 0, value, NULL);
}

/* The static `name` of `ce` as code in `scope` reads it directly, or NULL with an exception. */
static zval *sharp_static_read(zend_class_entry *scope, zend_class_entry *ce, zend_string *name)
{
	const zend_class_entry *outer = EG(fake_scope);
	EG(fake_scope) = scope;
	zval *read = zend_read_static_property_ex(ce, name, false);
	EG(fake_scope) = outer;

	return EG(exception) ? NULL : read;
}

/* The write of `value` into the storage of the static `name` of `ce` by code in `scope`, as a direct write reaches it,
 * with the value checked against its type in strict mode, or an exception. zend_update_static_property_ex writes in the
 * scope of the class it writes, and checks the value loosely. */
static void sharp_static_write(zend_class_entry *scope, zend_class_entry *ce, zend_string *name, zval *value)
{
	zend_property_info *info;
	const zend_class_entry *outer = EG(fake_scope);
	EG(fake_scope) = scope;
	zval *slot = zend_std_get_static_property_with_info(ce, name, BP_VAR_W, &info);
	EG(fake_scope) = outer;
	zval written;
	ZVAL_COPY(&written, value);
	if (!slot || (ZEND_TYPE_IS_SET(info->type) && !zend_verify_property_type(info, &written, true))) {
		zval_ptr_dtor(&written);
		return;
	}
	zend_assign_to_variable(slot, &written, IS_TMP_VAR, true);
}

/* The access modifier that reaches the write of an `extern` property, as the words of its line give it after `set`:
 * ZEND_ACC_PRIVATE, ZEND_ACC_PROTECTED or ZEND_ACC_PUBLIC, `internal` too, with `internal` set. */
static uint32_t sharp_extern_set_flags(const char *reach, size_t length, bool *internal)
{
	*internal = length >= strlen("internal") && memcmp(reach, "internal", strlen("internal")) == 0;
	if (length >= strlen("private") && memcmp(reach, "private", strlen("private")) == 0) {
		return ZEND_ACC_PRIVATE;
	}
	if (length >= strlen("protected") && memcmp(reach, "protected", strlen("protected")) == 0) {
		return ZEND_ACC_PROTECTED;
	}

	return ZEND_ACC_PUBLIC;
}

static bool sharp_property_value_fits(
	const sharp_type *type, zval *value, const zend_class_entry *ce, const char *name, bool reference);

/* The call by `caller`'s code of the plain PHP parent's `__get` on `receiver`, or of its `__set` with `value`, with the
 * key of the `extern` property `name` whose hidden static is `carrier`, spec section 14.3, into `return_value`. The
 * code reaches it as a direct use of the property would: a read by the property's access, and a write by the access
 * its line gives its `set`, with the Error PHP gives a property it can't reach, and a get-only one is read-only. A
 * written value is checked against the property's type as any property's write is, see sharp_property_value_fits. */
static void sharp_extern_call(const zend_execute_data *caller, zend_object *receiver, const zend_property_info *carrier,
	zend_string *name, zval *value, zval *return_value)
{
	const zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	uint32_t flags = carrier->flags;
	const sharp_type *type = NULL;
	if (value) {
		zend_string *property;
		sharp_declared_member line;
		type = sharp_extern_type(carrier, receiver->ce, sharp_type_arguments(receiver), &property, &line);
		zend_string_release_ex(property, false);
		if (!type) {
			return;
		}
		if (line.words_length < strlen("set ") || memcmp(line.words, "set ", strlen("set ")) != 0) {
			zend_throw_error(NULL, "Property %s::$%s is read-only", ZSTR_VAL(carrier->ce->name), ZSTR_VAL(name));
			return;
		}
		bool internal;
		flags = sharp_extern_set_flags(
			line.words + strlen("set "), line.words_length - strlen("set "), &internal);
		/* Plain PHP writes an `internal set` as public, as the engine runs one, see ZEND_ACC_SHARP_INTERNAL. */
		const zend_string *code = zend_sharp_frame_code_name(caller);
		if (internal && caller && sharp_function_is_sharp(caller->func)
				&& !zend_sharp_code_reaches_internal(carrier->ce, code)) {
			zend_string *outside = zend_sharp_code_outside_namespace(carrier->ce, code);
			zend_throw_error(NULL, "Cannot modify internal(set) property %s::$%s%s", ZSTR_VAL(carrier->ce->name),
				ZSTR_VAL(name), ZSTR_VAL(outside));
			zend_string_release_ex(outside, false);
			return;
		}
	}
	if (((flags & ZEND_ACC_PRIVATE) && carrier->ce != scope)
			|| ((flags & ZEND_ACC_PROTECTED) && !zend_check_protected(carrier->ce, scope))) {
		const char *visibility = zend_visibility_string(flags);
		if (value) {
			zend_throw_error(NULL, "Cannot modify %s(set) property %s::$%s from %s%s", visibility,
				ZSTR_VAL(carrier->ce->name), ZSTR_VAL(name), scope ? "scope " : "global scope",
				scope ? ZSTR_VAL(scope->name) : "");
		} else {
			zend_throw_error(NULL, "Cannot access %s property %s::$%s", visibility, ZSTR_VAL(carrier->ce->name),
				ZSTR_VAL(name));
		}
		return;
	}
	zval *held = &carrier->ce->default_static_members_table[carrier->offset];
	ZVAL_DEINDIRECT(held);
	zval key;
	ZVAL_STR(&key, Z_STR_P(held));
	if (!value) {
		zend_call_method_with_1_params(receiver, receiver->ce, NULL, "__get", return_value, &key);
		return;
	}
	if (!sharp_property_value_fits(type, value, carrier->ce, ZSTR_VAL(name), false)) {
		return;
	}
	zval written;
	zend_call_method_with_2_params(receiver, receiver->ce, NULL, "__set", &written, &key, value);
	zval_ptr_dtor(&written);
}

/* The read of the property `name` that `class_name` declares on `receiver`, or of the static `name`, into
 * `return_value`, by the code that called the standard library member whose body made the internal call
 * `execute_data`, see Sharp_Internal_read. A static whose accessors run as hidden static methods runs its `get`, and an
 * `extern` property calls its plain PHP parent's `__get` with its key, see sharp_extern_call. */
static void sharp_reflected_read(zend_execute_data *execute_data, zend_object *receiver, zend_string *class_name,
	zend_string *name, zval *return_value)
{
	bool is_static;
	zend_class_entry *ce = sharp_property_class(receiver, class_name, name, "get", &is_static);
	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	if (!ce || !sharp_reaches_property(caller, ce, name)) {
		RETURN_THROWS();
	}
	zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	zend_function *get = is_static ? sharp_static_accessor(ce, ZSTR_VAL(name), ZEND_PROPERTY_HOOK_GET) : NULL;
	if (get) {
		sharp_static_accessor_call(scope, get, ce, name, NULL, return_value);
		return;
	}
	const zend_property_info *carrier = is_static ? NULL : sharp_extern_carrier(ce, name);
	if (carrier) {
		sharp_extern_call(caller, receiver, carrier, name, NULL, return_value);
		return;
	}
	zval value;
	zval *read = is_static ? sharp_static_read(scope, ce, name)
		: zend_read_property_ex(scope, receiver, name, false, &value);
	if (EG(exception) || !read) {
		RETURN_THROWS();
	}
	RETURN_COPY_DEREF(read);
}

/* Spec section 25: `Property.get(receiver)`, the read of the property `name` that `class` declares on `receiver`, or of
 * the static `name`, as .NET's PropertyInfo.GetValue and Kotlin's KProperty1.get make it. It runs a computed property's
 * `get`, and the caller of `get` reaches the property as a direct read would. A caller with no class scope reads in the
 * scope of the standard library's Property, which reaches no member of another class beyond a public one. */
ZEND_FUNCTION(Sharp_Internal_read)
{
	zend_object *receiver;
	zend_string *class_name;
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_OBJ_OR_NULL(receiver)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	sharp_reflected_read(execute_data, receiver, class_name, name, return_value);
}

/* The write of `value` into the property `name` that `class_name` declares on `receiver`, or into the static `name`,
 * by the code that called the standard library member whose body made the internal call `execute_data`, see
 * Sharp_Internal_write. A static whose accessors run as hidden static methods runs its `set`, and one with no `set` and
 * no storage is read-only. An `extern` property calls its plain PHP parent's `__set` with its key, see
 * sharp_extern_call. */
static void sharp_reflected_write(zend_execute_data *execute_data, zend_object *receiver, zend_string *class_name,
	zend_string *name, zval *value, zval *return_value)
{
	bool is_static;
	zend_class_entry *ce = sharp_property_class(receiver, class_name, name, "set", &is_static);
	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	if (!ce || !sharp_reaches_property(caller, ce, name)) {
		RETURN_THROWS();
	}
	zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	const zend_property_info *carrier = is_static ? NULL : sharp_extern_carrier(ce, name);
	if (carrier) {
		sharp_extern_call(caller, receiver, carrier, name, value, return_value);
		return;
	}
	if (!is_static) {
		zend_update_property_ex(scope, receiver, name, value);
		return;
	}
	zend_function *set = sharp_static_accessor(ce, ZSTR_VAL(name), ZEND_PROPERTY_HOOK_SET);
	if (set) {
		sharp_static_accessor_call(scope, set, ce, name, value, return_value);
		return;
	}
	if (sharp_static_accessor(ce, ZSTR_VAL(name), ZEND_PROPERTY_HOOK_GET) && !zend_hash_exists(&ce->properties_info, name)) {
		zend_throw_error(NULL, "Property %s::$%s is read-only", ZSTR_VAL(ce->name), ZSTR_VAL(name));
		RETURN_THROWS();
	}
	sharp_static_write(scope, ce, name, value);
}

/* Spec section 25: `Property.set(receiver, value)`, the write of `value` into the property `name` that `class` declares
 * on `receiver`, or into the static `name`, as .NET's PropertyInfo.SetValue and Kotlin's KMutableProperty1.set make
 * it. The caller of `set` reaches the property as a direct write would, a get-only or readonly one included, and the
 * value is checked against the property's type as a direct PHP# write's is, since `write` runs in strict mode
 * (PHP_MINIT_FUNCTION). */
ZEND_FUNCTION(Sharp_Internal_write)
{
	zend_object *receiver;
	zend_string *class_name;
	zend_string *name;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_OBJ_OR_NULL(receiver)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_reflected_write(execute_data, receiver, class_name, name, value, return_value);
}

/* Whether the next use of the statics of `ce` runs code a project wrote, spec section 6: the `#statics` of `ce` or of a
 * class above it, which sets their initial values that aren't constant when the request builds them, or a static
 * constructor that has not run yet, see zend_sharp_static_constructors. */
static bool sharp_statics_run_code(zend_class_entry *ce)
{
	for (zend_class_entry *declaring = ce; declaring; declaring = declaring->parent) {
		const zend_function *initializer = zend_hash_str_find_ptr(&declaring->function_table, ZEND_STRL("#statics"));
		if (!initializer || initializer->common.scope != declaring) {
			continue;
		}
		const zval *state = sharp_static_constructor_state(declaring);
		if (!CE_STATIC_MEMBERS(declaring) || (state && Z_TYPE_P(state) == IS_TRUE)) {
			return true;
		}
	}

	return false;
}

/* Whether the PHP# line of the property `info`, named `name`, gives it `init`, spec section 6.1, by which its words
 * open, as `init protected`. */
static bool sharp_is_init(const zend_property_info *info, zend_string *name)
{
	smart_str key = {0};
	smart_str_appendc(&key, '$');
	smart_str_append(&key, name);
	smart_str_0(&key);
	sharp_declared_member line;
	bool found = sharp_declared_line(info->ce, ZSTR_VAL(key.s), ZSTR_LEN(key.s), &line);
	smart_str_free(&key);

	return found && line.words_length >= strlen("init") && memcmp(line.words, "init", strlen("init")) == 0
		&& (line.words_length == strlen("init") || line.words[strlen("init")] == ' ');
}

/* Spec section 25: `Field.get(receiver)`, or with `value` `Field.set(receiver, value)`, the read or the write of the
 * storage of the field, auto-property or plain PHP property `name` that `class_name` declares, on `receiver`, or of the
 * static `name`, by the code that called the standard library member whose body made the internal call `execute_data`,
 * as .NET's FieldInfo.GetValue and SetValue reach a field. The code reaches the storage as a direct use from it would,
 * and a written value is checked against its type as `Property.set` checks it, since `writeField` runs in strict mode
 * (PHP_MINIT_FUNCTION). The storage of an `init` property takes a write only while it holds no value, as only the
 * object's creation writes it. No code a project wrote runs, so a receiver whose class gives the property an accessor,
 * as a PHP 8.4 subclass may by declaring it again, and statics whose initial values have not run throw Error rather than
 * run that code. */
static void sharp_storage_use(zend_execute_data *execute_data, zend_object *receiver, zend_string *class_name,
	zend_string *name, zval *value, zval *return_value)
{
	const char *member = value ? "set" : "get";
	const char *use = value ? "writes" : "reads";
	bool is_static;
	zend_class_entry *ce = sharp_property_class(receiver, class_name, name, member, &is_static);
	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	if (!ce || !sharp_reaches_property(caller, ce, name)) {
		RETURN_THROWS();
	}
	zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	if (is_static) {
		if (sharp_statics_run_code(ce)) {
			zend_throw_error(NULL, "`%s` %s the storage of %s::$%s, and the statics of %s have not run their initial "
				"values yet, which `%s` never runs", member, use, ZSTR_VAL(ce->name), ZSTR_VAL(name), ZSTR_VAL(ce->name),
				member);
			RETURN_THROWS();
		}
		if (value) {
			sharp_static_write(scope, ce, name, value);
			return;
		}
		zval *read = sharp_static_read(scope, ce, name);
		if (!read) {
			RETURN_THROWS();
		}
		RETURN_COPY_DEREF(read);
	}
	const zend_class_entry *outer = EG(fake_scope);
	EG(fake_scope) = scope;
	zend_property_info *info = zend_get_property_info(receiver->ce, name, false);
	EG(fake_scope) = outer;
	if (info == ZEND_WRONG_PROPERTY_INFO) {
		RETURN_THROWS();
	}
	if (info && info->hooks) {
		zend_throw_error(NULL, "`%s` %s the storage of %s::$%s, and %s gives it an accessor, which `%s` never runs",
			member, use, ZSTR_VAL(ce->name), ZSTR_VAL(name), ZSTR_VAL(receiver->ce->name), member);
		RETURN_THROWS();
	}
	if (value && info && IS_VALID_PROPERTY_OFFSET(info->offset) && !Z_ISUNDEF_P(OBJ_PROP(receiver, info->offset))
			&& sharp_is_init(info, name)) {
		zend_throw_error(NULL, "`set` writes the storage of %s::$%s, which `init` lets the object's creation write "
			"once, and it holds a value", ZSTR_VAL(info->ce->name), ZSTR_VAL(name));
		RETURN_THROWS();
	}
	if (!value) {
		zval read;
		zval *found = zend_read_property_ex(scope, receiver, name, false, &read);
		if (EG(exception)) {
			RETURN_THROWS();
		}
		if (found != &read) {
			RETURN_COPY_DEREF(found);
		}
		if (Z_ISREF(read)) {
			zend_unwrap_reference(&read);
		}
		RETURN_COPY_VALUE(&read);
	}
	zend_update_property_ex(scope, receiver, name, value);
}

/* Spec section 25: `Field.get(receiver)`, see sharp_storage_use. */
ZEND_FUNCTION(Sharp_Internal_readField)
{
	zend_object *receiver;
	zend_string *class_name;
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_OBJ_OR_NULL(receiver)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	sharp_storage_use(execute_data, receiver, class_name, name, NULL, return_value);
}

/* Spec section 25: `Field.set(receiver, value)`, see sharp_storage_use. */
ZEND_FUNCTION(Sharp_Internal_writeField)
{
	zend_object *receiver;
	zend_string *class_name;
	zend_string *name;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_OBJ_OR_NULL(receiver)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_storage_use(execute_data, receiver, class_name, name, value, return_value);
}

/* The function whose parameters `Parameter` reads as the method `name` of `ce`: the method itself, or for an accessor,
 * named `$total::get` or `$total::set` as PHP names a property hook, a static's hidden method or an instance property's
 * hook, as `ClassMembers.reflectedMethod` reaches it. NULL for none. */
static const zend_function *sharp_parameter_function(zend_class_entry *ce, zend_string *name)
{
	if (ZSTR_VAL(name)[0] != '$') {
		return sharp_method_named(ce, name);
	}
	const char *separator = strstr(ZSTR_VAL(name), "::");
	if (!separator) {
		return NULL;
	}
	zend_string *property = zend_string_init(ZSTR_VAL(name) + 1, separator - ZSTR_VAL(name) - 1, false);
	const char *accessor = separator + strlen("::");
	int kind = strcmp(accessor, "get") == 0 ? ZEND_PROPERTY_HOOK_GET : ZEND_PROPERTY_HOOK_SET;
	const zend_function *func = sharp_static_accessor(ce, ZSTR_VAL(property), kind);
	if (!func) {
		const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, property);
		func = info && info->hooks ? info->hooks[kind] : NULL;
	}
	zend_string_release_ex(property, false);

	return func;
}

/* Spec section 25: `Parameter.defaultValue()`, the default of the parameter at `position` of the method `method` that
 * `class_name` declares, evaluated as PHP's ReflectionParameter::getDefaultValue evaluates it, in the scope of the
 * class that declares the method. A default that runs code, see sharp_ast_runs_code, throws ValueError before any of it
 * runs, so the read is pure, spec section 29, and `Parameter.hasConstantDefault` tells such a default apart first. A
 * parameter with none, a required or a variadic one, throws OutOfRangeException, as `List.first()` does. */
ZEND_FUNCTION(Sharp_Internal_defaultValue)
{
	zend_string *class_name;
	zend_string *method;
	zend_long position;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(method)
		Z_PARAM_LONG(position)
	ZEND_PARSE_PARAMETERS_END();

	zend_class_entry *ce = zend_lookup_class(class_name);
	if (!ce) {
		if (!EG(exception)) {
			zend_throw_error(NULL, "Class \"%s\" not found", ZSTR_VAL(class_name));
		}
		RETURN_THROWS();
	}
	const zend_function *func = sharp_parameter_function(ce, method);
	uint32_t count = func ? func->common.num_args + !!(func->common.fn_flags & ZEND_ACC_VARIADIC) : 0;
	if (position < 0 || (zend_ulong) position >= count) {
		zend_throw_error(NULL, "PHP# reads no parameter %d of %s::%s. This is a bug in PHP#", (int) position,
			ZSTR_VAL(ce->name), ZSTR_VAL(method));
		RETURN_THROWS();
	}
	zval value;
	if (!sharp_parameter_default(func, (uint32_t) position, &value)) {
		zend_throw_exception_ex(spl_ce_OutOfRangeException, 0,
			"`defaultValue` reads a parameter that has a default, and `%s` of `%s` has none.",
			sharp_parameter_name(func, (uint32_t) position), ZSTR_VAL(method));
		RETURN_THROWS();
	}
	if (Z_TYPE(value) == IS_CONSTANT_AST && sharp_ast_runs_code(Z_ASTVAL(value))) {
		zval_ptr_dtor(&value);
		zend_value_error("`defaultValue` reads a default that runs no code, and `%s` of `%s` has one that runs code, "
			"which `defaultValue` never runs", sharp_parameter_name(func, (uint32_t) position), ZSTR_VAL(method));
		RETURN_THROWS();
	}
	if (Z_TYPE(value) == IS_CONSTANT_AST && zval_update_constant_ex(&value, func->common.scope) != SUCCESS) {
		zval_ptr_dtor(&value);
		RETURN_THROWS();
	}
	RETURN_COPY_VALUE(&value);
}

/* The interned type `node`, a whole type read into `reader`'s arena, spells, for the request. */
static const sharp_type *sharp_type_node_interned(sharp_type_node *node, sharp_type_reader *reader)
{
	sharp_type_node *list = sharp_type_node_start(reader, SHARP_TYPE_LIST, NULL);
	sharp_type_node_add(list, node);
	/* The interned types are found by their text, which the list spells from its member. */
	sharp_type_node_spell(list, &reader->arena);

	return sharp_type_intern(list, SHARP_TYPE_FROM_SUBSTITUTION, &reader->arena)->members[0];
}

/* The type the class value `value` is of, spec section 25: the one sharp_class_values knows it by, or the one its text
 * reads as, where `typeof(List)`, `typeof(Map)` and `typeof(Set)`, which hold the engine's class of the collection,
 * read as the built-in type, spec section 12. NULL with a ValueError for a string that reads as no type. */
static const sharp_type *sharp_class_value_of(const zend_string *value)
{
	const sharp_type *type = sharp_class_value_type(value);
	if (type) {
		return type;
	}
	const char *built_in = zend_string_equals_literal_ci(value, "Sharp\\List") ? "List"
		: zend_string_equals_literal_ci(value, "Sharp\\Map") ? "Map"
		: zend_string_equals_literal_ci(value, "Sharp\\Set") ? "Set"
		: NULL;
	type = built_in ? sharp_type_list(built_in, strlen(built_in)) : NULL;
	if (type) {
		return type->members[0];
	}
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *node = sharp_class_value_read(value, &reader);
	type = node ? sharp_type_node_interned(node, &reader) : NULL;
	zend_arena_destroy(reader.arena);
	if (!type) {
		zend_value_error("\"%s\" is no class value: it names no type", ZSTR_VAL(value));
	}

	return type;
}

/* The type a class value's members read, spec section 25: the type `value` is of without its `?`. */
static const sharp_type *sharp_class_value_read_whole(const zend_string *value)
{
	const sharp_type *type = sharp_class_value_of(value);

	return type && type->kind == SHARP_TYPE_NULLABLE ? type->members[0] : type;
}

/* Adds the class value of each of the `count` types at `types` to the array `list`. */
static void sharp_add_class_values(zval *list, const sharp_type *const *types, uint32_t count)
{
	for (uint32_t i = 0; i < count; i++) {
		add_next_index_str(list, zend_string_copy(sharp_type_class_value(types[i])));
	}
}

/* The text `type` is read from, read whole into `reader`'s arena. */
static sharp_type_node *sharp_type_reread(const sharp_type *type, sharp_type_reader *reader)
{
	sharp_type_reader text = sharp_type_reader_of(ZSTR_VAL(type->text), ZSTR_LEN(type->text), reader->arena);
	sharp_type_node *node = sharp_type_read(&text);
	reader->arena = text.arena;
	ZEND_ASSERT(node && text.at == text.end);

	return node;
}

#define SHARP_CLASS_VALUE_FUNCTION_START() \
	zend_string *value; \
	ZEND_PARSE_PARAMETERS_START(1, 1) \
		Z_PARAM_STR(value) \
	ZEND_PARSE_PARAMETERS_END(); \
	const sharp_type *type = sharp_class_value_read_whole(value); \
	if (!type) { \
		RETURN_THROWS(); \
	}

/* Spec section 25: `Class<T>.typeArguments()`, the class values of the type arguments of the type `type` holds without
 * its `?`, in order, each whole, as `[typeof(string), typeof(Box<int>?)]` for `Map<string, Box<int>?>`. A type of any
 * other kind, or with none, as a class or `int`, gives none. */
ZEND_FUNCTION(Sharp_Internal_typeArguments)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	array_init(return_value);
	if (type->kind == SHARP_TYPE_NAMED) {
		sharp_add_class_values(return_value, type->members, type->count);
	}
}

/* Spec section 25: `Class<T>.kind()`, the kind of the type `type` holds without its `?`, as TypeKind names it: a union, an
 * intersection, a tuple, a function type, `callable` among them, a type parameter, or else a class or a built-in type. */
ZEND_FUNCTION(Sharp_Internal_typeKind)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	switch (type->kind) {
		case SHARP_TYPE_UNION:
			RETURN_STRING("Union");
		case SHARP_TYPE_INTERSECTION:
			RETURN_STRING("Intersection");
		case SHARP_TYPE_TUPLE:
			RETURN_STRING("Tuple");
		case SHARP_TYPE_FUNCTION:
			RETURN_STRING("Function");
		case SHARP_TYPE_PARAMETER:
			RETURN_STRING("TypeParameter");
		default:
			RETURN_STRING(type->built_in == SHARP_BUILT_IN_CALLABLE ? "Function" : "Named");
	}
}

/* Spec section 25: `Class<T>.isNullable()`, whether the type `type` holds takes null: `T?`, a union that holds null, and
 * `null` itself. */
ZEND_FUNCTION(Sharp_Internal_isNullable)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_of(value);
	if (!type) {
		RETURN_THROWS();
	}
	RETURN_BOOL(type->kind == SHARP_TYPE_NULLABLE || type->built_in == SHARP_BUILT_IN_NULL);
}

/* Spec section 25: `Class<T>.nonNullable()`, the class value of the type `type` holds without null: `int` for `int?`,
 * `never` for `null`, and the type itself for one that takes no null. */
ZEND_FUNCTION(Sharp_Internal_nonNullable)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_of(value);
	if (!type) {
		RETURN_THROWS();
	}
	if (type->kind == SHARP_TYPE_NULLABLE) {
		RETURN_STR_COPY(sharp_type_class_value(type->members[0]));
	}
	if (type->built_in == SHARP_BUILT_IN_NULL) {
		RETURN_STRING("never");
	}
	RETURN_STR_COPY(value);
}

/* Spec section 25: the class values the type `type` holds without its `?` is made of, by its kind: a union's and an
 * intersection's members, a tuple's element types, a function type's result type then its parameter types, and a type
 * parameter's bound. `callable` takes a function of any shape, so its result is `Any?` and it lists no parameter. A class
 * gives none: its type arguments are typeArguments'. */
ZEND_FUNCTION(Sharp_Internal_typeParts)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	array_init(return_value);
	if (type->built_in == SHARP_BUILT_IN_CALLABLE) {
		add_next_index_string(return_value, "Any?");
	} else if (type->kind == SHARP_TYPE_FUNCTION) {
		/* A function type's last members are the effects its `uses` lists, which its text counts. */
		sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
		uint32_t effects = sharp_type_reread(type, &reader)->effects;
		zend_arena_destroy(reader.arena);
		sharp_add_class_values(return_value, type->members, type->count - effects);
	} else if (type->kind != SHARP_TYPE_NAMED) {
		sharp_add_class_values(return_value, type->members, type->count);
	}
}

/* Spec section 25: the name of each element of the tuple `type` holds without its `?`, in order, or null for one written
 * without a name. A type of any other kind gives none. */
ZEND_FUNCTION(Sharp_Internal_tupleNames)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	array_init(return_value);
	if (type->kind != SHARP_TYPE_TUPLE) {
		return;
	}
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	for (const sharp_type_node *element = sharp_type_reread(type, &reader)->first; element; element = element->next) {
		if (element->label) {
			add_next_index_stringl(return_value, element->label, element->label_length);
		} else {
			add_next_index_null(return_value);
		}
	}
	zend_arena_destroy(reader.arena);
}

static bool sharp_type_arguments_fit(
	const sharp_type_node *arguments, const zend_class_entry *ce, sharp_type_reader *reader);

/* The number of type parameters the built-in type or the PHP# class-like `type`, a SHARP_TYPE_NAMED, declares. */
static uint32_t sharp_type_arity(const sharp_type *type)
{
	if (!type->class_name) {
		const char *open = memchr(ZSTR_VAL(type->text), '<', ZSTR_LEN(type->text));
		int arity = sharp_type_built_in_arity(
			ZSTR_VAL(type->text), open ? (size_t) (open - ZSTR_VAL(type->text)) : ZSTR_LEN(type->text));

		return arity < 0 ? 0 : (uint32_t) arity;
	}
	zend_class_entry *ce = zend_lookup_class(type->class_name);

	return ce && sharp_class_is_sharp(ce) ? sharp_class_arity(ce) : 0;
}

/* Spec section 25: `Class<T>.genericDefinition()`, the class value of the generic type `type` holds without its `?`,
 * without its type arguments: `List` for `List<int>`, `App.Crate` for `App.Crate<App.Mark>` and for `App.Crate`
 * itself, and `Illuminate.Support.Collection` for a plain PHP class written with type arguments, whose type parameters
 * only its docblock declares. Null for a type that declares no type parameter. */
ZEND_FUNCTION(Sharp_Internal_genericDefinition)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	if (type->kind != SHARP_TYPE_NAMED || (!type->count && !sharp_type_arity(type))) {
		return;
	}
	if (type->class_name) {
		/* A copy, which names the class without type arguments, as its name does, see sharp_class_values. */
		RETURN_STRINGL(ZSTR_VAL(type->class_name), ZSTR_LEN(type->class_name));
	}
	/* `typeof(List)`, `typeof(Map)` and `typeof(Set)` hold the engine's class of the collection, so the definition of
	 * a collection type is that class value, which `==` finds equal to theirs. */
	switch (type->built_in) {
		case SHARP_BUILT_IN_LIST: RETURN_STRING("Sharp\\List");
		case SHARP_BUILT_IN_MAP: RETURN_STRING("Sharp\\Map");
		case SHARP_BUILT_IN_SET: RETURN_STRING("Sharp\\Set");
		default: RETURN_STRINGL(ZSTR_VAL(type->text), strcspn(ZSTR_VAL(type->text), "<"));
	}
}

/* Appends the type parameters of the built-in type `type`, a SHARP_TYPE_NAMED, as sharp_class_parameters spells a
 * class's: spec section 12 names `List<T>`, `Set<T>` and `Map<TKey, TValue>`, and sections 12 and 25 `Iterable<T>` and
 * `Class<T>`. Each has no bound. */
static void sharp_built_in_parameters(smart_str *text, const sharp_type *type)
{
	if (type->built_in == SHARP_BUILT_IN_MAP) {
		smart_str_appends(text, "TKey : Any?, TValue : Any?");
	} else if (sharp_type_arity(type)) {
		smart_str_appends(text, "T : Any?");
	}
}

/* Adds to `list` the class value of each type the type parameter list `text` spells, as sharp_class_parameters spells
 * one, or of none for an empty text. */
static void sharp_add_parameters(zval *list, const smart_str *text)
{
	if (!text->s) {
		return;
	}
	const sharp_type *parameters = sharp_type_list(ZSTR_VAL(text->s), ZSTR_LEN(text->s));
	ZEND_ASSERT(parameters != NULL);
	sharp_add_class_values(list, parameters->members, parameters->count);
}

/* Spec section 25: `Class<T>.typeParameters()`, the type parameters the generic type `type` holds without its `?`
 * declares, each the class value of a TypeParameter with its name and its bound, whatever type arguments the class
 * value gives them. A type that declares none gives none. */
ZEND_FUNCTION(Sharp_Internal_typeParameters)
{
	SHARP_CLASS_VALUE_FUNCTION_START();

	array_init(return_value);
	if (type->kind != SHARP_TYPE_NAMED) {
		return;
	}
	smart_str text = {0};
	if (type->class_name) {
		zend_class_entry *ce = zend_lookup_class(type->class_name);
		if (ce) {
			sharp_class_parameters(&text, ce);
		}
	} else {
		sharp_built_in_parameters(&text, type);
	}
	smart_str_0(&text);
	sharp_add_parameters(return_value, &text);
	smart_str_free(&text);
}

/* The class value of `ancestor`, a class `ce` extends or implements, with the type arguments `ce` gives it when `ce`
 * has `arguments`, or its own type parameters without, spec section 25. */
static zend_string *sharp_ancestor_class_value(
	const zend_class_entry *ce, const sharp_type *arguments, const zend_class_entry *ancestor)
{
	if (!sharp_class_is_sharp(ancestor) || !ancestor->info.user.sharp_bounds) {
		return zend_string_copy(ancestor->name);
	}
	smart_str own = {0};
	if (arguments) {
		smart_str_append(&own, arguments->text);
	} else {
		sharp_class_parameters(&own, ce);
	}
	smart_str_0(&own);
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *given = sharp_type_node_ancestor(ce, sharp_type_node_list(own.s, &reader), ancestor, &reader);
	smart_str text = {0};
	for (size_t i = 0; i < ZSTR_LEN(ancestor->name); i++) {
		smart_str_appendc(&text, ZSTR_VAL(ancestor->name)[i] == '\\' ? '.' : ZSTR_VAL(ancestor->name)[i]);
	}
	smart_str_appendc(&text, '<');
	sharp_type_node_join(&text, given->first, ", ");
	smart_str_appendc(&text, '>');
	smart_str_0(&text);
	const sharp_type *list = sharp_type_list(ZSTR_VAL(text.s), ZSTR_LEN(text.s));
	ZEND_ASSERT(list != NULL);
	smart_str_free(&text);
	smart_str_free(&own);
	zend_arena_destroy(reader.arena);

	return zend_string_copy(sharp_type_class_value(list->members[0]));
}

/* Spec section 25: `Class<T>.baseType()`, the class value of the class the class `type` names extends, with the type
 * arguments it gives it, as sharp_ancestor_class_value reads it. Null for a class that extends none, and for a type
 * that is no class. */
ZEND_FUNCTION(Sharp_Internal_baseType)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	if (ce->parent) {
		RETURN_STR(sharp_ancestor_class_value(ce, arguments, ce->parent));
	}
}

/* Spec section 25: `Class<T>.interfaces()`, the class values of the interfaces the class `type` names implements, its own
 * and those it inherits, each with the type arguments it gives it, as sharp_ancestor_class_value reads them. */
ZEND_FUNCTION(Sharp_Internal_interfaces)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	array_init(return_value);
	for (uint32_t i = 0; i < ce->num_interfaces; i++) {
		add_next_index_str(return_value, sharp_ancestor_class_value(ce, arguments, ce->interfaces[i]));
	}
}

/* Spec section 25: whether the class `type` names is abstract, an interface included, an interface, a struct, and an
 * enum, in that order. */
ZEND_FUNCTION(Sharp_Internal_classFacts)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	bool interface = ce->ce_flags & ZEND_ACC_INTERFACE;
	array_init_size(return_value, 4);
	add_next_index_bool(return_value, interface || (ce->ce_flags & ZEND_ACC_EXPLICIT_ABSTRACT_CLASS));
	add_next_index_bool(return_value, interface);
	add_next_index_bool(return_value, !interface && instanceof_function(ce, sharp_ce_struct));
	add_next_index_bool(return_value, ce->ce_flags & ZEND_ACC_ENUM);
}

/* Spec section 25: `Class<T>.enumBackingType()`, `int` or `string` for a backed enum the class `type` names, and null
 * for a pure enum and any other class. */
ZEND_FUNCTION(Sharp_Internal_enumBackingType)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	if ((ce->ce_flags & ZEND_ACC_ENUM) && ce->enum_backing_type == IS_LONG) {
		RETURN_STRING("int");
	}
	if ((ce->ce_flags & ZEND_ACC_ENUM) && ce->enum_backing_type == IS_STRING) {
		RETURN_STRING("string");
	}
}

/* Spec section 25: `Class<T>.makeGenericType(arguments)`, the class value of the generic type `type` holds, which has no
 * type arguments, with `arguments`, as `typeof(List<Order>)` for `typeof(List)` and `[typeof(Order)]`. Throws
 * InvalidArgumentException when the type is not generic, when `arguments` has another count than it has type
 * parameters, and when an argument falls outside its parameter's bound. */
ZEND_FUNCTION(Sharp_Internal_makeGenericType)
{
	zend_string *value;
	HashTable *given;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(value)
		Z_PARAM_ARRAY_HT(given)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_of(value);
	if (!type) {
		RETURN_THROWS();
	}
	uint32_t arity = type->kind == SHARP_TYPE_NAMED && !type->count ? sharp_type_arity(type) : 0;
	if (zend_hash_num_elements(given) != arity) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0,
			"%s takes %" PRIu32 " type argument%s, and makeGenericType gave it %" PRIu32,
			ZSTR_VAL(type->text), arity, arity == 1 ? "" : "s", zend_hash_num_elements(given));
		RETURN_THROWS();
	}
	smart_str text = {0};
	smart_str_append(&text, type->text);
	smart_str_appendc(&text, '<');
	zval *argument;
	bool first = true;
	ZEND_HASH_FOREACH_VAL(given, argument) {
		const sharp_type *argument_type = Z_TYPE_P(argument) == IS_STRING ? sharp_class_value_of(Z_STR_P(argument)) : NULL;
		if (!argument_type) {
			smart_str_free(&text);
			if (!EG(exception)) {
				zend_argument_type_error(2, "must be a list of class values");
			}
			RETURN_THROWS();
		}
		if (!first) {
			smart_str_appends(&text, ", ");
		}
		smart_str_append(&text, argument_type->text);
		first = false;
	} ZEND_HASH_FOREACH_END();
	smart_str_appendc(&text, '>');
	smart_str_0(&text);
	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(text.s), ZSTR_LEN(text.s), zend_arena_create(1024));
	sharp_type_node *made = sharp_type_read(&reader);
	ZEND_ASSERT(made && reader.at == reader.end);
	zend_class_entry *ce = type->class_name ? zend_lookup_class(type->class_name) : NULL;
	if (ce && !sharp_type_arguments_fit(made, ce, &reader)) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0,
			"%s gives %s type arguments outside the bounds of its type parameters", ZSTR_VAL(text.s), ZSTR_VAL(ce->name));
		smart_str_free(&text);
		zend_arena_destroy(reader.arena);
		RETURN_THROWS();
	}
	const sharp_type *result = sharp_type_node_interned(made, &reader);
	smart_str_free(&text);
	zend_arena_destroy(reader.arena);
	RETURN_STR_COPY(sharp_type_class_value(result));
}

/* Spec section 25: what `Class<T>.enumCases()` reads, the cases of the enum `type` names, in the order they are
 * declared. Empty for a class value that names no enum. */
ZEND_FUNCTION(Sharp_Internal_enumCases)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	array_init(return_value);
	if (!(ce->ce_flags & ZEND_ACC_ENUM)) {
		return;
	}
	zend_string *name;
	zend_class_constant *constant;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(CE_CONSTANTS_TABLE(ce), name, constant) {
		if (!(ZEND_CLASS_CONST_FLAGS(constant) & ZEND_CLASS_CONST_IS_CASE)) {
			continue;
		}
		zend_object *found = zend_enum_get_case(ce, name);
		if (!found) {
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
		zval of_case;
		ZVAL_OBJ_COPY(&of_case, found);
		add_next_index_zval(return_value, &of_case);
	} ZEND_HASH_FOREACH_END();
}

/* The type the class constant `constant`, named `name`, declares, read on a class value of `ce` whose type arguments are
 * `arguments`, as sharp_declared_types reads it: the line `::name` of its PHP# class, or its PHP declaration's type,
 * `Any?` for one with none. */
static const sharp_type *sharp_constant_declared_type(const zend_class_constant *constant, const zend_string *name,
	const zend_class_entry *ce, const sharp_type *arguments)
{
	smart_str key = {0};
	smart_str_appends(&key, "::");
	smart_str_append(&key, name);
	smart_str_0(&key);
	sharp_declared_member line;
	smart_str written = {0};
	if (!sharp_declared_line(constant->ce, ZSTR_VAL(key.s), ZSTR_LEN(key.s), &line)) {
		sharp_php_type_text(&written, constant->type, constant->ce, ce);
		smart_str_0(&written);
		line.type = ZSTR_VAL(written.s);
		line.type_length = ZSTR_LEN(written.s);
	}
	const sharp_type *types = sharp_declared_types(line.type, line.type_length, constant->ce, ce, arguments);
	smart_str_free(&written);
	smart_str_free(&key);

	return types ? types->members[0] : NULL;
}

/* Spec section 25: what `Class<T>.constants()` reads, the constants of the class `type` names, its own first and then
 * those it inherits, in the order they are declared, at every visibility, without an enum's cases, which
 * `enumCases()` reads, and without a hidden one the bridge names with `#`. Each is the list of the class that declares
 * it, its name, and its type as sharp_add_declared_type adds it. A class's are read once a request, see
 * request_reflected. */
ZEND_FUNCTION(Sharp_Internal_constants)
{
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	zend_string *key;
	zval *read = sharp_reflected('c', ce, arguments, &key);
	if (read) {
		zend_string_release_ex(key, false);
		RETURN_COPY(read);
	}

	array_init(return_value);
	zend_string *name;
	zend_class_constant *constant;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(CE_CONSTANTS_TABLE(ce), name, constant) {
		if ((ZEND_CLASS_CONST_FLAGS(constant) & ZEND_CLASS_CONST_IS_CASE) || memchr(ZSTR_VAL(name), '#', ZSTR_LEN(name))) {
			continue;
		}
		const sharp_type *type = sharp_constant_declared_type(constant, name, ce, arguments);
		if (!type) {
			zend_string_release_ex(key, false);
			zend_throw_error(NULL, "PHP# reads no type for %s::%s. This is a bug in PHP#", ZSTR_VAL(constant->ce->name),
				ZSTR_VAL(name));
			zval_ptr_dtor(return_value);
			RETVAL_NULL();
			RETURN_THROWS();
		}
		zval row;
		array_init_size(&row, 3);
		add_next_index_str(&row, zend_string_copy(constant->ce->name));
		add_next_index_str(&row, zend_string_copy(name));
		sharp_add_declared_type(&row, type);
		zend_hash_next_index_insert_new(Z_ARRVAL_P(return_value), &row);
	} ZEND_HASH_FOREACH_END();
	sharp_reflected_keep(key, return_value);
}

/* Spec section 25: `Constant.value()`, the value of the constant `name` that `class` declares, as a direct read of
 * `Class.NAME` from the code that called `value()` gives it, which reaches a private constant only from its class and
 * a protected one from the class and its subclasses. A `Map` or `Set` constant holds the PHP array of its value, and
 * gives the object a direct read makes of it, kept under the key a direct read keeps it by, see Sharp_Internal_keep,
 * so the two share it. */
ZEND_FUNCTION(Sharp_Internal_constant)
{
	zend_string *class_name;
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	zend_class_entry *scope = caller ? caller->func->common.scope : NULL;
	zval *read = zend_get_class_constant_ex(class_name, name, scope, ZEND_FETCH_CLASS_EXCEPTION);
	if (!read || EG(exception)) {
		RETURN_THROWS();
	}
	ZVAL_DEREF(read);
	zend_class_entry *ce = zend_lookup_class(class_name);
	const zend_class_constant *constant = ce ? zend_hash_find_ptr(CE_CONSTANTS_TABLE(ce), name) : NULL;
	const sharp_type *type = constant && Z_TYPE_P(read) == IS_ARRAY
		? sharp_constant_declared_type(constant, name, ce, NULL)
		: NULL;
	zval plan;
	ZVAL_NULL(&plan);
	if (type) {
		sharp_type_plan(&plan, type);
	}
	if (Z_TYPE(plan) == IS_NULL) {
		RETURN_COPY(read);
	}
	zend_string *kept_key = zend_strpprintf(0, "%s::%s#constant", ZSTR_VAL(constant->ce->name), ZSTR_VAL(name));
	zval *kept = zend_hash_find(&SHARP_G(container_singletons), kept_key);
	if (kept) {
		zend_string_release_ex(kept_key, false);
		zval_ptr_dtor(&plan);
		RETURN_COPY(kept);
	}
	bool crossed = sharp_crossed(return_value, read, &plan, type);
	zval_ptr_dtor(&plan);
	if (!crossed) {
		zend_string_release_ex(kept_key, false);
		RETURN_THROWS();
	}
	Z_TRY_ADDREF_P(return_value);
	zend_hash_update(&SHARP_G(container_singletons), kept_key, return_value);
	zend_string_release_ex(kept_key, false);
}

/* Whether each class `node` and the types inside it name exists, as sharp_type_class loads it. */
static bool sharp_type_node_names_classes(sharp_type_node *node)
{
	for (sharp_type_node *member = node->first; member; member = member->next) {
		if (!sharp_type_node_names_classes(member)) {
			return false;
		}
	}

	return node->kind != SHARP_TYPE_NAMED || !node->name || sharp_type_node_class(node);
}

/* Whether the string `value` is a class value of `Class<type>`, for a `type` that is no class, spec section 25: the
 * class value of a type that fits `type`, as the checker lets `Class<X>` fit `Class<Y>` when `X` fits `Y`, so a
 * `Class<int|string>` takes `int`, `string` and `int|string`. */
static bool sharp_class_value_fits(const zend_string *value, const sharp_type *type)
{
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *given = sharp_class_value_read(value, &reader);
	bool fits = given && sharp_type_argument_takes(type, given, '+', &reader);
	zend_arena_destroy(reader.arena);

	return fits;
}

/* Whether the string `value` is the class value of a type: one with type arguments sharp_class_values knows, or a text
 * that reads as a type whose every class exists. */
static bool sharp_class_value_names_type(const zend_string *value)
{
	if (sharp_class_value_type(value)) {
		return true;
	}
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *given = sharp_class_value_read(value, &reader);
	bool names = given && sharp_type_node_names_classes(given);
	zend_arena_destroy(reader.arena);

	return names;
}

/* Whether the string `value` is a class value of `Class<type>`, spec section 11: it names `type`'s class or a class that
 * extends or implements it, and the type arguments it carries give that class type arguments `type` takes. A class name
 * plain PHP writes carries none, so it gives its class's bounds, as an object plain PHP creates does. A `Class` of a
 * class with no type arguments written, as `Class<Box>` of a generic `Box`, takes the class with any type arguments.
 * `Class<Object>` takes the class value of any class. A `Class` of a type that is no class, as `Class<int>` or
 * `Class<int|string>`, takes what sharp_class_value_fits takes, and `Class<Any?>` and a `Class` of a type parameter no
 * type argument resolves take the class value of any type. A string that names no type is no class value. */
static bool sharp_class_value_is(const zval *value, const sharp_type *type)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return false;
	}
	const sharp_type *held = type->kind == SHARP_TYPE_NULLABLE ? type->members[0] : type;
	if (held->kind == SHARP_TYPE_NAMED && held->built_in == SHARP_BUILT_IN_OBJECT) {
		return sharp_class_value_class(Z_STR_P(value)) != NULL;
	}
	if (held->kind == SHARP_TYPE_NAMED
		&& (held->built_in == SHARP_BUILT_IN_ANY || zend_string_equals_literal(held->text, SHARP_TYPE_UNRESOLVED))) {
		return sharp_class_value_names_type(Z_STR_P(value));
	}
	if (type->kind != SHARP_TYPE_NAMED || !type->class_name) {
		return sharp_class_value_fits(Z_STR_P(value), type);
	}

	/* A class value is text, so the class it names may not be loaded yet. That class loads each class it extends or
	 * implements, so `type`'s class is loaded whenever it is one of them. */
	zend_class_entry *given = sharp_class_value_class(Z_STR_P(value));
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

/* Appends the class `ce` as PHP# writes it, `App.Order`. */
static void sharp_class_text(smart_str *text, const zend_class_entry *ce)
{
	const zend_string *name = ce->name;
	for (size_t i = 0; i < ZSTR_LEN(name); i++) {
		smart_str_appendc(text, ZSTR_VAL(name)[i] == '\\' ? '.' : ZSTR_VAL(name)[i]);
	}
}

/* Appends an object's class as PHP# writes it, `App.Order`, with its type arguments when its class is generic,
 * `App.Box<int>`. */
static void sharp_object_text(smart_str *text, zend_object *object)
{
	sharp_class_text(text, object->ce);

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
	/* As `is` tests it: a closure plain PHP made is of the function types its PHP declaration's can be assigned to. */
	SHARP_HOLDS_TEST,
	/* As a value from plain PHP enters PHP#: a closure plain PHP made is of every function type, as PHP's own check of
	 * `Closure` takes it, as a raw generic object has its bounds. */
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

	/* A key is text or an int, so the enum it names a case of may not be loaded yet. */
	zend_class_entry *ce = type->class_name ? sharp_type_class(type->class_name) : NULL;
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
 * key a Sharp\Map holds, as `[object Lib\Standing::Active]`. Without `problems`, a bad key stops the check. With no
 * `element`, only the key is checked. */
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
	if (element && (holds || problems) && !sharp_type_holds(elements, element, mode, problems)) {
		holds = false;
	}

	if (problems) {
		ZSTR_LEN(problems->path.s) = length;
	}

	return holds;
}

/* Gives the Sharp\Map or Sharp\Set `table` the type arguments of `type`, its `Map` or `Set`, `string, int` for
 * `Map<string, int>`, unless it has its own already. */
static void sharp_table_type_store(zend_object *table, const sharp_type *type)
{
	if (sharp_table_object_of(table)->arguments) {
		return;
	}
	const char *open = memchr(ZSTR_VAL(type->text), '<', ZSTR_LEN(type->text));
	const sharp_type *arguments = open
		? sharp_type_list(open + 1, ZSTR_VAL(type->text) + ZSTR_LEN(type->text) - open - 2) : NULL;

	if (arguments) {
		sharp_type_arguments_store(table, arguments);
	}
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
		if (UNEXPECTED(EG(exception)) || (!holds && !problems) || sharp_out_of_memory()) {
			break;
		}
	} SHARP_TABLE_FOREACH_END();
	OBJ_RELEASE(table);

	return holds;
}

/* Whether `type` is `Any?`, which every value is of. */
static bool sharp_type_is_any_value(const sharp_type *type)
{
	return type->kind == SHARP_TYPE_NULLABLE && type->members[0]->kind == SHARP_TYPE_NAMED
		&& !type->members[0]->class_name && type->members[0]->built_in == SHARP_BUILT_IN_ANY;
}

/* Whether `value`, an array, or the Sharp\Map or Sharp\Set of a `Map` or a `Set`, is of the collection `type`, each
 * element checked by sharp_type_holds. A collection of `Any?`, as `List<Any?>`, holds each element without a walk. */
static bool sharp_type_collection_holds(
	const sharp_type *type, const zval *value, sharp_holds_mode mode, sharp_type_problems *problems)
{
	const sharp_type *keys = type->built_in == SHARP_BUILT_IN_MAP ? type->members[0] : NULL;
	const sharp_type *elements = sharp_type_elements(type);
	bool walked = !sharp_type_is_any_value(elements) || (keys && !sharp_type_is_any_value(keys));
	if (Z_TYPE_P(value) == IS_OBJECT) {
		bool holds = !walked || sharp_type_table_holds(type, Z_OBJ_P(value), mode, problems);
		/* The first typed boundary that takes the table, a property, a parameter or a return, gives it its type, which
		 * each later write plain PHP makes into it takes, as an object has one type. A test, as `is`, only looks. */
		if (holds && mode != SHARP_HOLDS_TEST && type->built_in != SHARP_BUILT_IN_ITERABLE && !EG(exception)) {
			sharp_table_type_store(Z_OBJ_P(value), type);
		}
		return holds;
	}
	if (type->built_in == SHARP_BUILT_IN_LIST && !zend_array_is_list(Z_ARRVAL_P(value))) {
		if (problems) {
			sharp_type_problem(problems, "", type, value);
		}
		return false;
	}
	if (!walked) {
		return true;
	}

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
		if (UNEXPECTED(EG(exception)) || (!holds && !problems) || sharp_out_of_memory()) {
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
		if (UNEXPECTED(EG(exception)) || (!holds && !problems) || sharp_out_of_memory()) {
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
		case SHARP_TYPE_PARAMETER:
			/* Only reflection reads a type parameter itself, which holds what its bound holds. */
			return sharp_type_holds(type->members[0], value, mode, NULL);
		/* No value is checked against a list or a function's result alone, which only a function carries. */
		case SHARP_TYPE_LIST:
		case SHARP_TYPE_RESULT:
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
		case SHARP_BUILT_IN_NEVER:
			return false;
		case SHARP_BUILT_IN_CALLABLE: {
			zval tested;
			ZVAL_COPY_VALUE(&tested, value);
			return zend_is_callable(&tested, 0, NULL);
		}
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
		if (sharp_out_of_memory()) {
			return;
		}
		sharp_type_convert(sharp_type_element_at(converting, position++), element);
	} ZEND_HASH_FOREACH_END();
}

/* Whether a value of `type` is or holds a `List`, in a `Map`'s values or at a tuple's position, which plain PHP's array
 * keyed by int alone enters renumbered. */
static bool sharp_type_holds_list(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_holds_list(type->members[0]);
	}
	if (type->kind == SHARP_TYPE_TUPLE) {
		for (uint32_t i = 0; i < type->count; i++) {
			if (sharp_type_holds_list(type->members[i])) {
				return true;
			}
		}
		return false;
	}
	if (type->kind != SHARP_TYPE_NAMED || type->class_name) {
		return false;
	}

	return type->built_in == SHARP_BUILT_IN_LIST
		|| (type->built_in == SHARP_BUILT_IN_MAP && sharp_type_holds_list(type->members[1]));
}

/* Whether `array` is keyed by int alone with a gap or out of order, as array_filter leaves an array. */
static bool sharp_array_needs_renumbering(HashTable *array)
{
	if (zend_array_is_list(array)) {
		return false;
	}
	zend_string *name;
	ZEND_HASH_FOREACH_STR_KEY(array, name) {
		if (name) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* The type of the element at `position` of `type`, `T?` read as `T`, which sharp_type_renumber walks: NULL past a
 * tuple's last position and for a type that holds no `List`. */
static const sharp_type *sharp_type_renumbered_element(const sharp_type *type, uint32_t position)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		type = type->members[0];
	}
	if (type->kind == SHARP_TYPE_TUPLE && position >= type->count) {
		return NULL;
	}
	const sharp_type *element = sharp_type_element_at(type, position);

	return element && sharp_type_holds_list(element) ? element : NULL;
}

/* Whether `type` takes a string as text, spec section 18: `string`, and a nullable type or a union that holds it. */
static bool sharp_type_takes_text(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_takes_text(type->members[0]);
	}
	if (type->kind == SHARP_TYPE_UNION) {
		for (uint32_t i = 0; i < type->count; i++) {
			if (sharp_type_takes_text(type->members[i])) {
				return true;
			}
		}

		return false;
	}

	return type->kind == SHARP_TYPE_NAMED && type->built_in == SHARP_BUILT_IN_STRING;
}

/* The type `type` gives the element at `position` of an array it holds, as sharp_type_element_at reads it through a
 * nullable type, or NULL for a type that holds no collection or tuple, or a tuple shorter than `position`. */
static const sharp_type *sharp_type_text_element(const sharp_type *type, uint32_t position)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		type = type->members[0];
	}
	if (type->kind == SHARP_TYPE_TUPLE) {
		return position < type->count ? type->members[position] : NULL;
	}

	return type->kind == SHARP_TYPE_NAMED ? sharp_type_elements(type) : NULL;
}

/* The key type of a `Map` `type` holds, through a nullable type, or NULL for any other type. */
static const sharp_type *sharp_type_text_keys(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		type = type->members[0];
	}

	return type->kind == SHARP_TYPE_NAMED && type->built_in == SHARP_BUILT_IN_MAP ? type->members[0] : NULL;
}

/* Whether `text` is valid UTF-8. A string read as valid keeps IS_STR_VALID_UTF8, as PCRE keeps it, so it is read once. */
static bool sharp_text_is_valid(zend_string *text)
{
	if (GC_FLAGS(text) & IS_STR_VALID_UTF8) {
		return true;
	}
	const unsigned char *bytes = (const unsigned char *) ZSTR_VAL(text);
	size_t length = ZSTR_LEN(text), cursor = 0;
	while (cursor < length) {
		if (bytes[cursor] < 0x80) {
			cursor++;
			continue;
		}
		zend_result status;
		php_next_utf8_char(bytes, length, &cursor, &status);
		if (status == FAILURE) {
			return false;
		}
	}
	if (!ZSTR_IS_INTERNED(text)) {
		GC_ADD_FLAGS(text, IS_STR_VALID_UTF8);
	}

	return true;
}

/* Appends `text` to `out`, each byte sequence that is not UTF-8 written by `invalid`: U+FFFD once for each maximal
 * subpart, as Unicode recommends and PHP's own decoder reads them, or each of its bytes as `\xHH`. */
static void sharp_text_append(smart_str *out, const zend_string *text, bool escape)
{
	const unsigned char *bytes = (const unsigned char *) ZSTR_VAL(text);
	size_t length = ZSTR_LEN(text), cursor = 0;
	while (cursor < length) {
		size_t start = cursor;
		if (bytes[cursor] < 0x80) {
			cursor++;
			smart_str_appendc(out, (char) bytes[start]);
			continue;
		}
		zend_result status;
		php_next_utf8_char(bytes, length, &cursor, &status);
		if (status == SUCCESS) {
			smart_str_appendl(out, (const char *) bytes + start, cursor - start);
		} else if (escape) {
			for (size_t i = start; i < cursor; i++) {
				smart_str_append_printf(out, "\\x%02X", bytes[i]);
			}
		} else {
			smart_str_appendl(out, "\xEF\xBF\xBD", 3);
		}
	}
}

/* `text` as valid UTF-8, each byte sequence that is not UTF-8 replaced by U+FFFD, spec section 18. */
static zend_string *sharp_text_of(zend_string *text)
{
	if (sharp_text_is_valid(text)) {
		return zend_string_copy(text);
	}
	smart_str replaced = {0};
	sharp_text_append(&replaced, text, false);
	zend_string *result = smart_str_extract(&replaced);
	if (!ZSTR_IS_INTERNED(result)) {
		GC_ADD_FLAGS(result, IS_STR_VALID_UTF8);
	}

	return result;
}

/* Whether `type` takes the standard library's `Bytes`, alone, nullable or in a union, and no text beside it. */
static bool sharp_type_takes_bytes(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_takes_bytes(type->members[0]);
	}
	if (type->kind == SHARP_TYPE_UNION) {
		bool bytes = false;
		for (uint32_t i = 0; i < type->count; i++) {
			bytes = bytes || sharp_type_takes_bytes(type->members[i]);
		}

		return bytes && !sharp_type_takes_text(type);
	}

	return type->kind == SHARP_TYPE_NAMED && type->class_name
		&& zend_string_equals_literal_ci(type->class_name, "Sharp\\Bytes");
}

/* Whether `value` is the standard library's `Bytes`, which plain PHP receives as PHP's string of its bytes. */
static bool sharp_is_bytes(const zval *value)
{
	return Z_TYPE_P(value) == IS_OBJECT && zend_string_equals_literal_ci(Z_OBJCE_P(value)->name, "Sharp\\Bytes");
}

/* The slot of the `Bytes` object `bytes` that holds PHP's string of its bytes. */
static zval *sharp_bytes_data(zend_object *bytes)
{
	zend_property_info *data = zend_hash_str_find_ptr(&bytes->ce->properties_info, ZEND_STRL("data"));
	ZEND_ASSERT(data && !(data->flags & ZEND_ACC_STATIC));

	return OBJ_PROP(bytes, data->offset);
}

/* Spec section 18: puts in `value` the `Bytes` that holds the string `value` holds, every byte kept. It is built as its
 * engine-only creation, with no constructor: a plain PHP string becomes `Bytes` only where it crosses. */
static void sharp_bytes_wrap(zval *value)
{
	zend_string *name = zend_string_init(ZEND_STRL("Sharp\\Bytes"), false);
	zend_class_entry *ce = zend_lookup_class(name);
	zend_string_release(name);
	if (!ce) {
		if (!EG(exception)) {
			zend_throw_error(NULL, "Sharp.Bytes is not loaded, and a plain PHP string crosses into a Bytes place");
		}
		return;
	}
	zval bytes;
	if (object_init_to_build(&bytes, ce) == FAILURE) {
		return;
	}
	zval *slot = sharp_bytes_data(Z_OBJ(bytes));
	ZVAL_COPY_VALUE(slot, value);
	Z_PROP_FLAG_P(slot) = 0;
	ZVAL_COPY_VALUE(value, &bytes);
}

/* Whether sharp_type_take_strings changes `value`: a string in a place that takes text that is not valid UTF-8, a string
 * in a `Bytes` place, or such a string among the elements, the tuple slots or the `Map` keys `type` gives an array. */
static bool sharp_type_takes_strings(const sharp_type *type, const zval *value)
{
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) == IS_STRING) {
		return sharp_type_takes_text(type) ? !sharp_text_is_valid(Z_STR_P(value)) : sharp_type_takes_bytes(type);
	}
	if (Z_TYPE_P(value) != IS_ARRAY) {
		return false;
	}
	const sharp_type *keys = sharp_type_text_keys(type);
	bool text_keys = keys && sharp_type_takes_text(keys);
	uint32_t position = 0;
	zend_string *key;
	zval *element;
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL_P(value), key, element) {
		const sharp_type *at = sharp_type_text_element(type, position++);
		if ((text_keys && key && !sharp_text_is_valid(key)) || (at && sharp_type_takes_strings(at, element))) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();

	return false;
}

/* Throws the ValueError for two keys of a `Map` that are one once each byte sequence that is not UTF-8 becomes U+FFFD,
 * each bad byte written as `\xHH`, so the message itself is text. */
static ZEND_COLD void sharp_keys_refuse_collision(const zend_string *first, const zend_string *second)
{
	smart_str message = {0};
	smart_str_appends(&message, "This `Map` holds both `\"");
	sharp_text_append(&message, first, true);
	smart_str_appends(&message, "\"` and `\"");
	sharp_text_append(&message, second, true);
	smart_str_appends(&message, "\"`, which are one key once each byte sequence that is not UTF-8 becomes U+FFFD");
	smart_str_0(&message);
	zend_value_error("%s", ZSTR_VAL(message.s));
	smart_str_free(&message);
}

/* Spec section 18: a string that crosses into a place that takes text, alone or as an element, a tuple slot or a `Map`
 * key, has each byte sequence that is not UTF-8 replaced by U+FFFD, and one that crosses into a `Bytes` place becomes
 * the `Bytes` that holds it. An array is copied only when a string in it changes, and the keys are rebuilt only when
 * one changes. */
static void sharp_type_take_strings(const sharp_type *type, zval *value)
{
	if (!sharp_type_takes_strings(type, value)) {
		return;
	}
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) == IS_STRING && !sharp_type_takes_text(type)) {
		sharp_bytes_wrap(value);
		return;
	}
	if (Z_TYPE_P(value) == IS_STRING) {
		zend_string *text = sharp_text_of(Z_STR_P(value));
		zval_ptr_dtor_str(value);
		ZVAL_STR(value, text);
		return;
	}

	SEPARATE_ARRAY(value);
	uint32_t position = 0;
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		const sharp_type *at = sharp_type_text_element(type, position++);
		if (at) {
			sharp_type_take_strings(at, element);
			if (UNEXPECTED(EG(exception))) {
				return;
			}
		}
	} ZEND_HASH_FOREACH_END();

	const sharp_type *keys = sharp_type_text_keys(type);
	if (!keys || !sharp_type_takes_text(keys)) {
		return;
	}
	bool changes = false;
	zend_string *key;
	ZEND_HASH_FOREACH_STR_KEY(Z_ARRVAL_P(value), key) {
		changes = changes || (key && !sharp_text_is_valid(key));
	} ZEND_HASH_FOREACH_END();
	if (!changes) {
		return;
	}

	HashTable *rekeyed = zend_new_array(zend_hash_num_elements(Z_ARRVAL_P(value)));
	HashTable *sources = zend_new_array(0);
	zend_ulong index;
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(value), index, key, element) {
		Z_TRY_ADDREF_P(element);
		if (!key) {
			zend_hash_index_update(rekeyed, index, element);
			continue;
		}
		zend_string *text = sharp_text_of(key);
		zval *earlier = zend_symtable_find(sources, text);
		if (earlier) {
			sharp_keys_refuse_collision(Z_STR_P(earlier), key);
			zval_ptr_dtor(element);
			zend_string_release(text);
			zend_array_destroy(rekeyed);
			zend_array_destroy(sources);
			return;
		}
		zval source;
		ZVAL_STR_COPY(&source, key);
		zend_symtable_update(sources, text, &source);
		zend_symtable_update(rekeyed, text, element);
		zend_string_release(text);
	} ZEND_HASH_FOREACH_END();
	zend_array_destroy(sources);
	zval_ptr_dtor(value);
	ZVAL_ARR(value, rekeyed);
}

/* Spec section 18: `Bytes.toText()`, `bytes` read as UTF-8 as a string that crosses into PHP# is. */
ZEND_FUNCTION(Sharp_Internal_text)
{
	zend_string *bytes;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(bytes)
	ZEND_PARSE_PARAMETERS_END();

	RETURN_STR(sharp_text_of(bytes));
}

/* Whether sharp_type_renumber changes `value`. */
static bool sharp_type_renumbers(const sharp_type *type, const zval *value)
{
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) != IS_ARRAY || !sharp_type_holds_list(type)) {
		return false;
	}
	const sharp_type *place = type->kind == SHARP_TYPE_NULLABLE ? type->members[0] : type;
	if (place->kind == SHARP_TYPE_NAMED && place->built_in == SHARP_BUILT_IN_LIST
			&& sharp_array_needs_renumbering(Z_ARRVAL_P(value))) {
		return true;
	}

	uint32_t position = 0;
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		const sharp_type *at = sharp_type_renumbered_element(type, position++);
		if (at && sharp_type_renumbers(at, element)) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();

	return false;
}

/* Spec section 12: plain PHP's array keyed by int alone, such as array_filter gives, enters a `List` place renumbered,
 * as PHP# renumbers it where it reads one, and so does each such array in it that `type` places in a `List`: a `Map`'s
 * value, a `List`'s element and a tuple's element. The three crossings take it so: an argument from plain PHP, the
 * result of a plain PHP call PHP# checks, and a property plain PHP writes. A list is taken as it is, and an array with
 * a string key is no list. An array is separated only when it changes. Each crossing makes its strings text first, as
 * sharp_type_take_strings does, and wraps a plain PHP closure that crosses into a function type, as sharp_closure_check
 * does. */
static void sharp_type_renumber(const sharp_type *type, zval *value)
{
	sharp_type_take_strings(type, value);
	sharp_closure_check(type, value);
	if (UNEXPECTED(EG(exception)) || !sharp_type_renumbers(type, value)) {
		return;
	}
	ZVAL_DEREF(value);
	const sharp_type *place = type->kind == SHARP_TYPE_NULLABLE ? type->members[0] : type;
	if (place->kind == SHARP_TYPE_NAMED && place->built_in == SHARP_BUILT_IN_LIST
			&& sharp_array_needs_renumbering(Z_ARRVAL_P(value))) {
		HashTable *renumbered = zend_array_to_list(Z_ARRVAL_P(value));
		zval_ptr_dtor(value);
		ZVAL_ARR(value, renumbered);
	}

	SEPARATE_ARRAY(value);
	uint32_t position = 0;
	zval *element;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
		if (sharp_out_of_memory()) {
			return;
		}
		const sharp_type *at = sharp_type_renumbered_element(type, position++);
		if (at) {
			sharp_type_renumber(at, element);
		}
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

	return !EG(exception);
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

/* What is wrong with the type text list `text`, which an internal function takes as an argument, or NULL when it holds
 * `count` types, or any number for 0, and names a type parameter of this only for an `object` and one of the method
 * only for a `method`. Plain PHP may pass any text, and sharp_type_list_of_frame reads only a text that passes. */
static const char *sharp_caller_type_text_problem(zend_string *text, uint32_t count, bool object, bool method)
{
	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(text), ZSTR_LEN(text), zend_arena_create(1024));
	const sharp_type_node *read = sharp_type_read_list(&reader);
	bool is_list = read && (!count || read->count == count) && reader.at == reader.end;
	uint32_t names = reader.names;
	zend_arena_destroy(reader.arena);
	if (!is_list) {
		return count == 1 ? "must be one PHP# type" : "must be a PHP# type list";
	}
	if (((names & SHARP_TYPE_NAMES_THIS) && !object) || ((names & SHARP_TYPE_NAMES_METHOD) && !method)) {
		return "must name only type parameters the calling code has type arguments for";
	}

	return NULL;
}

/* The type text list `text` that the internal function `execute_data` runs takes as its argument `argument`, spelled
 * for the user code that calls it, as ZEND_SHARP_VERIFY_TYPE_ARGS spells its own, so it can name `$0` or `#0`. It holds
 * `count` types, or any number for 0. NULL after the ValueError for a text that is no such list or names a type
 * parameter the calling code has no type argument for. */
static const sharp_type *sharp_caller_type_list(
	zend_execute_data *execute_data, zend_string *text, uint32_t argument, uint32_t count)
{
	const zend_execute_data *caller = EX(prev_execute_data);
	bool is_user_code = caller && caller->func && ZEND_USER_CODE(caller->func->type);
	zend_object *object = is_user_code && Z_TYPE(caller->This) == IS_OBJECT ? Z_OBJ(caller->This) : NULL;
	const sharp_type *method = is_user_code ? sharp_frame_method_type_arguments(caller) : NULL;
	const char *problem = sharp_caller_type_text_problem(text, count, object, method);
	if (problem) {
		zend_argument_value_error(argument, "%s", problem);
		return NULL;
	}

	zval spelled;
	ZVAL_STR(&spelled, text);
	void *cache[4] = {NULL};
	const sharp_type *list = sharp_type_list_of_frame(
		&spelled, object, method, false, is_user_code ? caller->func->common.scope : NULL, cache);
	if (!list && !EG(exception)) {
		zend_argument_value_error(argument, "must name only type parameters the calling code has type arguments for");
	}

	return list;
}

/* Gives the Map or Set `table`, which a native made for the PHP# code that calls it, the type arguments `text` the
 * bridge passes as the native's argument `argument`, `K, V` or `T`, so each write plain PHP makes into it is checked,
 * as in a literal's. A native plain PHP calls passes none, and its table takes them at the first typed boundary. False
 * after the Error. */
static bool sharp_table_made_with(zend_execute_data *execute_data, zend_object *table, zend_string *text, uint32_t argument)
{
	if (!text) {
		return true;
	}
	const sharp_type *arguments = sharp_caller_type_list(execute_data, text, argument, 0);
	if (!arguments || !sharp_class_takes(table->ce, arguments)) {
		return false;
	}
	sharp_type_arguments_store(table, arguments);

	return true;
}

zval *sharp_static_constructor_state(zend_class_entry *ce)
{
	zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, sharp_static_constructor_key);
	if (EXPECTED(!info) || info->ce != ce || !(info->flags & ZEND_ACC_SHARP_HIDDEN) || !CE_STATIC_MEMBERS(ce)) {
		return NULL;
	}

	zval *state = CE_STATIC_MEMBERS(ce) + info->offset;
	ZVAL_DEINDIRECT(state);

	return state;
}

ZEND_COLD void sharp_throw_unset_property(const zend_class_entry *ce, const zend_string *name)
{
	smart_str member = {0};
	for (size_t i = 0; i < ZSTR_LEN(ce->name); i++) {
		smart_str_appendc(&member, ZSTR_VAL(ce->name)[i] == '\\' ? '.' : ZSTR_VAL(ce->name)[i]);
	}
	smart_str_appendc(&member, '.');
	smart_str_append(&member, name);
	smart_str_0(&member);

	zend_throw_error(NULL, "%s is read before its first write.", ZSTR_VAL(member.s));
	smart_str_free(&member);
}

/* Spec section 6, as C#'s TypeInitializationException: `The static constructor of App.Rates threw
 * RuntimeException: rates are down`, with the failure as its previous throwable. */
ZEND_COLD void sharp_throw_static_constructor_failure(
	const zend_class_entry *ce, const zend_string *type_arguments, zend_object *failure)
{
	smart_str class = {0};
	for (size_t i = 0; i < ZSTR_LEN(ce->name); i++) {
		smart_str_appendc(&class, ZSTR_VAL(ce->name)[i] == '\\' ? '.' : ZSTR_VAL(ce->name)[i]);
	}
	if (type_arguments) {
		smart_str_appendc(&class, '<');
		smart_str_append(&class, type_arguments);
		smart_str_appendc(&class, '>');
	}
	smart_str_0(&class);
	smart_str thrown = {0};
	sharp_object_text(&thrown, failure);
	smart_str_0(&thrown);
	zval rv;
	zend_string *message = zval_get_string(zend_read_property_ex(
		zend_get_exception_base(failure), failure, ZSTR_KNOWN(ZEND_STR_MESSAGE), true, &rv));

	zend_throw_error(NULL, "The static constructor of %s threw %s: %s",
		ZSTR_VAL(class.s), ZSTR_VAL(thrown.s), ZSTR_VAL(message));
	GC_ADDREF(failure);
	zend_exception_set_previous(EG(exception), failure);

	zend_string_release(message);
	smart_str_free(&thrown);
	smart_str_free(&class);
}

/* Spec section 11: each type argument of a generic class has its own statics, which every PHP# access reads and
 * writes through this, with the text of the access's type arguments, as
 * `\Sharp\Internal\statics(\App\Counter::class, 'App.Order')['count']`. Returns by reference the entry of the class's
 * hidden static sharp_statics_key for the type argument text `key`, so every write is PHP's own array write. The entry
 * starts as a copy of the defaults under the key "", and the call that adds it runs the class's hidden `#init` with
 * the type arguments `key` spells, which sets each initial value that runs as code, as C# runs a closed type's static
 * initializer on its first use. A throw there reaches this call's caller and leaves the entry as it is, so nothing
 * runs again. In a class with a static constructor, which `#init` runs last, the throw stays in place of the entry,
 * and each later call throws the Error that names it, spec section 6. An access that reads the static `field`, which
 * such a throw can leave out of the entry, names it, and throws PHP's own error for a typed static read before it is
 * set, as a class that isn't generic does. */
ZEND_FUNCTION(Sharp_Internal_statics)
{
	zend_class_entry *ce = NULL;
	zend_string *key;
	zend_string *field = NULL;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_CLASS(ce)
		Z_PARAM_STR(key)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(field)
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
	if (UNEXPECTED(entry && Z_TYPE_P(entry) == IS_OBJECT)) {
		sharp_throw_static_constructor_failure(ce, key, Z_OBJ_P(entry));
		RETURN_THROWS();
	}
	if (!entry) {
		zend_function *init = zend_hash_str_find_ptr(&ce->function_table, ZEND_STRL("#init"));
		const sharp_type *arguments = NULL;
		if (init && init->common.scope == ce) {
			arguments = sharp_type_list(ZSTR_VAL(key), ZSTR_LEN(key));
			if (UNEXPECTED(!arguments)) {
				zend_argument_value_error(2, "must be the text of type arguments");
				RETURN_THROWS();
			}
		}
		zval defaults;
		ZVAL_COPY(&defaults, zend_hash_find_known_hash(Z_ARRVAL_P(store), ZSTR_EMPTY_ALLOC()));
		entry = zend_hash_add_new(Z_ARRVAL_P(store), key, &defaults);
		if (arguments) {
			/* `#init` writes the entry through this function, which finds it, and so can add other entries to the
			 * store, which moves this one. */
			zval type_arguments;
			ZVAL_PTR(&type_arguments, (void *) arguments);
			zend_call_known_function(init, NULL, ce, NULL, 1, &type_arguments, NULL);
			store = CE_STATIC_MEMBERS(ce) + info->offset;
			ZVAL_DEINDIRECT(store);
			if (UNEXPECTED(EG(exception))) {
				/* Spec section 6: what a static constructor threw stays in place of the entry, which every later
				 * use of these statics names. */
				if (sharp_static_constructor_state(ce) && !zend_is_unwind_exit(EG(exception))) {
					SEPARATE_ARRAY(store);
					entry = zend_hash_find_known_hash(Z_ARRVAL_P(store), key);
					zval_ptr_dtor(entry);
					ZVAL_OBJ_COPY(entry, EG(exception));
				}
				RETURN_THROWS();
			}
			entry = zend_hash_find_known_hash(Z_ARRVAL_P(store), key);
		}
	}
	if (field && UNEXPECTED(!zend_hash_exists(Z_ARRVAL_P(Z_ISREF_P(entry) ? Z_REFVAL_P(entry) : entry), field))) {
		zend_throw_error(NULL, "Typed static property %s::$%s must not be accessed before initialization",
			ZSTR_VAL(ce->name), ZSTR_VAL(field));
		RETURN_THROWS();
	}

	ZVAL_MAKE_REF(entry);
	RETURN_COPY(entry);
}

/* Spec section 25: PHP# compares two class values with `\Sharp\Internal\equals($value, $other)`, by the whole type, as
 * sharp_equal compares them. Spec section 24 compares an unchecked `Any?` with `==` here too, and section 31 a value of
 * a type parameter, as `Result<T, TError>`'s `==` compares its `T` field. */
ZEND_FUNCTION(Sharp_Internal_equals)
{
	zval *value, *other;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(value)
		Z_PARAM_ZVAL(other)
	ZEND_PARSE_PARAMETERS_END();

	bool equal;
	if (!sharp_equal(value, other, &equal)) {
		RETURN_THROWS();
	}
	RETURN_BOOL(equal);
}

/* Throws the JsonException json_encode throws for `code` with JSON_THROW_ON_ERROR. */
static ZEND_COLD bool sharp_refuse_json(php_json_error_code code)
{
	zend_throw_exception(php_json_exception_ce, php_json_get_error_msg(code), code);

	return false;
}

static bool sharp_stored_value(zval *value, zval *stored);

/* Appends the stored form of `value` to the List `stored`, or sets it in the Map `stored` under the text of `key`, or
 * of `index` for an int key, as JSON writes an object's key. */
static bool sharp_stored_put(zval *stored, zend_string *key, zend_ulong index, zval *value)
{
	zval held;
	if (sharp_out_of_memory() || !sharp_stored_value(value, &held)) {
		return false;
	}
	if (Z_TYPE_P(stored) == IS_ARRAY) {
		zend_hash_next_index_insert_new(Z_ARRVAL_P(stored), &held);
		return true;
	}
	zval text;
	ZVAL_STR(&text, key ? zend_string_copy(key) : zend_long_to_str((zend_long) index));
	bool put = sharp_table_put(sharp_table_of(Z_OBJ_P(stored)), &text, &held);
	zval_ptr_dtor(&text);
	zval_ptr_dtor(&held);

	return put;
}

/* The stored form of the PHP array `array`: a List for a list, which JSON writes as an array, and a Map for any other,
 * which it writes as an object. */
static bool sharp_stored_array(zval *array, zval *stored)
{
	HashTable *entries = Z_ARRVAL_P(array);
	if (GC_IS_RECURSIVE(entries)) {
		return sharp_refuse_json(PHP_JSON_ERROR_RECURSION);
	}
	if (zend_array_is_list(entries)) {
		array_init_size(stored, zend_hash_num_elements(entries));
	} else {
		object_init_ex(stored, sharp_ce_map);
	}
	bool kept = true;
	zend_string *key;
	zend_ulong index;
	zval *element;
	GC_TRY_PROTECT_RECURSION(entries);
	ZEND_HASH_FOREACH_KEY_VAL_IND(entries, index, key, element) {
		if (!sharp_stored_put(stored, key, index, element)) {
			kept = false;
			break;
		}
	} ZEND_HASH_FOREACH_END();
	GC_TRY_UNPROTECT_RECURSION(entries);
	if (!kept) {
		zval_ptr_dtor(stored);
		ZVAL_UNDEF(stored);
	}

	return kept;
}

/* The stored form of `object` that nothing stands in for, as JSON writes one: the Map of its public properties, each
 * under its name, a hooked one read through its get hook, and a virtual one without one left out. */
static bool sharp_stored_properties(zval *object, zval *stored)
{
	HashTable *properties = zend_get_properties_for(object, ZEND_PROP_PURPOSE_JSON);
	object_init_ex(stored, sharp_ce_map);
	if (!properties) {
		return true;
	}
	bool kept = true;
	zend_string *key;
	zend_ulong index;
	zval *property;
	ZEND_HASH_FOREACH_KEY_VAL_IND(properties, index, key, property) {
		if (key && ZSTR_VAL(key)[0] == '\0' && ZSTR_LEN(key) > 0) {
			continue;
		}
		zval read;
		ZVAL_UNDEF(&read);
		if (Z_TYPE_P(property) == IS_PTR) {
			const zend_property_info *info = Z_PTR_P(property);
			if ((info->flags & ZEND_ACC_VIRTUAL) && !info->hooks[ZEND_PROPERTY_HOOK_GET]) {
				continue;
			}
			property = zend_read_property_ex(info->ce, Z_OBJ_P(object), info->name, true, &read);
		}
		kept = !EG(exception) && sharp_stored_put(stored, key, index, property);
		zval_ptr_dtor(&read);
		if (!kept) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	zend_release_properties(properties);
	if (!kept) {
		zval_ptr_dtor(stored);
		ZVAL_UNDEF(stored);
	}

	return kept;
}

/* The stored form of `object`, as the JSON encoder writes it: the stored form of what stands in for it, which is what
 * its jsonSerialize() gives, as a Map's entries keyed by their text and a Set's elements, a backed enum case's value, or
 * what the `#json` the compile gives its class gives, spec section 10, and else its public properties'. */
static bool sharp_stored_object(zval *object, zval *stored)
{
	zend_object *found = Z_OBJ_P(object);
	zend_class_entry *ce = found->ce;
	if (GC_IS_RECURSIVE(found)) {
		return sharp_refuse_json(PHP_JSON_ERROR_RECURSION);
	}
	zval stand_in;
	ZVAL_UNDEF(&stand_in);
	if (instanceof_function(ce, php_json_serializable_ce)) {
		zend_call_method_with_0_params(found, ce, NULL, "jsonserialize", &stand_in);
	} else if (ce->ce_flags & ZEND_ACC_ENUM) {
		if (ce->enum_backing_type == IS_UNDEF) {
			return sharp_refuse_json(PHP_JSON_ERROR_NON_BACKED_ENUM);
		}
		ZVAL_COPY(stored, zend_enum_fetch_case_value(found));
		return true;
	} else {
		zend_function *json = zend_hash_str_find_ptr(&ce->function_table, "#json", sizeof("#json") - 1);
		if (json) {
			zend_call_known_function(json, found, ce, &stand_in, 0, NULL, NULL);
		}
	}
	if (EG(exception)) {
		zval_ptr_dtor(&stand_in);
		return false;
	}
	GC_PROTECT_RECURSION(found);
	bool kept = Z_ISUNDEF(stand_in) || (Z_TYPE(stand_in) == IS_OBJECT && Z_OBJ(stand_in) == found)
		? sharp_stored_properties(object, stored)
		: sharp_stored_value(&stand_in, stored);
	GC_UNPROTECT_RECURSION(found);
	zval_ptr_dtor(&stand_in);

	return kept;
}

/* Spec section 10: the stored form of `value` as a value, what `Json.encode` writes as text: each JSON object a Map,
 * each JSON array a List, and each scalar itself. False with the exception it threw, the JsonException json_encode
 * throws for a value JSON can't hold, as one that holds itself. */
static bool sharp_stored_value(zval *value, zval *stored)
{
	ZVAL_UNDEF(stored);
#ifdef ZEND_CHECK_STACK_LIMIT
	if (UNEXPECTED(zend_call_stack_overflowed(EG(stack_limit)))) {
		return sharp_refuse_json(PHP_JSON_ERROR_DEPTH);
	}
#endif
	ZVAL_DEREF(value);
	switch (Z_TYPE_P(value)) {
		case IS_NULL:
		case IS_FALSE:
		case IS_TRUE:
		case IS_LONG:
		case IS_DOUBLE:
		case IS_STRING:
			ZVAL_COPY(stored, value);
			return true;
		case IS_ARRAY:
			return sharp_stored_array(value, stored);
		case IS_OBJECT:
			return sharp_stored_object(value, stored);
		default:
			return sharp_refuse_json(PHP_JSON_ERROR_UNSUPPORTED_TYPE);
	}
}

/* Spec section 10: `Class<T>.encode(value)`, the stored form of `value` that `Class<T>.decode` reads back, as
 * sharp_stored_value gives it. */
ZEND_FUNCTION(Sharp_Internal_storedValue)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_stored_value(value, return_value)) {
		RETURN_THROWS();
	}
}

/* Spec section 24: the JSON text Sharp.Encoding.Json.encode writes, json_encode's with `$flags`, or with
 * JSON_PRETTY_PRINT's layout at `$indent` spaces a level when `$indent` is above 0. A NaN or infinite float is null,
 * which JSON has no number for. It throws JsonException as json_encode does with JSON_THROW_ON_ERROR. */
ZEND_FUNCTION(Sharp_Internal_jsonEncode)
{
	zval *value;
	zend_long flags, indent;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(value)
		Z_PARAM_LONG(flags)
		Z_PARAM_LONG(indent)
	ZEND_PARSE_PARAMETERS_END();

	if (indent < 0 || indent > INT_MAX) {
		zend_argument_value_error(3, "must be between 0 and %d", INT_MAX);
		RETURN_THROWS();
	}
	php_json_encoder encoder;
	php_json_encode_init(&encoder);
	encoder.max_depth = PHP_JSON_PARSER_DEFAULT_DEPTH;
	encoder.indent = (int) indent;
	encoder.nonfinite_as_null = true;
	smart_str text = {0};
	php_json_encode_zval(&text, value, (int) (indent ? flags | PHP_JSON_PRETTY_PRINT : flags & ~PHP_JSON_PRETTY_PRINT),
		&encoder);
	if (encoder.error_code != PHP_JSON_ERROR_NONE) {
		smart_str_free(&text);
		zend_throw_exception(php_json_exception_ce, php_json_get_error_msg(encoder.error_code), encoder.error_code);
		RETURN_THROWS();
	}
	RETURN_STR(smart_str_extract(&text));
}

static int sharp_json_array_create(php_json_parser *parser, zval *array)
{
	array_init(array);

	return SUCCESS;
}

static int sharp_json_array_append(php_json_parser *parser, zval *array, zval *value)
{
	zend_hash_next_index_insert(Z_ARRVAL_P(array), value);

	return SUCCESS;
}

/* A JSON object starts as a PHP array, as json_decode's JSON_OBJECT_AS_ARRAY reads it, whose keys a `Map` takes. */
static int sharp_json_object_create(php_json_parser *parser, zval *object)
{
	array_init(object);

	return SUCCESS;
}

static int sharp_json_object_update(php_json_parser *parser, zval *object, zend_string *key, zval *value)
{
	zend_symtable_update(Z_ARRVAL_P(object), key, value);
	zend_string_release_ex(key, false);

	return SUCCESS;
}

/* An empty JSON object is an empty Sharp\Map, which `Json.encode` writes as `{}` again, since an empty PHP array is
 * also an empty JSON array's, which it writes as `[]`. */
static int sharp_json_object_end(php_json_parser *parser, zval *object)
{
	if (zend_hash_num_elements(Z_ARRVAL_P(object)) == 0) {
		zval_ptr_dtor(object);
		object_init_ex(object, sharp_ce_map);
	}

	return SUCCESS;
}

/* Spec section 22: what `Json.parse` and `Json.decode` read, the value the JSON text `json` holds in `value`, as
 * json_decode reads it with JSON_OBJECT_AS_ARRAY, each object a PHP array, but an empty object an empty Sharp\Map.
 * Throws JsonException on text that is no JSON, as JSON_THROW_ON_ERROR does. */
static bool sharp_json_decode(const zend_string *json, zval *value)
{
	static const php_json_parser_methods methods = {
		sharp_json_array_create,
		sharp_json_array_append,
		NULL,
		NULL,
		sharp_json_object_create,
		sharp_json_object_update,
		NULL,
		sharp_json_object_end,
	};
	php_json_parser parser;
	php_json_parser_init_ex(&parser, value, ZSTR_VAL(json), ZSTR_LEN(json), PHP_JSON_OBJECT_AS_ARRAY,
		PHP_JSON_PARSER_DEFAULT_DEPTH, &methods);
	if (php_json_parse(&parser)) {
		php_json_error_code code = php_json_parser_error_code(&parser);
		zend_throw_exception(php_json_exception_ce, php_json_get_error_msg(code), code);
		return false;
	}

	return true;
}

ZEND_FUNCTION(Sharp_Internal_jsonDecode)
{
	zend_string *json;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(json)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_json_decode(json, return_value)) {
		RETURN_THROWS();
	}
}

/* Spec section 29: the addresses Sharp.Net.Dns.addresses gives, each as text, once, in the order the system resolver
 * gives them, as .NET's Dns.GetHostAddresses reads them through getaddrinfo, the hosts file included. A host the
 * resolver cannot find throws RuntimeException, as .NET throws SocketException. */
ZEND_FUNCTION(Sharp_Internal_resolve)
{
	char *host;
	size_t host_length;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_PATH(host, host_length)
	ZEND_PARSE_PARAMETERS_END();

	struct addrinfo hints = {0}, *found;
	hints.ai_family = AF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;
	int failure = getaddrinfo(host, NULL, &hints, &found);
	if (failure != 0) {
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "Dns.addresses: \"%s\" cannot be resolved: %s", host,
			gai_strerror(failure));
		RETURN_THROWS();
	}

	array_init(return_value);
	HashTable seen;
	zend_hash_init(&seen, 8, NULL, NULL, false);
	for (struct addrinfo *entry = found; entry; entry = entry->ai_next) {
		char text[INET6_ADDRSTRLEN];
		const void *address = entry->ai_family == AF_INET6
			? (const void *) &((struct sockaddr_in6 *) entry->ai_addr)->sin6_addr
			: (const void *) &((struct sockaddr_in *) entry->ai_addr)->sin_addr;
		if (inet_ntop(entry->ai_family, address, text, sizeof text)
			&& zend_hash_str_add_empty_element(&seen, text, strlen(text))) {
			add_next_index_string(return_value, text);
		}
	}
	zend_hash_destroy(&seen);
	freeaddrinfo(found);
}

/* Spec section 29: the processors Sharp.System.Environment.processorCount gives, those online, as .NET's
 * Environment.ProcessorCount and Rust's available_parallelism count them, and 1 where the system cannot say. */
ZEND_FUNCTION(Sharp_Internal_processorCount)
{
	ZEND_PARSE_PARAMETERS_NONE();

	long online = sysconf(_SC_NPROCESSORS_ONLN);
	RETURN_LONG(online > 0 ? online : 1);
}

/* Orders two values as PHP#'s < orders two values of their type, spec section 19, -1, 0 or 1: an object by the
 * operator <=> its class declares, which the bridge writes as the static op_Comparison, two tuples element by element,
 * as Rust orders tuples, two strings by their bytes, and two numbers as numbers. A null sorts before every other value,
 * as sharp_order_keys puts it. An object whose class declares no <=> throws ValueError. A call that throws leaves the
 * exception for the caller to throw. */
static int sharp_compare_values(zval *left, zval *right)
{
	ZVAL_DEREF(left);
	ZVAL_DEREF(right);
	if (Z_TYPE_P(left) == IS_NULL || Z_TYPE_P(right) == IS_NULL) {
		return (Z_TYPE_P(left) != IS_NULL) - (Z_TYPE_P(right) != IS_NULL);
	}
	if (Z_TYPE_P(left) == IS_ARRAY && Z_TYPE_P(right) == IS_ARRAY) {
		uint32_t left_count = zend_hash_num_elements(Z_ARRVAL_P(left));
		uint32_t right_count = zend_hash_num_elements(Z_ARRVAL_P(right));
		for (uint32_t index = 0; index < MIN(left_count, right_count); index++) {
			int result = sharp_compare_values(zend_hash_index_find(Z_ARRVAL_P(left), index),
				zend_hash_index_find(Z_ARRVAL_P(right), index));
			if (result || EG(exception)) {
				return result;
			}
		}

		return ZEND_THREEWAY_COMPARE(left_count, right_count);
	}
	if (Z_TYPE_P(left) == IS_OBJECT) {
		zend_class_entry *ce = Z_OBJCE_P(left);
		/* A class with exact names keys its methods as written, spec section 24, and any other in lowercase. */
		zend_function *operator = (ce->sharp_flags & SHARP_CLASS_EXACT_NAMES)
			? zend_hash_str_find_ptr(&ce->function_table, ZEND_STRL("op_Comparison"))
			: zend_hash_str_find_ptr(&ce->function_table, ZEND_STRL("op_comparison"));
		if (!operator) {
			zend_value_error("%s declares no operator <=>, so it has no order: give the collection a comparison",
				ZSTR_VAL(ce->name));
			return 0;
		}
		zval operands[2], result;
		ZVAL_COPY_VALUE(&operands[0], left);
		ZVAL_COPY_VALUE(&operands[1], right);
		ZVAL_UNDEF(&result);
		zend_call_known_function(operator, NULL, operator->common.scope, &result, 2, operands, NULL);
		int order = Z_TYPE(result) == IS_LONG ? ZEND_NORMALIZE_BOOL(Z_LVAL(result)) : 0;
		zval_ptr_dtor(&result);

		return order;
	}
	if (Z_TYPE_P(left) == IS_STRING && Z_TYPE_P(right) == IS_STRING) {
		return ZEND_NORMALIZE_BOOL(zend_binary_zval_strcmp(left, right));
	}

	return zend_compare(left, right);
}

/* Spec section 12: the order SortedMap, SortedSet and PriorityQueue keep their keys in when they take no comparison,
 * as Rust's BTreeMap orders by Ord: the order sharp_compare_values gives. */
ZEND_FUNCTION(Sharp_Internal_compare)
{
	zval *left, *right;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_ZVAL(left)
		Z_PARAM_ZVAL(right)
	ZEND_PARSE_PARAMETERS_END();

	int order = sharp_compare_values(left, right);
	if (EG(exception)) {
		RETURN_THROWS();
	}
	RETURN_LONG(order);
}

/* Spec section 12: PHP# passes plain PHP `\Sharp\Internal\arrayOf($value)`, the PHP array sharp_array_of gives. Through
 * a function value, `$receiver` is the function the value goes to, and a closure of PHP# takes the value as it is. */
ZEND_FUNCTION(Sharp_Internal_arrayOf)
{
	zval *value, *receiver = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ZVAL(value)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(receiver)
	ZEND_PARSE_PARAMETERS_END();

	if (receiver && Z_TYPE_P(receiver) == IS_OBJECT && Z_OBJCE_P(receiver) == zend_ce_closure
			&& sharp_function_is_sharp(zend_get_closure_method_def(Z_OBJ_P(receiver)))) {
		RETURN_COPY_DEREF(value);
	}
	sharp_array_of(return_value, value);
}

static bool sharp_type_verified(const sharp_type *type, const sharp_type *shown, zval *value, const char *target);

/* Spec section 22: PHP# reads the result of each call whose implementation may be plain PHP, as a test double's or an
 * override's, through `\Sharp\Internal\returned($result, $implementer, $method, $types, $target)`, which the compiler
 * makes a ZEND_SHARP_RETURNED. A PHP# method gives its result as its type says. Plain PHP's result crosses in, each PHP
 * array a Map or Set it holds becoming the object and a List renumbered, and is checked against the second type, which
 * may read less of the value. A class's methods stay the same while the request runs, so `cache` keeps the method each
 * class gives by the name. */
bool sharp_returned(zend_execute_data *caller, zval *result, zval *value, zval *implementer, zend_string *method,
	const zval *types, void **cache)
{
	ZVAL_DEREF(value);
	ZVAL_DEREF(implementer);
	zend_class_entry *ce = NULL;
	if (Z_TYPE_P(implementer) == IS_OBJECT) {
		ce = Z_OBJCE_P(implementer);
	} else if (Z_TYPE_P(implementer) == IS_STRING) {
		ce = zend_lookup_class(Z_STR_P(implementer));
	} else if (Z_TYPE_P(implementer) == IS_NULL && ZSTR_LEN(method)) {
		ZVAL_COPY(result, value);
		return true;
	}
	if (ce) {
		if (cache[0] != ce) {
			const zend_function *ran = zend_class_find_method(ce, method);
			cache[0] = ce;
			cache[1] = sharp_function_is_sharp(ran) ? (void *) ran : NULL;
		}
		if (cache[1]) {
			ZVAL_COPY(result, value);
			return true;
		}
	}

	bool is_user_code = caller && caller->func && ZEND_USER_CODE(caller->func->type);
	zend_object *object = is_user_code && Z_TYPE(caller->This) == IS_OBJECT ? Z_OBJ(caller->This) : NULL;
	/* The list is spelled for the calling frame as ZEND_SHARP_VERIFY_TYPE_ARGS spells its own, a method's own type
	 * parameter as any type, since a call from plain PHP gives the method none. Plain PHP may write the call itself. */
	const char *problem = sharp_caller_type_text_problem(Z_STR_P(types), 0, object, true);
	if (problem) {
		zend_value_error("Sharp\\Internal\\returned(): Argument #4 ($types) %s", problem);
		ZVAL_UNDEF(result);
		return false;
	}
	const sharp_type *expected = sharp_type_list_of_frame(types, object, NULL, true,
		is_user_code ? sharp_type_scope(caller->func->common.scope, caller->func) : NULL, cache + 2);
	if (!expected) {
		if (EG(exception)) {
			ZVAL_UNDEF(result);
			return false;
		}
		ZVAL_COPY(result, value);
		return true;
	}

	const sharp_type *crossing = expected->members[0];
	const sharp_type *checked = expected->count > 1 ? expected->members[1] : crossing;
	/* Each List the PHP arrays hold is renumbered before a Map or Set takes it. */
	zval renumbered, plan;
	ZVAL_COPY(&renumbered, value);
	sharp_type_renumber(crossing, &renumbered);
	sharp_type_plan(&plan, crossing);
	bool crossed = sharp_crossed(result, &renumbered, &plan, crossing);
	zval_ptr_dtor(&plan);
	zval_ptr_dtor(&renumbered);
	/* sharp_crossed released what it made when it failed. */
	if (!crossed) {
		ZVAL_UNDEF(result);
		return false;
	}
	if (!sharp_type_verified(checked, crossing, result, Z_STRVAL_P(types + 1))) {
		zval_ptr_dtor(result);
		ZVAL_UNDEF(result);
		return false;
	}

	return true;
}

/* A call of \Sharp\Internal\returned the compiler can't make a ZEND_SHARP_RETURNED, as call_user_func's, finds the
 * method each time. */
ZEND_FUNCTION(Sharp_Internal_returned)
{
	zval *result, *implementer;
	zend_string *method, *types, *target;

	ZEND_PARSE_PARAMETERS_START(5, 5)
		Z_PARAM_ZVAL(result)
		Z_PARAM_ZVAL(implementer)
		Z_PARAM_STR(method)
		Z_PARAM_STR(types)
		Z_PARAM_STR(target)
	ZEND_PARSE_PARAMETERS_END();

	zval texts[2];
	ZVAL_STR(&texts[0], types);
	ZVAL_STR(&texts[1], target);
	void *cache[6] = {NULL};
	sharp_returned(EX(prev_execute_data), return_value, result, implementer, method, texts, cache);
}

/* Throws the Error for the type arguments of a frame that ZEND_SHARP_TYPE_ARGS could not spell. */
static ZEND_COLD void sharp_type_list_unknown_error(void)
{
	zend_throw_error(NULL, "Cannot read the type arguments of the class a lambda is written in from an object of another class");
}

/* The member of `type` that holds `value`, as sharp_type_holds tests it: the member of `T?`, and the first member of a
 * union that holds it, as PHP picks a union member for an argument. `type` itself for any other type. */
static const sharp_type *sharp_type_holding(const sharp_type *type, const zval *value)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		return sharp_type_holding(type->members[0], value);
	}
	if (type->kind == SHARP_TYPE_UNION) {
		for (uint32_t i = 0; i < type->count; i++) {
			if (sharp_type_holds(type->members[i], value, SHARP_HOLDS_TEST, NULL)) {
				return sharp_type_holding(type->members[i], value);
			}
		}
	}

	return type;
}

bool sharp_type_list_holds(const sharp_type *list, zval *value, bool binds)
{
	if (UNEXPECTED(!list)) {
		sharp_type_list_unknown_error();
		return false;
	}

	const sharp_type *type = list->members[0];
	/* A lazy proxy's initializer runs to read its type arguments, and can throw. */
	if (!sharp_type_holds(type, value, SHARP_HOLDS_TEST, NULL) || EG(exception)) {
		return false;
	}
	ZVAL_DEREF(value);
	if (!binds || Z_TYPE_P(value) != IS_ARRAY) {
		return true;
	}

	const sharp_type *holding = sharp_type_holding(type, value);
	zval plan, crossed;
	sharp_type_plan(&plan, holding);
	if (Z_TYPE(plan) == IS_NULL) {
		return true;
	}
	bool is_crossed = sharp_crossed(&crossed, value, &plan, holding);
	zval_ptr_dtor(&plan);
	if (!is_crossed) {
		return false;
	}
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &crossed);

	return true;
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
			/* A class value is the class's name as PHP writes it, `App\Order`, or as PHP# does, `App.Order`. */
			zend_string *name = sharp_type_class_name(Z_STRVAL_P(created), Z_STRLEN_P(created), false);
			zend_class_entry *ce = zend_fetch_class(name, ZEND_FETCH_CLASS_DEFAULT | ZEND_FETCH_CLASS_EXCEPTION);
			zend_string_release_ex(name, false);

			return ce;
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

/* Throws the InvalidArgumentException of `Class<T>.createInstance` for `ce`, with the reason `format` gives. */
static ZEND_COLD void sharp_create_refused(const zend_class_entry *ce, const char *format, ...)
{
	va_list arguments;
	va_start(arguments, format);
	zend_string *reason = zend_vstrpprintf(0, format, arguments);
	va_end(arguments);
	zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "`createInstance` cannot create %s: %s.",
		ZSTR_VAL(ce->name), ZSTR_VAL(reason));
	zend_string_release_ex(reason, false);
}

/* The class the class value `value` names, with its type arguments in `arguments`, when `Class<T>.createInstance` can
 * create an object of it: no interface, trait, enum or abstract class. NULL with an exception otherwise. */
static zend_class_entry *sharp_creatable_class(zend_string *value, const sharp_type **arguments)
{
	zval created;
	ZVAL_STR(&created, value);
	zend_class_entry *ce = sharp_new_class(&created, arguments);
	if (!ce) {
		return NULL;
	}
	if (ce->ce_flags & (ZEND_ACC_INTERFACE | ZEND_ACC_TRAIT | ZEND_ACC_ENUM)) {
		sharp_create_refused(ce, "it is %s", (ce->ce_flags & ZEND_ACC_INTERFACE) ? "an interface"
			: (ce->ce_flags & ZEND_ACC_TRAIT) ? "a trait" : "an enum");
		return NULL;
	}
	if (ce->ce_flags & (ZEND_ACC_EXPLICIT_ABSTRACT_CLASS | ZEND_ACC_IMPLICIT_ABSTRACT_CLASS)) {
		sharp_create_refused(ce, "it is abstract");
		return NULL;
	}

	return *arguments && !sharp_class_takes(ce, *arguments) ? NULL : ce;
}

/* Spec section 25: `Class<T>.createInstance(arguments)`, a new object of the class `type` names, with its type
 * arguments, created by its constructor with `arguments`, as C#'s `Activator.CreateInstance` makes one. The caller
 * reaches the constructor as a direct `new` would. Throws InvalidArgumentException for an abstract class, an interface,
 * a trait and an enum, for another count of arguments than the constructor takes, and for an argument outside its
 * parameter's declared type, as sharp_method_declared_types reads it for the class value. */
ZEND_FUNCTION(Sharp_Internal_createInstance)
{
	zend_string *value;
	HashTable *given;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(value)
		Z_PARAM_ARRAY_HT(given)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *arguments;
	zend_class_entry *ce = sharp_creatable_class(value, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	uint32_t count = zend_hash_num_elements(given);
	const zend_function *constructor = ce->constructor;
	uint32_t required = constructor ? constructor->common.required_num_args : 0;
	uint32_t most = constructor ? constructor->common.num_args : 0;
	bool variadic = constructor && (constructor->common.fn_flags & ZEND_ACC_VARIADIC);
	if (count < required || (count > most && !variadic)) {
		sharp_create_refused(ce,
			"its constructor takes %s%" PRIu32 " argument%s, and was given %" PRIu32, required == most ? "" : count < required ? "at least " : "at most ", count < required ? required : most,
			(count < required ? required : most) == 1 ? "" : "s", count);
		RETURN_THROWS();
	}
	if (constructor && count) {
		smart_str words = {0};
		const sharp_type *types = sharp_method_declared_types(constructor, ce, arguments, &words);
		smart_str_free(&words);
		if (!types) {
			RETURN_THROWS();
		}
		uint32_t position = 0;
		zval *argument;
		ZEND_HASH_FOREACH_VAL(given, argument) {
			if (position < most && !sharp_type_holds(types->members[position + 1], argument, SHARP_HOLDS_ENTRY, NULL)) {
				if (!EG(exception)) {
					sharp_create_refused(ce, "its parameter `%s` takes %s, and was given %s",
						sharp_parameter_name(constructor, position), ZSTR_VAL(types->members[position + 1]->text),
						zend_zval_value_name(argument));
				}
				RETURN_THROWS();
			}
			position++;
		} ZEND_HASH_FOREACH_END();
	}
	if (object_init_to_build(return_value, ce) != SUCCESS) {
		RETURN_THROWS();
	}
	if (arguments) {
		sharp_type_arguments_store(Z_OBJ_P(return_value), arguments);
	}
	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	const zend_class_entry *outer = EG(fake_scope);
	EG(fake_scope) = caller ? caller->func->common.scope : NULL;
	zend_function *found = Z_OBJ_HT_P(return_value)->get_constructor(Z_OBJ_P(return_value));
	EG(fake_scope) = outer;
	if (found) {
		zend_call_known_function(found, Z_OBJ_P(return_value), ce, NULL, 0, NULL, given);
	}
	if (EG(exception)) {
		zend_object_store_ctor_failed(Z_OBJ_P(return_value));
		zval_ptr_dtor(return_value);
		RETVAL_NULL();
		RETURN_THROWS();
	}
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
		sharp_type_renumber(expected->members[i], value);
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

	/* Spec section 18: the list a variadic parameter holds, which PHP builds of the arguments, holds text too. */
	zval *rest = expected->count > count ? EX_VAR_NUM(count) : NULL;
	if (rest && Z_TYPE_P(rest) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL_P(rest))) {
		SEPARATE_ARRAY(rest);
		zval *element;
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(rest), element) {
			sharp_type_take_strings(expected->members[count], element);
			if (UNEXPECTED(EG(exception))) {
				return;
			}
		} ZEND_HASH_FOREACH_END();
	}
}

/* Throws for the names plain PHP passed a PHP# `...` parameter, which is a List and has no names, so no name or value
 * is lost, spec section 16: "`Wrapper.wrap` takes `...attributes` by position, and was given `class:` and `id:`." */
ZEND_COLD void sharp_refuse_named_variadic(const zend_function *func, const HashTable *named)
{
	smart_str message = {0};
	smart_str_appendc(&message, '`');
	const zend_string *full = func->common.scope ? func->common.scope->name : func->common.function_name;
	const char *last = zend_memrchr(ZSTR_VAL(full), '\\', ZSTR_LEN(full));
	const char *name = last ? last + 1 : ZSTR_VAL(full);
	size_t length = ZSTR_VAL(full) + ZSTR_LEN(full) - name;
	if (!func->common.scope) {
		smart_str_appendl(&message, name, length);
	} else if (zend_string_equals_literal_ci(func->common.function_name, "__construct")) {
		smart_str_appends(&message, "new ");
		smart_str_appendl(&message, name, length);
	} else {
		smart_str_appendl(&message, name, length);
		smart_str_appendc(&message, '.');
		smart_str_append(&message, func->common.function_name);
	}
	smart_str_appends(&message, "` takes `...");
	smart_str_append(&message, func->common.arg_info[func->common.num_args].name);
	smart_str_appends(&message, "` by position, and was given ");

	uint32_t count = zend_hash_num_elements(named);
	uint32_t i = 0;
	zend_string *key;
	ZEND_HASH_MAP_FOREACH_STR_KEY(named, key) {
		if (i > 0) {
			smart_str_appends(&message, i + 1 == count ? " and " : ", ");
		}
		smart_str_appendc(&message, '`');
		smart_str_append(&message, key);
		smart_str_appends(&message, ":`");
		i++;
	} ZEND_HASH_FOREACH_END();
	smart_str_appendc(&message, '.');
	smart_str_0(&message);

	zend_throw_error(NULL, "%s", ZSTR_VAL(message.s));
	smart_str_free(&message);
}

/* Whether `value`, renumbered where `type` is a List, is of `type`, which `target` takes. A value of another type
 * throws a TypeError worded as PHP's for a function's return value, which names `shown`, the type the code wrote. */
static bool sharp_type_verified(const sharp_type *type, const sharp_type *shown, zval *value, const char *target)
{
	sharp_type_renumber(type, value);
	if (!sharp_type_accepts(type, value, NULL)) {
		if (!EG(exception)) {
			/* Worded as PHP's TypeError for a function's return value. */
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *problem = sharp_first_problem(shown, value);
			zend_type_error("%s: Return value must be of type %s, %s returned%s",
				target, ZSTR_VAL(shown->text), ZSTR_VAL(given.s), ZSTR_VAL(problem));
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
			target, ZSTR_VAL(shown->text), ZSTR_VAL(given.s));
		smart_str_free(&given);
		sharp_sequence_check(value, iterable, head);
	}

	return true;
}

bool sharp_type_verify(zend_execute_data *execute_data, zval *value, const zval *text, void **cache)
{
	const sharp_type *expected = sharp_type_list_of_frame(text,
		Z_TYPE(EX(This)) == IS_OBJECT ? Z_OBJ(EX(This)) : NULL, NULL, true, sharp_type_scope(EX(func)->common.scope, EX(func)), cache);
	if (!expected) {
		return !EG(exception);
	}

	/* The error names the type the code wrote, which a second type in the list spells where the check reads less of the
	 * value than that type says. */
	const sharp_type *type = expected->members[0];

	return sharp_type_verified(type, expected->count > 1 ? expected->members[1] : type, value, Z_STRVAL_P(text + 1));
}

/* Whether the PHP# type of the property `info` names a type parameter of its class, which only an object spells. */
static bool sharp_property_is_open(const zend_property_info *info)
{
	return info->sharp_type && memchr(ZSTR_VAL(info->sharp_type), '$', ZSTR_LEN(info->sharp_type));
}

/* Each check spells the property's text for the object's class and type arguments once a request. */
static const sharp_type *sharp_property_type(const zend_property_info *info, zend_object *object)
{
	zval text;
	ZVAL_STR(&text, info->sharp_type);
	void *cache[4] = {NULL};
	const sharp_type *list = sharp_type_list_of_frame(&text, object, NULL, true, info->ce, cache);

	return list ? list->members[0] : NULL;
}

bool sharp_property_accepts(const zend_property_info *info, zval *value, zend_object *object, bool reference)
{
	/* A hook writes its own property's storage for the code that set the property. */
	const zend_execute_data *writer = EG(current_execute_data);
	if (writer && writer->func && writer->func->common.prop_info
			&& writer->func->common.prop_info->prototype == info->prototype) {
		writer = writer->prev_execute_data;
	}
	if (writer && sharp_function_is_sharp(writer->func)) {
		return true;
	}
	if (!object && sharp_property_is_open(info)) {
		return true;
	}

	const sharp_type *type = sharp_property_type(info, object);
	const char *name = zend_get_unmangled_property_name(info->name);
	if (!type || !sharp_property_value_fits(type, value, info->ce, name, reference)) {
		return false;
	}

	return Z_TYPE_P(value) != IS_ARRAY || (reference && (info->flags & ZEND_ACC_SHARP_CROSSING))
		|| !sharp_crosses_property(info) || sharp_cross_property(info, value);
}

/* Whether `value`, renumbered where `type` is a List, is of `type`, the type of the property `name` that `ce` declares,
 * or of the reference a property holds for `reference`. A value of another type throws the TypeError PHP gives a typed
 * property's write, which names the type the class wrote. */
static bool sharp_property_value_fits(
	const sharp_type *type, zval *value, const zend_class_entry *ce, const char *name, bool reference)
{
	sharp_type_renumber(type, value);
	if (sharp_type_accepts(type, value, NULL)) {
		const sharp_type *iterable = sharp_type_lazily_checked(type, value);
		if (iterable) {
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *head = zend_strpprintf(0, "Cannot assign %s to %sproperty %s::$%s of type %s: ", ZSTR_VAL(given.s),
				reference ? "reference held by " : "", ZSTR_VAL(ce->name), name, ZSTR_VAL(type->text));
			smart_str_free(&given);
			sharp_sequence_check(value, iterable, head);
		}
		return true;
	}
	if (!EG(exception)) {
		smart_str given = {0};
		sharp_value_type_text(&given, value);
		zend_string *problem = sharp_first_problem(type, value);
		zend_type_error("Cannot assign %s to %sproperty %s::$%s of type %s%s", ZSTR_VAL(given.s),
			reference ? "reference held by " : "", ZSTR_VAL(ce->name), name, ZSTR_VAL(type->text), ZSTR_VAL(problem));
		zend_string_release(problem);
		smart_str_free(&given);
	}

	return false;
}

bool sharp_object_properties_accept(zend_object *object)
{
	for (uint32_t i = 0; i < object->ce->default_properties_count; i++) {
		zend_property_info *info = object->ce->properties_info_table[i];
		zval *slot = OBJ_PROP_NUM(object, i);
		if (info && info->sharp_type && Z_TYPE_P(slot) != IS_UNDEF
				&& !sharp_property_accepts(info, slot, object, false)) {
			return false;
		}
	}

	return true;
}

static bool sharp_type_arguments_fit(
	const sharp_type_node *arguments, const zend_class_entry *ce, sharp_type_reader *reader);

/* Whether `allowed`, the effects a function type's `uses` lists, has the effect the `length` bytes at `effect` name,
 * by its class's name, which PHP compares without case, spec section 29. */
static bool sharp_effect_allowed(const char *effect, size_t length, const sharp_type_node *allowed)
{
	for (; allowed; allowed = allowed->next) {
		if (zend_binary_strcasecmp(effect, length, allowed->text, allowed->length) == 0) {
			return true;
		}
	}

	return false;
}

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
	for (; effect; effect = effect->next) {
		if (!sharp_effect_allowed(effect->text, effect->length, allowed)) {
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

/* Whether the built-in type `type`, a SHARP_TYPE_NAMED, is named `name`. */
static bool sharp_type_node_names(const sharp_type_node *type, const char *name)
{
	return !type->name && type->name_length == strlen(name) && memcmp(type->text, name, type->name_length) == 0;
}

/* A copy of `node`, read from its text into `reader`'s arena, which another node can take as a member. */
static sharp_type_node *sharp_type_node_copy(const sharp_type_node *node, sharp_type_reader *reader)
{
	sharp_type_reader text = sharp_type_reader_of(node->text, node->length, reader->arena);
	sharp_type_node *copy = sharp_type_read(&text);
	reader->arena = text.arena;
	ZEND_ASSERT(copy && text.at == text.end);

	return copy;
}

/* Whether `type` is within `bound`, a generic built-in type, spec section 12: a collection is a value, so each type
 * argument need only be within the bound's, as for `out`, and `List<string>` is within `List<Any?>`. An `Iterable` holds
 * a List's and a Set's elements, and a Map's entries as `[TKey, TValue]` tuples. A bound with no type arguments
 * written, as `List`, holds its kind with any, as a generic class's does. */
static bool sharp_type_built_in_fits(sharp_type_node *type, sharp_type_node *bound, sharp_type_reader *reader)
{
	if (type->kind != SHARP_TYPE_NAMED || type->name) {
		return false;
	}
	bool same = type->name_length == bound->name_length && memcmp(type->text, bound->text, bound->name_length) == 0;
	bool element_of = sharp_type_node_names(bound, "Iterable")
		&& (sharp_type_node_names(type, "List") || sharp_type_node_names(type, "Set") || sharp_type_node_names(type, "Map"));
	if (!same && !element_of) {
		return false;
	}
	if (!bound->count || !type->count) {
		return !bound->count;
	}
	if (same) {
		sharp_type_node *wanted = bound->first;
		for (sharp_type_node *argument = type->first; argument; argument = argument->next, wanted = wanted->next) {
			if (!wanted || !sharp_type_fits(argument, wanted, reader)) {
				return false;
			}
		}

		return !wanted;
	}
	sharp_type_node *element = type->first;
	if (type->count == 2) {
		element = sharp_type_node_start(reader, SHARP_TYPE_TUPLE, NULL);
		sharp_type_node_add(element, sharp_type_node_copy(type->first, reader));
		sharp_type_node_add(element, sharp_type_node_copy(type->last, reader));
		sharp_type_node_spell(element, &reader->arena);
	}

	return sharp_type_fits(element, bound->first, reader);
}

/* Whether `type` is within `bound`: `Any?` holds every type, `Any` every type but null, `Object` every object, a class's
 * and a function's, spec section 24, a class its subclasses, a class
 * with type arguments each subclass whose header gives it type arguments its own take, a function type each function
 * type that can be assigned to it, a tuple each tuple whose element types are within its own, an intersection what
 * each of its classes holds, and any other bound only itself. A
 * union is within a bound when each of its members is, and an intersection when one of its classes is. `never`, which
 * no value is of, is within every bound. */
static bool sharp_type_fits(sharp_type_node *type, sharp_type_node *bound, sharp_type_reader *reader)
{
	if (sharp_type_node_is(bound, "Any?") || sharp_type_node_is(type, "never")) {
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
	if (sharp_type_node_is(bound, "Object")) {
		return type->kind == SHARP_TYPE_FUNCTION
			|| (type->kind == SHARP_TYPE_NAMED && (type->name || sharp_type_node_is(type, "Object")));
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
	if (bound->kind == SHARP_TYPE_NAMED && !bound->name && sharp_type_built_in_arity(bound->text, bound->name_length) > 0) {
		return sharp_type_built_in_fits(type, bound, reader);
	}
	if (bound->kind != SHARP_TYPE_NAMED || !bound->name) {
		return sharp_type_argument_fits(type, bound, '=', reader);
	}

	/* The type's class loads first, which loads each class it extends or implements, so the bound's is then loaded
	 * whenever it is one of them. */
	if (type->kind != SHARP_TYPE_NAMED || !sharp_type_node_class(type)) {
		return false;
	}
	zend_class_entry *ce = sharp_type_node_class(bound);
	if (!ce || !instanceof_function(type->ce, ce)) {
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
 * own type parameters: its function type, spec section 21, or the SHARP_TYPE_RESULT of its result alone when no
 * function type can say its effects, as for one that calls plain PHP no `extern` declares. NULL for a plain PHP
 * function, for a method that carries no type, and for one whose type names a type parameter it has no type argument
 * for. */
static const sharp_type *sharp_function_carried(const zend_function *func, zend_object *object, const sharp_type *method)
{
	if (func->type != ZEND_USER_FUNCTION || !func->op_array.sharp_type) {
		return NULL;
	}

	zval text;
	ZVAL_STR(&text, func->op_array.sharp_type);
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

/* The result type of `carried`, a type sharp_function_carried read, which a function type and a result alone each keep
 * as their first member. */
static const sharp_type *sharp_carried_result(const sharp_type *carried)
{
	return carried ? carried->members[0] : NULL;
}

/* The function type the PHP declaration of the plain PHP function `func` gives it run on `called`, spec section 21, as
 * sharp_php_function_type_text writes it. The declaration is code, so its type lives for the process. */
static const sharp_type *sharp_php_function_type(const zend_function *func, const zend_class_entry *called)
{
	smart_str written = {0};
	sharp_php_function_type_text(&written, func, called);
	smart_str_0(&written);
	const sharp_type *list = sharp_type_list(ZSTR_VAL(written.s), ZSTR_LEN(written.s));
	smart_str_free(&written);

	return list ? list->members[0] : NULL;
}

/* A Sharp\Internal\Delegate is the function value `Method.createDelegate` makes, spec section 25, whose closure calls
 * `func`: a method, or the `get` or `set` of the property `property`, an instance property's hook or a static one's
 * hidden method, see sharp_static_accessor. An instance one takes its receiver, of `ce`, as its first argument, and a
 * static one runs on `ce`, the class of the class value its Method was read from. It reads and writes a property in
 * `scope`, that of the code that made it, as that code's direct use would, and reads a plain PHP override's `extern`
 * effects from the listener table above `file`, that code's file, or NULL. Its function type is `type`. One
 * `Method.createInvoker` makes spreads its arguments: it takes the method's arguments as one list after its receiver. */
typedef struct {
	const sharp_type *type;
	zend_function *func;
	zend_class_entry *ce;
	zend_class_entry *scope;
	zend_string *file;
	zend_string *property;
	/* ZEND_PROPERTY_HOOK_GET or ZEND_PROPERTY_HOOK_SET for an accessor, -1 for a method. */
	int accessor;
	bool spreads;
	/* The plain PHP closure a delegate made at a crossing calls, see sharp_closure_check, and UNDEF for a method's. */
	zval closure;
	zend_object std;
} sharp_delegate;

static zend_class_entry *sharp_ce_delegate;

static zend_object_handlers sharp_delegate_handlers;

/* Sharp\Internal\Delegate::call, the function each delegate's closure calls. */
static zend_function *sharp_delegate_call;

static sharp_delegate *sharp_delegate_from(zend_object *object)
{
	return (sharp_delegate *) ((char *) object - XtOffsetOf(sharp_delegate, std));
}

static zend_object *sharp_delegate_create(zend_class_entry *ce)
{
	sharp_delegate *delegate = zend_object_alloc(sizeof(sharp_delegate), ce);
	zend_object_std_init(&delegate->std, ce);

	return &delegate->std;
}

static void sharp_delegate_free(zend_object *object)
{
	sharp_delegate *delegate = sharp_delegate_from(object);
	if (delegate->property) {
		zend_string_release_ex(delegate->property, false);
	}
	if (delegate->file) {
		zend_string_release(delegate->file);
	}
	zval_ptr_dtor(&delegate->closure);
	zend_object_std_dtor(object);
}

/* The plain PHP closure a delegate holds, which the cycle collector walks into. */
static HashTable *sharp_delegate_gc(zend_object *object, zval **table, int *count)
{
	sharp_delegate *delegate = sharp_delegate_from(object);
	*table = Z_ISUNDEF(delegate->closure) ? NULL : &delegate->closure;
	*count = Z_ISUNDEF(delegate->closure) ? 0 : 1;

	return NULL;
}

/* Appends the name a message gives the method `func`, its class's dotted name and its own, `App.Order.customer`, or
 * an accessor's by its property, `App.Order.total.get`, whatever PHP or the compile names it, `$total::get` or
 * `total#get`. */
static void sharp_delegate_name(smart_str *text, const zend_function *func)
{
	const zend_string *class_name = func->common.scope->name;
	for (size_t i = 0; i < ZSTR_LEN(class_name); i++) {
		smart_str_appendc(text, ZSTR_VAL(class_name)[i] == '\\' ? '.' : ZSTR_VAL(class_name)[i]);
	}
	smart_str_appendc(text, '.');
	const char *name = ZSTR_VAL(func->common.function_name);
	for (const char *at = *name == '$' ? name + 1 : name; *at; at++) {
		if (*at == '#' || (at[0] == ':' && at[1] == ':')) {
			smart_str_appendc(text, '.');
			at += *at == ':';
		} else {
			smart_str_appendc(text, *at);
		}
	}
}

/* Throws the ValueError that `func` doesn't fit a function type, named as sharp_delegate_name names it and followed by
 * the reason `format` spells: "`App.Order.customer` returns `int`, and `Function<string(App.Order)>` returns
 * `string`." */
static ZEND_COLD void sharp_delegate_refuse(const zend_function *func, const char *format, ...)
{
	smart_str name = {0};
	sharp_delegate_name(&name, func);
	smart_str_0(&name);
	va_list arguments;
	va_start(arguments, format);
	zend_string *reason = zend_vstrpprintf(0, format, arguments);
	va_end(arguments);
	zend_value_error("`%s` %s", ZSTR_VAL(name.s), ZSTR_VAL(reason));
	zend_string_release_ex(reason, false);
	smart_str_free(&name);
}

static HashTable *sharp_extern_effects(const zend_function *func, const zend_string *file);

/* Whether the function `func` has only effects the function type `type`, a node of `reader`'s, lists in its `uses`,
 * spec section 29: a PHP# function's are those its compile carries in its own function type, see
 * sharp_function_carried, and a plain PHP one's those its `extern` lines declare, as the listener table of the root
 * above `file` lists them, see sharp_extern_effects. A PHP# function that carries its result alone, as one that calls
 * plain PHP no `extern` declares, and a plain PHP one no `extern` line declares or whose line says `uses any`, may have
 * every effect. False with a ValueError naming the first effect `type` doesn't allow. */
static bool sharp_delegate_effects_fit(const zend_function *func, const zend_string *file, sharp_type_node *type,
	sharp_type_reader *reader)
{
	sharp_type_node *allowed = type->first;
	for (uint32_t i = type->effects; i < type->count; i++) {
		allowed = allowed->next;
	}
	smart_str listed = {0};
	for (sharp_type_node *effect = allowed; effect; effect = effect->next) {
		smart_str_appends(&listed, listed.s ? ", `" : "allows only `");
		smart_str_appendl(&listed, effect->text, effect->length);
		smart_str_appendc(&listed, '`');
	}
	smart_str_0(&listed);
	const char *allows = listed.s ? ZSTR_VAL(listed.s) : "allows none";

	/* The function's effects: the nodes of its own function type, or the names its `extern` lines declare. */
	sharp_type_node *own = NULL;
	HashTable *declared = NULL;
	if (sharp_function_is_sharp(func)) {
		zend_string *text = func->op_array.sharp_type;
		sharp_type_reader carried = sharp_type_reader_of(text ? ZSTR_VAL(text) : NULL, text ? ZSTR_LEN(text) : 0, reader->arena);
		sharp_type_node *list = text ? sharp_type_read_list(&carried) : NULL;
		reader->arena = carried.arena;
		own = list && list->first->kind == SHARP_TYPE_FUNCTION ? list->first : NULL;
	} else {
		declared = sharp_extern_effects(func, file);
	}
	bool fits = !EG(exception);
	if (fits && !own && !declared) {
		sharp_delegate_refuse(func, "may have every effect, and `%.*s` %s.", (int) type->length, type->text, allows);
		fits = false;
	}
	sharp_type_node *effect = own ? own->first : NULL;
	for (uint32_t i = own ? own->effects : 0; i < (own ? own->count : 0); i++) {
		effect = effect->next;
	}
	for (; fits && effect; effect = effect->next) {
		if (!sharp_effect_allowed(effect->text, effect->length, allowed)) {
			sharp_delegate_refuse(func, "has the effect `%.*s`, and `%.*s` %s.", (int) effect->length, effect->text,
				(int) type->length, type->text, allows);
			fits = false;
		}
	}
	zval *name;
	if (fits && declared) {
		ZEND_HASH_FOREACH_VAL(declared, name) {
			if (!sharp_effect_allowed(Z_STRVAL_P(name), Z_STRLEN_P(name), allowed)) {
				sharp_delegate_refuse(func, "has the effect `%s`, and `%.*s` %s.", Z_STRVAL_P(name), (int) type->length,
					type->text, allows);
				fits = false;
				break;
			}
		} ZEND_HASH_FOREACH_END();
	}
	smart_str_free(&listed);

	return fits;
}

/* The type the `length` bytes at `text` spell, read into `reader`'s arena, or NULL for none. */
static sharp_type_node *sharp_type_text_read(const char *text, size_t length, sharp_type_reader *reader)
{
	sharp_type_reader read = sharp_type_reader_of(text, length, reader->arena);
	sharp_type_node *node = sharp_type_read(&read);
	reader->arena = read.arena;

	return node;
}

/* Whether the method or accessor `func`, of the function type `declared` as reflection reads it, fits the function type
 * `type`, spec section 25, by the rules a function is assigned to a function type by, spec section 21: an instance
 * one, whose receiver is of the class value `receiver`, takes it as `type`'s first parameter, of that class, of a
 * class below it, or of a class it extends, whose value each call checks, `type` passes each parameter `func` needs,
 * each parameter type of `type` is within the one at its place, `func`'s result is within `type`'s unless that is
 * `void` or `Any?`, and `type` lists each effect `func` has, see sharp_delegate_effects_fit, whose `file` it passes.
 * One that `spreads` passes one `List<Any?>` after the receiver in place of the parameters, whose values each call
 * checks. False with a ValueError naming the first that doesn't. */
static bool sharp_delegate_fits(const zend_function *func, const zend_string *file, const zend_string *declared,
	const zend_string *receiver, const sharp_type *type, bool spreads)
{
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *wanted = sharp_type_text_read(ZSTR_VAL(type->text), ZSTR_LEN(type->text), &reader);
	sharp_type_node *own = sharp_type_text_read(ZSTR_VAL(declared), ZSTR_LEN(declared), &reader);
	ZEND_ASSERT(wanted && own && wanted->kind == SHARP_TYPE_FUNCTION && own->kind == SHARP_TYPE_FUNCTION);
	int length = (int) wanted->length;
	uint32_t passes = wanted->count - 1 - wanted->effects;
	sharp_type_node *passed = wanted->first->next;
	bool fits = true;
	if (receiver) {
		sharp_type_reader read = sharp_type_reader_of(NULL, 0, reader.arena);
		sharp_type_node *self = sharp_class_value_read(receiver, &read);
		reader.arena = read.arena;
		ZEND_ASSERT(self);
		/* A base class walks its subclass's methods with its own type as the receiver's, which each call checks. */
		fits = passes && (sharp_type_fits(passed, self, &reader) || sharp_type_fits(self, passed, &reader));
		if (!fits && passes) {
			sharp_delegate_refuse(func, "takes a receiver of `%.*s` first, and `%.*s` passes `%.*s`.",
				(int) self->length, self->text, length, wanted->text, (int) passed->length, passed->text);
		} else if (!fits) {
			sharp_delegate_refuse(func, "takes a receiver of `%.*s` first, and `%.*s` passes nothing.",
				(int) self->length, self->text, length, wanted->text);
		} else {
			passed = passed->next;
			passes--;
		}
	}
	if (fits && spreads && (passes != 1 || !sharp_type_node_is(passed, "List<Any?>"))) {
		sharp_delegate_refuse(func, "takes its arguments as one `List<Any?>`%s, and `%.*s` doesn't.",
			receiver ? " after its receiver" : "", length, wanted->text);
		fits = false;
	}
	uint32_t required = spreads ? 0 : func->common.required_num_args;
	if (fits && passes < required) {
		sharp_delegate_refuse(func, "takes %" PRIu32 " argument%s, and `%.*s` passes %" PRIu32 ".", required,
			required == 1 ? "" : "s", length, wanted->text, passes);
		fits = false;
	}
	/* A function may take fewer parameters than its type passes, and a variadic one takes the rest as its last. */
	uint32_t takes = own->count - 1;
	bool variadic = func->common.fn_flags & ZEND_ACC_VARIADIC;
	sharp_type_node *parameter = own->first;
	for (uint32_t i = 0; fits && !spreads && i < passes && (i < takes || variadic); i++, passed = passed->next) {
		parameter = i < takes ? parameter->next : parameter;
		if (!sharp_type_fits(passed, parameter, &reader)) {
			sharp_delegate_refuse(func, "takes `%.*s` as `%s`, and `%.*s` passes `%.*s`.", (int) parameter->length,
				parameter->text, sharp_parameter_name(func, i < takes ? i : takes - 1), length, wanted->text,
				(int) passed->length, passed->text);
			fits = false;
		}
	}
	if (fits && !sharp_type_node_is(wanted->first, "void") && !sharp_type_node_is(wanted->first, "Any?")
			&& !sharp_type_fits(own->first, wanted->first, &reader)) {
		sharp_delegate_refuse(func, "returns `%.*s`, and `%.*s` returns `%.*s`.", (int) own->first->length,
			own->first->text, length, wanted->text, (int) wanted->first->length, wanted->first->text);
		fits = false;
	}
	fits = fits && sharp_delegate_effects_fit(func, file, wanted, &reader);
	zend_arena_destroy(reader.arena);

	return fits;
}

/* The method `name` that `class_name` declares, which a delegate runs on a class value of `ce` whose type arguments are
 * `arguments`: the one `ce` runs unless it is private, as a call through `ce` runs it, with its function type in
 * `declared`, as sharp_method_declared_types reads it. The code `caller` runs reaches it as a direct call would, see
 * sharp_reaches_method. NULL with an exception for a method it can't reach. */
static zend_function *sharp_delegate_method(const zend_execute_data *caller, zend_class_entry *ce,
	const sharp_type *arguments, zend_string *class_name, zend_string *name, zend_string **declared)
{
	zend_class_entry *declaring = zend_lookup_class(class_name);
	zend_function *func = declaring ? sharp_method_named(declaring, name) : NULL;
	if (!func) {
		zend_throw_error(NULL, "Call to undefined method %s::%s()", ZSTR_VAL(class_name), ZSTR_VAL(name));
		return NULL;
	}
	if (!sharp_reaches_method(caller, func, name)) {
		return NULL;
	}
	if (!(func->common.fn_flags & ZEND_ACC_PRIVATE)) {
		func = sharp_method_named(ce, name);
	}
	smart_str words = {0};
	const sharp_type *types = sharp_method_declared_types(func, ce, arguments, &words);
	smart_str_free(&words);
	if (!types) {
		zend_throw_error(NULL, "PHP# reads no type for %s::%s(). This is a bug in PHP#",
			ZSTR_VAL(func->common.scope->name), ZSTR_VAL(name));
		return NULL;
	}
	*declared = zend_string_copy(types->text);

	return func;
}

/* The accessor, `kind`, of the property `property` of `ce`, whose type arguments are `arguments`, which a delegate runs:
 * an instance property's hook, or a static one's hidden method, see sharp_static_accessor, with its function type in
 * `declared`: a `get` gives the property's type, and a `set` takes it and gives `void`, as `Property.getter()` and
 * `Property.setter()` read them. The code `caller` runs reaches it as a direct use would, see sharp_reaches_property.
 * NULL with an exception for a property without the accessor or one it can't reach. */
static zend_function *sharp_delegate_accessor(const zend_execute_data *caller, zend_class_entry *ce,
	const sharp_type *arguments, zend_string *property, int kind, zend_string **declared)
{
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, property);
	bool is_static = !info || (info->flags & ZEND_ACC_STATIC);
	zend_function *func = is_static ? sharp_static_accessor(ce, ZSTR_VAL(property), kind)
		: info->hooks ? info->hooks[kind] : NULL;
	const char *accessor = kind == ZEND_PROPERTY_HOOK_GET ? "get" : "set";
	if (!func) {
		zend_throw_error(NULL, "Property %s::$%s has no %s", ZSTR_VAL(ce->name), ZSTR_VAL(property), accessor);
		return NULL;
	}
	if (!sharp_reaches_property(caller, ce, property)) {
		return NULL;
	}
	smart_str words = {0};
	const sharp_type *type = is_static ? sharp_static_accessor_declared_type(func, ZSTR_VAL(property), ce, arguments, &words)
		: sharp_property_declared_type(info, ce, arguments, &words);
	smart_str_free(&words);
	if (!type) {
		zend_throw_error(NULL, "PHP# reads no type for %s::$%s. This is a bug in PHP#", ZSTR_VAL(ce->name),
			ZSTR_VAL(property));
		return NULL;
	}
	*declared = kind == ZEND_PROPERTY_HOOK_GET ? zend_strpprintf(0, "Function<%s()>", ZSTR_VAL(type->text))
		: zend_strpprintf(0, "Function<void(%s)>", ZSTR_VAL(type->text));

	return func;
}

/* Spec section 25: `Method.createDelegate<TFunction>()`, or `Method.createInvoker<TFunction>()` when it `spreads`, as
 * `member` names it, a value of the function type `type` that calls the method `method` that `class` declares, or the
 * accessor a `Property.getter()` or `Property.setter()` names, `$total::get`. An instance one takes its receiver, of
 * `read_on`, the class value its Method was read from, as the type's first parameter, and a static one runs on
 * `read_on`. An invoker takes the member's arguments as one list after that. The caller reaches the member as a direct
 * use would. A member whose parameters, result or effects don't fit the type, see sharp_delegate_fits, is a ValueError,
 * so the type alone says what a call of the value has, and the checker types the call by it. */
static void sharp_delegate_make(INTERNAL_FUNCTION_PARAMETERS, const char *member, bool spreads)
{
	zend_string *read_on;
	zend_string *class_name;
	zend_string *name;
	zend_string *value;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_STR(read_on)
		Z_PARAM_STR(class_name)
		Z_PARAM_STR(name)
		Z_PARAM_STR(value)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_of(value);
	if (type && type->kind != SHARP_TYPE_FUNCTION) {
		zend_value_error("`%s` makes a value of a function type, and was given `%s`.", member, ZSTR_VAL(value));
	}
	if (EG(exception)) {
		RETURN_THROWS();
	}
	const sharp_type *arguments;
	zend_class_entry *ce = sharp_class_value_declaring(read_on, &arguments);
	if (!ce) {
		RETURN_THROWS();
	}
	const zend_execute_data *caller = sharp_reflection_caller(execute_data);
	zend_string *file = caller && ZEND_USER_CODE(caller->func->type) ? caller->func->op_array.filename : NULL;
	zend_string *property = NULL;
	zend_string *declared = NULL;
	int accessor = sharp_accessor_kind(name, &property);
	zend_function *func = accessor >= 0 ? sharp_delegate_accessor(caller, ce, arguments, property, accessor, &declared)
		: sharp_delegate_method(caller, ce, arguments, class_name, name, &declared);
	bool is_static = func && (func->common.fn_flags & ZEND_ACC_STATIC);
	if (!func || !sharp_delegate_fits(func, file, declared, is_static ? NULL : read_on, type, spreads)) {
		if (property) {
			zend_string_release_ex(property, false);
		}
		if (declared) {
			zend_string_release_ex(declared, false);
		}
		RETURN_THROWS();
	}
	zend_string_release_ex(declared, false);

	zval object;
	object_init_ex(&object, sharp_ce_delegate);
	sharp_delegate *delegate = sharp_delegate_from(Z_OBJ(object));
	delegate->type = type;
	delegate->func = func;
	delegate->ce = ce;
	delegate->scope = caller ? caller->func->common.scope : NULL;
	delegate->file = file ? zend_string_copy(file) : NULL;
	delegate->property = property;
	delegate->accessor = accessor;
	delegate->spreads = spreads;
	zend_create_fake_closure(return_value, sharp_delegate_call, sharp_ce_delegate, sharp_ce_delegate, &object);
	zval_ptr_dtor(&object);
}

ZEND_FUNCTION(Sharp_Internal_createDelegate)
{
	sharp_delegate_make(INTERNAL_FUNCTION_PARAM_PASSTHRU, "createDelegate", false);
}

ZEND_FUNCTION(Sharp_Internal_createInvoker)
{
	sharp_delegate_make(INTERNAL_FUNCTION_PARAM_PASSTHRU, "createInvoker", true);
}

/* The function `delegate` runs on `receiver`: the receiver's override of its method unless that is private, or its
 * class's accessor of the property, as a direct call or use would run it. An override whose effects the delegate's
 * type doesn't list is a ValueError, as at the delegate's creation, and NULL. */
static zend_function *sharp_delegate_runs(const sharp_delegate *delegate, const zend_object *receiver)
{
	zend_function *func = delegate->func;
	zend_function *own = func;
	if (delegate->property) {
		const zend_property_info *info = zend_hash_find_ptr(&receiver->ce->properties_info, delegate->property);
		own = info && info->hooks && info->hooks[delegate->accessor] ? info->hooks[delegate->accessor] : func;
	} else if (!(func->common.fn_flags & ZEND_ACC_PRIVATE)) {
		own = sharp_method_named(receiver->ce, func->common.function_name);
	}
	if (own == func) {
		return func;
	}
	sharp_type_reader reader = sharp_type_reader_of(NULL, 0, zend_arena_create(1024));
	sharp_type_node *type = sharp_type_text_read(ZSTR_VAL(delegate->type->text), ZSTR_LEN(delegate->type->text), &reader);
	bool fits = sharp_delegate_effects_fit(own, delegate->file, type, &reader);
	zend_arena_destroy(reader.arena);

	return fits ? own : NULL;
}

/* Whether the method `func` takes the `given` arguments an invoker spreads, spec section 25: no more than it takes
 * unless it is variadic, each it requires, and, for each it leaves out, a default that runs no code, as
 * `Parameter.defaultValue()` reads one, so the call has only the effects the invoker's type lists. False with an
 * ArgumentCountError or a ValueError naming the method and the parameter. */
static bool sharp_invoker_takes(const zend_function *func, uint32_t given)
{
	uint32_t takes = func->common.num_args;
	const char *left_out = NULL;
	bool runs_code = false;
	if (given <= takes || (func->common.fn_flags & ZEND_ACC_VARIADIC)) {
		for (uint32_t i = given; !left_out && i < takes; i++) {
			zval value;
			if (!sharp_parameter_default(func, i, &value)) {
				left_out = sharp_parameter_name(func, i);
				continue;
			}
			runs_code = Z_TYPE(value) == IS_CONSTANT_AST && sharp_ast_runs_code(Z_ASTVAL(value));
			left_out = runs_code ? sharp_parameter_name(func, i) : NULL;
			zval_ptr_dtor(&value);
		}
		if (!left_out) {
			return true;
		}
	}
	smart_str name = {0};
	sharp_delegate_name(&name, func);
	smart_str_0(&name);
	if (!left_out) {
		zend_argument_count_error("`%s` takes at most %" PRIu32 " argument%s, and was given %" PRIu32 ".",
			ZSTR_VAL(name.s), takes, takes == 1 ? "" : "s", given);
	} else if (runs_code) {
		zend_value_error("`%s` gives `%s` a default that runs code, which an invoker never runs, and was given no value "
			"for it.", ZSTR_VAL(name.s), left_out);
	} else {
		zend_argument_count_error("`%s` takes `%s`, and was given no value for it.", ZSTR_VAL(name.s), left_out);
	}
	smart_str_free(&name);

	return false;
}

/* Runs the delegate on `count` `arguments`, see sharp_delegate_make: an instance one on its first, which must be of its
 * class, and the rest, and a static one on them all. An accessor reads or writes its property in the scope of the code
 * that made the delegate. An invoker's arguments must fit its method's or accessor's parameters, see
 * sharp_invoker_takes. Each argument is checked as a direct PHP# call's is, since `call` runs in strict mode (PHP_MINIT_FUNCTION). */
static void sharp_delegate_run(const sharp_delegate *delegate, zval *arguments, uint32_t count, zval *return_value)
{
	zend_function *func = delegate->func;
	if (func->common.fn_flags & ZEND_ACC_STATIC) {
		if (delegate->spreads && !sharp_invoker_takes(func, count)) {
			RETURN_THROWS();
		}
		if (delegate->property) {
			sharp_static_accessor_call(delegate->scope, func, delegate->ce, delegate->property, count ? arguments : NULL,
				return_value);
		} else {
			zend_call_known_function(func, NULL, delegate->ce, return_value, count, arguments, NULL);
		}
		return;
	}
	if (!count || Z_TYPE(arguments[0]) != IS_OBJECT || !instanceof_function(Z_OBJCE(arguments[0]), delegate->ce)) {
		smart_str name = {0};
		sharp_delegate_name(&name, func);
		smart_str_0(&name);
		zend_type_error("`%s` takes a receiver of `%s`, and was given %s.", ZSTR_VAL(name.s), ZSTR_VAL(delegate->ce->name),
			count ? zend_zval_value_name(arguments) : "none");
		smart_str_free(&name);
		RETURN_THROWS();
	}
	zend_object *receiver = Z_OBJ(arguments[0]);
	func = sharp_delegate_runs(delegate, receiver);
	if (!func || (delegate->spreads && !sharp_invoker_takes(func, count - 1))) {
		RETURN_THROWS();
	}
	if (!delegate->property) {
		zend_call_known_function(func, receiver, receiver->ce, return_value, count - 1, arguments + 1, NULL);
	} else if (delegate->accessor == ZEND_PROPERTY_HOOK_GET) {
		zval value;
		zval *read = zend_read_property_ex(delegate->scope, receiver, delegate->property, false, &value);
		if (!EG(exception)) {
			ZVAL_COPY_DEREF(return_value, read);
		}
		if (read == &value) {
			zval_ptr_dtor(&value);
		}
	} else if (count == 2) {
		zend_update_property_ex(delegate->scope, receiver, delegate->property, &arguments[1]);
	} else {
		smart_str name = {0};
		sharp_delegate_name(&name, func);
		smart_str_0(&name);
		zend_argument_count_error("`%s` takes 1 argument, and was given %" PRIu32 ".", ZSTR_VAL(name.s), count - 1);
		smart_str_free(&name);
	}
}

/* Calls the plain PHP closure `delegate` holds with the `count` `arguments` PHP# passes, which PHP checks against the
 * closure's declared parameters, and checks its result as any crossing's, spec section 14: a result of another type than
 * the delegate's function type returns throws the TypeError of a function's return value naming that type. */
static void sharp_closure_checked_call(const sharp_delegate *delegate, zval *arguments, uint32_t count, zval *return_value)
{
	if (call_user_function(NULL, NULL, (zval *) &delegate->closure, return_value, count, arguments) == FAILURE
		|| EG(exception)) {
		return;
	}
	const sharp_type *result = delegate->type->members[0];
	if (!sharp_type_verified(result, result, return_value, ZSTR_VAL(delegate->type->text))) {
		zval_ptr_dtor(return_value);
		ZVAL_NULL(return_value);
	}
}

/* Runs the delegate on the `count` arguments its closure was called with, see sharp_delegate_run, or the plain PHP
 * closure it holds, see sharp_closure_checked_call. An invoker takes its method's arguments as the one list after its
 * receiver, which it spreads: each value of the list is an argument. */
ZEND_METHOD(Sharp_Internal_Delegate, call)
{
	zval *arguments = NULL;
	uint32_t count = 0;

	ZEND_PARSE_PARAMETERS_START(0, -1)
		Z_PARAM_VARIADIC('*', arguments, count)
	ZEND_PARSE_PARAMETERS_END();

	sharp_delegate *delegate = sharp_delegate_from(Z_OBJ_P(ZEND_THIS));
	if (!Z_ISUNDEF(delegate->closure)) {
		sharp_closure_checked_call(delegate, arguments, count, return_value);
		return;
	}
	if (!delegate->spreads) {
		sharp_delegate_run(delegate, arguments, count, return_value);
		return;
	}
	uint32_t list = !(delegate->func->common.fn_flags & ZEND_ACC_STATIC);
	if (count != list + 1 || Z_TYPE(arguments[list]) != IS_ARRAY) {
		smart_str name = {0};
		sharp_delegate_name(&name, delegate->func);
		smart_str_0(&name);
		zend_type_error("`%s` takes its arguments as one `List<Any?>`%s, and was given %s.", ZSTR_VAL(name.s),
			list ? " after its receiver" : "", count > list ? zend_zval_value_name(&arguments[list]) : "none");
		smart_str_free(&name);
		RETURN_THROWS();
	}
	HashTable *values = Z_ARRVAL(arguments[list]);
	uint32_t spread_count = list + zend_hash_num_elements(values);
	zval *spread = safe_emalloc(spread_count, sizeof(zval), 0);
	if (list) {
		ZVAL_COPY(&spread[0], &arguments[0]);
	}
	uint32_t at = list;
	zval *value;
	ZEND_HASH_FOREACH_VAL(values, value) {
		ZVAL_COPY_DEREF(&spread[at], value);
		at++;
	} ZEND_HASH_FOREACH_END();
	sharp_delegate_run(delegate, spread, spread_count, return_value);
	for (uint32_t i = 0; i < spread_count; i++) {
		zval_ptr_dtor(&spread[i]);
	}
	efree(spread);
}

ZEND_METHOD(Sharp_Internal_Delegate, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* The function type `type` is, or holds as `T?`, whose result a crossing checks: NULL for any other type, and for a
 * function type returning `void` or `Any?`, which every result fits. */
static const sharp_type *sharp_type_checked_function(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NULLABLE) {
		type = type->members[0];
	}
	if (type->kind != SHARP_TYPE_FUNCTION) {
		return NULL;
	}
	const sharp_type *result = type->members[0];
	const sharp_type *named = result->kind == SHARP_TYPE_NULLABLE ? result->members[0] : result;
	bool open = named->kind == SHARP_TYPE_NAMED
		&& (named->built_in == SHARP_BUILT_IN_VOID || (named->built_in == SHARP_BUILT_IN_ANY && named != result));

	return open ? NULL : type;
}

/* Spec section 14: puts in `value`, a plain PHP closure that crosses into the function type `type`, the delegate that
 * calls it and checks each result, see sharp_closure_checked_call, as a gradual type system wraps a function from
 * untyped code. One that crosses in again keeps its delegate for the same type, and has its plain PHP closure wrapped
 * for another. A PHP# closure and a method's delegate carry their own type, which the crossing's check compares. */
static void sharp_closure_check(const sharp_type *type, zval *value)
{
	const sharp_type *function = sharp_type_checked_function(type);
	if (!function || Z_TYPE_P(value) != IS_OBJECT || Z_OBJCE_P(value) != zend_ce_closure) {
		return;
	}
	const zend_function *func = zend_get_closure_method_def(Z_OBJ_P(value));
	zval *closure = value;
	if (func->common.scope == sharp_ce_delegate) {
		sharp_delegate *held = sharp_delegate_from(Z_OBJ_P(zend_get_closure_this_ptr(value)));
		if (Z_ISUNDEF(held->closure) || sharp_type_equals(held->type, function)) {
			return;
		}
		closure = &held->closure;
	} else if (sharp_function_is_sharp(func)) {
		return;
	}
	zval object;
	object_init_ex(&object, sharp_ce_delegate);
	sharp_delegate *delegate = sharp_delegate_from(Z_OBJ(object));
	delegate->type = function;
	ZVAL_COPY(&delegate->closure, closure);
	zval_ptr_dtor(value);
	zend_create_fake_closure(value, sharp_delegate_call, sharp_ce_delegate, sharp_ce_delegate, &object);
	zval_ptr_dtor(&object);
}

/* The type the closure `value` carries: a PHP# closure's as sharp_function_carried spells it for the object the closure
 * is bound to and the method type arguments it captured, and a plain PHP closure's as its PHP declaration gives it, its
 * `static` the closure's called class. A delegate's is the function type it was made of, see
 * Sharp_Internal_createDelegate. */
static const sharp_type *sharp_closure_carried(const zval *value)
{
	const zend_function *func = zend_get_closure_method_def(Z_OBJ_P(value));
	if (func->common.scope == sharp_ce_delegate) {
		return sharp_delegate_from(Z_OBJ_P(zend_get_closure_this_ptr((zval *) value)))->type;
	}
	if (!sharp_function_is_sharp(func)) {
		zend_class_entry *called;
		zend_function *invoked;
		zend_object *bound;
		Z_OBJ_HANDLER_P(value, get_closure)(Z_OBJ_P(value), &called, &invoked, &bound, true);

		return sharp_php_function_type(func, called);
	}
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
 * decides. Entry leaves a plain PHP closure to PHP's own check of `Closure`, and `is` tests its PHP declaration's. */
static bool sharp_closure_is(const zval *value, const sharp_type *type, sharp_holds_mode mode)
{
	if (Z_TYPE_P(value) != IS_OBJECT || Z_OBJCE_P(value) != zend_ce_closure) {
		return false;
	}
	const zend_function *func = zend_get_closure_method_def(Z_OBJ_P(value));
	if (mode != SHARP_HOLDS_TEST && !sharp_function_is_sharp(func)) {
		return true;
	}
	const sharp_type *own = sharp_closure_type(value);
	if (!own) {
		return !EG(exception) && mode != SHARP_HOLDS_TEST;
	}

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

/* Whether `object`, of a PHP# class whose header names `Iterable<T>`, which PHP links as `IteratorAggregate<int, T>`,
 * gives only values of `element`: its `T` as the object's own type arguments spell it, spec section 12. */
static bool sharp_elements_object_holds(zend_object *object, const sharp_type *element)
{
	smart_str text = {0};
	sharp_object_text(&text, object);
	smart_str_0(&text);
	sharp_type_reader reader = sharp_type_reader_of(ZSTR_VAL(text.s), ZSTR_LEN(text.s), zend_arena_create(1024));
	sharp_type_node *own = sharp_type_read(&reader);
	const sharp_type_node *iterated = own ? sharp_type_node_ancestor(object->ce, own, zend_ce_aggregate, &reader) : NULL;
	sharp_type_reader tested = sharp_type_reader_of(ZSTR_VAL(element->text), ZSTR_LEN(element->text), reader.arena);
	sharp_type_node *wanted = sharp_type_read(&tested);
	reader.arena = tested.arena;
	bool within = iterated && iterated->count == 2 && wanted && sharp_type_fits(iterated->first->next, wanted, &reader);
	zend_arena_destroy(reader.arena);
	smart_str_free(&text);

	return within && !EG(exception);
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
		return sharp_class_is_sharp(object->ce) && instanceof_function(object->ce, zend_ce_aggregate)
			? sharp_elements_object_holds(object, element)
			: sharp_type_within(NULL, element);
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
	sharp_type_plan(&sequence->crossing, sequence->checked);
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

/* The type arguments `text` spells for an object of `ce`, from serialized data, as sharp_type_arguments_unserialize
 * reads them, or NULL. */
static const sharp_type *sharp_type_arguments_read(
	const zend_class_entry *ce, const zval *text, sharp_type_class_resolver resolve, void *context)
{
	if (!sharp_type_arguments_slot(ce) || Z_TYPE_P(text) != IS_STRING) {
		return NULL;
	}

	/* The text parses whole and each class it names resolves before any of it is interned. */
	sharp_type_reader reader = sharp_type_reader_of(Z_STRVAL_P(text), Z_STRLEN_P(text), zend_arena_create(1024));
	reader.depth_left = SHARP_TYPE_INPUT_DEPTH;
	sharp_type_node *arguments = sharp_type_read_list(&reader);
	const sharp_type *list = NULL;
	if (arguments && !reader.names && sharp_type_node_accept(arguments, resolve, context, &reader)
		&& sharp_type_arguments_fit(arguments, ce, &reader)) {
		list = sharp_type_intern(arguments, SHARP_TYPE_FROM_INPUT, &reader.arena);
	}
	zend_arena_destroy(reader.arena);

	return list;
}

zend_result sharp_type_arguments_unserialize(
	zend_object *object, const zval *text, sharp_type_class_resolver resolve, void *context)
{
	const sharp_type *list = sharp_type_arguments_read(object->ce, text, resolve, context);
	if (!list) {
		return FAILURE;
	}
	sharp_type_arguments_store(object, list);

	return SUCCESS;
}

/* The native body of `Sharp.System.SystemEnvironment.commandLine`: the command-line arguments as PHP's `$argv` holds them,
 * starting with the script's name. Only the SAPI holds them, so this body is C behind the prototype sharp/bin/native writes. */
void sharp_native_System_SystemEnvironment_commandLine(zval *result)
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

/* The slot of each zend_fiber_context's reserved array that holds its sharp_fiber_scopes. */
static int sharp_fiber_slot;

static sharp_scope *sharp_scope_start(void)
{
	sharp_scope *scope = emalloc(sizeof(sharp_scope));

	scope->references = 1;
	scope->ended = false;
	zend_hash_init(&scope->services, 8, NULL, ZVAL_PTR_DTOR, false);

	return scope;
}

/* The scope drops its services, and builds none from then on. */
static void sharp_scope_end(sharp_scope *scope)
{
	scope->ended = true;
	zend_hash_clean(&scope->services);
}

static void sharp_scope_release(sharp_scope *scope)
{
	if (--scope->references == 0) {
		zend_hash_destroy(&scope->services);
		efree(scope);
	}
}

/* The scopes of the fiber that runs now, or of the request's main context. */
static sharp_fiber_scopes *sharp_running_fiber_scopes(void)
{
	return EG(current_fiber_context)->reserved[sharp_fiber_slot];
}

/* Starts scopes in started_in, building nothing. */
static void sharp_fiber_scopes_start(sharp_fiber_scopes *scopes, sharp_scope *started_in)
{
	scopes->started_in = scopes->current = started_in;
	zend_hash_init(&scopes->building, 8, NULL, NULL, false);
	zend_stack_init(&scopes->effect_calls, sizeof(struct sharp_effect_run *));
}

/* Ends scopes: they let go of the scope they started in, and a build a suspended fiber never finished ends with them. */
static void sharp_fiber_scopes_end(sharp_fiber_scopes *scopes)
{
	sharp_scope_release(scopes->started_in);
	zend_hash_destroy(&scopes->building);
	zend_stack_destroy(&scopes->effect_calls);
}

/* A fiber starts in the scope of the code that starts it, as .NET's AsyncLocal flows into the work it starts. */
static void sharp_fiber_started(zend_fiber_context *context)
{
	sharp_fiber_scopes *scopes = emalloc(sizeof(sharp_fiber_scopes));
	sharp_scope *started_in = sharp_running_fiber_scopes()->current;

	started_in->references++;
	sharp_fiber_scopes_start(scopes, started_in);
	context->reserved[sharp_fiber_slot] = scopes;
}

static void sharp_fiber_destroyed(zend_fiber_context *context)
{
	sharp_fiber_scopes *scopes = context->reserved[sharp_fiber_slot];

	sharp_fiber_scopes_end(scopes);
	efree(scopes);
	context->reserved[sharp_fiber_slot] = NULL;
}

/* The key the bindings of id, a service's id as sharp_container_id() writes it, in the namespace's first length bytes
 * are kept under. A namespace matches in any case, so its part is lowercase. */
static zend_string *sharp_container_key(const char *namespace, size_t length, const zend_string *id)
{
	zend_string *key = zend_strpprintf(0, "%.*s %s", (int) length, namespace, ZSTR_VAL(id));
	zend_str_tolower(ZSTR_VAL(key), length);

	return key;
}

/* The id the container keys the service a type names by, as mago compile writes a binding's: the type with the name of
 * each PHP# class in it exact, which the class table keys by its exact name, spec section 24, and the rest in
 * lowercase, as PHP compares a plain class's name. A class not yet loaded is no PHP# class here, so the caller loads
 * the class a type names first. */
static zend_string *sharp_container_id(const zend_string *type)
{
	zend_string *id = zend_string_init(ZSTR_VAL(type), ZSTR_LEN(type), 0);
	char *start = ZSTR_VAL(id), *end = start + ZSTR_LEN(id);

	for (char *name = start; name < end;) {
		char *after = name;
		while (after < end && (isalnum((unsigned char) *after) || *after == '_' || *after == '\\')) {
			after++;
		}
		if (after == name) {
			name++;
			continue;
		}
		zend_class_entry *ce = zend_hash_str_find_ptr(EG(class_table), name, after - name);
		if (!ce || !(ce->sharp_flags & SHARP_CLASS_EXACT_KEY)) {
			zend_str_tolower(name, after - name);
		}
		name = after;
	}

	return id;
}

/* Adds rows, one module's binding rows, to module: under each namespace and service id, a list of its rows. */
static void sharp_container_group(HashTable *rows, HashTable *module)
{
	zval *row;
	ZEND_HASH_FOREACH_VAL(rows, row) {
		zend_string *namespace = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 0));
		zend_string *key = sharp_container_key(ZSTR_VAL(namespace), ZSTR_LEN(namespace), Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 1)));
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

/* Copies `value`, which a compiled file returns or holds as a literal, into `copy` for the request to keep. When opcache
 * persists a script it preloads, after the preload script runs and before that request ends, it frees each mutable array
 * of the script's literals whatever its refcount, so each one is copied, nested ones too. An immutable array lives in
 * opcache's shared memory, and opcache only releases a string, so they and every other value are shared. */
static void sharp_copy_literal(zval *copy, const zval *value)
{
	if (Z_TYPE_P(value) != IS_ARRAY || (GC_FLAGS(Z_ARRVAL_P(value)) & IS_ARRAY_IMMUTABLE)) {
		ZVAL_COPY(copy, value);
		return;
	}

	zend_string *key;
	zend_ulong index;
	zval *element;
	array_init_size(copy, zend_hash_num_elements(Z_ARRVAL_P(value)));
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(value), index, key, element) {
		zval owned;
		sharp_copy_literal(&owned, element);
		if (key) {
			zend_hash_add_new(Z_ARRVAL_P(copy), key, &owned);
		} else {
			zend_hash_index_add_new(Z_ARRVAL_P(copy), index, &owned);
		}
	} ZEND_HASH_FOREACH_END();
}

/* Runs a file's op_array in the symbol table symbols for what it returns, as zend_execute does, but in a table the
 * engine's caller gives. zend_execute would give the PHP code that called the engine a symbol table, which a
 * tracing-JIT frame never frees, and a table of PHP's global scope would leave a frame suspended in a fiber with the
 * values the file moved out of it. */
static void sharp_execute_file(zend_op_array *op_array, HashTable *symbols, zval *returned)
{
	zend_execute_data *execute_data = zend_vm_stack_push_call_frame(ZEND_CALL_TOP_CODE | ZEND_CALL_HAS_SYMBOL_TABLE,
		(zend_function *) op_array, 0, NULL);
	execute_data->symbol_table = symbols;
	zend_init_code_execute_data(execute_data, op_array, returned);
	ZEND_OBSERVER_FCALL_BEGIN(execute_data);
	zend_execute_ex(execute_data);
	zend_vm_stack_free_call_frame(execute_data);
}

/* The slot of the global variable name, through the compiled variable of the script's own frame that holds it, or NULL
 * when it is not set. A superglobal PHP fills on first use, as $_SERVER is under auto_globals_jit, is filled first. */
static zval *sharp_global_slot(zend_string *name)
{
	zend_is_auto_global(name);
	zval *slot = zend_hash_find(&EG(symbol_table), name);
	if (slot && Z_TYPE_P(slot) == IS_INDIRECT) {
		slot = Z_INDIRECT_P(slot);
	}
	if (!slot || Z_TYPE_P(slot) == IS_UNDEF) {
		return NULL;
	}
	return slot;
}

/* Sets the global variable name to value, as PHP's $GLOBALS[name] = value does: through a reference to it, and
 * through the compiled variable of the script's own frame that holds it. */
static void sharp_assign_global(zend_string *name, zval *value)
{
	zval *slot = zend_hash_find(&EG(symbol_table), name);
	if (slot && Z_TYPE_P(slot) == IS_INDIRECT) {
		slot = Z_INDIRECT_P(slot);
	}
	if (!slot) {
		zval copy;
		ZVAL_COPY_DEREF(&copy, value);
		zend_hash_add_new(&EG(symbol_table), name, &copy);
		return;
	}
	ZVAL_DEREF(slot);
	zval old;
	ZVAL_COPY_VALUE(&old, slot);
	ZVAL_COPY_DEREF(slot, value);
	zval_ptr_dtor(&old);
}

/* Spec section 29: what PhpGlobals.get and PhpGlobals.read give, the global variable name, or its element at key, as
 * PHP's $GLOBALS[name][key] reads it, or null when either is not set. */
ZEND_FUNCTION(Sharp_Internal_readGlobal)
{
	zend_string *name, *key = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STR(name)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(key)
	ZEND_PARSE_PARAMETERS_END();

	zval *value = sharp_global_slot(name);
	if (value) {
		ZVAL_DEREF(value);
	}
	if (value && key) {
		value = Z_TYPE_P(value) == IS_ARRAY ? zend_symtable_find(Z_ARRVAL_P(value), key) : NULL;
	}
	if (!value) {
		RETURN_NULL();
	}
	RETURN_COPY_DEREF(value);
}

/* Spec section 29: what PhpGlobals.set and PhpGlobals.write run, setting the global variable name, or its element at
 * key, to value, as PHP's $GLOBALS[name][key] = value does. A global that is not set becomes an array for its first
 * element, and one that holds no array throws. */
ZEND_FUNCTION(Sharp_Internal_writeGlobal)
{
	zend_string *name, *key = NULL;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_STR(name)
		Z_PARAM_ZVAL(value)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(key)
	ZEND_PARSE_PARAMETERS_END();

	if (!key) {
		zend_is_auto_global(name);
		sharp_assign_global(name, value);
		return;
	}
	zval *array = sharp_global_slot(name);
	if (!array) {
		zval empty;
		array_init(&empty);
		sharp_assign_global(name, &empty);
		zval_ptr_dtor(&empty);
		array = sharp_global_slot(name);
	}
	ZVAL_DEREF(array);
	if (Z_TYPE_P(array) == IS_NULL) {
		array_init(array);
	} else if (Z_TYPE_P(array) != IS_ARRAY) {
		zend_value_error("PhpGlobals.write: $%s holds %s, not an array", ZSTR_VAL(name), zend_zval_value_name(array));
		RETURN_THROWS();
	}
	SEPARATE_ARRAY(array);
	zval copy;
	ZVAL_COPY_DEREF(&copy, value);
	zend_symtable_update(Z_ARRVAL_P(array), key, &copy);
}

/* Spec section 29: what PhpGlobals.include runs, the PHP file at path in PHP's global scope, as a page's own include
 * runs it. The file runs in a symbol table of its own, sharp_execute_file's, which holds each global variable as
 * `global $name` holds one in a function, a reference the global shares, so the file reads each global and its writes
 * reach it. Each variable the file declares, or binds anew, is a global after it, though it throws. */
ZEND_FUNCTION(Sharp_Internal_includeGlobal)
{
	zend_string *path;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_PATH_STR(path)
	ZEND_PARSE_PARAMETERS_END();

	zend_stat_t status = {0};
	zend_op_array *op_array = VCWD_STAT(ZSTR_VAL(path), &status) == 0 ? compile_filename(ZEND_INCLUDE, path) : NULL;
	if (!op_array) {
		if (!EG(exception)) {
			zend_throw_exception_ex(spl_ce_RuntimeException, 0, "PhpGlobals.include: \"%s\" cannot be read",
				ZSTR_VAL(path));
		}
		RETURN_THROWS();
	}

	HashTable symbols;
	zend_hash_init(&symbols, zend_hash_num_elements(&EG(symbol_table)), NULL, ZVAL_PTR_DTOR, false);
	zend_string *name;
	zval *global;
	ZEND_HASH_FOREACH_STR_KEY_VAL(&EG(symbol_table), name, global) {
		if (Z_TYPE_P(global) == IS_INDIRECT) {
			global = Z_INDIRECT_P(global);
		}
		if (!name || Z_TYPE_P(global) == IS_UNDEF || zend_hash_exists(CG(auto_globals), name)) {
			continue;
		}
		ZVAL_MAKE_REF(global);
		Z_ADDREF_P(global);
		zend_hash_add_new(&symbols, name, global);
	} ZEND_HASH_FOREACH_END();

	zval returned;
	ZVAL_UNDEF(&returned);
	sharp_execute_file(op_array, &symbols, &returned);
	zval_ptr_dtor(&returned);

	zval *value;
	ZEND_HASH_FOREACH_STR_KEY_VAL(&symbols, name, value) {
		zval *bound = name ? sharp_global_slot(name) : NULL;
		if (name && !(bound && Z_ISREF_P(bound) && Z_ISREF_P(value) && Z_REF_P(bound) == Z_REF_P(value))) {
			sharp_assign_global(name, value);
		}
	} ZEND_HASH_FOREACH_END();
	zend_hash_destroy(&symbols);
	zend_destroy_static_vars(op_array);
	destroy_op_array(op_array);
	efree_size(op_array, sizeof(zend_op_array));
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
			zval returned;
			ZVAL_UNDEF(&returned);
			HashTable symbols;
			zend_hash_init(&symbols, 8, NULL, ZVAL_PTR_DTOR, false);
			sharp_execute_file(op_array, &symbols, &returned);
			zend_hash_destroy(&symbols);
			if (!EG(exception) && Z_TYPE(returned) == IS_ARRAY) {
				sharp_copy_literal(&rows, &returned);
			}
			zval_ptr_dtor(&returned);
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

/* Reads the standard library's module, once, before any other module and any binding is read, so a request that
 * resolves no service never needs it compiled. */
static void sharp_container_read_library(void)
{
	if (SHARP_G(container_library)) {
		sharp_container_read(ZSTR_VAL(SHARP_G(container_library)), &SHARP_G(container_bindings));
	}
}

/* Reads into bindings each module of listed, the list of workspace-relative paths of the listener table's modules
 * under one key, or NULL, under the root, the first root_length bytes of path. It stops at the first that throws. */
static void sharp_container_read_listed(const char *path, size_t root_length, const zval *listed, HashTable *bindings)
{
	const zval *module;
	if (!listed) {
		return;
	}
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(listed), module) {
		if (EG(exception)) {
			break;
		}
		zend_string *module_path = zend_string_concat3(path, root_length, "/", 1, Z_STRVAL_P(module), Z_STRLEN_P(module));
		sharp_container_read(ZSTR_VAL(module_path), bindings);
		zend_string_release(module_path);
	} ZEND_HASH_FOREACH_END();
}

static HashTable *sharp_listener_table(const char *path, size_t root_length);

/* Reads the modules that serve namespace, spec section 32: of namespace and of each namespace above it, wherever their
 * files are, as mago compile records them in the listener table of the project that holds file, each namespace's
 * Module.<SHARP_ENV>.sharp files among the environment modules and its Module.sharp files among the base modules. It
 * stops at the first module that throws. */
static void sharp_container_read_modules(const zend_string *file, zend_string *namespace)
{
	sharp_container_read_library();
	size_t root;
	if (EG(exception) || !sharp_find_root(file, &root)) {
		return;
	}
	HashTable *table = sharp_listener_table(ZSTR_VAL(file), root);
	const zval *modules = table ? zend_hash_str_find(table, "modules", sizeof("modules") - 1) : NULL;
	if (!modules) {
		return;
	}

	zend_string *environment = sharp_environment_variable("SHARP_ENV", sizeof("SHARP_ENV") - 1);
	zend_string *lowercase = zend_string_tolower(namespace);
	for (size_t length = ZSTR_LEN(lowercase); length > 0 && !EG(exception);) {
		if (environment && ZSTR_LEN(environment) > 0) {
			zend_string *key = zend_strpprintf(0, "%.*s %s", (int) length, ZSTR_VAL(lowercase), ZSTR_VAL(environment));
			sharp_container_read_listed(ZSTR_VAL(file), root, zend_hash_find(Z_ARRVAL_P(modules), key),
				&SHARP_G(container_environment));
			zend_string_release(key);
		}
		if (!EG(exception)) {
			sharp_container_read_listed(ZSTR_VAL(file), root,
				zend_hash_str_find(Z_ARRVAL_P(modules), ZSTR_VAL(lowercase), length), &SHARP_G(container_bindings));
		}
		const char *end = zend_memrchr(ZSTR_VAL(lowercase), '\\', length);
		length = end ? (size_t) (end - ZSTR_VAL(lowercase)) : 0;
	}

	zend_string_release(lowercase);
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
 * for any class. A row names a PHP# class by its exact name, spec section 24, and a plain class in any case. */
static zval *sharp_container_row(zval *list, const zend_class_entry *requester)
{
	zval *row, *any = NULL;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(list), row) {
		zval *target = zend_hash_index_find(Z_ARRVAL_P(row), 2);
		if (Z_TYPE_P(target) == IS_NULL) {
			any = any ? any : row;
		} else if (requester && ((requester->sharp_flags & SHARP_CLASS_EXACT_KEY)
				? zend_string_equals(Z_STR_P(target), requester->name)
				: zend_string_equals_ci(Z_STR_P(target), requester->name))) {
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

/* The namespace of the standard library's module, in lowercase, as mago compile writes the namespace of its rows. */
static const char sharp_library_namespace[] = "sharp";

/* The row of the modules of the namespace's first length bytes that serves id to requester, or NULL. While a test
 * runs, its module replaces the environment modules, as the checker checks a test module over the base modules
 * alone. */
static zval *sharp_container_namespace_row(
	const char *namespace, size_t length, const zend_string *id, const zend_class_entry *requester)
{
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

	return row;
}

/* The binding row that serves id to requester, a class of namespace or NULL, or NULL when no module binds it: of the
 * modules of namespace and of each namespace above it, the root one included, then of the standard library's module,
 * which serves every class after them, spec section 32. */
static zval *sharp_container_binding(const zend_string *id, const zend_string *namespace, const zend_class_entry *requester)
{
	sharp_container_read_library();
	if (EG(exception)) {
		return NULL;
	}

	for (size_t length = ZSTR_LEN(namespace);;) {
		zval *row = sharp_container_namespace_row(ZSTR_VAL(namespace), length, id, requester);
		if (row) {
			return row;
		}
		if (length == 0) {
			break;
		}
		const char *end = zend_memrchr(ZSTR_VAL(namespace), '\\', length);
		length = end ? (size_t) (end - ZSTR_VAL(namespace)) : 0;
	}

	return sharp_container_namespace_row(sharp_library_namespace, sizeof(sharp_library_namespace) - 1, id, requester);
}

static bool sharp_container_service(zend_string *id, const zend_string *written, const zend_string *namespace, const zend_class_entry *requester, zval *service);

/* Fills arguments with the service of each parameter of function: the id its Sharp\Internal\Service attribute
 * names, as mago compile writes it, or its class's. A parameter with neither ends the arguments when it is optional.
 * On failure arguments is undefined. */
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
		const zend_string *written;
		if (attribute && zend_get_attribute_value(&named, attribute, 0, function->common.scope) == SUCCESS) {
			id = Z_STR(named);
			written = id;
		} else if (ZEND_TYPE_HAS_NAME(argument->type)) {
			written = ZEND_TYPE_NAME(argument->type);
			zend_lookup_class((zend_string *) written);
			id = sharp_container_id(written);
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
		bool built = sharp_container_service(id, written, namespace, requester, &service);
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
	HashTable *building = &sharp_running_fiber_scopes()->building;
	if (!zend_hash_index_add_empty_element(building, (zend_ulong) (uintptr_t) ce)) {
		zend_throw_error(sharp_ce_container_error, "%s needs itself, so the container can't build it", ZSTR_VAL(ce->name));
		return false;
	}
	zend_string *namespace = sharp_container_namespace(ce->name);
	if (ce->type == ZEND_USER_CLASS) {
		sharp_container_read_modules(ce->info.user.filename, namespace);
	}

	zval arguments;
	ZVAL_UNDEF(&arguments);
	bool built = true;
	if (ce->constructor) {
		built = sharp_container_arguments(ce->constructor, namespace, ce, &arguments);
	}
	zend_string_release(namespace);
	if (built && object_init_to_build(object, ce) == SUCCESS) {
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
	zend_hash_index_del(building, (zend_ulong) (uintptr_t) ce);

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
 * resolve from the row's namespace. A singleton is kept for as long as the app runs, and a scoped service for the
 * scope the running fiber runs in. */
static bool sharp_container_bound(zval *row, zval *service)
{
	zend_string *namespace = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 0));
	zend_string *lifetime = Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 3));
	zval *value = zend_hash_index_find(Z_ARRVAL_P(row), 4);
	zend_ulong key = (zend_ulong) (uintptr_t) Z_ARRVAL_P(row);
	/* A class the row binds is named as it is written, and any other service by its id. */
	const char *name = Z_STRVAL_P(Z_TYPE_P(value) == IS_STRING ? value : zend_hash_index_find(Z_ARRVAL_P(row), 1));
	HashTable *kept = NULL;
	sharp_scope *scope = NULL;
	if (zend_string_equals_literal(lifetime, "singleton")) {
		kept = &SHARP_G(container_singletons);
	} else if (zend_string_equals_literal(lifetime, "scoped")) {
		scope = sharp_running_fiber_scopes()->current;
		if (scope->ended) {
			zend_throw_error(sharp_ce_container_error,
				"The scope this code runs in has ended, so the container can't build the scoped %s", name);
			return false;
		}
		kept = &scope->services;
	}
	zval *found = kept ? zend_hash_index_find(kept, key) : NULL;
	if (found) {
		ZVAL_COPY(service, found);
		return true;
	}

	HashTable *building = &sharp_running_fiber_scopes()->building;
	if (!zend_hash_index_add_empty_element(building, key)) {
		zend_throw_error(sharp_ce_container_error, "%s needs itself, so the container can't build it", name);
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

	zend_hash_index_del(building, key);
	/* A fiber suspended while it built the service, and the scope it started in ended meanwhile, keeps nothing. Two
	 * fibers that built one service at once each get the one kept first. */
	if (built && kept && !(scope && scope->ended)) {
		zval *first = zend_hash_index_find(kept, key);
		if (first) {
			zval_ptr_dtor(service);
			ZVAL_COPY(service, first);
		} else {
			Z_TRY_ADDREF_P(service);
			zend_hash_index_add_new(kept, key, service);
		}
	}

	return built;
}

/* The service id names for requester, a class of namespace or NULL. A failure names the service as written. */
static bool sharp_container_service(zend_string *id, const zend_string *written, const zend_string *namespace, const zend_class_entry *requester, zval *service)
{
	zval *row = sharp_container_binding(id, namespace, requester);
	if (row) {
		return sharp_container_bound(row, service);
	}

	if (!EG(exception)) {
		zend_throw_error(sharp_ce_container_error, "No module binds %s%s%s", ZSTR_VAL(written), requester ? " for " : "",
			requester ? ZSTR_VAL(requester->name) : "");
	}

	return false;
}

/* The service named name as written, without a leading `\`, its id and its namespace, after reading the modules that
 * serve its class's namespace. */
static zend_string *sharp_container_requested(zend_string *name, zend_string **id, zend_string **namespace)
{
	zend_string *written = ZSTR_LEN(name) > 0 && ZSTR_VAL(name)[0] == '\\'
		? zend_string_init(ZSTR_VAL(name) + 1, ZSTR_LEN(name) - 1, 0)
		: zend_string_copy(name);
	zend_class_entry *ce = memchr(ZSTR_VAL(written), '<', ZSTR_LEN(written)) ? NULL : zend_lookup_class(written);
	*namespace = sharp_container_namespace(written);
	if (ce && ce->type == ZEND_USER_CLASS) {
		sharp_container_read_modules(ce->info.user.filename, *namespace);
	}
	*id = sharp_container_id(written);

	return written;
}

ZEND_METHOD(Sharp_Container, get)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *id, *namespace;
	zend_string *written = sharp_container_requested(name, &id, &namespace);
	sharp_container_service(id, written, namespace, NULL, return_value);
	zend_string_release(namespace);
	zend_string_release(id);
	zend_string_release(written);
}

ZEND_METHOD(Sharp_Container, has)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *id, *namespace;
	zend_string *written = sharp_container_requested(name, &id, &namespace);
	bool has = sharp_container_binding(id, namespace, NULL) != NULL;
	zend_string_release(namespace);
	zend_string_release(id);
	zend_string_release(written);

	RETURN_BOOL(has);
}

/* Spec section 32: Scope.run runs body in a new scope of the running fiber, and ends the scope when body returns or
 * throws. A fatal error leaves it to the request's end. */
ZEND_FUNCTION(Sharp_Internal_runScoped)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_FUNC(fci, fcc)
	ZEND_PARSE_PARAMETERS_END();

	sharp_fiber_scopes *running = sharp_running_fiber_scopes();
	sharp_scope *outer = running->current;
	sharp_scope *scope = sharp_scope_start();
	running->current = scope;

	fci.retval = return_value;
	zend_try {
		zend_call_function(&fci, &fcc);
	} zend_catch {
		running->current = outer;
		zend_bailout();
	} zend_end_try();

	running->current = outer;
	sharp_scope_end(scope);
	sharp_scope_release(scope);
}

/* Spec section 32: the standard library's autoloader names the library's module, whose file is at path. The container
 * reads it at its first module lookup, before any other module, so autoload.php loads before anything is compiled. */
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
	if (SHARP_G(container_library)) {
		zend_string_release(SHARP_G(container_library));
	}
	SHARP_G(container_library) = zend_string_copy(path);
}

/* Spec section 32: a test file's module, the rows bindings holds, applies while a test of the class named class runs.
 * A file declares one type, section 27, so the module is that one class's. The compiler calls this from the test
 * file. */
ZEND_FUNCTION(Sharp_Internal_testModule)
{
	zend_string *class;
	HashTable *bindings;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(class)
		Z_PARAM_ARRAY_HT(bindings)
	ZEND_PARSE_PARAMETERS_END();

	zval module;
	array_init(&module);
	sharp_container_group(bindings, Z_ARRVAL(module));
	zend_hash_update(&SHARP_G(container_tests), class, &module);
}

/* Spec section 6.5: a `static lazy` property computes its value on its first read and keeps it for a singleton's
 * lifetime, section 32, as the container keeps a singleton service, so a test's start and end forget it too. PHP#
 * reads `Class.name` as `Class::{'name#get'}()`, which returns `\Sharp\Internal\keep('Class::name', static fn () =>
 * body)`. The value is kept under the key `key` beside the singleton services. A throw in `compute` keeps nothing, so
 * the next read runs it again. Without `compute` it gives the kept value or null, so a read of a value kept by now
 * makes no closure: a `Map` or `Set` constant's read is `\Sharp\Internal\keep($key) ?? \Sharp\Internal\keep($key, …)`. */
ZEND_FUNCTION(Sharp_Internal_keep)
{
	zend_string *key;
	zval *compute = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STR(key)
		Z_PARAM_OPTIONAL
		Z_PARAM_OBJECT_OF_CLASS_OR_NULL(compute, zend_ce_closure)
	ZEND_PARSE_PARAMETERS_END();

	zval *kept = zend_hash_find(&SHARP_G(container_singletons), key);
	if (kept) {
		RETURN_COPY(kept);
	}
	if (!compute) {
		RETURN_NULL();
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
	zend_hash_clean(&SHARP_G(container_main_scopes).started_in->services);
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
	zval *module = zend_hash_find(&SHARP_G(container_tests), class);
	SHARP_G(container_test) = module ? Z_ARRVAL_P(module) : NULL;
}

/* Spec section 32: the test that runs ends, so the container forgets its module and the services it built. */
ZEND_FUNCTION(Sharp_Internal_endTest)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_container_forget();
	SHARP_G(container_test) = NULL;
}

/* Spec section 27: whether the .sharp file whose code calls the running function, execute_data's, runs as PHP's
 * script, the CLI's script, a server's script or auto_prepend_file, which PHP runs from no other code. include and an
 * autoloader run a file from the code that loads it, so it only declares its class. */
static bool sharp_runs_as_program(const zend_execute_data *execute_data)
{
	const zend_execute_data *file = EX(prev_execute_data);

	return file && !file->prev_execute_data;
}

/* Spec section 27: a .sharp file whose class declares main, run as PHP's script, first loads its project's Composer
 * autoloader through the .sharp/autoload.php mago compile writes, so the parent and the interfaces the class names
 * load as PHP declares it, and the container finds the classes the constructor takes. It runs in a symbol table of its
 * own, as a module does, since the program's own code holds no variable. */
ZEND_FUNCTION(Sharp_Internal_startProgram)
{
	ZEND_PARSE_PARAMETERS_NONE();

	if (!sharp_runs_as_program(execute_data)) {
		return;
	}
	zend_string *file = EX(prev_execute_data)->func->op_array.filename;
	size_t root_length = 0;
	bool rooted = sharp_find_root(file, &root_length);
	zend_string *autoload = rooted
		? zend_string_concat3(ZSTR_VAL(file), root_length, "/", 1, SHARP_AUTOLOAD, sizeof(SHARP_AUTOLOAD) - 1)
		: NULL;
	zend_stat_t status = {0};
	if (!autoload || VCWD_STAT(ZSTR_VAL(autoload), &status) != 0) {
		zend_throw_exception_ex(zend_ce_compile_error, 0, "%s runs as a program, and " SHARP_AUTOLOAD
			", which loads the project's Composer autoloader, is missing. Run vendor/bin/mago compile.",
			ZSTR_VAL(file) + (rooted ? root_length + 1 : 0));
		if (autoload) {
			zend_string_release(autoload);
		}
		RETURN_THROWS();
	}

	zend_op_array *op_array = compile_filename(ZEND_REQUIRE, autoload);
	zend_string_release(autoload);
	if (!op_array) {
		RETURN_THROWS();
	}
	HashTable symbols;
	zend_hash_init(&symbols, 8, NULL, ZVAL_PTR_DTOR, false);
	zval returned;
	ZVAL_UNDEF(&returned);
	sharp_execute_file(op_array, &symbols, &returned);
	zval_ptr_dtor(&returned);
	zend_hash_destroy(&symbols);
	zend_destroy_static_vars(op_array);
	destroy_op_array(op_array);
	efree_size(op_array, sizeof(zend_op_array));
}

/* Spec section 27: a .sharp file whose class, the one name names, declares main, run as PHP's script, builds the
 * class through the container, as it builds a listener's, and calls main. An int main returns ends the run with that
 * exit code, as PHP's exit does, and a void main returns to PHP, as a front controller or auto_prepend_file does. */
ZEND_FUNCTION(Sharp_Internal_runProgram)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_runs_as_program(execute_data)) {
		return;
	}
	zval program, returned;
	if (!sharp_container_build_named(name, &program)) {
		RETURN_THROWS();
	}
	ZVAL_UNDEF(&returned);
	zend_call_method_with_0_params(Z_OBJ(program), Z_OBJCE(program), NULL, "main", &returned);
	zval_ptr_dtor(&program);
	if (EG(exception)) {
		zval_ptr_dtor(&returned);
		RETURN_THROWS();
	}
	if (Z_TYPE(returned) == IS_LONG) {
		EG(exit_status) = (int) Z_LVAL(returned);
		zend_throw_unwind_exit();
	}
	zval_ptr_dtor(&returned);
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

/* Whether lists, a part of a listener table, is [a name => [a string, ...], ...], where a name may also hold null when
 * nullable. */
static bool sharp_listener_lists_fit(const zval *lists, bool nullable)
{
	const zval *list, *element;
	zend_string *name;

	if (!lists || Z_TYPE_P(lists) != IS_ARRAY) {
		return false;
	}
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL_P(lists), name, list) {
		if (name && nullable && Z_TYPE_P(list) == IS_NULL) {
			continue;
		}
		if (!name || Z_TYPE_P(list) != IS_ARRAY) {
			return false;
		}
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(list), element) {
			if (Z_TYPE_P(element) != IS_STRING) {
				return false;
			}
		} ZEND_HASH_FOREACH_END();
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* Whether table is a table mago compile wrote: ['inputs' => [input, ...], 'listeners' => [row, ...], 'dispatcher' =>
 * null or the lowercase namespace of the app's modules that bind the dispatcher, 'packages' => [the workspace-relative
 * folder, ending in `/`, Composer installs each of the project's packages in, ...], 'attributes' => [the lowercase name
 * of a plain PHP class an `extern` line gives attributes => [the workspace-relative path of each .sharp file whose line
 * does, ...], ...], 'effects' => [the lowercase name of a plain PHP class or method an `extern` line declares => [the
 * dotted full name of each effect it declares, ...], or null for `uses any`, ...], 'tags' => [a family's full name =>
 * [the key its `[Tag]` names, or '' for a machine that stores as its value, [a variant's name => its class's full
 * name, ...]], ...], 'modules' => [a lowercase namespace, or one, a space and an environment's name => [the
 * workspace-relative path of each of its base or environment modules, ...], ...]]. A name of digits, as an
 * int-backed machine's case's, is PHP's integer key. */
static bool sharp_listener_table_fits(const zval *table)
{
	const zval *inputs = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "inputs", sizeof("inputs") - 1) : NULL;
	const zval *listeners = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "listeners", sizeof("listeners") - 1) : NULL;
	const zval *dispatcher = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "dispatcher", sizeof("dispatcher") - 1) : NULL;
	const zval *attributes = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "attributes", sizeof("attributes") - 1) : NULL;
	const zval *effects = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "effects", sizeof("effects") - 1) : NULL;
	const zval *tags = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "tags", sizeof("tags") - 1) : NULL;
	const zval *modules = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "modules", sizeof("modules") - 1) : NULL;
	const zval *packages = Z_TYPE_P(table) == IS_ARRAY ? zend_hash_str_find(Z_ARRVAL_P(table), "packages", sizeof("packages") - 1) : NULL;
	const zval *element, *file;
	zend_string *class;

	if (!inputs || Z_TYPE_P(inputs) != IS_ARRAY || !listeners || Z_TYPE_P(listeners) != IS_ARRAY || !dispatcher
			|| (Z_TYPE_P(dispatcher) != IS_NULL && Z_TYPE_P(dispatcher) != IS_STRING) || !packages || Z_TYPE_P(packages) != IS_ARRAY
			|| !sharp_listener_lists_fit(attributes, false) || !sharp_listener_lists_fit(effects, true) || !tags
			|| Z_TYPE_P(tags) != IS_ARRAY || !sharp_listener_lists_fit(modules, false)) {
		return false;
	}
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL_P(tags), class, element) {
		const zval *variants = sharp_element(element, 1, IS_ARRAY);
		if (!class || !sharp_element(element, 0, IS_STRING) || !variants) {
			return false;
		}
		ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(variants), file) {
			if (Z_TYPE_P(file) != IS_STRING) {
				return false;
			}
		} ZEND_HASH_FOREACH_END();
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(inputs), element) {
		if (!sharp_listener_input_fits(element)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(packages), element) {
		if (Z_TYPE_P(element) != IS_STRING) {
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
			sharp_copy_literal(table, RT_CONSTANT(opline, opline->op1));
		}
		destroy_op_array(op_array);
		efree_size(op_array, sizeof(zend_op_array));
	}
}

/* Whether the request runs the compile command for table, the listener table of the root, the first root_length bytes
 * of path: it compiles on demand, hasn't compiled yet, and the table it read is whole but its inputs changed since. */
static bool sharp_listener_table_stale(const char *path, size_t root_length, const zval *table)
{
	return !Z_ISUNDEF_P(table) && sharp_listener_table_fits(table) && !EG(exception) && sharp_compiles_on_demand()
		&& Z_ISUNDEF(SHARP_G(compile_command_report)) && !sharp_listener_inputs_current(path, root_length, table);
}

/* The listener table in the compiled folder of the root, the first root_length bytes of path, read once per request
 * through zend_compile_file, so opcache serves it. A missing table lists no listener and no dispatcher. With
 * sharp.compile_command set, the table is read past opcache, since this request or an earlier one may have rewritten
 * it, and outside a compile a table whose inputs changed since mago compile wrote it makes the request run the
 * command, as an out-of-date compiled file does, and the table it writes is read instead. Throws and returns NULL when
 * the table is damaged. */
static HashTable *sharp_listener_table(const char *path, size_t root_length)
{
	zend_string *folder = sharp_compiled_folder(path, strlen(path), root_length);
	zend_string *table_path = zend_string_concat2(ZSTR_VAL(folder), ZSTR_LEN(folder), "/" SHARP_LISTENERS, sizeof("/" SHARP_LISTENERS) - 1);
	zval *known = zend_hash_find(&SHARP_G(listener_tables), table_path);

	zend_string_release(folder);
	bool developing = SHARP_G(compile_command) && *SHARP_G(compile_command);
	zval table;

	if (known) {
		zend_string_release(table_path);
		return Z_ARRVAL_P(known);
	}
	sharp_read_listener_table(table_path, developing, &table);
	/* A compile another request ran while this one waited for the lock may have written the table. */
	if (sharp_listener_table_stale(path, root_length, &table) && sharp_lock_compiles(path, root_length)) {
		zval_ptr_dtor(&table);
		sharp_read_listener_table(table_path, true, &table);
		if (sharp_listener_table_stale(path, root_length, &table)) {
			zval_ptr_dtor(&table);
			sharp_run_compile_command(path, root_length);
			sharp_read_listener_table(table_path, true, &table);
		}
		sharp_unlock_compiles();
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

/* Frees a table of attributes sharp_class_attributes joined, which owns none of the attributes it points at. */
static void sharp_free_attribute_table(zval *entry)
{
	HashTable *attributes = Z_PTR_P(entry);

	if (attributes) {
		zend_hash_destroy(attributes);
		FREE_HASHTABLE(attributes);
	}
}

/* The name the listener table gives the declaration an `extern` line names, by which the line is found: a class's full
 * name, `Class::method` for a method, and `function()` for a function, each in lowercase, as PHP looks it up. target
 * is the line's ZEND_ATTRIBUTE_TARGET_CLASS, _METHOD or _FUNCTION. */
static zend_string *sharp_extern_key(zend_string *name, uint32_t target)
{
	zend_string *lowercase = zend_string_tolower(name);
	zend_string *key;

	if (target != ZEND_ATTRIBUTE_TARGET_FUNCTION) {
		return lowercase;
	}
	key = zend_string_concat2(ZSTR_VAL(lowercase), ZSTR_LEN(lowercase), "()", 2);
	zend_string_release(lowercase);

	return key;
}

/* The method whose `extern` lines declare the method `func`: the trait's, under the name the trait gives it, for a
 * method a class takes from a trait, else `func` itself. */
static const zend_function *sharp_extern_method(const zend_function *func)
{
	for (const zend_function *original; (original = sharp_trait_method(func->common.scope, func));) {
		func = original;
	}

	return func;
}

/* The `extern` line of the plain PHP method `func`, spec section 29, as the listener table of the root above `file`
 * lists it: the method's own line, else the line of the class or trait that declares it, as the checker reads them.
 * Nothing passes from a parent class. The entry is the list of the line's effects, each a dotted full name, or null
 * for a line that says `uses any`. NULL when no line declares the method, and after an exception reading the table
 * threw. */
static zval *sharp_method_extern_line(const zend_function *func, const zend_string *file)
{
	size_t root_length;
	if (!file || !func->common.scope || !sharp_find_root(file, &root_length)) {
		return NULL;
	}
	func = sharp_extern_method(func);
	HashTable *table = sharp_listener_table(ZSTR_VAL(file), root_length);
	/* A missing table, which sharp_listener_table gives as one without effects, declares none. */
	zval *effects = table ? zend_hash_str_find(table, "effects", sizeof("effects") - 1) : NULL;
	if (!effects) {
		return NULL;
	}
	zend_string *class = zend_string_tolower(func->common.scope->name);
	zend_string *member = zend_string_tolower(func->common.function_name);
	zend_string *key = zend_string_concat3(ZSTR_VAL(class), ZSTR_LEN(class), "::", 2, ZSTR_VAL(member), ZSTR_LEN(member));
	zval *declared = zend_hash_find(Z_ARRVAL_P(effects), key);
	declared = declared ? declared : zend_hash_find(Z_ARRVAL_P(effects), class);
	zend_string_release(key);
	zend_string_release(member);
	zend_string_release(class);

	return declared;
}

/* The effects the `extern` line of the plain PHP method `func` gives it, as sharp_method_extern_line finds the line. NULL when
 * no line or a line that says `uses any` declares them, so the method may have every effect, as the checker holds a
 * call of it, and after an exception reading the table threw. */
static HashTable *sharp_extern_effects(const zend_function *func, const zend_string *file)
{
	zval *declared = sharp_method_extern_line(func, file);

	return declared && Z_TYPE_P(declared) == IS_ARRAY ? Z_ARRVAL_P(declared) : NULL;
}

/* Spec section 29: what the calls of one plain PHP method an `extern` line declares ran this request, while
 * sharp.effect_report is set: how many calls, the times they ran each built-in, by its name, as `PDO::query` or
 * `strlen`, and the folder Composer installed the method's package in, a read of a file inside which records
 * nothing, or NULL for a method outside `vendor/`. */
typedef struct sharp_effect_run {
	zend_long calls;
	HashTable ran;
	zend_string *folder;
} sharp_effect_run;

/* The report file sharp.effect_report names, from the folder PHP started in, or NULL when it is not set. */
static char *sharp_effect_report;

static void sharp_effect_run_free(zval *entry)
{
	sharp_effect_run *run = Z_PTR_P(entry);

	zend_hash_destroy(&run->ran);
	if (run->folder) {
		zend_string_release(run->folder);
	}
	efree(run);
}

/* The folder Composer installed the package of the file `file` in, as `/app/vendor/acme/lib/`: the longest of the
 * `packages` the listener table of the root above it lists that holds it. NULL for a file of no package, and after an
 * exception reading the table threw. */
static zend_string *sharp_package_folder(const zend_string *file)
{
	size_t root_length;
	if (!sharp_find_root(file, &root_length)) {
		return NULL;
	}
	HashTable *table = sharp_listener_table(ZSTR_VAL(file), root_length);
	zval *packages = table ? zend_hash_str_find(table, "packages", sizeof("packages") - 1) : NULL;
	if (!packages) {
		return NULL;
	}

	size_t length = 0;
	zval *folder;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(packages), folder) {
		size_t holds = root_length + 1 + Z_STRLEN_P(folder);
		if (holds > length && ZSTR_LEN(file) > holds && ZSTR_VAL(file)[root_length] == '/'
				&& memcmp(ZSTR_VAL(file) + root_length + 1, Z_STRVAL_P(folder), Z_STRLEN_P(folder)) == 0) {
			length = holds;
		}
	} ZEND_HASH_FOREACH_END();

	return length ? zend_string_init(ZSTR_VAL(file), length, false) : NULL;
}

/* A call of a plain PHP method an `extern` line declares: it counts, and the built-ins it runs count against it. */
static void sharp_effect_declared_call(zend_execute_data *execute_data)
{
	const zend_function *func = sharp_extern_method(execute_data->func);
	zend_string *name = zend_create_member_string(func->common.scope->name, func->common.function_name);
	sharp_effect_run *run = zend_hash_find_ptr(&SHARP_G(effect_runs), name);

	if (!run) {
		run = emalloc(sizeof(sharp_effect_run));
		run->calls = 0;
		zend_hash_init(&run->ran, 8, NULL, NULL, false);
		run->folder = sharp_package_folder(execute_data->func->op_array.filename);
		zend_hash_add_new_ptr(&SHARP_G(effect_runs), name, run);
	}
	zend_string_release(name);
	run->calls++;
	zend_stack_push(&sharp_running_fiber_scopes()->effect_calls, &run);
}

/* A call of PHP# code, as a callback a declared method calls: the built-ins it runs count against nothing. */
static void sharp_effect_sharp_call(zend_execute_data *execute_data)
{
	sharp_effect_run *none = NULL;

	zend_stack_push(&sharp_running_fiber_scopes()->effect_calls, &none);
}

static void sharp_effect_returned(zend_execute_data *execute_data, zval *retval)
{
	zend_stack_del_top(&sharp_running_fiber_scopes()->effect_calls);
}

/* A call of a built-in, which counts against the innermost declared call that runs it, past no PHP# code, unless an
 * autoload runs it or it names a file inside the declared method's own package by its first argument. */
static void sharp_effect_built_in_call(zend_execute_data *execute_data)
{
	zend_stack *calls = &sharp_running_fiber_scopes()->effect_calls;
	sharp_effect_run *run = zend_stack_is_empty(calls) ? NULL : *(sharp_effect_run **) zend_stack_top(calls);

	if (!run || (EG(in_autoload) && zend_hash_num_elements(EG(in_autoload)) > 0)) {
		return;
	}
	if (run->folder && ZEND_CALL_NUM_ARGS(execute_data) > 0) {
		zval *path = ZEND_CALL_ARG(execute_data, 1);
		if (Z_TYPE_P(path) == IS_STRING && zend_string_starts_with(Z_STR_P(path), run->folder)) {
			return;
		}
	}
	const zend_function *func = execute_data->func;
	zend_string *name = func->common.scope
		? zend_create_member_string(func->common.scope->name, func->common.function_name)
		: zend_string_copy(func->common.function_name);
	zval *times = zend_hash_find(&run->ran, name);

	if (times) {
		Z_LVAL_P(times)++;
	} else {
		zval once;
		ZVAL_LONG(&once, 1);
		zend_hash_add_new(&run->ran, name, &once);
	}
	zend_string_release(name);
}

/* The observer sharp.effect_report registers: of each function's first call in the request, a built-in's, a PHP#
 * function's, and a plain PHP method's an `extern` line declares. Every other function runs unobserved. */
static zend_observer_fcall_handlers sharp_effect_observer(zend_execute_data *execute_data)
{
	zend_function *func = execute_data->func;

	if (func->type == ZEND_INTERNAL_FUNCTION) {
		return (zend_observer_fcall_handlers){sharp_effect_built_in_call, NULL};
	}
	if (sharp_function_is_sharp(func)) {
		return (zend_observer_fcall_handlers){sharp_effect_sharp_call, sharp_effect_returned};
	}
	if (func->common.scope && sharp_method_extern_line(func, func->op_array.filename)) {
		return (zend_observer_fcall_handlers){sharp_effect_declared_call, sharp_effect_returned};
	}

	return (zend_observer_fcall_handlers){NULL, NULL};
}

/* Appends what the request's declared calls ran to the report, as one line of JSON that `mago effects` reads: each
 * method, by its name, as {"calls": 3, "ran": {"PDO::query": 2}}. The lock keeps the lines of requests that end
 * together whole. */
static void sharp_write_effect_report(void)
{
	if (zend_hash_num_elements(&SHARP_G(effect_runs)) == 0) {
		return;
	}

	zval report;
	zend_string *name;
	sharp_effect_run *run;
	array_init(&report);
	ZEND_HASH_FOREACH_STR_KEY_PTR(&SHARP_G(effect_runs), name, run) {
		zval entry, ran;
		array_init(&entry);
		add_assoc_long(&entry, "calls", run->calls);
		ZVAL_ARR(&ran, zend_array_dup(&run->ran));
		add_assoc_zval(&entry, "ran", &ran);
		zend_hash_add_new(Z_ARRVAL(report), name, &entry);
	} ZEND_HASH_FOREACH_END();

	smart_str line = {0};
	php_json_encode(&line, &report, PHP_JSON_FORCE_OBJECT | PHP_JSON_UNESCAPED_SLASHES | PHP_JSON_UNESCAPED_UNICODE);
	smart_str_appendc(&line, '\n');
	zval_ptr_dtor(&report);

	int file = open(sharp_effect_report, O_WRONLY | O_APPEND | O_CREAT | O_CLOEXEC, 0644);
	if (file < 0) {
		php_error_docref(NULL, E_WARNING, "sharp.effect_report can't open %s: %s", sharp_effect_report, strerror(errno));
	} else {
		flock(file, LOCK_EX);
		for (size_t written = 0; written < ZSTR_LEN(line.s);) {
			ssize_t wrote = write(file, ZSTR_VAL(line.s) + written, ZSTR_LEN(line.s) - written);
			if (wrote < 0 && errno == EINTR) {
				continue;
			}
			if (wrote <= 0) {
				php_error_docref(NULL, E_WARNING, "sharp.effect_report can't write %s: %s", sharp_effect_report, strerror(errno));
				break;
			}
			written += wrote;
		}
		close(file);
	}
	smart_str_free(&line);
}

/* The name of the method of an anonymous class that holds a method's or a function's `extern` attributes. */
#define SHARP_EXTERN_HOLDER "holder"

/* The parser of a .sharp file whose `extern` lines give the declaration SHARP_G(extern_key) names attributes, spec
 * section 29. In place of the file's code it gives, for each such line, `new class {}` that holds the line's
 * attributes: on the class for a class's line, and on its one method, SHARP_EXTERN_HOLDER, for a method's or a
 * function's. PHP compiles them as it compiles a declaration's attributes, and the file's own classes stay undeclared. */
static int sharp_parse_extern_attributes(void)
{
	const char *filename = ZSTR_VAL(zend_get_compiled_filename());
	zend_ast *statements = zend_ast_create_list(0, ZEND_AST_STMT_LIST);
	sharp_compiled compiled;
	bool failed = false;

	sharp_locate(&compiled, filename);
	if (sharp_check(&compiled, NULL, 0) != SHARP_CURRENT) {
		sharp_release(&compiled);
		return FAILURE;
	}

	zend_try {
		const sharp_node *root = &compiled.unit.nodes[compiled.unit.root];

		for (uint32_t i = 0; i < root->child_count; i++) {
			uint32_t child = compiled.unit.children[root->first_child + i];
			zend_ast *line, *holder, *members;
			zend_string *key;
			bool named;

			if (compiled.unit.nodes[child].kind != SHARP_AST_SHARP_EXTERN) {
				continue;
			}
			line = sharp_translate(&compiled.unit, child);
			key = sharp_extern_key(zend_ast_get_str(line->child[0]), line->attr);
			named = zend_string_equals(key, SHARP_G(extern_key));
			zend_string_release(key);
			if (!named) {
				zend_ast_destroy(line);
				continue;
			}
			members = zend_ast_create_list(0, ZEND_AST_STMT_LIST);
			if (line->attr != ZEND_ATTRIBUTE_TARGET_CLASS) {
				members = zend_ast_list_add(members, zend_ast_create_decl(ZEND_AST_METHOD, ZEND_ACC_PUBLIC,
					zend_ast_get_lineno(line), NULL, ZSTR_INIT_LITERAL(SHARP_EXTERN_HOLDER, 0),
					zend_ast_create_list(0, ZEND_AST_PARAM_LIST), NULL, zend_ast_create_list(0, ZEND_AST_STMT_LIST),
					NULL, line->child[1]));
			}
			holder = zend_ast_create_decl(ZEND_AST_CLASS, ZEND_ACC_ANON_CLASS, zend_ast_get_lineno(line), NULL, NULL,
				NULL, NULL, members, line->attr == ZEND_ATTRIBUTE_TARGET_CLASS ? line->child[1] : NULL, NULL);
			zend_ast_destroy(line->child[0]);
			statements = zend_ast_list_add(statements, zend_ast_create(ZEND_AST_NEW, holder, zend_ast_create_list(0, ZEND_AST_ARG_LIST)));
		}
	} zend_catch {
		failed = true;
	} zend_end_try();

	sharp_release(&compiled);
	if (failed) {
		zend_bailout();
	}
	CG(ast) = statements;

	return SUCCESS;
}

/* Appends to attributes the attributes the `extern` lines of the .sharp file source give the declaration key names,
 * compiled as the anonymous classes of sharp_parse_extern_attributes, which the request's class table keeps. Throws and
 * returns false when the file is not compiled. */
static bool sharp_add_extern_attributes(HashTable *attributes, zend_string *key, zend_string *source)
{
	zend_string *previous = SHARP_G(extern_key);
	zend_file_handle handle;
	zend_op_array *op_array;

	SHARP_G(extern_key) = key;
	zend_stream_init_filename_ex(&handle, source);
	op_array = zend_compile_file_with(&handle, ZEND_INCLUDE, sharp_parse_extern_attributes);
	zend_destroy_file_handle(&handle);
	SHARP_G(extern_key) = previous;
	if (!op_array) {
		if (!EG(exception)) {
			zend_throw_exception_ex(zend_ce_compile_error, 0,
				"%s gives %s attributes and is not compiled. Run vendor/bin/mago compile.", ZSTR_VAL(source), ZSTR_VAL(key));
		}
		return false;
	}

	for (uint32_t i = 0; i < op_array->last; i++) {
		const zend_op *opline = &op_array->opcodes[i];
		zend_class_entry *holder;
		zend_function *method;
		HashTable *held;
		zend_attribute *attribute;

		if (opline->opcode != ZEND_DECLARE_ANON_CLASS) {
			continue;
		}
		holder = zend_hash_find_ptr(CG(class_table), Z_STR_P(RT_CONSTANT(opline, opline->op1)));
		if (!holder) {
			continue;
		}
		method = zend_hash_str_find_ptr(&holder->function_table, SHARP_EXTERN_HOLDER, sizeof(SHARP_EXTERN_HOLDER) - 1);
		held = method ? method->common.attributes : holder->attributes;
		if (held) {
			ZEND_HASH_PACKED_FOREACH_PTR(held, attribute) {
				zend_hash_next_index_insert_ptr(attributes, attribute);
			} ZEND_HASH_FOREACH_END();
		}
	}
	destroy_op_array(op_array);
	efree_size(op_array, sizeof(zend_op_array));

	return true;
}

/* The attributes reflection reads of the declaration key names, whose own are own: those and then the ones of each
 * `extern` line the listener table of the .sharp folder above file lists for key, kept for the request by key, since
 * a method's closure copies the method. A library's declaration in vendor/ finds its project's lines, as an extern
 * event finds its listeners. Throws and returns NULL when the table is damaged or a file that holds a line is not
 * compiled. */
static HashTable *sharp_extern_attributes(HashTable *own, zend_string *file, zend_string *key)
{
	zval *known = zend_hash_find(&SHARP_G(class_attributes), key);
	zval entry, *files, *listed;
	HashTable *table, *attributes;
	zend_attribute *attribute;
	size_t root_length;

	if (known) {
		return Z_PTR_P(known) ? Z_PTR_P(known) : own;
	}
	if (!sharp_find_root(file, &root_length)) {
		return own;
	}
	table = sharp_listener_table(ZSTR_VAL(file), root_length);
	if (!table) {
		return NULL;
	}
	files = zend_hash_str_find(table, "attributes", sizeof("attributes") - 1);
	files = files ? zend_hash_find(Z_ARRVAL_P(files), key) : NULL;
	ZVAL_PTR(&entry, NULL);
	if (!files) {
		zend_hash_add_new(&SHARP_G(class_attributes), key, &entry);
		return own;
	}

	ALLOC_HASHTABLE(attributes);
	zend_hash_init(attributes, 8, NULL, NULL, false);
	zend_hash_real_init_packed(attributes);
	if (own) {
		ZEND_HASH_PACKED_FOREACH_PTR(own, attribute) {
			zend_hash_next_index_insert_ptr(attributes, attribute);
		} ZEND_HASH_FOREACH_END();
	}
	ZVAL_PTR(&entry, attributes);
	zend_hash_add_new(&SHARP_G(class_attributes), key, &entry);
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(files), listed) {
		zend_string *source = zend_string_concat3(ZSTR_VAL(file), root_length, "/", 1, Z_STRVAL_P(listed), Z_STRLEN_P(listed));
		bool added = sharp_add_extern_attributes(attributes, key, source);

		zend_string_release(source);
		if (!added) {
			zend_hash_del(&SHARP_G(class_attributes), key);
			return NULL;
		}
	} ZEND_HASH_FOREACH_END();

	return attributes;
}

HashTable *sharp_class_attributes(zend_class_entry *ce)
{
	zend_string *key;
	HashTable *attributes;

	if (ce->type != ZEND_USER_CLASS) {
		return ce->attributes;
	}
	key = sharp_extern_key(ce->name, ZEND_ATTRIBUTE_TARGET_CLASS);
	attributes = sharp_extern_attributes(ce->attributes, ce->info.user.filename, key);
	zend_string_release(key);

	return attributes;
}

HashTable *sharp_function_attributes(zend_function *function)
{
	zend_string *key, *name;
	HashTable *attributes;

	/* A closure is no declaration an `extern` line names, and a method's closure, as Closure::fromCallable gives, is. */
	if (function->type != ZEND_USER_FUNCTION
			|| (function->common.fn_flags & (ZEND_ACC_CLOSURE | ZEND_ACC_FAKE_CLOSURE)) == ZEND_ACC_CLOSURE) {
		return function->common.attributes;
	}
	if (function->common.scope) {
		name = zend_string_concat3(ZSTR_VAL(function->common.scope->name), ZSTR_LEN(function->common.scope->name),
			"::", 2, ZSTR_VAL(function->common.function_name), ZSTR_LEN(function->common.function_name));
		key = sharp_extern_key(name, ZEND_ATTRIBUTE_TARGET_METHOD);
	} else {
		name = zend_string_copy(function->common.function_name);
		key = sharp_extern_key(name, ZEND_ATTRIBUTE_TARGET_FUNCTION);
	}
	zend_string_release(name);
	attributes = sharp_extern_attributes(function->common.attributes, function->op_array.filename, key);
	zend_string_release(key);

	return attributes;
}

/* Whether a listener of the type type hears the event: an object of the type's class, or of a class that extends it or
 * implements it, with the type arguments the type writes, as `is` tests them, spec section 15. The type is a class's
 * name, a generic event with its type arguments in the type text the listener table writes, as
 * `App.Saved<App.Order>`, or the class value Events.on holds. A class the event extends is loaded with it, so a type
 * that isn't loaded is none of them. */
static bool sharp_hears(zend_object *event, zend_string *type)
{
	const sharp_type *generic = sharp_class_value_type(type);

	if (!generic && memchr(ZSTR_VAL(type), '<', ZSTR_LEN(type))) {
		const sharp_type *list = sharp_type_list(ZSTR_VAL(type), ZSTR_LEN(type));
		if (!list) {
			return false;
		}
		generic = list->members[0];
	}
	if (generic) {
		return sharp_object_is(event, generic);
	}
	zend_class_entry *listed = zend_lookup_class_ex(type, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);

	return listed && instanceof_function(event->ce, listed);
}

/* The listeners of an event, in the table's order: each row that lists a type the event is. The table is the one in the
 * .sharp folder above the file that declares the event's class, so an extern event in vendor/ finds its project's
 * table. The rows are kept for the request under the event's class, and for an event of a generic class under its type
 * arguments too, since the table and every class it names are fixed for it. Throws and returns NULL when the table is
 * damaged. */
static HashTable *sharp_listeners_of(zend_object *event)
{
	zend_class_entry *ce = event->ce;
	zend_ulong key = (zend_ulong) (uintptr_t) ce;
	zval *known = zend_hash_index_find(&SHARP_G(event_listeners), key);
	zend_string *arguments = NULL;
	zval listeners, *row, *type;
	HashTable *table;
	size_t root_length;

	/* An event of a generic class keeps its rows under its type arguments' text, and one plain PHP created without
	 * them under "". A lazy proxy's initializer runs here, and can throw. */
	if (ce->ce_flags & ZEND_ACC_SHARP_GENERIC) {
		const sharp_type *own = sharp_type_arguments(event);
		if (UNEXPECTED(EG(exception))) {
			return NULL;
		}
		arguments = own ? own->text : ZSTR_EMPTY_ALLOC();
		if (!known) {
			zval by_arguments;
			array_init(&by_arguments);
			known = zend_hash_index_add_new(&SHARP_G(event_listeners), key, &by_arguments);
		}
		zval *rows = zend_hash_find(Z_ARRVAL_P(known), arguments);
		if (rows) {
			return Z_ARRVAL_P(rows);
		}
	} else if (known) {
		return Z_ARRVAL_P(known);
	}
	array_init(&listeners);
	if (ce->type == ZEND_USER_CLASS && sharp_find_root(ce->info.user.filename, &root_length)) {
		table = sharp_listener_table(ZSTR_VAL(ce->info.user.filename), root_length);
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
	if (arguments) {
		return Z_ARRVAL_P(zend_hash_add_new(Z_ARRVAL_P(known), arguments, &listeners));
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
 * function runs nothing. Throws and returns false when the table is damaged or the request runs out of memory. */
static bool sharp_listeners_for(zval *event, zval *listeners)
{
	HashTable *rows = sharp_listeners_of(Z_OBJ_P(event));
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
		if (sharp_out_of_memory()) {
			zval_ptr_dtor(listeners);
			ZVAL_NULL(listeners);
			return false;
		}
		if (sharp_hears(Z_OBJ_P(event), listening->type)) {
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

/* Spec section 10: the variants of the family `family`, each one's class name under its name, as mago compile writes
 * them into the listener table, with the key the family's `[Tag]` names in `key`. NULL for a class that is no family
 * the table lists, or after an exception reading the table threw. */
static HashTable *sharp_family_variants(const zend_class_entry *family, zend_string **key)
{
	size_t root_length;

	if (family->type != ZEND_USER_CLASS || !sharp_find_root(family->info.user.filename, &root_length)) {
		return NULL;
	}
	HashTable *table = sharp_listener_table(ZSTR_VAL(family->info.user.filename), root_length);
	if (!table) {
		return NULL;
	}
	zval *row = zend_hash_find(Z_ARRVAL_P(zend_hash_str_find(table, "tags", sizeof("tags") - 1)), family->name);
	if (!row) {
		return NULL;
	}
	*key = Z_STR_P(sharp_element(row, 0, IS_STRING));

	return Z_ARRVAL_P(sharp_element(row, 1, IS_ARRAY));
}

/* Throws that the listener table lists no variants of the family `family`, which a compile before its `[Tag]` wrote. */
static ZEND_COLD void sharp_family_out_of_date(const zend_string *family)
{
	zend_throw_exception_ex(zend_ce_compile_error, 0,
		SHARP_LISTENER_TABLE " is out of date: it lists no variants of the family %s. Run vendor/bin/mago compile.",
		ZSTR_VAL(family));
}

/* Throws one Sharp\InvalidFields of `problems`, each problem under its path, as a struct's parse throws it. Where the
 * class doesn't load, as in a request whose autoloader doesn't reach the standard library, it throws a ValueError with
 * the message InvalidFields gives: each problem after its path, the parts joined by "; ". */
static ZEND_COLD void sharp_throw_invalid_fields(zval *problems)
{
	zend_string *name = zend_string_init_interned("Sharp\\InvalidFields", sizeof("Sharp\\InvalidFields") - 1, false);
	zend_class_entry *ce = zend_lookup_class(name);
	zval exception;

	zend_string_release(name);
	if (EG(exception)) {
		return;
	}
	if (ce) {
		if (object_init_with_constructor(&exception, ce, 1, problems, NULL) == SUCCESS) {
			zend_throw_exception_object(&exception);
		}
		return;
	}
	smart_str message = {0};
	zend_string *path;
	zend_ulong index;
	zval *problem;
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(problems), index, path, problem) {
		if (message.s) {
			smart_str_appendl(&message, "; ", 2);
		}
		if (!path) {
			smart_str_append_unsigned(&message, index);
			smart_str_appendl(&message, ": ", 2);
		} else if (ZSTR_LEN(path) > 0) {
			smart_str_append(&message, path);
			smart_str_appendl(&message, ": ", 2);
		}
		smart_str_append(&message, Z_STR_P(problem));
	} ZEND_HASH_FOREACH_END();
	smart_str_0(&message);
	zend_throw_exception(zend_ce_value_error, message.s ? ZSTR_VAL(message.s) : "", 0);
	smart_str_free(&message);
}

/* The value `value`, a struct's input, holds under `key`: an array's element, a Map's value or an object's public
 * property, as a struct's parse reads a field. NULL where it holds none. */
static zval *sharp_input_field(zval *value, zend_string *key, zval *read)
{
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) == IS_ARRAY) {
		return zend_symtable_find(Z_ARRVAL_P(value), key);
	}
	if (Z_TYPE_P(value) != IS_OBJECT) {
		return NULL;
	}
	if (Z_OBJCE_P(value) == sharp_ce_map) {
		zval lookup;
		ZVAL_STR(&lookup, key);
		sharp_table_bucket *bucket = sharp_table_find(sharp_table_of(Z_OBJ_P(value)), &lookup);

		return bucket ? &bucket->value : NULL;
	}
	if (!zend_hash_exists(zend_get_properties_for(value, ZEND_PROP_PURPOSE_ARRAY_CAST), key)) {
		return NULL;
	}

	return zend_read_property_ex(NULL, Z_OBJ_P(value), key, true, read);
}

/* Spec section 10: one read of a value into a type, the one place a value is read into a type, for a struct's or a
 * class's `parse`, a family's, `Json.decode` and `Query.decode`. It holds the problems it found, each under its path,
 * the paths of the structs that reported their own problems, where the walk's problem goes, and the problems a backed
 * struct's own `parse` gave, which take the walk's place at their path. With `text`, a number or a bool is read from
 * its text, as Query.decode reads it, at every depth. `order` names the classes and scalar types the declaration of
 * the field being read writes, in its order, `App.Card, App.Bank`, which a union tries in that order, or is NULL.
 * `holder` and `field` name the field of a class whose value is being read, which a Secret's encryption is bound to,
 * or are NULL. */
typedef struct {
	sharp_type_problems found;
	zval failed;
	zval texts;
	bool text;
	const zend_string *order;
	const zend_class_entry *holder;
	const zend_string *field;
} sharp_read;

static void sharp_read_value(const sharp_type *type, zval *value, sharp_read *read);

static void sharp_read_start(sharp_read *read, zval *problems, bool text, const zend_string *order)
{
	read->found = (sharp_type_problems) {problems, {0}};
	array_init(&read->failed);
	array_init(&read->texts);
	read->text = text;
	read->order = order;
	read->holder = NULL;
	read->field = NULL;
}

static void sharp_read_end(sharp_read *read)
{
	smart_str_free(&read->found.path);
	zval_ptr_dtor(&read->failed);
	zval_ptr_dtor(&read->texts);
}

/* Records `value` under the path the read is at in the array `table`, which takes it. */
static void sharp_read_note(zval *table, const sharp_read *read, zval *value)
{
	const zend_string *path = read->found.path.s ? read->found.path.s : ZSTR_EMPTY_ALLOC();

	zend_symtable_str_update(Z_ARRVAL_P(table), ZSTR_VAL(path), ZSTR_LEN(path), value);
}

/* Records that the struct at the path the read is at reported its own problems, so the walk's goes. */
static void sharp_read_fail(sharp_read *read)
{
	zval failed;
	ZVAL_TRUE(&failed);
	sharp_read_note(&read->failed, read, &failed);
}

/* Reads `value` into `type`, then checks it with the walk, each problem under its path. The walk's problem at a
 * struct that reported its own goes, and a backed struct's own takes the walk's place. Only the structs and backed
 * structs this read reaches count, so a struct's field drops only the problems its own read made. */
static void sharp_read_whole(const sharp_type *type, zval *value, sharp_read *read)
{
	zval outer_failed, outer_texts;
	ZVAL_COPY_VALUE(&outer_failed, &read->failed);
	ZVAL_COPY_VALUE(&outer_texts, &read->texts);
	array_init(&read->failed);
	array_init(&read->texts);

	sharp_read_value(type, value, read);
	if (!EG(exception)) {
		sharp_type_renumber(type, value);
	}
	if (!EG(exception)) {
		sharp_type_accepts(type, value, &read->found);
	}
	HashTable *problems = Z_ARRVAL_P(read->found.problems);
	zend_ulong index;
	zend_string *path;
	zval *problem;
	ZEND_HASH_FOREACH_KEY(Z_ARRVAL(read->failed), index, path) {
		if (path) {
			zend_hash_del(problems, path);
		} else {
			zend_hash_index_del(problems, index);
		}
	} ZEND_HASH_FOREACH_END();
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL(read->texts), index, path, problem) {
		Z_TRY_ADDREF_P(problem);
		if (path) {
			zend_hash_update(problems, path, problem);
		} else {
			zend_hash_index_update(problems, index, problem);
		}
	} ZEND_HASH_FOREACH_END();

	zval_ptr_dtor(&read->failed);
	zval_ptr_dtor(&read->texts);
	ZVAL_COPY_VALUE(&read->failed, &outer_failed);
	ZVAL_COPY_VALUE(&read->texts, &outer_texts);
}

/* The class whose terms the type text of the field `name` of the struct or class `ce`'s `#fields` is written in: the
 * class that declares its property, as `PaginatedList<TItem>` declares `items` for `OrderPage : PaginatedList<Order>`,
 * or `ce` for a main constructor parameter that holds no property. */
static const zend_class_entry *sharp_field_scope(const zend_class_entry *ce, zend_string *name)
{
	const zend_property_info *info = zend_hash_find_ptr(&ce->properties_info, name);

	return info ? info->ce : ce;
}

/* Spec section 10: builds in `built` the struct or class `ce`, of the type arguments `arguments` or none, from `value`,
 * a Map, a PHP array or an object, as its `parse` reads it: each field a row of its `#fields` gives, read under its key
 * as a value of its type, then its main constructor called with its parameters by name, so a missing one takes its
 * default, then each property written. A missing field with no default is null when its type takes null, and a
 * problem otherwise. With any problem `built` stays undefined, and `value`'s path is among the read's failed paths. */
static void sharp_read_struct(zend_class_entry *ce, const sharp_type *arguments, zval *value, sharp_read *read, zval *built)
{
	zend_function *fields = zend_hash_str_find_ptr(&ce->function_table, "#fields", sizeof("#fields") - 1);
	zval rows, parameters, properties;
	zend_call_known_function(fields, NULL, ce, &rows, 0, NULL, NULL);
	array_init(&parameters);
	array_init(&properties);
	HashTable *problems = Z_ARRVAL_P(read->found.problems);
	uint32_t found = zend_hash_num_elements(problems);
	size_t length = read->found.path.s ? ZSTR_LEN(read->found.path.s) : 0;
	const zend_string *order = read->order;
	const zend_class_entry *holder = read->holder;
	const zend_string *holder_field = read->field;

	zval *row;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL(rows), row) {
		HashTable *parts = Z_ARRVAL_P(row);
		zend_string *name = Z_STR_P(zend_hash_index_find(parts, 0));
		zend_string *key = Z_STR_P(zend_hash_index_find(parts, 1));
		zend_string *written = Z_STR_P(zend_hash_index_find(parts, 2));
		zval *into = Z_TYPE_P(zend_hash_index_find(parts, 3)) == IS_TRUE ? &parameters : &properties;
		bool optional = Z_TYPE_P(zend_hash_index_find(parts, 4)) == IS_TRUE;
		zval *written_order = zend_hash_index_find(parts, 5);
		const sharp_type *types =
			sharp_declared_types(ZSTR_VAL(written), ZSTR_LEN(written), sharp_field_scope(ce, name), ce, arguments);
		ZEND_ASSERT(types != NULL && "the compile writes each field's type as one PHP# type");
		const sharp_type *type = types->members[0];
		if (length) {
			smart_str_appendc(&read->found.path, '.');
		}
		smart_str_append(&read->found.path, key);

		zval held;
		ZVAL_UNDEF(&held);
		zval *field = sharp_input_field(value, key, &held);
		if (field) {
			zval field_value;
			ZVAL_COPY_DEREF(&field_value, field);
			read->order = written_order ? Z_STR_P(written_order) : NULL;
			read->holder = ce;
			read->field = name;
			sharp_read_whole(type, &field_value, read);
			read->holder = holder;
			read->field = holder_field;
			zend_hash_update(Z_ARRVAL_P(into), name, &field_value);
		} else if (!optional) {
			zval missing;
			if (type->kind == SHARP_TYPE_NULLABLE || zend_string_equals_literal(type->text, "Any?")) {
				ZVAL_NULL(&missing);
				zend_hash_update(Z_ARRVAL_P(into), name, &missing);
			} else {
				ZVAL_STRINGL(&missing, "missing", sizeof("missing") - 1);
				sharp_read_note(read->found.problems, read, &missing);
			}
		}
		zval_ptr_dtor(&held);
		ZSTR_LEN(read->found.path.s) = length;
		if (EG(exception)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	read->order = order;
	zval_ptr_dtor(&rows);

	if (!EG(exception) && zend_hash_num_elements(problems) > found) {
		sharp_read_fail(read);
	} else if (!EG(exception)) {
		object_init_to_build(built, ce);
		if (arguments) {
			sharp_type_arguments_store(Z_OBJ_P(built), arguments);
		}
		if (ce->constructor) {
			zval constructed;
			zend_call_known_function(ce->constructor, Z_OBJ_P(built), ce, &constructed, 0, NULL, Z_ARRVAL(parameters));
			zval_ptr_dtor(&constructed);
		}
		/* Each stored field the constructor does not take is written by the class that declares it, as `clone with`
		 * writes one, so a `private set` property and a get-only one, which runs as `readonly`, take their stored value. */
		zend_object *object = Z_OBJ_P(built);
		bool readonly = ZEND_CLASS_HAS_READONLY_PROPS(ce);
		for (uint32_t i = 0; readonly && i < ce->default_properties_count; i++) {
			Z_PROP_FLAG_P(OBJ_PROP_NUM(object, i)) |= IS_PROP_REINITABLE;
		}
		zend_string *property;
		zval *property_value;
		ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL(properties), property, property_value) {
			if (EG(exception)) {
				break;
			}
			zend_update_property_ex(sharp_field_scope(ce, property), object, property, property_value);
		} ZEND_HASH_FOREACH_END();
		for (uint32_t i = 0; readonly && i < ce->default_properties_count; i++) {
			Z_PROP_FLAG_P(OBJ_PROP_NUM(object, i)) &= ~IS_PROP_REINITABLE;
		}
		/* Spec section 20.2: a machine's case holds each invariant of the machine, which its `#invariants` evaluates. */
		zend_function *invariants = zend_hash_str_find_ptr(&ce->function_table, "#invariants", sizeof("#invariants") - 1);
		if (!EG(exception) && invariants) {
			zend_call_known_function(invariants, Z_OBJ_P(built), ce, NULL, 0, NULL, NULL);
		}
		if (EG(exception)) {
			zval_ptr_dtor(built);
			ZVAL_UNDEF(built);
		}
	}
	zval_ptr_dtor(&parameters);
	zval_ptr_dtor(&properties);
}

/* Spec section 10: builds in `built` the variant of the family `family` that `value` names under the family's key, as
 * sharp_read_struct builds a struct, or the one object of an enum case that carries no data. A missing key, or a name
 * the family has no variant of, is a problem under the key, as `type: expected card or bank, got string 'cash'`, and
 * leaves `built` undefined with `value`'s path among the read's failed paths. */
static void sharp_read_variant(zend_class_entry *family, zval *value, sharp_read *read, zval *built)
{
	zend_string *key = NULL;
	HashTable *variants = sharp_family_variants(family, &key);
	if (!variants) {
		if (!EG(exception)) {
			sharp_family_out_of_date(family->name);
		}
		return;
	}

	zval held;
	ZVAL_UNDEF(&held);
	zval *name = sharp_input_field(value, key, &held);
	zval *variant_name = name && Z_TYPE_P(name) == IS_STRING ? zend_symtable_find(variants, Z_STR_P(name)) : NULL;
	if (!variant_name) {
		smart_str problem = {0};
		if (name) {
			smart_str_appends(&problem, "expected ");
			zend_string *known;
			zend_ulong number;
			uint32_t index = 0, count = zend_hash_num_elements(variants);
			ZEND_HASH_FOREACH_KEY(variants, number, known) {
				if (index > 0) {
					smart_str_appends(&problem, index + 1 == count ? " or " : ", ");
				}
				if (known) {
					smart_str_append(&problem, known);
				} else {
					smart_str_append_long(&problem, (zend_long) number);
				}
				index++;
			} ZEND_HASH_FOREACH_END();
			smart_str_appends(&problem, ", got ");
			sharp_value_text(&problem, name);
		} else {
			smart_str_appends(&problem, "missing");
		}
		smart_str_0(&problem);
		zval_ptr_dtor(&held);
		sharp_read_fail(read);
		size_t length = read->found.path.s ? ZSTR_LEN(read->found.path.s) : 0;
		if (length) {
			smart_str_appendc(&read->found.path, '.');
		}
		smart_str_append(&read->found.path, key);
		zval text;
		ZVAL_STR(&text, problem.s);
		sharp_read_note(read->found.problems, read, &text);
		ZSTR_LEN(read->found.path.s) = length;

		return;
	}
	zval_ptr_dtor(&held);

	zend_class_entry *variant = zend_lookup_class(Z_STR_P(variant_name));
	if (!variant) {
		if (!EG(exception)) {
			sharp_listener_table_out_of_date(Z_STR_P(variant_name));
		}
		return;
	}
	if (zend_hash_str_exists(&variant->function_table, "#fields", sizeof("#fields") - 1)) {
		sharp_read_struct(variant, NULL, value, read, built);
		return;
	}
	zend_function *instance = zend_hash_str_find_ptr(&variant->function_table, "instance", sizeof("instance") - 1);
	ZEND_ASSERT(instance != NULL && "a variant with no fields is a case that carries no data");
	zend_call_known_function(instance, NULL, variant, built, 0, NULL, NULL);
}

/* Spec section 25: `Class<T>.withTag(tag)`, the class name of the variant of the family `family` whose `[Key]` is
 * `tag`, or null for a name it has no variant of. The checker gives it only a family. */
ZEND_FUNCTION(Sharp_Internal_withTag)
{
	zend_string *family_name, *tag;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(family_name)
		Z_PARAM_STR(tag)
	ZEND_PARSE_PARAMETERS_END();

	zend_class_entry *family = zend_lookup_class(family_name);
	zend_string *key;
	HashTable *variants = family ? sharp_family_variants(family, &key) : NULL;
	if (!variants) {
		if (EG(exception)) {
			RETURN_THROWS();
		}
		if (family && zend_get_attribute_str(family->attributes, "sharp\\tag", sizeof("sharp\\tag") - 1)) {
			sharp_family_out_of_date(family_name);
			RETURN_THROWS();
		}
		RETURN_NULL();
	}
	zval *variant = zend_symtable_find(variants, tag);
	if (!variant) {
		RETURN_NULL();
	}

	RETURN_STR_COPY(Z_STR_P(variant));
}

/* The name the family `family` stores for its variant `variant`, from the table `withTag` reads, or NULL when `family`
 * is no family that lists it, or after an exception reading the table threw. */
static zend_string *sharp_variant_tag(const zend_class_entry *family, const zend_class_entry *variant)
{
	zend_string *key, *name;
	zend_ulong number;
	zval *listed;
	HashTable *variants = sharp_family_variants(family, &key);
	if (!variants) {
		if (!EG(exception) && zend_get_attribute_str(family->attributes, "sharp\\tag", sizeof("sharp\\tag") - 1)) {
			sharp_family_out_of_date(family->name);
		}
		return NULL;
	}
	ZEND_HASH_FOREACH_KEY_VAL(variants, number, name, listed) {
		if (zend_string_equals_ci(Z_STR_P(listed), variant->name)) {
			return name ? zend_string_copy(name) : zend_long_to_str((zend_long) number);
		}
	} ZEND_HASH_FOREACH_END();

	return NULL;
}

/* Spec section 25: `Class<T>.tag()`, the name the family of the class `variant_name` stores for it, its `[Key]` or its
 * value as a backed machine's case, read from the family among the classes it extends and the interfaces it
 * implements. Null for a class that is no variant of a family. */
ZEND_FUNCTION(Sharp_Internal_tag)
{
	zend_string *variant_name, *tag = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(variant_name)
	ZEND_PARSE_PARAMETERS_END();

	zend_class_entry *variant = zend_lookup_class(variant_name);
	if (!variant) {
		RETURN_NULL();
	}
	for (const zend_class_entry *family = variant->parent; family && !tag && !EG(exception); family = family->parent) {
		tag = sharp_variant_tag(family, variant);
	}
	for (uint32_t index = 0; index < variant->num_interfaces && !tag && !EG(exception); index++) {
		tag = sharp_variant_tag(variant->interfaces[index], variant);
	}
	if (EG(exception)) {
		RETURN_THROWS();
	}
	if (!tag) {
		RETURN_NULL();
	}

	RETURN_STR(tag);
}

/* Appends to `path` the part an element at `index` or `key` adds, as a struct's parse names an element: `["eu"]` in a
 * Map, whose `keyed` key is text, and `[3]` in a List, a Set or a tuple. */
static void sharp_path_element(smart_str *path, zend_ulong index, zend_string *key, bool keyed)
{
	smart_str_appends(path, keyed ? "[\"" : "[");
	if (key) {
		smart_str_append(path, key);
	} else {
		smart_str_append_long(path, (zend_long) index);
	}
	smart_str_appends(path, keyed ? "\"]" : "]");
}

/* Records what the own `parse` of a class the read reached threw at the read's path, and clears the exception: the
 * problems of a Sharp\InvalidFields, each under that path, which then counts as failed, or the message of any other
 * ValueError, which takes the walk's place there. Leaves any other exception. */
static void sharp_read_failed(sharp_read *read)
{
	zend_object *thrown = EG(exception);
	if (!thrown || !instanceof_function(thrown->ce, zend_ce_value_error)) {
		return;
	}
	const zend_string *path = read->found.path.s ? read->found.path.s : ZSTR_EMPTY_ALLOC();
	zval held, *fields = NULL;
	ZVAL_UNDEF(&held);
	if (zend_string_equals_literal(thrown->ce->name, "Sharp\\InvalidFields")) {
		fields = zend_read_property(thrown->ce, thrown, "fields", sizeof("fields") - 1, true, &held);
	}
	if (fields && Z_TYPE_P(fields) == IS_OBJECT && Z_OBJCE_P(fields) == sharp_ce_map) {
		SHARP_TABLE_FOREACH(sharp_table_of(Z_OBJ_P(fields)), bucket) {
			zend_string *inner = zval_get_string(&bucket->key);
			zend_string *at = ZSTR_LEN(path)
				? zend_string_concat3(ZSTR_VAL(path), ZSTR_LEN(path), ".", 1, ZSTR_VAL(inner), ZSTR_LEN(inner))
				: zend_string_copy(inner);
			Z_TRY_ADDREF(bucket->value);
			zend_symtable_update(Z_ARRVAL_P(read->found.problems), at, &bucket->value);
			zend_string_release(at);
			zend_string_release(inner);
		} SHARP_TABLE_FOREACH_END();
		sharp_read_fail(read);
	} else {
		zval message;
		zval *thrown_message = zend_read_property_ex(zend_get_exception_base(thrown), thrown, ZSTR_KNOWN(ZEND_STR_MESSAGE), true, &held);
		ZVAL_STR(&message, zval_get_string(thrown_message));
		sharp_read_note(&read->texts, read, &message);
	}
	zval_ptr_dtor(&held);
	zend_clear_exception();
}

/* `text`, a number or a bool as query text holds it, read as `built_in` reads it, in place: an int as Int.parse, a
 * float as Float.parse and a bool as `true` or `false`. Text that holds none stays text, which the walk refuses. */
static void sharp_read_scalar_text(sharp_built_in built_in, zval *text)
{
	zend_long number;
	double fraction;
	uint8_t read = is_numeric_string(Z_STRVAL_P(text), Z_STRLEN_P(text), &number, &fraction, false);

	if (built_in == SHARP_BUILT_IN_INT && read == IS_LONG) {
		zval_ptr_dtor(text);
		ZVAL_LONG(text, number);
	} else if (built_in == SHARP_BUILT_IN_FLOAT && read) {
		double value = read == IS_LONG ? (double) number : fraction;
		zval_ptr_dtor(text);
		ZVAL_DOUBLE(text, value);
	} else if (built_in == SHARP_BUILT_IN_BOOL && (zend_string_equals_literal(Z_STR_P(text), "true") || zend_string_equals_literal(Z_STR_P(text), "false"))) {
		bool value = Z_STRVAL_P(text)[0] == 't';
		zval_ptr_dtor(text);
		ZVAL_BOOL(text, value);
	}
}

/* Builds each element of the array `value` as `element` reads it, each at its path, a Map's `keyed` by its text. */
static void sharp_read_elements(const sharp_type *element, zval *value, sharp_read *read, bool keyed)
{
	size_t length = read->found.path.s ? ZSTR_LEN(read->found.path.s) : 0;
	zend_ulong index;
	zend_string *key;
	zval *each;

	SEPARATE_ARRAY(value);
	ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(value), index, key, each) {
		sharp_path_element(&read->found.path, index, key, keyed);
		sharp_read_value(element, each, read);
		ZSTR_LEN(read->found.path.s) = length;
		if (EG(exception)) {
			return;
		}
	} ZEND_HASH_FOREACH_END();
}

/* Replaces the Map `value` with the PHP array of its entries, each value as it is, which a Map field reads as it reads
 * a JSON object, so the Map `Class<T>.encode` gives, keyed by text as JSON keys an object, reads back: an int key is the
 * int, a string is the key PHP makes of it, "7" the int 7, and a backed enum case is its value. A Map with any other
 * key, or with two keys that are one PHP array key, as 5 and "5" are, stays, and its type is checked as it is. */
static void sharp_read_map_entries(zval *value)
{
	sharp_table *table = sharp_table_entries(Z_OBJ_P(value));
	if (!table) {
		return;
	}
	zval entries;
	array_init_size(&entries, table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *key = &bucket->key;
		if (Z_TYPE_P(key) == IS_OBJECT && (Z_OBJCE_P(key)->ce_flags & ZEND_ACC_ENUM)
				&& Z_OBJCE_P(key)->enum_backing_type != IS_UNDEF) {
			key = zend_enum_fetch_case_value(Z_OBJ_P(key));
		}
		if (Z_TYPE_P(key) != IS_LONG && Z_TYPE_P(key) != IS_STRING) {
			zval_ptr_dtor(&entries);
			return;
		}
		zval element;
		ZVAL_COPY_DEREF(&element, &bucket->value);
		if (Z_TYPE_P(key) == IS_LONG) {
			zend_hash_index_update(Z_ARRVAL(entries), Z_LVAL_P(key), &element);
		} else {
			zend_symtable_update(Z_ARRVAL(entries), Z_STR_P(key), &element);
		}
	} SHARP_TABLE_FOREACH_END();
	if (zend_hash_num_elements(Z_ARRVAL(entries)) < table->size) {
		zval_ptr_dtor(&entries);
		return;
	}
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &entries);
}

/* The collection class `ce` makes of the array `value`, through its static `from`, as a struct's parse crosses a PHP
 * array into a Map or a Set. */
static void sharp_read_collection(zend_class_entry *ce, zval *value)
{
	zend_function *from = zend_hash_str_find_ptr(&ce->function_table, "from", sizeof("from") - 1);
	zval made;

	zend_call_known_function(from, NULL, ce, &made, 1, value, NULL);
	if (!EG(exception)) {
		zval_ptr_dtor(value);
		ZVAL_COPY_VALUE(value, &made);
	}
}

/* The place of the union member `member`, at `index` in the union's own order, in `order`, the classes and scalar
 * types a field's declaration writes in its order, `App.Card, App.Bank` or `string, int`. A member `order` doesn't
 * name comes after every one it names, in the union's own order. */
static uint32_t sharp_read_rank(const sharp_type *member, uint32_t index, const zend_string *order)
{
	if (order) {
		size_t length = strcspn(ZSTR_VAL(member->text), "<");
		uint32_t place = 0;
		for (const char *at = ZSTR_VAL(order), *end = at + ZSTR_LEN(order); at < end; place++) {
			const char *comma = memchr(at, ',', end - at);
			const char *stop = comma ? comma : end;
			if ((size_t) (stop - at) == length && memcmp(at, ZSTR_VAL(member->text), length) == 0) {
				return place;
			}
			at = comma ? comma + strlen(", ") : end;
		}
	}

	return UINT16_MAX + index;
}

/* Spec section 20.2: builds in `built` the case of the machine `machine`, one that stores as its case's value, whose
 * value `value` is, by the rows of its `#cases`, as a backed enum's case is read: from a query's text too when the
 * machine is backed by ints. Any other value leaves `built` undefined, and the walk then gives its problem. */
static void sharp_read_machine_case(zend_class_entry *machine, zval *value, sharp_read *read, zval *built)
{
	zend_function *cases = zend_hash_str_find_ptr(&machine->function_table, "#cases", sizeof("#cases") - 1);
	zval rows;
	zend_call_known_function(cases, NULL, machine, &rows, 0, NULL, NULL);
	zval *row;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL(rows), row) {
		zval *case_value = zend_hash_index_find(Z_ARRVAL_P(row), 0);
		if (read->text && Z_TYPE_P(case_value) == IS_LONG && Z_TYPE_P(value) == IS_STRING) {
			sharp_read_scalar_text(SHARP_BUILT_IN_INT, value);
		}
		if (!zend_is_identical(case_value, value)) {
			continue;
		}
		zend_class_entry *found = zend_lookup_class(Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(row), 1)));
		if (found) {
			zend_function *instance = zend_hash_str_find_ptr(&found->function_table, "instance", sizeof("instance") - 1);
			zend_call_known_function(instance, NULL, found, built, 0, NULL, NULL);
		}
		break;
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&rows);
}

/* Spec section 24: Sharp.Security's Secret and Hashed, whose stored forms the engine writes and reads, since only it
 * knows the field that holds one and the type of the value it holds. */
static bool sharp_is_secret(const zend_class_entry *ce)
{
	return zend_string_equals_literal(ce->name, "Sharp\\Security\\Secret");
}

static bool sharp_is_hashed(const zend_class_entry *ce)
{
	return zend_string_equals_literal(ce->name, "Sharp\\Security\\Hashed");
}

/* The JSON text of `value` that a Secret encrypts and a Hashed hashes, its stored form as `Json.encode` writes it by
 * default, or NULL with the exception its encoding threw. */
static zend_string *sharp_stored_text(zval *value)
{
	php_json_encoder encoder;
	php_json_encode_init(&encoder);
	encoder.max_depth = PHP_JSON_PARSER_DEFAULT_DEPTH;
	encoder.nonfinite_as_null = true;
	smart_str text = {0};
	php_json_encode_zval(&text, value,
		PHP_JSON_UNESCAPED_SLASHES | PHP_JSON_UNESCAPED_UNICODE | PHP_JSON_PRESERVE_ZERO_FRACTION, &encoder);
	if (EG(exception) || encoder.error_code != PHP_JSON_ERROR_NONE) {
		smart_str_free(&text);
		if (!EG(exception)) {
			zend_throw_exception(php_json_exception_ce, php_json_get_error_msg(encoder.error_code), encoder.error_code);
		}
		return NULL;
	}

	return smart_str_extract(&text);
}

/* Calls the PHP function `name`, which ext/sodium or ext/standard declares, with `count` `arguments`, into `result`.
 * False with the exception it threw. */
static bool sharp_call_function(const char *name, zval *result, uint32_t count, zval *arguments)
{
	zend_function *function = zend_hash_str_find_ptr(EG(function_table), name, strlen(name));
	ZVAL_UNDEF(result);
	if (!function) {
		zend_throw_error(NULL, "Secret and Hashed need the PHP function %s(), which this build of PHP# lacks", name);
		return false;
	}
	zend_call_known_function(function, NULL, NULL, result, count, arguments, NULL);

	return !EG(exception);
}

/* The key of the key ring SHARP_KEYS whose id is `id`, or with `id` NULL its newest, whose id `newest` then takes, or
 * NULL when the ring has no key of `id`. The ring is comma-separated `<id>:<base64 of 32 bytes>` entries, newest first,
 * as `mago generate-key` prints each. Throws RuntimeException when it is not set, and ValueError naming the first entry
 * that is no key, never its text. */
static zend_string *sharp_ring_key(const char *id, size_t id_length, zend_string **newest)
{
	char *ring = sapi_getenv("SHARP_KEYS", sizeof("SHARP_KEYS") - 1);
	if (!ring) {
		const char *process = getenv("SHARP_KEYS");
		ring = process ? estrdup(process) : NULL;
	}
	if (!ring || !*ring) {
		zend_throw_exception(spl_ce_RuntimeException,
			"a Secret is encrypted with the keys in SHARP_KEYS, which is not set. Generate a key with "
			"`vendor/bin/mago generate-key`.", 0);
		if (ring) {
			efree(ring);
		}
		return NULL;
	}
	zend_string *found = NULL;
	uint32_t number = 0;
	for (const char *entry = ring; entry; ) {
		const char *end = strchr(entry, ',');
		size_t length = end ? (size_t) (end - entry) : strlen(entry);
		const char *colon = memchr(entry, ':', length);
		number++;
		zend_string *key = colon && colon > entry
			? php_base64_decode_ex((const unsigned char *) colon + 1, length - (colon - entry) - 1, true)
			: NULL;
		if (!key || ZSTR_LEN(key) != 32 || colon - entry > UINT8_MAX) {
			if (key) {
				zend_string_release(key);
			}
			zend_value_error("SHARP_KEYS: entry %" PRIu32 " is not `<id>:<base64 of 32 bytes>`", number);
			break;
		}
		size_t named_length = colon - entry;
		if (!id || (named_length == id_length && memcmp(entry, id, id_length) == 0)) {
			if (!id) {
				*newest = zend_string_init(entry, named_length, false);
			}
			found = key;
			break;
		}
		zend_string_release(key);
		entry = end ? end + 1 : NULL;
	}
	efree(ring);

	return found;
}

/* The unpadded base64url of `bytes`, as a Secret's stored text holds them. */
static zend_string *sharp_base64url(const zend_string *bytes)
{
	zend_string *text = php_base64_encode_ex((const unsigned char *) ZSTR_VAL(bytes), ZSTR_LEN(bytes),
		PHP_BASE64_NO_PADDING);
	for (char *c = ZSTR_VAL(text); *c; c++) {
		*c = *c == '+' ? '-' : *c == '/' ? '_' : *c;
	}

	return text;
}

/* Spec section 24: the stored text of a Secret holding `value` in the field `path`, as `App.SmtpAccount.password`: the
 * unpadded base64url of the newest key's id length as one byte, that id, a random 24-byte nonce and the
 * XChaCha20-Poly1305 encryption of the value's stored text with `path` as the additional data, so it decrypts only in
 * that field. NULL with the exception that stopped it. */
static zend_string *sharp_encrypt(zval *value, const zend_string *path)
{
	zend_string *id = NULL;
	zend_string *key = sharp_ring_key(NULL, 0, &id);
	if (!key) {
		return NULL;
	}
	zend_string *text = sharp_stored_text(value);
	zend_string *nonce = zend_string_alloc(24, false);
	zend_string *stored = NULL;
	if (text && php_random_bytes_throw(ZSTR_VAL(nonce), 24) == SUCCESS) {
		ZSTR_VAL(nonce)[24] = '\0';
		zval arguments[4], sealed;
		ZVAL_STR(&arguments[0], text);
		ZVAL_STR(&arguments[1], (zend_string *) path);
		ZVAL_STR(&arguments[2], nonce);
		ZVAL_STR(&arguments[3], key);
		if (sharp_call_function("sodium_crypto_aead_xchacha20poly1305_ietf_encrypt", &sealed, 4, arguments)) {
			smart_str payload = {0};
			smart_str_appendc(&payload, (char) ZSTR_LEN(id));
			smart_str_append(&payload, id);
			smart_str_append(&payload, nonce);
			smart_str_append(&payload, Z_STR(sealed));
			zend_string *bytes = smart_str_extract(&payload);
			stored = sharp_base64url(bytes);
			zend_string_release(bytes);
		}
		zval_ptr_dtor(&sealed);
	}
	if (text) {
		zend_string_release(text);
	}
	zend_string_release(nonce);
	zend_string_release(id);
	zend_string_release(key);

	return stored;
}

/* Reads into `opened` the value of a Secret whose stored text `stored` the field `path` holds. False with ValueError
 * when no key of SHARP_KEYS decrypts it for `path`, as for text encrypted in another field or under a key the ring no
 * longer holds, or with the exception that stopped it. */
static bool sharp_decrypt(const zend_string *stored, const zend_string *path, zval *opened)
{
	zend_string *text = zend_string_init(ZSTR_VAL(stored), ZSTR_LEN(stored), false);
	for (char *c = ZSTR_VAL(text); *c; c++) {
		*c = *c == '-' ? '+' : *c == '_' ? '/' : *c;
	}
	zend_string *payload = php_base64_decode_ex((const unsigned char *) ZSTR_VAL(text), ZSTR_LEN(text), true);
	zend_string_release(text);
	size_t id_length = payload && ZSTR_LEN(payload) ? (unsigned char) ZSTR_VAL(payload)[0] : 0;
	zend_string *key = NULL;
	if (payload && ZSTR_LEN(payload) > 1 + id_length + 24) {
		key = sharp_ring_key(ZSTR_VAL(payload) + 1, id_length, NULL);
	}
	bool read = false;
	if (key) {
		zval arguments[4], plain;
		const char *nonce = ZSTR_VAL(payload) + 1 + id_length;
		ZVAL_STRINGL(&arguments[0], nonce + 24, ZSTR_LEN(payload) - 1 - id_length - 24);
		ZVAL_STR(&arguments[1], (zend_string *) path);
		ZVAL_STRINGL(&arguments[2], nonce, 24);
		ZVAL_STR(&arguments[3], key);
		if (sharp_call_function("sodium_crypto_aead_xchacha20poly1305_ietf_decrypt", &plain, 4, arguments)
				&& Z_TYPE(plain) == IS_STRING) {
			read = sharp_json_decode(Z_STR(plain), opened);
		}
		zval_ptr_dtor(&plain);
		zval_ptr_dtor(&arguments[0]);
		zval_ptr_dtor(&arguments[2]);
		zend_string_release(key);
	}
	if (payload) {
		zend_string_release(payload);
	}
	if (!read && !EG(exception)) {
		zend_value_error("the encrypted text is not one SHARP_KEYS decrypts for %s", ZSTR_VAL(path));
	}

	return read;
}

/* `App.SmtpAccount.password`, the name of the field `field` of `holder` a Secret's encryption is bound to. */
static zend_string *sharp_secret_path(const zend_class_entry *holder, const zend_string *field)
{
	smart_str path = {0};
	sharp_class_text(&path, holder);
	smart_str_appendc(&path, '.');
	smart_str_append(&path, field);

	return smart_str_extract(&path);
}

/* The text under the one key `key` of the stored form `value`, `encrypted` for a Secret and `hashed` for a Hashed, or
 * NULL for any other value, which is the plain value, spec section 24. */
static zend_string *sharp_sealed_text(zval *value, const char *key)
{
	const HashTable *entries =Z_TYPE_P(value) == IS_ARRAY ? Z_ARRVAL_P(value)
		: Z_TYPE_P(value) == IS_OBJECT && Z_OBJCE_P(value) == zend_standard_class_def ? Z_OBJPROP_P(value) : NULL;
	if (!entries || zend_hash_num_elements(entries) != 1) {
		return NULL;
	}
	zval *text = zend_hash_str_find(entries, key, strlen(key));

	return text && Z_TYPE_P(text) == IS_STRING ? Z_STR_P(text) : NULL;
}

/* Spec section 24: builds in `built` the Secret or Hashed of `ce` that `value` stores. A plain value is read as the
 * type it holds, and a Hashed of it hashes it. A Secret's encrypted text decrypts for the field the read is in, and a
 * Hashed's hash is kept as it is. */
static void sharp_read_sealed(zend_class_entry *ce, const sharp_type *arguments, zval *value, sharp_read *read,
	zval *built)
{
	bool secret = sharp_is_secret(ce);
	zend_string *stored = sharp_sealed_text(value, secret ? "encrypted" : "hashed");
	if (stored && !secret) {
		object_init_to_build(built, ce);
		if (arguments) {
			sharp_type_arguments_store(Z_OBJ_P(built), arguments);
		}
		zend_update_property_str(ce, Z_OBJ_P(built), "hash", sizeof("hash") - 1, stored);
		return;
	}
	zval fields, held;
	array_init(&fields);
	if (!stored) {
		ZVAL_COPY(&held, value);
	} else if (!read->holder) {
		zend_value_error("an encrypted Secret is read only in the field that holds it, whose name it is encrypted for");
		sharp_read_failed(read);
		zval_ptr_dtor(&fields);
		return;
	} else {
		zend_string *path = sharp_secret_path(read->holder, read->field);
		bool opened = sharp_decrypt(stored, path, &held);
		zend_string_release(path);
		if (!opened) {
			sharp_read_failed(read);
			zval_ptr_dtor(&fields);
			return;
		}
	}
	zend_hash_str_update(Z_ARRVAL(fields), "value", sizeof("value") - 1, &held);
	sharp_read_struct(ce, arguments, &fields, read, built);
	zval_ptr_dtor(&fields);
}

/* Spec section 10: builds, in `value`, each struct, class, family's variant, backed struct and enum case `type` holds,
 * as a field `parse` reads builds them, each problem under its path in the read. With the read's `text`, a number or
 * a bool is read from its text, as `Query.decode` reads it. sharp_read_whole checks the whole value after. */
static void sharp_read_value(const sharp_type *type, zval *value, sharp_read *read)
{
	ZVAL_DEREF(value);
	switch (type->kind) {
		case SHARP_TYPE_NULLABLE:
			if (Z_TYPE_P(value) != IS_NULL) {
				sharp_read_value(type->members[0], value, read);
			}
			return;
		case SHARP_TYPE_UNION: {
			/* The first member, in the order the field's declaration writes them, that builds the value with no
			 * problem takes it. */
			uint32_t last = 0;
			for (uint32_t round = 0; round < type->count; round++) {
				uint32_t at = 0, best = UINT32_MAX;
				for (uint32_t i = 0; i < type->count; i++) {
					uint32_t rank = sharp_read_rank(type->members[i], i, read->order);
					if ((round == 0 || rank > last) && rank < best) {
						best = rank;
						at = i;
					}
				}
				last = best;
				zval tried, found;
				array_init(&found);
				sharp_read trying;
				sharp_read_start(&trying, &found, read->text, read->order);
				trying.holder = read->holder;
				trying.field = read->field;
				ZVAL_COPY(&tried, value);
				sharp_read_whole(type->members[at], &tried, &trying);
				bool built = !EG(exception) && zend_hash_num_elements(Z_ARRVAL(found)) == 0;
				sharp_read_end(&trying);
				zval_ptr_dtor(&found);
				if (built) {
					zval_ptr_dtor(value);
					ZVAL_COPY_VALUE(value, &tried);
					return;
				}
				zval_ptr_dtor(&tried);
				if (EG(exception)) {
					return;
				}
			}
			return;
		}
		case SHARP_TYPE_TUPLE:
			if (Z_TYPE_P(value) == IS_ARRAY) {
				size_t length = read->found.path.s ? ZSTR_LEN(read->found.path.s) : 0;
				SEPARATE_ARRAY(value);
				for (uint32_t i = 0; i < type->count; i++) {
					zval *element = zend_hash_index_find(Z_ARRVAL_P(value), i);
					if (!element) {
						continue;
					}
					sharp_path_element(&read->found.path, i, NULL, false);
					sharp_read_value(type->members[i], element, read);
					ZSTR_LEN(read->found.path.s) = length;
					if (EG(exception)) {
						return;
					}
				}
			}
			return;
		case SHARP_TYPE_NAMED:
			break;
		default:
			return;
	}

	switch (type->built_in) {
		case SHARP_BUILT_IN_INT:
		case SHARP_BUILT_IN_FLOAT:
		case SHARP_BUILT_IN_BOOL:
			if (read->text && Z_TYPE_P(value) == IS_STRING) {
				sharp_read_scalar_text(type->built_in, value);
			}
			return;
		case SHARP_BUILT_IN_LIST:
			if (Z_TYPE_P(value) == IS_ARRAY) {
				sharp_read_elements(type->members[0], value, read, false);
			}
			return;
		case SHARP_BUILT_IN_SET:
		case SHARP_BUILT_IN_MAP:
			if (type->built_in == SHARP_BUILT_IN_MAP && Z_TYPE_P(value) == IS_OBJECT && Z_OBJCE_P(value) == sharp_ce_map) {
				sharp_read_map_entries(value);
				if (EG(exception)) {
					return;
				}
			}
			if (Z_TYPE_P(value) == IS_ARRAY) {
				bool keyed = type->built_in == SHARP_BUILT_IN_MAP;
				sharp_read_elements(type->members[keyed ? 1 : 0], value, read, keyed);
				if (!EG(exception)) {
					sharp_read_collection(type->built_in == SHARP_BUILT_IN_MAP ? sharp_ce_map : sharp_ce_set, value);
				}
			}
			return;
		case SHARP_BUILT_IN_NONE:
			break;
		default:
			return;
	}

	zend_class_entry *ce = zend_lookup_class(type->class_name);
	if (!ce || (Z_TYPE_P(value) == IS_OBJECT && instanceof_function(Z_OBJCE_P(value), ce))) {
		return;
	}
	zval built;
	ZVAL_UNDEF(&built);
	bool readable = Z_TYPE_P(value) == IS_ARRAY || Z_TYPE_P(value) == IS_OBJECT;
	if (ce->ce_flags & ZEND_ACC_ENUM) {
		if (ce->enum_backing_type == IS_LONG && read->text && Z_TYPE_P(value) == IS_STRING) {
			sharp_read_scalar_text(SHARP_BUILT_IN_INT, value);
		}
		zend_object *found;
		if (ce->enum_backing_type != IS_UNDEF && Z_TYPE_P(value) == ce->enum_backing_type
				&& zend_enum_get_case_by_value(&found, ce, Z_TYPE_P(value) == IS_LONG ? Z_LVAL_P(value) : 0,
					Z_TYPE_P(value) == IS_STRING ? Z_STR_P(value) : NULL, true) == SUCCESS && found) {
			ZVAL_OBJ_COPY(&built, found);
		}
	} else if (zend_hash_str_exists(&ce->function_table, "#cases", sizeof("#cases") - 1)) {
		sharp_read_machine_case(ce, value, read, &built);
	} else if (ce->attributes && zend_get_attribute_str(ce->attributes, "sharp\\tag", sizeof("sharp\\tag") - 1)) {
		if (readable) {
			sharp_read_variant(ce, value, read, &built);
		}
	} else if (zend_hash_str_exists(&ce->function_table, "#fields", sizeof("#fields") - 1)) {
		bool sealed = sharp_is_secret(ce) || sharp_is_hashed(ce);
		if (readable || sealed) {
			const char *open = memchr(ZSTR_VAL(type->text), '<', ZSTR_LEN(type->text));
			const sharp_type *arguments =
				open ? sharp_type_list(open + 1, ZSTR_VAL(type->text) + ZSTR_LEN(type->text) - open - 2) : NULL;
			if (sealed) {
				sharp_read_sealed(ce, arguments, value, read, &built);
			} else {
				sharp_read_struct(ce, arguments, value, read, &built);
			}
		}
	} else {
		/* A class that declares its own `parse` of the value, as a backed struct's of its value and JsonValue's of any
		 * value, builds through it. */
		zend_function *parse = zend_hash_str_find_ptr(&ce->function_table, "parse", sizeof("parse") - 1);
		if (parse && (parse->common.fn_flags & ZEND_ACC_STATIC) && parse->common.num_args >= 1
				&& (!ZEND_TYPE_IS_SET(parse->common.arg_info[0].type)
					|| ZEND_TYPE_CONTAINS_CODE(parse->common.arg_info[0].type, Z_TYPE_P(value)))) {
			zend_call_known_function(parse, NULL, ce, &built, 1, value, NULL);
			if (EG(exception)) {
				zval_ptr_dtor(&built);
				ZVAL_UNDEF(&built);
				sharp_read_failed(read);
			}
		}
	}
	if (!Z_ISUNDEF(built)) {
		zval_ptr_dtor(value);
		ZVAL_COPY_VALUE(value, &built);
	}
}

/* Spec section 10: the value of `type` that `value` builds in `return_value`, as a field of that type a struct's
 * `parse` reads reads it, read from text with `text`, as `Query.decode` reads it. Throws one Sharp\InvalidFields that
 * lists every bad part, under its path. */
static void sharp_parse_as(const sharp_type *type, zval *value, bool text, zval *return_value)
{
	zval problems;
	array_init(&problems);
	sharp_read read;
	sharp_read_start(&read, &problems, text, NULL);
	ZVAL_COPY(return_value, value);
	sharp_read_whole(type, return_value, &read);
	sharp_read_end(&read);
	if (!EG(exception) && zend_hash_num_elements(Z_ARRVAL(problems)) > 0) {
		sharp_throw_invalid_fields(&problems);
	}
	zval_ptr_dtor(&problems);
	if (EG(exception)) {
		zval_ptr_dtor(return_value);
		ZVAL_NULL(return_value);
	}
}

zend_function *sharp_backed_value_method(const zend_class_entry *ce, bool keyed)
{
	if (!instanceof_function((zend_class_entry *) ce, sharp_ce_struct)
			|| zend_hash_str_exists(&ce->function_table, ZEND_STRL("#fields"))) {
		return NULL;
	}
	zend_function *parse = zend_hash_str_find_ptr(&ce->function_table, ZEND_STRL("parse"));
	if (!parse || !(parse->common.fn_flags & ZEND_ACC_STATIC) || parse->common.num_args != 1) {
		return NULL;
	}
	zend_type backing = parse->common.arg_info[0].type;
	if (keyed && !ZEND_TYPE_CONTAINS_CODE(backing, IS_LONG) && !ZEND_TYPE_CONTAINS_CODE(backing, IS_STRING)) {
		return NULL;
	}

	return zend_hash_str_find_ptr(&ce->function_table, ZEND_STRL("#json"));
}

ZEND_COLD void sharp_refuse_creation(const zend_class_entry *ce)
{
	/* A plain PHP class below a PHP# class, as a test double's, is named by the PHP# class whose constructor it skips. */
	while (!sharp_class_is_sharp(ce) && ce->parent) {
		ce = ce->parent;
	}
	smart_str name = {0};
	sharp_class_text(&name, ce);
	smart_str_0(&name);
	zend_throw_error(NULL, "%s is built only through its constructor, which this creation doesn't run",
		ZSTR_VAL(name.s));
	smart_str_free(&name);
}

bool sharp_owns_storage(const zend_class_entry *ce)
{
	return (ce->sharp_flags & SHARP_CLASS_BUILT_BY_CONSTRUCTOR)
		&& !ce->__serialize && !ce->__unserialize && !ce->serialize && !ce->unserialize
		&& !zend_hash_exists(&ce->function_table, ZSTR_KNOWN(ZEND_STR_SLEEP))
		&& !zend_hash_exists(&ce->function_table, ZSTR_KNOWN(ZEND_STR_WAKEUP));
}

/* Throws the Error serialize gives for the PHP# class `ce`, which has no stored form, `reason` saying why. */
static ZEND_COLD void sharp_refuse_storage(const zend_class_entry *ce, const char *reason, ...)
{
	va_list arguments;
	va_start(arguments, reason);
	zend_string *why = zend_vstrpprintf(0, reason, arguments);
	va_end(arguments);
	smart_str name = {0};
	sharp_class_text(&name, ce);
	smart_str_0(&name);
	zend_throw_error(NULL, "`serialize` writes the stored form of %s, the fields `Json.encode` writes, and %s.",
		ZSTR_VAL(name.s), ZSTR_VAL(why));
	smart_str_free(&name);
	zend_string_release_ex(why, false);
}

/* Whether a Map key of `type` has the text a stored form keys it by, as sharp_table_json_key gives it: an int, a
 * string, a backed enum case, or a struct backed by an int or a string. A type parameter's and an `Any`'s key is decided
 * by its value, as is the key of a class no autoloader finds. */
static bool sharp_type_keys_text(const sharp_type *type)
{
	switch (type->kind) {
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				if (!sharp_type_keys_text(type->members[i])) {
					return false;
				}
			}
			return true;
		case SHARP_TYPE_PARAMETER:
			return true;
		case SHARP_TYPE_NAMED:
			break;
		default:
			return false;
	}
	switch (type->built_in) {
		case SHARP_BUILT_IN_INT:
		case SHARP_BUILT_IN_STRING:
		case SHARP_BUILT_IN_ANY:
			return true;
		case SHARP_BUILT_IN_NONE:
			break;
		default:
			return false;
	}
	const zend_class_entry *ce = zend_lookup_class(type->class_name);
	if (!ce) {
		return !EG(exception);
	}

	return (ce->ce_flags & ZEND_ACC_ENUM) ? ce->enum_backing_type != IS_UNDEF : sharp_backed_value_method(ce, true) != NULL;
}

/* The key type of a Map in `type`, at any depth of its collections and unions, whose key has no stored text, as
 * sharp_type_keys_text decides, or NULL. */
static const sharp_type *sharp_type_unstored_key(const sharp_type *type)
{
	if (type->kind == SHARP_TYPE_NAMED && type->built_in == SHARP_BUILT_IN_MAP
			&& !sharp_type_keys_text(type->members[0])) {
		return type->members[0];
	}
	if (type->kind == SHARP_TYPE_NAMED && type->built_in == SHARP_BUILT_IN_NONE) {
		return NULL;
	}
	for (uint32_t i = 0; i < type->count; i++) {
		const sharp_type *key = sharp_type_unstored_key(type->members[i]);
		if (key) {
			return key;
		}
	}

	return NULL;
}

/* Whether the struct or class `ce`, whose `#fields` gives `rows`, of the type arguments `arguments` or none, has a
 * stored form: each main constructor parameter its `parse` reads holds a property, and each field's Map keys have
 * text. Throws the Error serialize gives otherwise. */
static bool sharp_fields_store(zend_class_entry *ce, const sharp_type *arguments, zval *rows)
{
	zval *row;
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(rows), row) {
		HashTable *parts = Z_ARRVAL_P(row);
		zend_string *name = Z_STR_P(zend_hash_index_find(parts, 0));
		zend_string *written = Z_STR_P(zend_hash_index_find(parts, 2));
		if (Z_TYPE_P(zend_hash_index_find(parts, 3)) == IS_TRUE && !zend_hash_exists(&ce->properties_info, name)) {
			sharp_refuse_storage(ce, "the main constructor parameter `%s` holds no property", ZSTR_VAL(name));
			return false;
		}
		const sharp_type *types =
			sharp_declared_types(ZSTR_VAL(written), ZSTR_LEN(written), sharp_field_scope(ce, name), ce, arguments);
		ZEND_ASSERT(types != NULL && "the compile writes each field's type as one PHP# type");
		const sharp_type *key = sharp_type_unstored_key(types->members[0]);
		if (key) {
			sharp_refuse_storage(ce, "its field `%s` is a Map keyed by %s, which has no stored text",
				ZSTR_VAL(name), ZSTR_VAL(key->text));
			return false;
		}
		if (EG(exception)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

zend_result sharp_stored_form(zend_object *object, zval *stored)
{
	zend_class_entry *ce = object->ce;
	zend_function *json = zend_hash_str_find_ptr(&ce->function_table, "#json", sizeof("#json") - 1);
	zend_function *fields = zend_hash_str_find_ptr(&ce->function_table, "#fields", sizeof("#fields") - 1);
	bool own_parse = zend_hash_str_exists(&ce->function_table, "parse", sizeof("parse") - 1);
	bool instance = zend_hash_str_exists(&ce->function_table, "instance", sizeof("instance") - 1);

	ZVAL_UNDEF(stored);
	if (instance) {
		/* A case of an enum that carries no data is the case's one object, so its stored form holds nothing. */
		array_init(stored);
		return SUCCESS;
	}
	if (!json) {
		sharp_refuse_storage(ce, "its own `parse` reads no stored form");
		return FAILURE;
	}
	/* A Hashed's `#json` writes the hash its constructor made from the value its main constructor takes. */
	if (fields && !sharp_is_hashed(ce)) {
		zval rows;
		zend_call_known_function(fields, NULL, ce, &rows, 0, NULL, NULL);
		bool stores = !EG(exception) && sharp_fields_store(ce, sharp_type_arguments(object), &rows);
		zval_ptr_dtor(&rows);
		if (!stores) {
			return FAILURE;
		}
	} else if (!own_parse) {
		sharp_refuse_storage(ce, "a field of it holds a function, which no stored form holds");
		return FAILURE;
	}

	zval written;
	zend_call_known_function(json, object, ce, &written, 0, NULL, NULL);
	if (EG(exception)) {
		return FAILURE;
	}
	if (Z_TYPE(written) != IS_OBJECT) {
		/* A backed struct, spec section 10, stores its value, which its own `parse` reads. */
		array_init_size(stored, 1);
		add_assoc_zval_ex(stored, "value", sizeof("value") - 1, &written);
		return SUCCESS;
	}
	ZVAL_ARR(stored, zend_array_dup(Z_OBJPROP(written)));
	zval_ptr_dtor(&written);

	return SUCCESS;
}

/* `\Sharp\Internal\seal($value, $path)`, spec section 24: the stored form of the field `path`, as
 * `App.SmtpAccount.password`, whose value is `value`, with each Secret in it encrypted for that field, a List's or a
 * Map's elements included. A class's `#json` writes each field of a type that holds a Secret through it. Without
 * `path`, it is the `#json` of the Secret or Hashed `value`: `{"encrypted": …}` for a Secret inside such a field, which
 * any other place refuses, as `serialize` refuses a type with no stored form, and `{"hashed": …}` for a Hashed. */
ZEND_FUNCTION(Sharp_Internal_seal)
{
	zval *value;
	zend_string *path = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ZVAL(value)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(path)
	ZEND_PARSE_PARAMETERS_END();

	if (path) {
		zend_string *outer = SHARP_G(seal_path);
		SHARP_G(seal_path) = path;
		zend_string *text = sharp_stored_text(value);
		SHARP_G(seal_path) = outer;
		if (!text || !sharp_json_decode(text, return_value)) {
			if (text) {
				zend_string_release(text);
			}
			RETURN_THROWS();
		}
		zend_string_release(text);
		return;
	}
	if (Z_TYPE_P(value) != IS_OBJECT || !(sharp_is_secret(Z_OBJCE_P(value)) || sharp_is_hashed(Z_OBJCE_P(value)))) {
		zend_argument_type_error(1, "must be a Secret or a Hashed without a path, %s given", zend_zval_value_name(value));
		RETURN_THROWS();
	}
	zend_object *object = Z_OBJ_P(value);
	zval held;
	if (sharp_is_hashed(object->ce)) {
		zval *hash = zend_read_property(object->ce, object, "hash", sizeof("hash") - 1, true, &held);
		object_init(return_value);
		add_property_zval_ex(return_value, "hashed", sizeof("hashed") - 1, hash);
		if (hash == &held) {
			zval_ptr_dtor(&held);
		}
		return;
	}
	if (!SHARP_G(seal_path)) {
		sharp_refuse_storage(object->ce,
			"a Secret is stored only in a field of a class or struct, whose name its encryption is bound to");
		RETURN_THROWS();
	}
	zval *held_value = zend_read_property(object->ce, object, "value", sizeof("value") - 1, true, &held);
	zend_string *stored = sharp_encrypt(held_value, SHARP_G(seal_path));
	if (held_value == &held) {
		zval_ptr_dtor(&held);
	}
	if (!stored) {
		RETURN_THROWS();
	}
	object_init(return_value);
	add_property_str_ex(return_value, "encrypted", sizeof("encrypted") - 1, stored);
}

/* `\Sharp\Internal\hashValue($value)`: the Argon2id hash, as a PHC string, of the stored text of `value`, which a new
 * `Hashed` holds. */
ZEND_FUNCTION(Sharp_Internal_hashValue)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *text = sharp_stored_text(value);
	if (!text) {
		RETURN_THROWS();
	}
	zval arguments[2];
	ZVAL_STR(&arguments[0], text);
	ZVAL_STRINGL(&arguments[1], "argon2id", sizeof("argon2id") - 1);
	sharp_call_function("password_hash", return_value, 2, arguments);
	zval_ptr_dtor(&arguments[0]);
	zval_ptr_dtor(&arguments[1]);
}

/* `\Sharp\Internal\verifyHash($hash, $value)`: whether `hash`, a Hashed's PHC string, is that of the stored text of
 * `value`. */
ZEND_FUNCTION(Sharp_Internal_verifyHash)
{
	zend_string *hash;
	zval *value;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(hash)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	zend_string *text = sharp_stored_text(value);
	if (!text) {
		RETURN_THROWS();
	}
	zval arguments[2];
	ZVAL_STR(&arguments[0], text);
	ZVAL_STR_COPY(&arguments[1], hash);
	sharp_call_function("password_verify", return_value, 2, arguments);
	zval_ptr_dtor(&arguments[0]);
	zval_ptr_dtor(&arguments[1]);
}

zend_result sharp_read_stored_form(zend_class_entry *ce, zval *stored, const zval *type_arguments,
	sharp_type_class_resolver resolve, void *context, zval *built)
{
	const sharp_type *arguments = NULL;
	ZVAL_UNDEF(built);
	if (Z_TYPE_P(type_arguments) != IS_UNDEF) {
		arguments = sharp_type_arguments_read(ce, type_arguments, resolve, context);
		if (!arguments) {
			return FAILURE;
		}
	}

	zval problems;
	array_init(&problems);
	sharp_read read;
	sharp_read_start(&read, &problems, false, NULL);
	zend_function *instance = zend_hash_str_find_ptr(&ce->function_table, "instance", sizeof("instance") - 1);
	zend_function *parse = zend_hash_str_find_ptr(&ce->function_table, "parse", sizeof("parse") - 1);
	if (zend_hash_str_exists(&ce->function_table, "#fields", sizeof("#fields") - 1)) {
		sharp_read_struct(ce, arguments, stored, &read, built);
	} else if (instance) {
		/* A case of an enum that carries no data is the case's one object. */
		zend_call_known_function(instance, NULL, ce, built, 0, NULL, NULL);
	} else if (parse) {
		/* A backed struct builds through its own `parse` of its value. */
		zval *value = zend_hash_str_find(Z_ARRVAL_P(stored), "value", sizeof("value") - 1);
		if (value) {
			zend_call_known_function(parse, NULL, ce, built, 1, value, NULL);
		} else {
			zval missing;
			ZVAL_STRINGL(&missing, "missing", sizeof("missing") - 1);
			zend_hash_str_update(Z_ARRVAL(problems), "value", sizeof("value") - 1, &missing);
		}
	} else {
		/* A type with a function field has no stored form, so data that holds one was written by hand. */
		sharp_refuse_creation(ce);
	}
	sharp_read_end(&read);
	if (!EG(exception) && zend_hash_num_elements(Z_ARRVAL(problems)) > 0) {
		sharp_throw_invalid_fields(&problems);
	}
	zval_ptr_dtor(&problems);
	if (EG(exception) || Z_ISUNDEF_P(built)) {
		zval_ptr_dtor(built);
		ZVAL_UNDEF(built);
		return FAILURE;
	}

	return SUCCESS;
}

/* Spec section 10: a family's `parse`, `\Sharp\Internal\parseVariant($family, $value)`, which an abstract class's or an
 * enum's calls, and which a call of an interface's runs, since an interface holds no static method's body: the variant
 * `$value` names under the family's key, read as sharp_parse_as reads a value of the family. With `throws` false it is
 * `tryParse`, null wherever `parse` throws a ValueError. */
ZEND_FUNCTION(Sharp_Internal_parseVariant)
{
	zend_string *family;
	zval *value;
	bool throws = true;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_STR(family)
		Z_PARAM_ZVAL(value)
		Z_PARAM_OPTIONAL
		Z_PARAM_BOOL(throws)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_of(family);
	if (!type) {
		RETURN_THROWS();
	}
	sharp_parse_as(type, value, false, return_value);
	if (!throws && EG(exception) && instanceof_function(EG(exception)->ce, zend_ce_value_error)) {
		zend_clear_exception();
	}
}

/* Spec sections 10 and 24: a struct's or a class's `parse`, `Json.decode<T>` and `Query.decode<T>`, the value of the
 * type the class value `type` holds that `value` builds, as sharp_parse_as reads it. */
ZEND_FUNCTION(Sharp_Internal_parseAs)
{
	zend_string *class_value;
	zval *value;
	bool text;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STR(class_value)
		Z_PARAM_ZVAL(value)
		Z_PARAM_BOOL(text)
	ZEND_PARSE_PARAMETERS_END();

	const sharp_type *type = sharp_class_value_read_whole(class_value);
	if (!type) {
		RETURN_THROWS();
	}
	sharp_parse_as(type, value, text, return_value);
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
	method = zend_class_find_method(ce, name);
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

/* The namespace whose binding of the dispatcher, the service of the id dispatcher names, serves the whole app, as spec
 * section 15's "An app replaces the dispatcher once at startup" says: the running test module's, whatever namespace its
 * header names; else that of the app's top modules that bind it, which mago compile writes in the listener table of the
 * event's project, whose modules are read here; else the root namespace, which the standard library's module serves.
 * Returns NULL and throws when the table is damaged. */
static zend_string *sharp_dispatcher_namespace(zend_class_entry *event, const zend_string *dispatcher)
{
	zend_string *key;
	size_t root_length;

	if (SHARP_G(container_test)) {
		/* A test module's binding is keyed "<namespace> <id>". */
		ZEND_HASH_FOREACH_STR_KEY(SHARP_G(container_test), key) {
			if (ZSTR_LEN(key) > ZSTR_LEN(dispatcher)) {
				size_t namespace_length = ZSTR_LEN(key) - ZSTR_LEN(dispatcher) - 1;

				if (ZSTR_VAL(key)[namespace_length] == ' '
						&& memcmp(ZSTR_VAL(key) + namespace_length + 1, ZSTR_VAL(dispatcher), ZSTR_LEN(dispatcher)) == 0) {
					return zend_string_init(ZSTR_VAL(key), namespace_length, 0);
				}
			}
		} ZEND_HASH_FOREACH_END();
	}
	if (event->type == ZEND_USER_CLASS && sharp_find_root(event->info.user.filename, &root_length)) {
		HashTable *table = sharp_listener_table(ZSTR_VAL(event->info.user.filename), root_length);
		zval *namespace;

		if (!table) {
			return NULL;
		}
		namespace = zend_hash_str_find(table, "dispatcher", sizeof("dispatcher") - 1);
		if (Z_TYPE_P(namespace) == IS_STRING) {
			sharp_container_read_modules(event->info.user.filename, Z_STR_P(namespace));

			return EG(exception) ? NULL : zend_string_copy(Z_STR_P(namespace));
		}
	}

	return ZSTR_EMPTY_ALLOC();
}

/* Hands `event` to the app's dispatcher with one function per listener. id is the dispatcher's service, the interface
 * the standard library marks DispatchesEmits, which mago compile names at each emit, so the engine names no class. */
static void sharp_dispatch(zval *event, zend_string *id)
{
	zval listeners, dispatcher;
	zend_string *namespace;
	bool found;

	if (!sharp_listeners_for(event, &listeners)) {
		return;
	}
	namespace = sharp_dispatcher_namespace(Z_OBJCE_P(event), id);
	if (!namespace) {
		zval_ptr_dtor(&listeners);
		return;
	}
	found = sharp_container_service(id, id, namespace, NULL, &dispatcher);
	zend_string_release(namespace);

	if (found) {
		zend_string *name = zend_string_init("dispatch", sizeof("dispatch") - 1, 0);
		zend_function *dispatch = zend_class_find_method(Z_OBJCE(dispatcher), name);
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

/* The newest hold, which the next emit joins, or NULL while no Database.transaction is open. */
static zval *sharp_newest_hold(void)
{
	uint32_t holds = zend_hash_num_elements(&SHARP_G(held_emits));

	return holds ? zend_hash_index_find(&SHARP_G(held_emits), holds - 1) : NULL;
}

/* emit hands the event to the app's dispatcher, which sees every event, one no listener hears included, whoever
 * emits it: an app class, a PHP# package in vendor/ or plain PHP. While a Database.transaction is open, spec section
 * 29, the event waits in its hold instead, so every dispatcher, the app's own included, hears only emits that landed. */
ZEND_FUNCTION(Sharp_Internal_emit)
{
	zval *event, *hold, held;
	zend_string *id;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_OBJECT(event)
		Z_PARAM_STR(id)
	ZEND_PARSE_PARAMETERS_END();

	hold = sharp_newest_hold();
	if (!hold) {
		sharp_dispatch(event, id);
		return;
	}
	array_init_size(&held, 2);
	Z_ADDREF_P(event);
	add_next_index_zval(&held, event);
	add_next_index_str(&held, zend_string_copy(id));
	add_next_index_zval(hold, &held);
}

/* Opens a hold for the transaction Database.transaction begins, inside any hold already open. */
ZEND_FUNCTION(Sharp_Internal_holdEmits)
{
	zval hold;

	ZEND_PARSE_PARAMETERS_NONE();

	array_init(&hold);
	zend_hash_index_update(&SHARP_G(held_emits), zend_hash_num_elements(&SHARP_G(held_emits)), &hold);
}

/* Closes the newest hold into `hold`, false when none is open. */
static bool sharp_close_hold(zval *hold)
{
	uint32_t holds = zend_hash_num_elements(&SHARP_G(held_emits));

	if (!holds) {
		zend_throw_error(NULL, "No emit is held: Database.transaction opens the hold each release or drop closes.");
		return false;
	}
	ZVAL_COPY(hold, zend_hash_index_find(&SHARP_G(held_emits), holds - 1));
	zend_hash_index_del(&SHARP_G(held_emits), holds - 1);

	return true;
}

/* Closes the newest hold once its transaction commits: each emit it held joins the hold around it, or, when none is
 * open, reaches the app's dispatcher in emit order. An exception a dispatch throws drops the emits after it. */
ZEND_FUNCTION(Sharp_Internal_releaseEmits)
{
	zval hold, *held;

	ZEND_PARSE_PARAMETERS_NONE();

	if (!sharp_close_hold(&hold)) {
		RETURN_THROWS();
	}
	ZEND_HASH_FOREACH_VAL(Z_ARRVAL(hold), held) {
		zval *outer = sharp_newest_hold();
		if (outer) {
			Z_TRY_ADDREF_P(held);
			add_next_index_zval(outer, held);
			continue;
		}
		sharp_dispatch(zend_hash_index_find(Z_ARRVAL_P(held), 0), Z_STR_P(zend_hash_index_find(Z_ARRVAL_P(held), 1)));
		if (EG(exception)) {
			break;
		}
	} ZEND_HASH_FOREACH_END();
	zval_ptr_dtor(&hold);
}

/* Closes the newest hold once its transaction rolls back, so no dispatcher hears the emits it held. */
ZEND_FUNCTION(Sharp_Internal_dropEmits)
{
	zval hold;

	ZEND_PARSE_PARAMETERS_NONE();

	if (!sharp_close_hold(&hold)) {
		RETURN_THROWS();
	}
	zval_ptr_dtor(&hold);
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
	created->arguments = NULL;
	created->unchecked_entries = false;

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
	copy->arguments = sharp_table_object_of(object)->arguments;
	copy->unchecked_entries = sharp_table_object_of(object)->unchecked_entries;

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

/* The `List`, `Map` or `Set`, as `built_in` names it, a value of `type` is when it is one: `type` itself, the member of
 * `T?`, or the one member of a union that is such a collection. NULL for any other type, and for no type. */
static const sharp_type *sharp_type_collection_member(const sharp_type *type, sharp_built_in built_in)
{
	if (!type) {
		return NULL;
	}
	switch (type->kind) {
		case SHARP_TYPE_NAMED:
			return !type->class_name && type->built_in == built_in ? type : NULL;
		case SHARP_TYPE_NULLABLE:
			return sharp_type_collection_member(type->members[0], built_in);
		case SHARP_TYPE_UNION:
			for (uint32_t i = 0; i < type->count; i++) {
				const sharp_type *member = sharp_type_collection_member(type->members[i], built_in);
				if (member) {
					return member;
				}
			}
			return NULL;
		default:
			return NULL;
	}
}

/* The value a PHP array crosses into, as `plan` describes the PHP# type it enters: ['map', key, values],
 * ['set', elements] or ['list', elements], each inner plan null when that part holds no Map or Set. A value that is
 * not a PHP array is the object already, as the bridge's own crossing leaves it. `type` is that PHP# type, or NULL
 * when the caller has none, and each Map and Set the crossing makes takes its type from it when it is made, so each
 * write plain PHP makes into one is checked, as in a table a typed boundary took. */
static bool sharp_crossed(zval *result, zval *value, const zval *plan, const sharp_type *type)
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
		const sharp_type *list = sharp_type_collection_member(type, SHARP_BUILT_IN_LIST);
		const sharp_type *elements = list ? sharp_type_elements(list) : NULL;
		array_init_size(result, zend_hash_num_elements(entries));
		ZEND_HASH_FOREACH_KEY_VAL(entries, index, name, element) {
			if (sharp_out_of_memory() || !sharp_crossed(&crossed, element, inner, elements)) {
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
	const sharp_type *collection =
		sharp_type_collection_member(type, is_set ? SHARP_BUILT_IN_SET : SHARP_BUILT_IN_MAP);
	const sharp_type *elements = collection ? sharp_type_elements(collection) : NULL;
	sharp_table_object *table =
		sharp_table_object_create(is_set ? sharp_ce_set : sharp_ce_map, zend_hash_num_elements(entries));
	ZVAL_OBJ(result, &table->std);
	if (collection) {
		sharp_table_type_store(&table->std, collection);
	}
	ZEND_HASH_FOREACH_KEY_VAL(entries, index, name, element) {
		if (sharp_out_of_memory() || !sharp_crossed(&crossed, element, inner, elements)) {
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

/* The plan sharp_crossed reads, as the bridge's #[\Sharp\Crossing] writes it, for a value of `type`: ['map', key,
 * values], ['set', elements] or ['list', elements]. Null where `type` names no Map or Set a PHP array becomes, as for
 * `Any`, a class or a `List` of ints. */
static void sharp_type_plan(zval *plan, const sharp_type *type)
{
	ZVAL_NULL(plan);
	if (type->kind == SHARP_TYPE_NULLABLE) {
		sharp_type_plan(plan, type->members[0]);
		return;
	}
	if (type->kind != SHARP_TYPE_NAMED || type->class_name) {
		return;
	}
	const char *kind;
	switch (type->built_in) {
		case SHARP_BUILT_IN_LIST:
			kind = "list";
			break;
		case SHARP_BUILT_IN_MAP:
			kind = "map";
			break;
		case SHARP_BUILT_IN_SET:
			kind = "set";
			break;
		default:
			return;
	}
	zval inner;
	sharp_type_plan(&inner, sharp_type_elements(type));
	if (type->built_in == SHARP_BUILT_IN_LIST && Z_TYPE(inner) == IS_NULL) {
		return;
	}

	array_init_size(plan, 3);
	add_next_index_string(plan, kind);
	if (type->built_in == SHARP_BUILT_IN_MAP) {
		/* The key as PHP holds it, a string back from an all-digit one, or the case of the backed enum it names. */
		const sharp_type *key = type->members[0];
		zend_class_entry *backed = key->kind == SHARP_TYPE_NAMED && key->class_name
			? zend_lookup_class(key->class_name) : NULL;
		if (key->kind == SHARP_TYPE_NAMED && key->built_in == SHARP_BUILT_IN_STRING) {
			add_next_index_string(plan, "string");
		} else if (backed && (backed->ce_flags & ZEND_ACC_ENUM) && backed->enum_backing_type != IS_UNDEF) {
			add_next_index_str(plan, zend_string_copy(key->class_name));
		} else {
			add_next_index_null(plan);
		}
	}
	add_next_index_zval(plan, &inner);
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

bool sharp_property_reads_as_array(const zend_property_info *info)
{
	const zend_attribute *crossing = sharp_crossing_of(info);

	return crossing && (crossing->argc < 2 || Z_TYPE(crossing->args[1].value) != IS_FALSE);
}

bool sharp_cross_property(const zend_property_info *info, zval *value)
{
	zend_attribute *crossing = sharp_crossing_of(info);
	zval plan, crossed;
	if (!crossing || zend_get_attribute_value(&plan, crossing, 0, info->ce) == FAILURE) {
		return false;
	}

	/* A type that names the class's type parameters needs the object, which no caller has here, so the Maps and Sets
	 * the crossing makes for it take their type at the next typed boundary instead. */
	const sharp_type *type = info->sharp_type && !sharp_property_is_open(info) ? sharp_property_type(info, NULL) : NULL;
	bool is_crossed = sharp_crossed(&crossed, value, &plan, type);
	zval_ptr_dtor(&plan);
	if (!is_crossed) {
		return false;
	}
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &crossed);

	return true;
}

/* Appends the Map key `key` as PHP# writes it: `5`, `"5"`, or a backed enum case as `Shop.Level.Low`. */
static ZEND_COLD void sharp_key_text(smart_str *text, const zval *key)
{
	if (Z_TYPE_P(key) == IS_LONG) {
		smart_str_append_long(text, Z_LVAL_P(key));
	} else if (Z_TYPE_P(key) == IS_STRING) {
		smart_str_appendc(text, '"');
		smart_str_append(text, Z_STR_P(key));
		smart_str_appendc(text, '"');
	} else {
		sharp_object_text(text, Z_OBJ_P(key));
		smart_str_appendc(text, '.');
		smart_str_append(text, Z_STR_P(zend_enum_fetch_case_name(Z_OBJ_P(key))));
	}
}

/* Throws for the Map `object`, whose PHP array lost an entry: two of its keys are one PHP array key, as 5 and "5" are,
 * or a backed enum case and its backing value. The exception names the first two such keys. A Map carries no key type
 * when it runs, so the message names none. */
static ZEND_COLD void sharp_table_refuse_collision(zend_object *object)
{
	sharp_table *table = sharp_table_of(object);
	HashTable seen;
	zend_hash_init(&seen, table->size, NULL, NULL, false);
	smart_str message = {0};
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *key = &bucket->key, *array_key = key;
		if (Z_TYPE_P(key) == IS_OBJECT) {
			array_key = zend_enum_fetch_case_value(Z_OBJ_P(key));
		}
		bool is_index = Z_TYPE_P(array_key) == IS_LONG;
		zval *first = is_index
			? zend_hash_index_find(&seen, Z_LVAL_P(array_key))
			: zend_symtable_find(&seen, Z_STR_P(array_key));
		if (!first) {
			zval held;
			ZVAL_PTR(&held, key);
			if (is_index) {
				zend_hash_index_add_new(&seen, Z_LVAL_P(array_key), &held);
			} else {
				zend_symtable_add_new(&seen, Z_STR_P(array_key), &held);
			}
		} else {
			smart_str_appends(&message, "This `Map` holds both `");
			sharp_key_text(&message, Z_PTR_P(first));
			smart_str_appends(&message, "` and `");
			sharp_key_text(&message, key);
			smart_str_appends(&message, "`, which plain PHP keeps as one key, so it can't become a plain PHP array");
			break;
		}
	} SHARP_TABLE_FOREACH_END();
	zend_hash_destroy(&seen);
	smart_str_0(&message);
	zend_value_error("%s", ZSTR_VAL(message.s));
	smart_str_free(&message);
}

/* Puts in `result` the PHP array of the Map or Set `object`, each value as sharp_array_of gives it, and gives whether
 * it could. A Map key a PHP array holds is an int, a string, or a backed enum case as its backing value. On false,
 * `result` is undefined, and `unheld` points at the first key that is none of them, or is NULL after an exception:
 * a value's own crossing threw, or two keys of the Map are one PHP array key, as 5 and "5" are. A PHP array would keep
 * only one of those two entries, so the crossing throws rather than drop one, and only when the array came out with
 * fewer entries than the Map. */
static bool sharp_table_array(zval *result, zend_object *object, zval **unheld)
{
	sharp_table *table = sharp_table_entries(object);
	bool is_set = object->ce == sharp_ce_set;
	*unheld = NULL;
	if (!table) {
		ZVAL_UNDEF(result);
		return false;
	}
	array_init_size(result, table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval *key = &bucket->key, converted;
		if (!is_set && Z_TYPE_P(key) == IS_OBJECT && (Z_OBJCE_P(key)->ce_flags & ZEND_ACC_ENUM)
				&& Z_OBJCE_P(key)->enum_backing_type != IS_UNDEF) {
			key = zend_enum_fetch_case_value(Z_OBJ_P(key));
		}
		if (!is_set && Z_TYPE_P(key) != IS_LONG && Z_TYPE_P(key) != IS_STRING) {
			*unheld = key;
			break;
		}
		sharp_array_of(&converted, is_set ? key : &bucket->value);
		if (UNEXPECTED(EG(exception)) || sharp_out_of_memory()) {
			zval_ptr_dtor(&converted);
			break;
		}
		if (is_set) {
			zend_hash_next_index_insert_new(Z_ARRVAL_P(result), &converted);
		} else if (Z_TYPE_P(key) == IS_LONG) {
			zend_hash_index_update(Z_ARRVAL_P(result), Z_LVAL_P(key), &converted);
		} else {
			zend_symtable_update(Z_ARRVAL_P(result), Z_STR_P(key), &converted);
		}
	} SHARP_TABLE_FOREACH_END();

	if (!*unheld && !EG(exception) && UNEXPECTED(zend_hash_num_elements(Z_ARRVAL_P(result)) < table->size)) {
		sharp_table_refuse_collision(object);
	}
	if (*unheld || EG(exception)) {
		zval_ptr_dtor(result);
		ZVAL_UNDEF(result);
		return false;
	}

	return true;
}

void sharp_array_of(zval *result, zval *value)
{
	ZVAL_DEREF(value);
	if (SHARP_IS_TABLE(value)) {
		zval *unheld;
		if (!sharp_table_array(result, Z_OBJ_P(value), &unheld)) {
			ZVAL_COPY(result, value);
		}
		return;
	}
	if (sharp_is_bytes(value)) {
		ZVAL_COPY(result, sharp_bytes_data(Z_OBJ_P(value)));
		return;
	}
	if (Z_TYPE_P(value) != IS_ARRAY || zend_hash_num_elements(Z_ARRVAL_P(value)) == 0) {
		ZVAL_COPY(result, value);
		return;
	}

	/* A PHP array that holds itself through a reference is plain PHP's, and keeps what it holds. */
	HashTable *array = Z_ARRVAL_P(value);
	if (GC_IS_RECURSIVE(array)) {
		ZVAL_COPY(result, value);
		return;
	}
	GC_TRY_PROTECT_RECURSION(array);
	HashTable *converted = NULL;
	zend_ulong index;
	zend_string *name;
	zval *element;
	ZEND_HASH_FOREACH_KEY_VAL(array, index, name, element) {
		zval *held = element;
		ZVAL_DEREF(held);
		if (!SHARP_IS_COLLECTION(held) && !sharp_is_bytes(held)) {
			continue;
		}
		zval crossed;
		sharp_array_of(&crossed, held);
		if (UNEXPECTED(EG(exception)) || sharp_out_of_memory()) {
			zval_ptr_dtor(&crossed);
			break;
		}
		if (!converted && Z_TYPE(crossed) == Z_TYPE_P(held)
				&& (Z_TYPE(crossed) == IS_ARRAY ? Z_ARR(crossed) == Z_ARR_P(held) : Z_OBJ(crossed) == Z_OBJ_P(held))) {
			zval_ptr_dtor(&crossed);
			continue;
		}
		if (!converted) {
			converted = zend_array_dup(array);
		}
		if (name) {
			zend_hash_update(converted, name, &crossed);
		} else {
			zend_hash_index_update(converted, index, &crossed);
		}
	} ZEND_HASH_FOREACH_END();
	GC_TRY_UNPROTECT_RECURSION(array);

	if (converted && UNEXPECTED(EG(exception))) {
		zend_array_destroy(converted);
		ZVAL_COPY(result, value);
	} else if (converted) {
		ZVAL_ARR(result, converted);
	} else {
		ZVAL_COPY(result, value);
	}
}

void sharp_cross_read(zval *value)
{
	zval array;
	sharp_array_of(&array, value);
	zval_ptr_dtor(value);
	ZVAL_COPY_VALUE(value, &array);
}

bool sharp_cross_slot_read(zval *array, zend_object *object, zval *slot)
{
	zval *value = slot;
	ZVAL_DEREF(value);
	if (Z_TYPE_P(value) != IS_ARRAY && Z_TYPE_P(value) != IS_OBJECT) {
		return false;
	}
	const zend_property_info *info = zend_get_property_info_for_slot(object, slot);
	if (!info || !(info->flags & ZEND_ACC_SHARP_CROSSING) || sharp_runs_sharp_code()) {
		return false;
	}
	sharp_array_of(array, value);

	return true;
}

void sharp_cross_declared_slots(zval *result, zend_object *object)
{
	if (UNEXPECTED(zend_object_is_lazy_proxy(object) && zend_lazy_object_initialized(object))) {
		object = zend_lazy_object_get_instance(object);
	}
	for (int i = 0; i < object->ce->default_properties_count; i++) {
		zval *slot = &object->properties_table[i], array;
		if (sharp_cross_slot_read(&array, object, slot)) {
			SEPARATE_ARRAY(result);
			zend_symtable_update(Z_ARRVAL_P(result), zend_get_property_info_for_slot(object, slot)->name, &array);
		}
	}
}

static bool sharp_plan_rebuilds(const zval *plan)
{
	if (Z_TYPE_P(plan) != IS_ARRAY) {
		return false;
	}
	const zval *inner = zend_hash_index_find(Z_ARRVAL_P(plan), zend_hash_num_elements(Z_ARRVAL_P(plan)) - 1);

	return Z_TYPE_P(inner) == IS_NULL || sharp_plan_rebuilds(inner);
}

static bool sharp_property_rebuilds(const zend_property_info *info)
{
	zend_attribute *crossing = sharp_crossing_of(info);
	zval plan;
	if (!crossing || zend_get_attribute_value(&plan, crossing, 0, info->ce) == FAILURE) {
		return false;
	}
	bool rebuilds = sharp_plan_rebuilds(&plan);
	zval_ptr_dtor(&plan);

	return rebuilds;
}

bool sharp_property_takes_reference(zval *slot, const zend_property_info *info)
{
	if ((info->flags & (ZEND_ACC_SHARP_CROSSING|ZEND_ACC_STATIC)) != ZEND_ACC_SHARP_CROSSING || sharp_runs_sharp_code()) {
		return true;
	}
	if (sharp_property_rebuilds(info)) {
		sharp_cross_read(slot);
	}

	return !EG(exception);
}

static bool sharp_reference_holds(const zend_property_info *info, zend_object *object, zval *value)
{
	const sharp_type *type = info->sharp_type ? sharp_property_type(info, object) : NULL;
	if (info->sharp_type && !type) {
		return false;
	}
	if (type) {
		sharp_type_renumber(type, value);
	}
	if (type && !sharp_type_accepts(type, value, NULL)) {
		if (!EG(exception)) {
			smart_str given = {0};
			sharp_value_type_text(&given, value);
			zend_string *problem = sharp_first_problem(type, value);
			zend_type_error("Reference held by property %s::$%s of type %s holds %s%s", ZSTR_VAL(info->ce->name),
				zend_get_unmangled_property_name(info->name), ZSTR_VAL(type->text), ZSTR_VAL(given.s), ZSTR_VAL(problem));
			zend_string_release(problem);
			smart_str_free(&given);
		}
		return false;
	}
	if (!(info->flags & ZEND_ACC_SHARP_CROSSING) || sharp_cross_property(info, value)) {
		return true;
	}
	if (!EG(exception)) {
		zend_throw_error(NULL, "Cannot rebuild property %s::$%s from the array its reference holds",
			ZSTR_VAL(info->ce->name), zend_get_unmangled_property_name(info->name));
	}

	return false;
}

/* Whether the reference `slot` holds is one PHP# code made to pass the property by reference, as to a `ref` receiver,
 * that nothing else holds: what it holds is what PHP# code wrote, so it needs no check. Plain PHP code that takes the
 * reference clears the mark, see sharp_property_for_plain_write and sharp_object_exposed. */
static bool sharp_reference_is_own(zval *slot)
{
	zend_reference *reference = Z_REF_P(slot);

	return (GC_FLAGS(reference) & IS_REF_SHARP_MADE) && GC_REFCOUNT(reference) == 1;
}

zval *sharp_property_from_reference(zval *slot, zend_object *object, const zend_property_info *info, zval *rv)
{
	zend_reference *reference = Z_REF_P(slot);
	ZVAL_COPY(rv, &reference->val);
	if (!sharp_reference_is_own(slot) && (!sharp_reference_holds(info, object, rv) || GC_REFCOUNT(reference) > 1)) {
		return rv;
	}

	ZEND_REF_DEL_TYPE_SOURCE(reference, (zend_property_info *) info);
	zval_ptr_dtor(slot);
	ZVAL_COPY_VALUE(slot, rv);

	return slot;
}

bool sharp_property_for_write(zend_object *object, zval *slot, const zend_property_info *info)
{
	/* The unset fetch before a `ref` change's ZEND_SEPARATE takes the reference the change made, see
	 * zend_sharp_separate_place. */
	if (sharp_reference_is_own(slot)) {
		return true;
	}
	zval value;
	if (sharp_property_from_reference(slot, object, info, &value) == slot) {
		return true;
	}
	if (EG(exception)) {
		zval_ptr_dtor(&value);
		return false;
	}
	/* An array plain PHP still holds a reference to is written in place, as plain PHP's writes through it are. */
	if (!(info->flags & ZEND_ACC_SHARP_CROSSING)) {
		zval_ptr_dtor(&value);
		return true;
	}

	/* A `ref` member's fetch finds the array the reference holds for plain PHP, and the reference holds the Map or the
	 * Set it crosses back into while the member changes it in place. The unset fetch after the call finds that table,
	 * and the reference takes the array it crosses into, with the typed-reference assignment, so plain PHP sees the
	 * change, see zend_sharp_separate_place. */
	zend_reference *reference = Z_REF_P(slot);
	if (SHARP_IS_TABLE(&reference->val)) {
		return zend_try_assign_typed_ref(reference, &value) == SUCCESS;
	}
	zval_ptr_dtor(&reference->val);
	ZVAL_COPY_VALUE(&reference->val, &value);

	return true;
}

void sharp_object_exposed(zend_object *object)
{
	if (!(object->ce->sharp_flags & SHARP_CLASS_HAS_TYPED_PROPS) || sharp_runs_sharp_code()) {
		return;
	}
	zval *slot = object->properties_table;
	zval *end = slot + object->ce->default_properties_count;
	for (; slot < end; slot++) {
		if (Z_ISREF_P(slot)) {
			GC_DEL_FLAGS(Z_REF_P(slot), IS_REF_SHARP_MADE);
		}
	}
}

void sharp_property_for_plain_write(zval *slot, const zend_property_info *info, uint32_t flags)
{
	if (Z_ISREF_P(slot)) {
		GC_DEL_FLAGS(Z_REF_P(slot), IS_REF_SHARP_MADE);
		return;
	}
	if (Z_ISERROR_P(slot)) {
		return;
	}
	/* A Map or a Set checks each write itself, with the type it got when it was made or at its first typed boundary. */
	if (SHARP_IS_TABLE(slot)) {
		SHARP_SEPARATE(slot);
		return;
	}
	/* The slot becomes a reference whatever it holds, before the write promotes it in place, as an element write
	 * makes an array of null, false, and a slot the constructor never set. An object holds no element PHP writes
	 * into in place, since a write into it goes through the object's own handlers, so it stays as it is. */
	if (Z_TYPE_P(slot) == IS_UNDEF) {
		if (flags != ZEND_FETCH_DIM_WRITE) {
			return;
		}
		ZVAL_NULL(slot);
	}
	if (Z_TYPE_P(slot) != IS_OBJECT) {
		ZVAL_NEW_REF(slot, slot);
		if (ZEND_TYPE_IS_SET(info->type)) {
			ZEND_REF_ADD_TYPE_SOURCE(Z_REF_P(slot), (zend_property_info *) info);
		}
	}
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

/* A Map as a list of [key, value] pairs and a Set as a list of its elements, as toList gives them. */
static HashTable *sharp_table_get_debug_info(zend_object *object, int *is_temp)
{
	*is_temp = 1;

	return sharp_table_to_list(object);
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

/* The type of the Map or Set `object`: `Map<string, int>` for the table made with, or given at its first typed
 * boundary, the type arguments `string, int`, or NULL for a table that has none. */
static const sharp_type *sharp_table_type(zend_object *object)
{
	const sharp_type *arguments = sharp_table_object_of(object)->arguments;
	if (!arguments) {
		return NULL;
	}
	smart_str text = {0};
	smart_str_appends(&text, object->ce == sharp_ce_map ? "Map<" : "Set<");
	smart_str_append(&text, arguments->text);
	smart_str_appendc(&text, '>');
	smart_str_0(&text);
	const sharp_type *type = sharp_type_list(ZSTR_VAL(text.s), ZSTR_LEN(text.s));
	smart_str_free(&text);
	ZEND_ASSERT(type != NULL);

	return type->members[0];
}

/* The type plain PHP's write into the Map or Set `object` takes, see sharp_table_type. NULL for a write PHP# code
 * makes, which the checker proved, and for a table that has no type. */
static const sharp_type *sharp_table_written_type(zend_object *object)
{
	return sharp_runs_sharp_code() ? NULL : sharp_table_type(object);
}

static sharp_table *sharp_table_entries(zend_object *object)
{
	sharp_table_object *table = sharp_table_object_of(object);
	if (EXPECTED(!table->unchecked_entries) || !sharp_runs_sharp_code()) {
		return &table->table;
	}
	const sharp_type *type = sharp_table_type(object);
	const sharp_type *elements = type ? sharp_type_elements(type) : NULL;
	if (!elements) {
		return &table->table;
	}
	zval plan;
	sharp_type_plan(&plan, type);
	const zval *plan_of_values = Z_TYPE(plan) == IS_ARRAY
		? zend_hash_index_find(Z_ARRVAL(plan), zend_hash_num_elements(Z_ARRVAL(plan)) - 1) : NULL;
	bool checked = true, unchecked = false;
	SHARP_TABLE_FOREACH(&table->table, bucket) {
		if (!Z_ISREF(bucket->value)) {
			continue;
		}
		zval value;
		ZVAL_COPY(&value, Z_REFVAL(bucket->value));
		sharp_type_renumber(elements, &value);
		if (!sharp_type_accepts(elements, &value, NULL)) {
			if (!EG(exception)) {
				smart_str given = {0}, key = {0};
				sharp_value_type_text(&given, &value);
				sharp_value_text(&key, &bucket->key);
				smart_str_0(&key);
				zend_string *problem = sharp_first_problem(elements, &value);
				zend_type_error("%s holds %s under the key %s%s", ZSTR_VAL(type->text), ZSTR_VAL(given.s),
					ZSTR_VAL(key.s), ZSTR_VAL(problem));
				zend_string_release(problem);
				smart_str_free(&given);
				smart_str_free(&key);
			}
			zval_ptr_dtor(&value);
			checked = false;
			break;
		}
		/* A PHP array plain PHP wrote where a Map or a Set belongs crosses into it, as a write of the whole entry does. */
		bool crosses = plan_of_values && Z_TYPE_P(plan_of_values) == IS_ARRAY && Z_TYPE(value) == IS_ARRAY;
		if (crosses) {
			zval crossed;
			if (!sharp_crossed(&crossed, &value, plan_of_values, elements)) {
				zval_ptr_dtor(&value);
				checked = false;
				break;
			}
			zval_ptr_dtor(&value);
			ZVAL_COPY_VALUE(&value, &crossed);
		}
		if (Z_REFCOUNT(bucket->value) == 1) {
			zval_ptr_dtor(&bucket->value);
			ZVAL_COPY_VALUE(&bucket->value, &value);
		} else if (crosses) {
			/* Plain PHP still holds the reference, which then holds the Map or the Set too, as PHP# reads it. */
			zval_ptr_dtor(Z_REFVAL(bucket->value));
			ZVAL_COPY_VALUE(Z_REFVAL(bucket->value), &value);
			unchecked = true;
		} else {
			/* Plain PHP still holds the reference, so its next write may change the entry again. */
			zval_ptr_dtor(&value);
			unchecked = true;
		}
	} SHARP_TABLE_FOREACH_END();
	zval_ptr_dtor(&plan);
	if (!checked) {
		return NULL;
	}
	table->unchecked_entries = unchecked;

	return &table->table;
}

/* Throws the TypeError of plain PHP's write into the table of `type`, as C# throws a wrong element's write into an
 * array at the write: at the entry's path, `["k"] expected int, got string 'x'`, or naming the key when the key is
 * wrong, `Cannot use string 'zzz' as a key of Map<Demo.Level, int>: expected Demo.Level`. `value` is NULL for a write
 * into an entry, as `$map[$key][] = $element`. */
static ZEND_COLD void sharp_table_refuse(const sharp_type *type, const sharp_type *keys, bool key_holds,
	const zval *key, const zval *value)
{
	if (keys && !key_holds) {
		smart_str given = {0};
		sharp_value_text(&given, key);
		smart_str_0(&given);
		zend_type_error("Cannot use %s as a key of %s: expected %s", ZSTR_VAL(given.s), ZSTR_VAL(type->text),
			ZSTR_VAL(keys->text));
		smart_str_free(&given);
		return;
	}

	zval problems;
	array_init(&problems);
	sharp_type_problems found = {&problems, {0}};
	sharp_type_entry_holds(keys, key_holds, key, sharp_type_elements(type), key_holds ? value : NULL,
		SHARP_HOLDS_ENTRY_INT_AS_FLOAT, &found);
	smart_str_free(&found.path);

	smart_str written = {0};
	if (value) {
		smart_str_appends(&written, "Cannot assign ");
		sharp_value_type_text(&written, value);
	} else {
		smart_str_appends(&written, "Cannot write an entry");
	}
	smart_str_0(&written);
	zend_string *path;
	zval *problem;
	ZEND_HASH_FOREACH_STR_KEY_VAL(Z_ARRVAL(problems), path, problem) {
		zend_type_error("%s to %s: %s %s", ZSTR_VAL(written.s), ZSTR_VAL(type->text), ZSTR_VAL(path),
			Z_STRVAL_P(problem));
		break;
	} ZEND_HASH_FOREACH_END();
	if (!EG(exception)) {
		zend_type_error("%s to %s", ZSTR_VAL(written.s), ZSTR_VAL(type->text));
	}
	smart_str_free(&written);
	zval_ptr_dtor(&problems);
}

/* What plain PHP's write of `value` under `offset` into the Map `object` stores, spec section 12: the key and the value
 * a PHP array's entry crosses into, as the Map's own type takes them, so the write throws where a wrong entry lands.
 * A Set's element is `value` under no `offset`, and a write into an entry, as `$map[$key][] = $element`, has no
 * `value`. False after the TypeError, with neither `key` nor `element` set. */
static bool sharp_table_entry(zend_object *object, zval *offset, zval *value, zval *key, zval *element)
{
	const sharp_type *type = sharp_table_written_type(object);
	if (offset) {
		ZVAL_COPY(key, offset);
	}
	if (value) {
		ZVAL_COPY_DEREF(element, value);
	}
	if (!type) {
		return true;
	}

	const sharp_type *keys = type->built_in == SHARP_BUILT_IN_MAP ? type->members[0] : NULL;
	const sharp_type *elements = sharp_type_elements(type);
	zval plan, at;
	sharp_type_plan(&plan, type);
	ZVAL_LONG(&at, sharp_table_of(object)->size);
	bool key_holds = true;
	if (keys && (Z_TYPE_P(offset) == IS_LONG || Z_TYPE_P(offset) == IS_STRING)) {
		/* Plain PHP writes the key it would write into a PHP array, so "5" is the int 5 a Map<string, …> holds as
		 * "5", and a backed enum's backing value is its case. */
		zend_ulong index = Z_TYPE_P(offset) == IS_LONG ? (zend_ulong) Z_LVAL_P(offset) : 0;
		zend_string *name = Z_TYPE_P(offset) == IS_STRING
			&& !ZEND_HANDLE_NUMERIC_STR(Z_STRVAL_P(offset), Z_STRLEN_P(offset), index) ? Z_STR_P(offset) : NULL;
		key_holds = sharp_type_holds_key(keys, index, name);
		if (key_holds) {
			zval_ptr_dtor(key);
			key_holds = sharp_crossed_key(key, zend_hash_index_find(Z_ARRVAL(plan), 1), index, name);
			if (!key_holds) {
				ZVAL_COPY(key, offset);
			}
		}
	} else if (keys) {
		key_holds = sharp_type_holds(keys, offset, SHARP_HOLDS_ENTRY, NULL);
	}

	bool taken = key_holds && !EG(exception);
	if (taken && value) {
		sharp_type_renumber(elements, element);
		taken = sharp_type_accepts(elements, element, NULL);
		zval crossed;
		if (taken && sharp_crossed(&crossed, element,
				zend_hash_index_find(Z_ARRVAL(plan), zend_hash_num_elements(Z_ARRVAL(plan)) - 1), elements)) {
			zval_ptr_dtor(element);
			ZVAL_COPY_VALUE(element, &crossed);
		}
	}
	if (!taken && !EG(exception)) {
		sharp_table_refuse(type, keys, key_holds, keys ? offset : &at, value);
	}
	zval_ptr_dtor(&plan);
	if (!taken || EG(exception)) {
		if (offset) {
			zval_ptr_dtor(key);
		}
		if (value) {
			zval_ptr_dtor(element);
		}
		return false;
	}

	return true;
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

	sharp_table *table = sharp_table_entries(object);
	if (!table) {
		return NULL;
	}
	if (type == BP_VAR_W || type == BP_VAR_RW) {
		zval null, key;
		if (!sharp_table_entry(object, offset, NULL, &key, NULL)) {
			return NULL;
		}
		ZVAL_NULL(&null);
		sharp_table_bucket *bucket = sharp_table_find(table, &key);
		if (!bucket && !EG(exception) && sharp_table_put(table, &key, &null)) {
			bucket = sharp_table_find(table, &key);
		}
		zval_ptr_dtor(&key);
		if (!bucket) {
			return NULL;
		}
		ZVAL_MAKE_REF(&bucket->value);
		/* Plain PHP's write into a typed Map's entry, which no check sees, goes through a reference the entry keeps
		 * once the write ends, since rv holds it too, so PHP# code checks the entry before it reads it. */
		if (sharp_table_written_type(object)) {
			sharp_table_object_of(object)->unchecked_entries = true;
			ZVAL_COPY(rv, &bucket->value);
			return rv;
		}
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
	zval key, element;
	if (object->ce == sharp_ce_set) {
		if (offset) {
			zend_throw_error(NULL, "A Set adds an element with $set[] = $element");
			return;
		}
		if (!sharp_table_entry(object, NULL, value, NULL, &element)) {
			return;
		}
		zval null;
		ZVAL_NULL(&null);
		sharp_table_put(sharp_table_of(object), &element, &null);
		zval_ptr_dtor(&element);
		return;
	}
	if (!offset) {
		zend_throw_error(NULL, "A Map write needs a key");
		return;
	}
	if (!sharp_table_entry(object, offset, value, &key, &element)) {
		return;
	}
	sharp_table_put(sharp_table_of(object), &key, &element);
	zval_ptr_dtor(&key);
	zval_ptr_dtor(&element);
}

static int sharp_table_has_dimension(zend_object *object, zval *offset, int check_empty)
{
	sharp_table *table = sharp_table_entries(object);
	sharp_table_bucket *bucket = table ? sharp_table_find(table, offset) : NULL;

	if (!bucket) {
		return 0;
	}
	zval *held = object->ce == sharp_ce_set ? &bucket->key : &bucket->value;
	ZVAL_DEREF(held);

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
	zval *held = Z_OBJCE(it->data) == sharp_ce_set ? &bucket->key : &bucket->value;
	ZVAL_DEREF(held);

	return held;
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
	if (!sharp_table_entries(Z_OBJ_P(object))) {
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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Table___unserialize, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
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
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, typeArguments, IS_STRING, 1, "null")
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

/* The text JSON writes for a key: an int, a string, or the value of a backed enum case or of a struct backed by an
 * int or a string, as spec section 10 keys a JSON object by a backed struct. NULL after an exception for any other
 * key, which has no text. */
static zend_string *sharp_table_json_key(zval *key)
{
	if (Z_TYPE_P(key) == IS_OBJECT && (Z_OBJCE_P(key)->ce_flags & ZEND_ACC_ENUM)
			&& Z_OBJCE_P(key)->enum_backing_type != IS_UNDEF) {
		key = zend_enum_fetch_case_value(Z_OBJ_P(key));
	}
	if (Z_TYPE_P(key) == IS_LONG || Z_TYPE_P(key) == IS_STRING) {
		return zval_get_string(key);
	}
	zend_function *backed = Z_TYPE_P(key) == IS_OBJECT ? sharp_backed_value_method(Z_OBJCE_P(key), true) : NULL;
	if (backed) {
		zval value;
		zend_call_known_instance_method_with_0_params(backed, Z_OBJ_P(key), &value);
		if (EG(exception)) {
			return NULL;
		}
		if (Z_TYPE(value) == IS_LONG || Z_TYPE(value) == IS_STRING) {
			zend_string *text = zval_get_string(&value);
			zval_ptr_dtor(&value);
			return text;
		}
		zval_ptr_dtor(&value);
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
	sharp_table *table = sharp_table_entries(object);
	if (!table) {
		RETURN_THROWS();
	}
	HashTable *encoded = zend_new_array(table->size);
	SHARP_TABLE_FOREACH(table, bucket) {
		if (sharp_out_of_memory()) {
			zend_array_release(encoded);
			RETURN_THROWS();
		}
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
		zval value;
		ZVAL_COPY_DEREF(&value, &bucket->value);
		zend_hash_update(encoded, key, &value);
		zend_string_release(key);
	} SHARP_TABLE_FOREACH_END();

	if (object->ce == sharp_ce_set) {
		RETURN_ARR(encoded);
	}
	object_and_properties_init(return_value, zend_standard_class_def, encoded);
}

/* The PHP array sharp_array_of gives, when every key is one a PHP array holds. */
ZEND_METHOD(Sharp_Table, toArray)
{
	ZEND_PARSE_PARAMETERS_NONE();

	zval *unheld;
	if (!sharp_table_array(return_value, Z_OBJ_P(ZEND_THIS), &unheld)) {
		if (!unheld) {
			RETURN_THROWS();
		}
		zend_value_error("A PHP array key is an int or a string, and this Map holds a key of type %s",
			Z_TYPE_P(unheld) == IS_OBJECT ? ZSTR_VAL(Z_OBJCE_P(unheld)->name) : zend_zval_value_name(unheld));
		RETURN_THROWS();
	}
}

/* A Map's keys or values, as sharp_table_list gives them. */
static void sharp_map_list(INTERNAL_FUNCTION_PARAMETERS, bool values)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_table *table = sharp_table_entries(Z_OBJ_P(ZEND_THIS));
	if (!table) {
		RETURN_THROWS();
	}
	sharp_table_list(table, values, return_value);
}

ZEND_METHOD(Sharp_Map, keys)
{
	sharp_map_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, false);
}

ZEND_METHOD(Sharp_Map, values)
{
	sharp_map_list(INTERNAL_FUNCTION_PARAM_PASSTHRU, true);
}

/* A Map's toList and a Set's: the List of its entries or its elements (sharp_table_to_list). */
ZEND_METHOD(Sharp_Table, toList)
{
	ZEND_PARSE_PARAMETERS_NONE();

	if (!sharp_table_entries(Z_OBJ_P(ZEND_THIS))) {
		RETURN_THROWS();
	}
	RETURN_ARR(sharp_table_to_list(Z_OBJ_P(ZEND_THIS)));
}

/* Each entry, key then value, in insertion order: the list __construct takes, which __unserialize runs on. */
ZEND_METHOD(Sharp_Map, __serialize)
{
	ZEND_PARSE_PARAMETERS_NONE();

	sharp_table *table = sharp_table_entries(Z_OBJ_P(ZEND_THIS));
	if (!table) {
		RETURN_THROWS();
	}
	HashTable *keys_and_values = zend_new_array(table->size * 2);
	SHARP_TABLE_FOREACH(table, bucket) {
		zval value;
		Z_TRY_ADDREF(bucket->key);
		zend_hash_next_index_insert_new(keys_and_values, &bucket->key);
		ZVAL_COPY_DEREF(&value, &bucket->value);
		zend_hash_next_index_insert_new(keys_and_values, &value);
	} SHARP_TABLE_FOREACH_END();

	RETURN_ARR(keys_and_values);
}

/* Whether `list` holds each key followed by its value. */
static bool sharp_is_keys_and_values(HashTable *list)
{
	return zend_hash_num_elements(list) % 2 == 0 && zend_array_is_list(list);
}

/* The table of `object`, a Map or a Set that holds no entry yet, or NULL after the Error for one that holds some: a
 * method that fills it again would change every holder of a shared one, as offsetSet would. */
static sharp_table *sharp_table_to_fill(zend_object *object)
{
	sharp_table *table = sharp_table_of(object);
	if (table->size) {
		zend_throw_error(NULL, "A %s that holds entries is written through its index, which copies a shared one first",
			ZSTR_VAL(object->ce->name));
		return NULL;
	}

	return table;
}

/* A Map literal's entries, key then value, in the order the literal writes them, so a later key replaces an earlier
 * one. A PHP# Map literal lowers to `new \Sharp\Map([$k1, $v1, ...])`, which a .sharp property default may hold.
 * __unserialize takes the list __serialize wrote. False after an exception. */
static bool sharp_map_fill(zend_object *map, HashTable *keys_and_values)
{
	sharp_table *table = sharp_table_to_fill(map);
	if (!table) {
		return false;
	}

	zval *key = NULL, *element;
	ZEND_HASH_FOREACH_VAL(keys_and_values, element) {
		if (sharp_out_of_memory()) {
			return false;
		}
		if (!key) {
			key = element;
		} else if (!sharp_table_put(table, key, element)) {
			return false;
		} else {
			key = NULL;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

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
	if (!sharp_is_keys_and_values(keys_and_values)) {
		zend_argument_value_error(1, "must be a list of keys each followed by its value");
		RETURN_THROWS();
	}
	if (!sharp_map_fill(Z_OBJ_P(ZEND_THIS), keys_and_values)) {
		RETURN_THROWS();
	}
}

ZEND_METHOD(Sharp_Map, __unserialize)
{
	HashTable *data;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(data)
	ZEND_PARSE_PARAMETERS_END();

	if (!sharp_is_keys_and_values(data)) {
		zend_value_error("A serialized Map must hold each key followed by its value");
		RETURN_THROWS();
	}
	if (!sharp_map_fill(Z_OBJ_P(ZEND_THIS), data)) {
		RETURN_THROWS();
	}
}

/* A PHP array's entries, under the keys PHP holds them by, of the type arguments PHP# code passes. */
ZEND_METHOD(Sharp_Map, from)
{
	HashTable *entries;
	zend_string *type_arguments = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ARRAY_HT(entries)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(type_arguments)
	ZEND_PARSE_PARAMETERS_END();

	sharp_table_object *map = sharp_table_object_create(sharp_ce_map, zend_hash_num_elements(entries));
	RETVAL_OBJ(&map->std);
	if (!sharp_table_made_with(execute_data, &map->std, type_arguments, 2)) {
		RETURN_THROWS();
	}
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
 * a PHP array's where plain PHP passes one for a Set, and the list toList wrote as __serialize, which __unserialize
 * takes. False after an exception. */
static bool sharp_set_fill(zend_object *set, HashTable *values)
{
	sharp_table *table = sharp_table_to_fill(set);
	if (!table) {
		return false;
	}

	zval null, *element;
	ZVAL_NULL(&null);
	ZEND_HASH_FOREACH_VAL(values, element) {
		if (sharp_out_of_memory() || !sharp_table_put(table, element, &null)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

ZEND_METHOD(Sharp_Set, __construct)
{
	HashTable *values = NULL;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_ARRAY_HT(values)
	ZEND_PARSE_PARAMETERS_END();

	if (values && !sharp_set_fill(Z_OBJ_P(ZEND_THIS), values)) {
		RETURN_THROWS();
	}
}

ZEND_METHOD(Sharp_Set, __unserialize)
{
	HashTable *data;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ARRAY_HT(data)
	ZEND_PARSE_PARAMETERS_END();

	if (!zend_array_is_list(data)) {
		zend_value_error("A serialized Set must hold a list of its elements");
		RETURN_THROWS();
	}
	if (!sharp_set_fill(Z_OBJ_P(ZEND_THIS), data)) {
		RETURN_THROWS();
	}
}

static const zend_function_entry class_Sharp_Map_methods[] = {
	ZEND_ME(Sharp_Map, __construct, arginfo_class_Sharp_Map___construct, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, __serialize, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, __unserialize, arginfo_class_Sharp_Table___unserialize, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, from, arginfo_class_Sharp_Map_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Map, keys, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Map, values, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("toList", zim_Sharp_Table_toList, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetExists", zim_Sharp_Table_offsetExists, arginfo_class_Sharp_Table_offsetExists, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetGet", zim_Sharp_Table_offsetGet, arginfo_class_Sharp_Table_offsetGet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetSet", zim_Sharp_Table_offsetSet, arginfo_class_Sharp_Table_offsetSet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetUnset", zim_Sharp_Table_offsetUnset, arginfo_class_Sharp_Table_offsetUnset, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Set_methods[] = {
	ZEND_ME(Sharp_Set, __construct, arginfo_class_Sharp_Set___construct, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("__serialize", zim_Sharp_Table_toList, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(Sharp_Set, __unserialize, arginfo_class_Sharp_Table___unserialize, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("getIterator", zim_Sharp_Table_getIterator, arginfo_class_Sharp_Table_getIterator, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("count", zim_Sharp_Table_count, arginfo_class_Sharp_Table_count, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("jsonSerialize", zim_Sharp_Table_jsonSerialize, arginfo_class_Sharp_Table_jsonSerialize, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toArray", zim_Sharp_Table_toArray, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("toList", zim_Sharp_Table_toList, arginfo_class_Sharp_Table_list, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetExists", zim_Sharp_Table_offsetExists, arginfo_class_Sharp_Table_offsetExists, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetGet", zim_Sharp_Table_offsetGet, arginfo_class_Sharp_Table_offsetGet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetSet", zim_Sharp_Table_offsetSet, arginfo_class_Sharp_Table_offsetSet, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_RAW_FENTRY("offsetUnset", zim_Sharp_Table_offsetUnset, arginfo_class_Sharp_Table_offsetUnset, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_FE_END
};

/* As WeakMap does, the class implements ArrayAccess and IteratorAggregate through its own handlers: the index runs
 * through read_dimension and the others, and a loop through get_iterator. As ArrayObject does, it serializes through
 * __serialize and __unserialize. */
static zend_class_entry *register_class_Sharp_Table(const char *name, const zend_function_entry *methods)
{
	zend_class_entry ce;

	INIT_CLASS_ENTRY_EX(ce, name, strlen(name), methods);
	zend_class_entry *registered = zend_register_internal_class_with_flags(&ce, NULL,
		ZEND_ACC_FINAL | ZEND_ACC_NO_DYNAMIC_PROPERTIES);
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

/* #[\Sharp\Crossing(plan, readsArray)], which the bridge puts on each property whose type holds a Map or a Set, or can
 * when it runs: the plan sharp_cross_property crosses a PHP array plain PHP writes there by, null where a write makes
 * no Map or Set, and whether plain PHP reads each Map and Set there as its PHP array, false for a Map that has none. */
ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Crossing___construct, 0, 0, 1)
	ZEND_ARG_TYPE_INFO(0, plan, IS_ARRAY, 1)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, readsArray, _IS_BOOL, 0, "true")
ZEND_END_ARG_INFO()

ZEND_METHOD(Sharp_Crossing, __construct)
{
	zval *plan;
	bool reads_array = true;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_ARRAY_OR_NULL(plan)
		Z_PARAM_OPTIONAL
		Z_PARAM_BOOL(reads_array)
	ZEND_PARSE_PARAMETERS_END();

	zval null;
	ZVAL_NULL(&null);
	zend_update_property(Z_OBJCE_P(ZEND_THIS), Z_OBJ_P(ZEND_THIS), "plan", sizeof("plan") - 1, plan ? plan : &null);
	zend_update_property_bool(Z_OBJCE_P(ZEND_THIS), Z_OBJ_P(ZEND_THIS), "readsArray", sizeof("readsArray") - 1,
		reads_array);
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
		(zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_ARRAY | MAY_BE_NULL));
	zend_string_release(plan);
	zend_string *reads_array = zend_string_init_interned("readsArray", sizeof("readsArray") - 1, true);
	zend_declare_typed_property(crossing, reads_array, &undefined, ZEND_ACC_PUBLIC | ZEND_ACC_READONLY, NULL,
		(zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_BOOL));
	zend_string_release(reads_array);

	zend_string *attribute_name = zend_string_init_interned("Attribute", sizeof("Attribute") - 1, true);
	zend_attribute *attribute = zend_add_class_attribute(crossing, attribute_name, 1);
	zend_string_release(attribute_name);
	ZVAL_LONG(&attribute->args[0].value, ZEND_ATTRIBUTE_TARGET_PROPERTY);
}

/* Keeps the text of an error a call inside a ZEND_SHARP_SILENCE raises, which no error handler sees, for
 * Sharp_Internal_silencedWarning. */
static void sharp_observe_error(int type, zend_string *file, uint32_t line, zend_string *message)
{
	if (!zend_sharp_silenced()) {
		return;
	}
	if (SHARP_G(silenced_warning)) {
		zend_string_release(SHARP_G(silenced_warning));
	}
	SHARP_G(silenced_warning) = zend_string_copy(message);
}

/* `\Sharp\Internal\silencedWarning()`: the text of the warning the last silenced built-in call of this request
 * raised, as "preg_match(): No ending matching delimiter ')' found", or null. The standard library reads a failure's
 * reason through it, as `Regex.parse` does. */
ZEND_FUNCTION(Sharp_Internal_silencedWarning)
{
	ZEND_PARSE_PARAMETERS_NONE();

	if (SHARP_G(silenced_warning)) {
		RETURN_STR_COPY(SHARP_G(silenced_warning));
	}
	RETURN_NULL();
}

static PHP_MINIT_FUNCTION(sharp)
{
	REGISTER_INI_ENTRIES();
	sharp_ce_sequence = register_class_Sharp_Internal_Sequence(zend_ce_aggregate);
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
	sharp_fiber_slot = zend_get_resource_handle("sharp");
	if (sharp_fiber_slot < 0) {
		return FAILURE;
	}
	zend_observer_fiber_init_register(sharp_fiber_started);
	zend_observer_fiber_destroy_register(sharp_fiber_destroyed);
	zend_observer_error_register(sharp_observe_error);
	/* Spec section 29: only a run that asks for the report observes its calls, so every other run pays nothing. */
	const char *effect_report = INI_STR("sharp.effect_report");
	if (effect_report && *effect_report) {
		char folder[MAXPATHLEN];
		size_t length = strlen(effect_report);
		if (IS_ABSOLUTE_PATH(effect_report, length) || !VCWD_GETCWD(folder, sizeof(folder))) {
			sharp_effect_report = pestrdup(effect_report, true);
		} else {
			size_t size = strlen(folder) + 1 + length + 1;
			sharp_effect_report = pemalloc(size, true);
			snprintf(sharp_effect_report, size, "%s/%s", folder, effect_report);
		}
		zend_observer_fcall_register(sharp_effect_observer);
	}

	sharp_ce_listener = register_class_Sharp_Internal_Listener();
	sharp_ce_listener->create_object = sharp_listener_create;
	memcpy(&sharp_listener_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_listener_handlers.offset = XtOffsetOf(sharp_listener, std);
	sharp_listener_handlers.free_obj = sharp_listener_free;
	sharp_listener_handlers.clone_obj = NULL;
	sharp_ce_listener->default_object_handlers = &sharp_listener_handlers;
	sharp_listener_hear = zend_hash_str_find_ptr(&sharp_ce_listener->function_table, "hear", sizeof("hear") - 1);

	sharp_ce_delegate = register_class_Sharp_Internal_Delegate();
	sharp_ce_delegate->create_object = sharp_delegate_create;
	memcpy(&sharp_delegate_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_delegate_handlers.offset = XtOffsetOf(sharp_delegate, std);
	sharp_delegate_handlers.free_obj = sharp_delegate_free;
	sharp_delegate_handlers.get_gc = sharp_delegate_gc;
	sharp_delegate_handlers.clone_obj = NULL;
	sharp_ce_delegate->default_object_handlers = &sharp_delegate_handlers;
	sharp_delegate_call = zend_hash_str_find_ptr(&sharp_ce_delegate->function_table, "call", sizeof("call") - 1);
	sharp_delegate_call->common.fn_flags |= ZEND_ACC_STRICT_TYPES;

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
	/* PHP checks a call's arguments and a property's write in the mode of the code that makes them, and an internal
	 * function's is coercive. Reflection's write runs in strict mode, as a .sharp file's direct one does, so `set`
	 * refuses "3" for an `int` as the direct write does, and so does a delegate's or an invoker's `call`. */
	static const char *const strict[] = {"sharp\\internal\\write", "sharp\\internal\\writefield"};
	for (size_t i = 0; i < sizeof(strict) / sizeof(strict[0]); i++) {
		zend_function *func = zend_hash_str_find_ptr(CG(function_table), strict[i], strlen(strict[i]));
		func->common.fn_flags |= ZEND_ACC_STRICT_TYPES;
	}

	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_type_arguments_key = zend_string_init_interned("\0<sharp>\0types", sizeof("\0<sharp>\0types") - 1, true);
	sharp_statics_key = zend_string_init_interned("\0<sharp>\0statics", sizeof("\0<sharp>\0statics") - 1, true);
	sharp_static_constructor_key = zend_string_init_interned(
		"\0<sharp>\0static constructor", sizeof("\0<sharp>\0static constructor") - 1, true);
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
	if (sharp_effect_report) {
		pefree(sharp_effect_report, true);
		sharp_effect_report = NULL;
	}
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
	zend_hash_init(&SHARP_G(request_stamps), 8, NULL, sharp_free_pointer, false);
	zend_hash_init(&SHARP_G(listener_tables), 0, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(event_listeners), 0, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(class_attributes), 0, NULL, sharp_free_attribute_table, false);
	SHARP_G(extern_key) = NULL;
	/* Each entry points at a live Sharp\Listening, which removes it when it stops, and holds no reference. */
	zend_hash_init(&SHARP_G(scoped_listeners), 0, NULL, NULL, false);
	SHARP_G(next_scoped_listener) = 0;
	zend_hash_init(&SHARP_G(held_emits), 0, NULL, ZVAL_PTR_DTOR, false);
	SHARP_G(sort) = NULL;
	ZVAL_UNDEF(&SHARP_G(compile_command_report));
	SHARP_G(compile_command_failure) = NULL;
	SHARP_G(compile_lock) = -1;
	SHARP_G(silenced_warning) = NULL;
	SHARP_G(seal_path) = NULL;
	zend_hash_init(&SHARP_G(container_bindings), 8, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(container_environment), 8, NULL, ZVAL_PTR_DTOR, false);
	zend_hash_init(&SHARP_G(container_modules), 8, NULL, NULL, false);
	SHARP_G(container_library) = NULL;
	zend_hash_init(&SHARP_G(container_tests), 8, NULL, ZVAL_PTR_DTOR, false);
	SHARP_G(container_test) = NULL;
	zend_hash_init(&SHARP_G(container_singletons), 8, NULL, ZVAL_PTR_DTOR, false);
	if (sharp_effect_report) {
		zend_hash_init(&SHARP_G(effect_runs), 8, NULL, sharp_effect_run_free, false);
	}
	/* Spec section 32: the request is the default scope, as each CLI run is. */
	sharp_fiber_scopes_start(&SHARP_G(container_main_scopes), sharp_scope_start());
	EG(main_fiber_context)->reserved[sharp_fiber_slot] = &SHARP_G(container_main_scopes);

	/* The CLI names the script in argv[0] only while the request starts, as PHP's $argv does. */
	zval *arguments = &SHARP_G(arguments);

	array_init_size(arguments, SG(request_info).argc);
	for (int i = 0; i < SG(request_info).argc; i++) {
		add_next_index_string(arguments, SG(request_info).argv[i]);
	}

	return SUCCESS;
}

/* Runs after the request's destructors and before its objects are freed. */
static PHP_RSHUTDOWN_FUNCTION(sharp)
{
	zend_hash_destroy(&SHARP_G(request_stamps));
	if (sharp_effect_report) {
		sharp_write_effect_report();
		zend_hash_destroy(&SHARP_G(effect_runs));
	}
	/* A fiber that is still to be freed keeps the request's scope, ended, until it is. */
	sharp_scope_end(SHARP_G(container_main_scopes).started_in);
	sharp_fiber_scopes_end(&SHARP_G(container_main_scopes));
	zend_hash_destroy(&SHARP_G(container_singletons));
	zend_hash_destroy(&SHARP_G(container_bindings));
	zend_hash_destroy(&SHARP_G(container_environment));
	zend_hash_destroy(&SHARP_G(container_modules));
	if (SHARP_G(container_library)) {
		zend_string_release(SHARP_G(container_library));
		SHARP_G(container_library) = NULL;
	}
	SHARP_G(container_test) = NULL;
	zend_hash_destroy(&SHARP_G(container_tests));
	zend_hash_destroy(&SHARP_G(listener_tables));
	zend_hash_destroy(&SHARP_G(event_listeners));
	zend_hash_destroy(&SHARP_G(class_attributes));
	/* A handle whose destructor didn't run, as after a fatal error, is freed after this, with no table to leave. */
	sharp_listening *listening;
	ZEND_HASH_FOREACH_PTR(&SHARP_G(scoped_listeners), listening) {
		listening->number = 0;
	} ZEND_HASH_FOREACH_END();
	zend_hash_destroy(&SHARP_G(scoped_listeners));
	zend_hash_destroy(&SHARP_G(held_emits));
	zval_ptr_dtor(&SHARP_G(compile_command_report));
	if (SHARP_G(compile_command_failure)) {
		zend_string_release(SHARP_G(compile_command_failure));
		SHARP_G(compile_command_failure) = NULL;
	}
	if (SHARP_G(silenced_warning)) {
		zend_string_release(SHARP_G(silenced_warning));
		SHARP_G(silenced_warning) = NULL;
	}
	/* A fatal error while the request held the lock, as one reading the listener table it compiled, ends it here. */
	sharp_unlock_compiles();
	zval_ptr_dtor(&SHARP_G(arguments));
	ZVAL_UNDEF(&SHARP_G(arguments));

	return SUCCESS;
}

/* Runs after the request's objects are freed, so no slot still points into the request's types. */
static ZEND_MODULE_POST_ZEND_DEACTIVATE_D(sharp)
{
	sharp_type_request_table_free(&SHARP_G(request_spelled_lists));
	sharp_type_request_table_free(&SHARP_G(request_reflected));
	sharp_type_request_table_free(&SHARP_G(request_class_values));
	sharp_type_request_table_free(&SHARP_G(equality_operators));
	sharp_type_request_table_free(&SHARP_G(request_type_lists));
	sharp_type_request_table_free(&SHARP_G(request_types));
	SHARP_G(request_input_types) = 0;

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

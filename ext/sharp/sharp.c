#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include "php.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/info.h"
#include "zend_closures.h"
#include "zend_exceptions.h"
#include "zend_system_id.h"
#include "php_sharp.h"
#include "sharp_arginfo.h"
#include "sharp_bridge.h"
#include "sharp_build_id.h"

#define SHARP_FILE_EXTENSION ".sharp"

#define SHARP_KIND_IS_ZEND_KIND(kind) \
	ZEND_STATIC_ASSERT((zend_ast_kind) SHARP_AST_##kind == ZEND_AST_##kind, "SHARP_AST_" #kind " differs from ZEND_AST_" #kind);
SHARP_KINDS(SHARP_KIND_IS_ZEND_KIND)

static zend_op_array *(*sharp_next_compile_file)(zend_file_handle *file_handle, int type);

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index);

static zend_string *sharp_string(sharp_str text)
{
	return zend_string_init(text.ptr, text.len, 0);
}

static zend_ast *sharp_translate_zval(const sharp_node *node)
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
			ZVAL_STR(&value, sharp_string(node->text));
			break;
		EMPTY_SWITCH_DEFAULT_CASE();
	}

	return zend_ast_create_zval_ex(&value, node->attr);
}

static zend_ast *sharp_translate_list(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *list = zend_ast_create_list(0, kind);

	for (uint32_t i = 0; i < node->child_count; i++) {
		list = zend_ast_list_add(list, sharp_translate(unit, unit->children[node->first_child + i]));
	}
	list->attr = node->attr;

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
		kind == ZEND_AST_CLOSURE || kind == ZEND_AST_ARROW_FUNC ? NULL : sharp_string(node->text),
		child[0], child[1], child[2], child[3], child[4]);
}

static zend_ast_attr sharp_operator_attr(zend_ast_kind kind, uint32_t attr)
{
	switch (kind) {
		case ZEND_AST_BINARY_OP:
		case ZEND_AST_ASSIGN_OP:
			return attr == ZEND_ADD || attr == ZEND_SUB || attr == ZEND_MUL || attr == ZEND_POW
				? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
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
		return sharp_translate_zval(node);
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

static int sharp_parse(void)
{
	zend_string *path = zend_get_compiled_filename();
	const char *source = (const char *) LANG_SCNG(yy_start);
	size_t length = LANG_SCNG(yy_limit) - LANG_SCNG(yy_start);
	sharp_unit *unit = sharp_lower(ZSTR_VAL(path), ZSTR_LEN(path), source, length);
	bool diagnosed = unit->diagnostic_count != 0;
	bool failed = false;

	zend_try {
		if (diagnosed) {
			const sharp_diagnostic *diagnostic = &unit->diagnostics[0];

			CG(zend_lineno) = diagnostic->line;
			zend_throw_exception_ex(
				diagnostic->severity == SHARP_PARSE_ERROR ? zend_ce_parse_error : zend_ce_compile_error,
				0, "%.*s", (int) diagnostic->message.len, diagnostic->message.ptr);
		} else {
			CG(ast) = sharp_translate(unit, unit->root);
			CG(zend_lineno) = unit->nodes[unit->root].end_line;
		}
	} zend_catch {
		failed = true;
	} zend_end_try();

	sharp_unit_free(unit);

	if (failed) {
		zend_bailout();
	}

	return diagnosed ? FAILURE : SUCCESS;
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

/* Whether the running code was compiled from a .sharp file. The file name decides the dialect in
 * sharp_compile_file, and only the cold paths of a missing member ask again. */
static bool sharp_is_sharp_caller(void)
{
	const zend_execute_data *ex = EG(current_execute_data);

	return ex && ex->func && ZEND_USER_CODE(ex->func->type) && sharp_is_sharp_file(ex->func->op_array.filename);
}

/* Runs a call of a missing method: reads the property of the method's name, in the caller's scope,
 * and calls the Closure it holds with the call's arguments, as PHP's ($object->name)(...) does. The
 * property is read after the arguments, which C# reads before them. */
static ZEND_FUNCTION(sharp_property_call_trampoline)
{
	zval *arguments = NULL;
	uint32_t argument_count = 0;
	HashTable *named_arguments = NULL;

	ZEND_PARSE_PARAMETERS_START(0, -1)
		Z_PARAM_VARIADIC_WITH_NAMED(arguments, argument_count, named_arguments)
	ZEND_PARSE_PARAMETERS_END_EX(goto clean);

	zend_object *object = Z_OBJ_P(ZEND_THIS);
	zval rv, function;
	zval *value = object->handlers->read_property(object, EX(func)->common.function_name, BP_VAR_R, NULL, &rv);
	ZVAL_COPY_DEREF(&function, value);
	if (value == &rv) {
		zval_ptr_dtor(&rv);
	}

	if (EG(exception)) {
		/* read_property threw, and its value is null. */
	} else if (Z_TYPE(function) == IS_OBJECT && Z_OBJCE(function) == zend_ce_closure) {
		call_user_function_named(NULL, NULL, &function, return_value, argument_count, arguments, named_arguments);
	} else {
		zend_throw_error(NULL, "Value not callable");
	}
	zval_ptr_dtor(&function);

clean:
	zend_string_release(EX(func)->common.function_name);
	zend_free_trampoline(EX(func));
	EX(func) = NULL;
}

/* Spec section 14: a PHP# call x.name(...) of a class with no method name and no __call
 * calls the function its instance property name holds. Returns NULL for any other call. */
zend_function *sharp_property_call(zend_object *object, zend_string *name)
{
	static const zend_internal_arg_info arg_info[1] = {
		{ .name = "arguments", .type = ZEND_TYPE_INIT_NONE(_ZEND_IS_VARIADIC_BIT) }
	};
	const zend_property_info *property = zend_hash_find_ptr(&object->ce->properties_info, name);

	if (!property || (property->flags & ZEND_ACC_STATIC) || !sharp_is_sharp_caller()) {
		return NULL;
	}

	zend_function *func = EXPECTED(EG(trampoline).common.function_name == NULL)
		? &EG(trampoline)
		: (zend_function *) ecalloc(1, sizeof(zend_internal_function));
	func->type = ZEND_INTERNAL_FUNCTION;
	/* The trampoline runs in its own frame, so it reserves no temporary for observers. */
	func->common.T = 0;
	func->common.arg_flags[0] = 0;
	func->common.arg_flags[1] = 0;
	func->common.arg_flags[2] = 0;
	func->common.fn_flags = ZEND_ACC_CALL_VIA_TRAMPOLINE | ZEND_ACC_PUBLIC | ZEND_ACC_VARIADIC;
	func->common.function_name = zend_string_copy(name);
	func->common.num_args = 0;
	func->common.required_num_args = 0;
	/* The caller's scope, which reads the property where the caller may read it. */
	func->common.scope = zend_get_executed_scope();
	func->common.prototype = NULL;
	func->common.prop_info = NULL;
	func->common.arg_info = (zend_arg_info *) arg_info;
	func->internal_function.handler = ZEND_FN(sharp_property_call_trampoline);
	func->internal_function.module = NULL;
	func->internal_function.reserved[0] = NULL;
	func->internal_function.reserved[1] = NULL;

	return func;
}

/* Spec section 14.3: a PHP# read x.name of a class with no property name gives its method name as a
 * Closure bound to x, as PHP's $x->name(...) does, and a read Class.name with no constant, enum case or
 * static property name gives its static method name, as PHP's Class::name(...) does. object is NULL for
 * Class.name. Returns NULL for any other read, and when finding the method threw, as for a private one. */
zval *sharp_method_value(zend_class_entry *ce, zend_object *object, zend_string *name, zval *result)
{
	if (!sharp_is_sharp_caller()) {
		return NULL;
	}

	zend_object *receiver = object;
	zend_function *method = object
		? object->handlers->get_method(&receiver, name, NULL)
		: zend_std_get_static_method(ce, name, NULL);
	if (!method) {
		return NULL;
	}
	if (method->common.fn_flags & ZEND_ACC_CALL_VIA_TRAMPOLINE) {
		zend_string_release(method->common.function_name);
		zend_free_trampoline(method);
		return NULL;
	}
	if (!object && !(method->common.fn_flags & ZEND_ACC_STATIC)) {
		return NULL;
	}

	zval this_value;
	if (object) {
		ZVAL_OBJ(&this_value, receiver);
	}
	zend_create_fake_closure(result, method, method->common.scope, object ? receiver->ce : ce,
		(method->common.fn_flags & ZEND_ACC_STATIC) ? NULL : &this_value);

	return result;
}

#define SHARP_SPARE_COLLECTIONS 4

ZEND_BEGIN_MODULE_GLOBALS(sharp)
	zend_object *spare_collections[SHARP_SPARE_COLLECTIONS];
ZEND_END_MODULE_GLOBALS(sharp)

ZEND_DECLARE_MODULE_GLOBALS(sharp)

#define SHARP_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(sharp, v)

static zend_class_entry *sharp_ce_collection;
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

ZEND_METHOD(Sharp_Collection, get)
{
	zend_string *string_key;
	zend_long long_key;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR_OR_LONG(string_key, long_key)
	ZEND_PARSE_PARAMETERS_END();

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
	zend_string *string_key;
	zend_long long_key;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR_OR_LONG(string_key, long_key)
	ZEND_PARSE_PARAMETERS_END();

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
	sharp_ce_collection = register_class_Sharp_Collection();
	sharp_ce_collection->create_object = sharp_collection_create;
	memcpy(&sharp_collection_handlers, &std_object_handlers, sizeof(zend_object_handlers));
	sharp_collection_handlers.offset = XtOffsetOf(sharp_collection, std);
	sharp_collection_handlers.dtor_obj = sharp_collection_release;
	sharp_collection_handlers.free_obj = sharp_collection_free;
	sharp_collection_handlers.clone_obj = NULL;
	sharp_ce_collection->default_object_handlers = &sharp_collection_handlers;

	sharp_init();
	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_next_compile_file = zend_compile_file;
	zend_compile_file = sharp_compile_file;

	return SUCCESS;
}

static PHP_GINIT_FUNCTION(sharp)
{
	memset(sharp_globals, 0, sizeof(*sharp_globals));
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

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	sharp_str commit = sharp_mago_commit();
	char *value = estrndup(commit.ptr, commit.len);

	php_info_print_table_start();
	php_info_print_table_row(2, "Mago commit", value);
	php_info_print_table_end();

	efree(value);
}

zend_module_entry sharp_module_entry = {
	STANDARD_MODULE_HEADER,
	"sharp",
	NULL,
	PHP_MINIT(sharp),
	NULL,
	NULL,
	PHP_RSHUTDOWN(sharp),
	PHP_MINFO(sharp),
	PHP_VERSION,
	PHP_MODULE_GLOBALS(sharp),
	PHP_GINIT(sharp),
	NULL,
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};

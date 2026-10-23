#ifndef PHP_SHARP_H
#define PHP_SHARP_H

#include "zend_smart_str_public.h"

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

#define PHP_SHARP_VERSION "0.5.0"

/* Whether `filename` names a PHP# source file, which ends in `.sharp`. */
bool sharp_is_sharp_file(const zend_string *filename);

/* The operators == each class the request compared objects of compares them by, keyed by the class's address, which
 * sharp_table.c fills and the request frees when it ends. */
HashTable *sharp_equality_operators(void);

/* Whether `func` is PHP# code, a function a .sharp file declares. Plain PHP code and an internal function are not. */
static zend_always_inline bool sharp_function_is_sharp(const zend_function *func)
{
	return func && ZEND_USER_CODE(func->type) && sharp_is_sharp_file(func->op_array.filename);
}

/* Whether the code of `frame` is PHP#, which gets a PHP# Map or Set as the object: the nearest frame of code from it,
 * past the internal functions that code called, such as get_object_vars() or ReflectionProperty::getValue(). */
static zend_always_inline bool sharp_code_at(const zend_execute_data *frame)
{
	while (frame && (!frame->func || !ZEND_USER_CODE(frame->func->type))) {
		frame = frame->prev_execute_data;
	}
	return frame && sharp_is_sharp_file(frame->func->op_array.filename);
}

/* Whether the code running now is PHP#, see sharp_code_at(). */
static zend_always_inline bool sharp_runs_sharp_code(void)
{
	return sharp_code_at(EG(current_execute_data));
}

/* The VM makes the Sharp\Collection receiver of a PHP# method call on a collection with these, see
 * ZEND_SHARP_OPERATOR in Zend/zend_compile.h: an array, a List, or a Sharp\Map or Sharp\Set object, see
 * SHARP_IS_COLLECTION. The receiver may point at a local of the calling frame, so a backtrace never
 * hands it out as a frame's object. */
extern zend_class_entry *sharp_ce_collection;
void sharp_collection_of_local(zval *result, zval *local);
void sharp_collection_of_value(zval *result, zval *value);
void sharp_collection_of_reference(zval *result, zval *reference);
void sharp_collection_of_property(zval *result, zend_object *object, zend_string *name, void **cache_slot);
void sharp_collection_of_static_property(zval *result, zval *slot, const zend_property_info *info);

/* Spec section 12: a Map and a Set are values, so the VM and the JIT copy a shared one into the holder an index
 * write or unset goes through, as SEPARATE_ARRAY copies a shared array. */
extern zend_class_entry *sharp_ce_map;
extern zend_class_entry *sharp_ce_set;
void sharp_separate(zval *holder);

/* Spec section 12: a PHP array plain PHP writes to a property PHP# declares a Map or a Set crosses into that object, as
 * the property's #[\Sharp\Crossing] plans it, where the engine coerces a value for a typed property. Returns whether
 * it did. sharp_crosses_property says whether the property has such an attribute, and sharp_property_reads_as_array
 * whether plain PHP reads each Map and Set its value holds as its PHP array, which the compiler marks with
 * ZEND_ACC_SHARP_CROSSING. */
bool sharp_crosses_property(const zend_property_info *info);
bool sharp_property_reads_as_array(const zend_property_info *info);
bool sharp_cross_property(const zend_property_info *info, zval *value);

/* Spec section 12: puts in `result` the value plain PHP receives for `value`. A Map is a PHP array of its entries, with
 * a backed enum key as its backing value, and a Set is the list of its elements. Each Map and Set a PHP array, a Map or a
 * Set holds converts the same way, and a PHP array that holds none is itself. A Map with a key no PHP array holds, a
 * class, a struct, a collection or an enum without backing values, is itself. A Map two of whose keys are one PHP array
 * key, as 5 and "5" are, or a backed enum case and its backing value, throws a ValueError that names both, since a PHP
 * array would keep only one of them, and `result` is then the value itself. Any other value is itself. */
void sharp_array_of(zval *result, zval *value);

/* Makes `value`, which a property whose type holds a Map or a Set gives plain PHP code, what sharp_array_of gives. */
void sharp_cross_read(zval *value);

/* Puts in `array` what plain PHP code reads from `slot`, a declared property slot of `object`, and gives true, when its
 * property has ZEND_ACC_SHARP_CROSSING and code outside a .sharp file runs now. Gives false and leaves `array` alone
 * otherwise. get_object_vars() and foreach read slots through it. */
bool sharp_cross_slot_read(zval *array, zend_object *object, zval *slot);

/* Makes each property of `object` that `result`, the (array) cast of `object`, holds under its mangled name, what plain
 * PHP code reads from it, by sharp_cross_slot_read(). */
void sharp_cross_array_cast(zval *result, zend_object *object);

bool sharp_property_takes_reference(zval *slot, const zend_property_info *info);
zval *sharp_property_from_reference(zval *slot, const zend_property_info *info, zval *rv);
bool sharp_property_for_write(zval *result, zend_object *object, zval *slot, const zend_property_info *info);

#define SHARP_IS_TABLE(value) \
	(Z_TYPE_P(value) == IS_OBJECT && (Z_OBJCE_P(value) == sharp_ce_map || Z_OBJCE_P(value) == sharp_ce_set))
#define SHARP_IS_COLLECTION(value) (Z_TYPE_P(value) == IS_ARRAY || SHARP_IS_TABLE(value))

/* Whether a value the optimizer or the JIT knows of type and class ce, ce one of its parents when is_instanceof, may be
 * a Sharp\Map or a Sharp\Set, which a PHP# method call's receiver turns into a Sharp\Collection. */
static zend_always_inline bool sharp_may_be_table(uint32_t type, const zend_class_entry *ce, bool is_instanceof)
{
	if (!(type & MAY_BE_OBJECT)) {
		return false;
	}
	if (!ce) {
		return true;
	}
	if (!is_instanceof) {
		return ce == sharp_ce_map || ce == sharp_ce_set;
	}

	return instanceof_function(sharp_ce_map, ce) || instanceof_function(sharp_ce_set, ce);
}

/* The holder holds an object. */
#define SHARP_SEPARATE(holder) do { \
	if (UNEXPECTED((Z_OBJCE_P(holder) == sharp_ce_map || Z_OBJCE_P(holder) == sharp_ce_set) \
			&& GC_REFCOUNT(Z_OBJ_P(holder)) > 1)) { \
		sharp_separate(holder); \
	} \
} while (0)

/* PHP# type texts, the one spelling of a type the bridge prints and the engine reads: `App.Order`, `int?`,
 * `List<App.Order>`, `(int|string)?`, `App.DatabaseEntity & App.Shareable`, `Function<bool(App.Order, int)>`, `[string, int]`.
 * Each text parses once into an interned
 * sharp_type, so equal types are one pointer. A type code spells lives for the process. A type first read from input
 * lives for its request, and only a type that lives for the process goes into a run-time cache slot. */
typedef enum {
	/* A type argument list, `App.Order, int`: each argument is a member. */
	SHARP_TYPE_LIST,
	/* A built-in type or a class, with its type arguments as members: `int`, `List<int>`, `App.Pair<int, string>`. */
	SHARP_TYPE_NAMED,
	/* `T?`: T is the one member. */
	SHARP_TYPE_NULLABLE,
	/* `A|B`: the members, sorted by their text. An intersection member stands in parentheses, `(A & B)|C`. */
	SHARP_TYPE_UNION,
	/* `A & B`: the classes, sorted by their text. */
	SHARP_TYPE_INTERSECTION,
	/* `Function<R(P1, P2)>`: the return type, then each parameter type. */
	SHARP_TYPE_FUNCTION,
	/* `[A, B]`, spec section 30: each element type, in order. The checker keeps the element names. */
	SHARP_TYPE_TUPLE,
} sharp_type_kind;

/* The built-in type a SHARP_TYPE_NAMED type names, so a check of a value reads no text. */
typedef enum {
	/* A class, or a type of any other kind. */
	SHARP_BUILT_IN_NONE,
	SHARP_BUILT_IN_INT,
	SHARP_BUILT_IN_FLOAT,
	SHARP_BUILT_IN_BOOL,
	SHARP_BUILT_IN_STRING,
	SHARP_BUILT_IN_VOID,
	SHARP_BUILT_IN_NULL,
	SHARP_BUILT_IN_ANY,
	SHARP_BUILT_IN_OBJECT,
	SHARP_BUILT_IN_LIST,
	SHARP_BUILT_IN_MAP,
	SHARP_BUILT_IN_SET,
	SHARP_BUILT_IN_ITERABLE,
	SHARP_BUILT_IN_CLASS,
} sharp_built_in;

typedef struct _sharp_type sharp_type;
struct _sharp_type {
	zend_string *text;
	/* The class a SHARP_TYPE_NAMED names, as PHP writes it, `App\Order`. NULL for a built-in type. */
	zend_string *class_name;
	sharp_type_kind kind;
	sharp_built_in built_in;
	uint32_t count;
	const sharp_type *members[1];
};

/* Whether `type` lives for the process. */
static zend_always_inline bool sharp_type_is_persistent(const sharp_type *type)
{
	return GC_FLAGS(type->text) & IS_STR_PERSISTENT;
}

/* Whether `a` and `b` are one type. Two types that live for the process are one pointer, but a type a request interned
 * can share its text with one another thread interned for the process at the same time, so their texts decide. */
static zend_always_inline bool sharp_type_equals(const sharp_type *a, const sharp_type *b)
{
	if (a == b) {
		return true;
	}
	if (!a || !b || (sharp_type_is_persistent(a) && sharp_type_is_persistent(b))) {
		return false;
	}

	return zend_string_equals(a->text, b->text);
}

/* The interned type argument list code spells as `text`, or NULL when it spells none. */
const sharp_type *sharp_type_list(const char *text, size_t length);

/* The type parameters an open type argument list writes. A class's is `$` and its index, `$0` for the first, which
 * stands for this's type argument at that index. A method's own is `#` and its index, which stands for the method's
 * type argument at that index. */
#define SHARP_TYPE_NAMES_THIS (1 << 0)
#define SHARP_TYPE_NAMES_METHOD (1 << 1)

/* Which SHARP_TYPE_NAMES_* type parameters the type argument list `text` writes, 0 when it is closed or no list. */
uint32_t sharp_type_list_names(const char *text, size_t length);

/* The interned type argument list `text` spells once each `$i` in it is `object`'s type argument i, for code written in
 * class `scope`, and each `#i` is member i of `method`, the type arguments of the method the code runs in, or
 * SHARP_TYPE_UNRESOLVED when `unresolved` holds. The four pointers at `cache` keep the last list it spelled for
 * `object`'s class and type arguments and for `method`, and the request keeps every list it spelled. `object` is NULL for code that runs without this, which never
 * names a `$i`, and only a text that names one reads `object`'s type arguments, which initializes a lazy proxy. NULL
 * when `object` is not of class `scope`, `text` names a `#i` while `method` is NULL and `unresolved` does not hold, or
 * `text` names an index the arguments have no member at. */
const sharp_type *sharp_type_list_of_frame(const zval *text, zend_object *object, const sharp_type *method,
	bool unresolved, const zend_class_entry *scope, void **cache);

/* The class whose type parameters the `$i` of code `scope` holds count, of `func` when the code is a function's. A
 * method a class took from a trait is the trait's code, whose op array it shares. The trait of a PHP# interface's
 * default bodies counts its interface's, which its header names, spec section 22. */
const zend_class_entry *sharp_type_scope(const zend_class_entry *scope, const zend_function *func);

/* The bad values one check found. The walk adds each one under the path to it, in the order it meets them. */
typedef struct {
	/* An array: the path to a bad value, then its problem, `expected int, got string 'abc'`. */
	zval *problems;
	/* The path to the value the walk is at, as `addresses[2]`. The walk appends to it and restores it. */
	smart_str path;
} sharp_type_problems;

/* Whether `value` is of `type`, one type: `Any?` holds every value, a class an instance of it or of a subclass, whose
 * type arguments for the class must be the type's own, except where the type has a method's type parameter a call
 * from plain PHP left unresolved, and a built-in type the values of its PHP type, an int also for `float`. A `List` is
 * an array that is a list, a `Map` and a `Set` an array or a Sharp\Map or Sharp\Set object, and an `Iterable` an array,
 * and each checks every element, a `Map` every key too. An `Iterable` is also a Traversable object, whose elements are
 * not checked. `Class` and `Function` hold every value, as PHP's own parameter type checks them. An int it accepts for a
 * `float`, at any depth, becomes a float in place, except in a Sharp\Map or Sharp\Set, and an array is separated only
 * when an int in it changes. With no `problems`, it stops at the first bad
 * value. With `problems`, it walks every value and adds each bad one. False when a lazy proxy's initializer throws,
 * with the exception left in EG(exception). */
bool sharp_type_accepts(const sharp_type *type, zval *value, sharp_type_problems *problems);

/* Whether `value` is of the one type of `list`, as a PHP# `is` tests it: as sharp_type_accepts decides, except that an
 * int is never a `float`, as `is float` decides. A NULL list, which ZEND_SHARP_TYPE_ARGS spells for a lambda bound to an
 * object of another class than the one it is written in, throws an Error. */
bool sharp_type_list_holds(const sharp_type *list, const zval *value);

/* The class value of the one type of `list`, as a PHP# `typeof` gives it, interned: the class name as PHP writes it, or
 * the type text of a type that is no class. A class with type arguments gives its own interned class name, which
 * sharp_new_class knows it by. With `whole`, the text of the whole list, as `App.Order, int`, which keys a generic
 * class's own statics. NULL, with an Error thrown, for a NULL list, as sharp_type_list_holds. */
zend_string *sharp_type_list_class_value(const sharp_type *list, bool whole);

/* The class that `new` creates from `created`, the TMP op2 of a ZEND_NEW with ZEND_FETCH_CLASS_SHARP_TYPE: the one type
 * of the list ZEND_SHARP_TYPE_ARGS spelled, as for `new TItem(…)`, or the class a class value names, as for
 * `new (type)(…)`. It puts that type's own type arguments in `arguments`, or NULL when it has none: a class value that
 * sharp_type_list_class_value gave carries them, and any other names a class without them. Throws an Error and gives
 * NULL when the list is NULL, its type is not a class, or no class has the name. */
zend_class_entry *sharp_new_class(const zval *created, const sharp_type **arguments);

/* Throws PHP's TypeError for the first argument of the call `execute_data` runs that the type text list `text` does
 * not accept, one entry per parameter, spelled for the frame as sharp_type_list_of_frame spells it, with each of the
 * method's own type parameters unresolved, since only a call from plain PHP is checked. An int it accepts for a float
 * becomes a float. */
void sharp_type_check_arguments(zend_execute_data *execute_data, const zval *text, void **cache);

/* Throws an Error for the arguments `named`, which plain PHP passed by name to the `...` parameter of `func`, a PHP#
 * function, since a PHP# `...` parameter is a List, which has no names. ZEND_RECV_VARIADIC runs it on entry. */
ZEND_COLD void sharp_refuse_named_variadic(const zend_function *func, const HashTable *named);

/* Whether `value` is of the one type the type text list `text` spells, spelled for the frame `execute_data` runs as
 * sharp_type_check_arguments spells it. Throws PHP's TypeError for a function's return value when not, since the value is
 * a plain PHP call's result, naming the call as the literal after `text` names it. An int it accepts for a float becomes
 * a float. */
bool sharp_type_verify(zend_execute_data *execute_data, zval *value, const zval *text, void **cache);

/* Whether the property `info`, whose sharp_type is set, takes `value`, as PHP checks a typed property on every write:
 * as sharp_type_accepts decides, with the type spelled for `object`, whose property it is, and an int it accepts for a
 * float becomes a float. A PHP array it takes crosses into the Map or Set object, as the property's #[\Sharp\Crossing]
 * plans it, and a crossing that throws refuses the value. A type that names a `$i` takes any value when `object` is
 * NULL, as a write through a reference or PHP's own API has no object. A write from a .sharp file is never checked,
 * since PHP# checked it where it compiled, and the write a property hook makes to its own property is its caller's.
 * Throws PHP's TypeError, for a write to the property or to a reference it holds when `reference` holds, when it does
 * not take the value. */
bool sharp_property_accepts(const zend_property_info *info, zval *value, zend_object *object, bool reference);

/* Whether the property `info` refuses a reference to it, which throws an Error: a property whose PHP# type names a type
 * parameter of its class, since a write through a reference has no object to spell the type for. */
bool sharp_property_refuses_reference(const zend_property_info *info);

/* Whether each property of `object` with a PHP# type takes its value, spelled for the object's type arguments, as
 * sharp_property_accepts decides. unserialize reads the type arguments after the properties, and fills each Map and Set
 * the object holds after the whole payload, so it checks the object's properties once those are filled. */
bool sharp_object_properties_accept(zend_object *object);

/* An object of a generic PHP# class keeps its type arguments in a declared property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN. It holds null until PHP# code, unserialize or the first read stores the IS_PTR of an interned
 * list. The name is also the key serialize writes the list under. */
extern zend_string *sharp_type_arguments_key;

/* A generic PHP# class with static fields keeps them in a static property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN: an array of each type argument text's own entry of the fields, and under "" their defaults. Only
 * \Sharp\Internal\statics() reads it. */
extern zend_string *sharp_statics_key;

/* Spec section 6: a PHP# class with a static constructor declares a static property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN. It holds true until the class's statics are set up, then null once the static constructor
 * returns, or the object it threw. */
extern zend_string *sharp_static_constructor_key;

/* The slot of the class `ce` declares with sharp_static_constructor_key in its statics, or NULL when `ce` has no
 * static constructor or its statics are not built yet. */
zval *sharp_static_constructor_state(zend_class_entry *ce);

/* Throws the Error a use of the statics of `ce` throws once its static constructor threw `failure`, which names the
 * class as PHP# writes it, with `type_arguments` when the statics are a generic class's for those, and the failure,
 * which it chains as the previous throwable. */
ZEND_COLD void sharp_throw_static_constructor_failure(
	const zend_class_entry *ce, const zend_string *type_arguments, zend_object *failure);

/* Whether `name` is sharp_type_arguments_key, which also names the hidden local a PHP# generic method keeps its own
 * type arguments in as an IS_PTR. Its name starts with NUL, so no PHP variable names it, and nothing that lists a
 * frame's or a closure's variables to user code lists it. */
static zend_always_inline bool sharp_is_type_arguments_key(const zend_string *name)
{
	return zend_string_equals(name, sharp_type_arguments_key);
}

/* The type arguments of `object`, whose class carries ZEND_ACC_SHARP_GENERIC. The first read of an object plain PHP
 * created interns its class's bounds. */
const sharp_type *sharp_type_arguments_of_slot(zend_object *object);

/* The type arguments of `object`, NULL when its class declares no type parameter. An object of a plain PHP class costs
 * one flag test. */
static zend_always_inline const sharp_type *sharp_type_arguments(zend_object *object)
{
	return UNEXPECTED(object->ce->ce_flags & ZEND_ACC_SHARP_GENERIC) ? sharp_type_arguments_of_slot(object) : NULL;
}

/* Whether `new` can give an object of `ce` the type arguments `arguments`, one for each type parameter `ce` declares.
 * PHP# checked them against the class the name reached when it compiled, but an alias or an autoloader can bind the
 * name to a class that declares none, or another number. Throws an Error when they do not fit. */
bool sharp_class_takes(const zend_class_entry *ce, const sharp_type *arguments);

/* Stores `arguments` in the slot of `object`, whose class declares one. */
void sharp_type_arguments_store(zend_object *object, const sharp_type *arguments);

/* The class `name`, as PHP writes it, names in serialized data, or NULL when the data may not hold it. */
typedef zend_class_entry *(*sharp_type_class_resolver)(zend_string *name, void *context);

/* Gives `object` the type arguments `text` spells, from serialized data. Each class the text names resolves through
 * `resolve` and takes the spelling it was declared with. Fails, with nothing stored, when `object` has no slot, `text`
 * spells no type, a class does not resolve, or a type is given type arguments it does not take or outside its
 * bounds. */
zend_result sharp_type_arguments_unserialize(
	zend_object *object, const zval *text, sharp_type_class_resolver resolve, void *context);

#endif

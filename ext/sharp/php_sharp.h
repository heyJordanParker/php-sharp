#ifndef PHP_SHARP_H
#define PHP_SHARP_H

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

#define PHP_SHARP_VERSION "0.2.0"

/* Whether `filename` names a PHP# source file, which ends in `.sharp`. */
bool sharp_is_sharp_file(const zend_string *filename);

/* The VM makes the Sharp\Collection receiver of a PHP# method call on an array with these, see
 * ZEND_SHARP_OPERATOR in Zend/zend_compile.h. The receiver may point at a local of the calling frame,
 * so a backtrace never hands it out as a frame's object. */
extern zend_class_entry *sharp_ce_collection;
void sharp_collection_of_local(zval *result, zval *local);
void sharp_collection_of_value(zval *result, zval *value);
void sharp_collection_of_property(zval *result, zend_object *object, zend_string *name, void **cache_slot);

/* PHP# type texts, the one spelling of a type the bridge prints and the engine reads: `App.Order`, `int?`,
 * `List<App.Order>`, `(int|string)?`, `App.DatabaseEntity & App.Shareable`, `Function<bool(App.Order, int)>`. Each text parses once into an interned
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
} sharp_type_kind;

typedef struct _sharp_type sharp_type;
struct _sharp_type {
	zend_string *text;
	/* The class a SHARP_TYPE_NAMED names, as PHP writes it, `App\Order`. NULL for a built-in type. */
	zend_string *class_name;
	sharp_type_kind kind;
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
 * class `scope`, and each `#i` is member i of `method`, the type arguments of the method the code runs in. The four
 * pointers at `cache` keep the last list it spelled for `object`'s class and type arguments and for `method`. `object` is
 * NULL for code that runs without this, which never names a `$i`. NULL when `object` is not of class `scope`, `method`
 * is NULL for a text that names a `#i`, or `text` names an index the arguments have no member at. */
const sharp_type *sharp_type_list_of_frame(const zval *text, zend_object *object, const sharp_type *method,
	const zend_class_entry *scope, void **cache);

/* Whether `value` is of `type`, one type: `Any?` holds every value, a class an instance of it or of a subclass, whose
 * type arguments for the class must be the type's own, except where the type's argument is `Any?`, and a built-in type
 * the values of its PHP type, an int also for `float`. `List`, `Map`, `Set`, `Iterable`, `Class` and `Function` hold
 * every value, as PHP's own parameter type checks them, and their elements are not checked. */
bool sharp_type_accepts(const sharp_type *type, const zval *value);

/* Throws PHP's TypeError for the first argument of the call `execute_data` runs that the type text list `text` does
 * not accept, one entry per parameter, spelled for the frame as sharp_type_list_of_frame spells it, with `method` the
 * method's own type arguments. */
void sharp_type_check_arguments(zend_execute_data *execute_data, const zval *text, const sharp_type *method,
	void **cache);

/* An object of a generic PHP# class keeps its type arguments in a declared property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN. It holds null until PHP# code, unserialize or the first read stores the IS_PTR of an interned
 * list. The name is also the key serialize writes the list under. */
extern zend_string *sharp_type_arguments_key;

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

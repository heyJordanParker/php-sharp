#ifndef PHP_SHARP_H
#define PHP_SHARP_H

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

#define PHP_SHARP_VERSION "0.2.0"

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

/* Whether `text` is an open type argument list: one that writes a type parameter of the class of the method it is in as
 * `$` and its index, `$0` for the first, which stands for this's type argument at that index. */
bool sharp_type_list_is_open(const char *text, size_t length);

/* The interned type argument list the open `text` spells once each `$i` in it is `object`'s type argument i, for code
 * written in class `scope`, cached for `object`'s class in the three pointers at `cache`. NULL when `object` is not of
 * class `scope`, `scope` declares no type parameter, or `text` names an index `object` has no type argument at. */
const sharp_type *sharp_type_list_of_this(
	const zval *text, zend_object *object, const zend_class_entry *scope, void **cache);

/* An object of a generic PHP# class keeps its type arguments in a declared property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN. It holds the class's bounds as a type text until PHP# code or unserialize stores the
 * IS_PTR of an interned list. The name is also the key serialize writes the list under. */
extern zend_string *sharp_type_arguments_key;

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

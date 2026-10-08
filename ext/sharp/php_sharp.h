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
 * `List<App.Order>`, `(int|string)?`, `Function<bool(App.Order, int)>`. Each text parses once into a sharp_type,
 * interned for the process, so equal types are one pointer. None is freed before module shutdown, so a run-time
 * cache slot may keep one. */
typedef enum {
	/* A type argument list, `App.Order, int`: each argument is a member. */
	SHARP_TYPE_LIST,
	/* A built-in type or a class, with its type arguments as members: `int`, `List<int>`, `App.Pair<int, string>`. */
	SHARP_TYPE_NAMED,
	/* `T?`: T is the one member. */
	SHARP_TYPE_NULLABLE,
	/* `A|B`: the members, sorted by their text. */
	SHARP_TYPE_UNION,
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

/* The interned type argument list `text` spells, or NULL when it spells none. */
const sharp_type *sharp_type_list(const char *text, size_t length);

/* An object of a generic PHP# class keeps its type arguments in a declared property of this name, which carries
 * ZEND_ACC_SHARP_HIDDEN. It holds the class's bounds as a type text until PHP# code or unserialize stores the
 * IS_PTR of an interned list. The name is also the key serialize writes the list under. */
extern zend_string *sharp_type_arguments_key;

/* The type arguments of `object`, NULL when its class declares no type parameter. The first read of an object plain
 * PHP created interns its class's bounds. */
const sharp_type *sharp_type_arguments(zend_object *object);

/* Stores `arguments` in the slot of `object`, whose class declares one. */
void sharp_type_arguments_store(zend_object *object, const sharp_type *arguments);

/* Gives `object` the type arguments `text` spells, from serialized data. Fails, with nothing stored, when `object`
 * has no slot, `text` spells no list of as many arguments as its class has type parameters, or a class it names
 * does not load. */
zend_result sharp_type_arguments_unserialize(zend_object *object, const zval *text);

#endif

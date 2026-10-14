#ifndef PHP_SHARP_H
#define PHP_SHARP_H

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

#define PHP_SHARP_VERSION "0.3.1"

/* The VM makes the Sharp\Collection receiver of a PHP# method call on a collection with these, see
 * ZEND_SHARP_OPERATOR in Zend/zend_compile.h: an array, a List, or a Sharp\Map or Sharp\Set object, see
 * SHARP_IS_COLLECTION. The receiver may point at a local of the calling frame, so a backtrace never
 * hands it out as a frame's object. */
extern zend_class_entry *sharp_ce_collection;
void sharp_collection_of_local(zval *result, zval *local);
void sharp_collection_of_value(zval *result, zval *value);
void sharp_collection_of_property(zval *result, zend_object *object, zend_string *name, void **cache_slot);
void sharp_collection_of_static_property(zval *result, zval *slot, const zend_property_info *info);

/* Spec section 12: a Map and a Set are values, so the VM and the JIT copy a shared one into the holder an index
 * write or unset goes through, as SEPARATE_ARRAY copies a shared array. */
extern zend_class_entry *sharp_ce_map;
extern zend_class_entry *sharp_ce_set;
void sharp_separate(zval *holder);

/* Spec section 12: a PHP array plain PHP writes to a property PHP# declares a Map or a Set crosses into that object, as
 * the property's #[\Sharp\Crossing] plans it, where the engine coerces a value for a typed property. Returns whether
 * it did. sharp_crosses_property says whether the property has such a plan. */
bool sharp_crosses_property(const zend_property_info *info);
bool sharp_cross_property(const zend_property_info *info, zval *value);

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

#endif

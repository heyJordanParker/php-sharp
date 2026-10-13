#ifndef PHP_SHARP_H
#define PHP_SHARP_H

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

#define PHP_SHARP_VERSION "0.3.1"

/* The VM makes the Sharp\Collection receiver of a PHP# method call on an array with these, see
 * ZEND_SHARP_OPERATOR in Zend/zend_compile.h. The receiver may point at a local of the calling frame,
 * so a backtrace never hands it out as a frame's object. */
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

#define SHARP_SEPARATE(holder) do { \
	if (UNEXPECTED((Z_OBJCE_P(holder) == sharp_ce_map || Z_OBJCE_P(holder) == sharp_ce_set) \
			&& GC_REFCOUNT(Z_OBJ_P(holder)) > 1)) { \
		sharp_separate(holder); \
	} \
} while (0)

#endif

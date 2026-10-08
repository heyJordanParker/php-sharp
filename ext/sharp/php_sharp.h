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

/* Spec section 14 runs a member of a class as a function value, on the cold paths where PHP finds no member of the
 * name a PHP# file uses: a call of a missing method calls the function a property of that name holds, and a read
 * of a missing property, or of a missing class member, gives a method of that name as a Closure. */
zend_function *sharp_property_call(zend_object *object, zend_string *name);
zval *sharp_method_value(zend_class_entry *ce, zend_object *object, zend_string *name, zval *result);

#endif

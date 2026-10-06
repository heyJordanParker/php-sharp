#ifndef PHP_SHARP_H
#define PHP_SHARP_H

extern zend_module_entry sharp_module_entry;
#define phpext_sharp_ptr &sharp_module_entry

/* The VM makes the Sharp\Collection receiver of a PHP# method call on an array with these, see
 * ZEND_SHARP_OPERATOR in Zend/zend_compile.h. */
void sharp_collection_of_local(zval *result, zval *local);
void sharp_collection_of_value(zval *result, zval *value);
void sharp_collection_of_property(zval *result, zend_object *object, zend_string *name, void **cache_slot);

/* Spec section 14 runs a member of a class as a function value, on the cold paths where PHP finds no member of the
 * name a PHP# file uses: a call of a missing method calls the function a property of that name holds, and a read
 * of a missing property gives a method of that name as a Closure. */
zend_function *sharp_property_call(zend_object *object, zend_string *name);
zval *sharp_method_value(zend_object *object, zend_string *name, zval *result);

#endif

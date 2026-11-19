/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: ef90abd01a00cb14907f76a548ac2704e066e158 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_statics, 1, 2, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, field, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_keep, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
	ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, compute, Closure, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_equals, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, other, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_jsonEncode, 0, 3, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, flags, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, indent, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_jsonDecode, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, json, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_parseAs, 0, 3, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, text, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_parseVariant, 0, 2, IS_OBJECT, 1)
	ZEND_ARG_TYPE_INFO(0, family, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, throws, _IS_BOOL, 0, "true")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_withTag, 0, 2, IS_STRING, 1)
	ZEND_ARG_TYPE_INFO(0, family, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, tag, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_resolve, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, host, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_processorCount, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_compare, 0, 2, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, left, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, right, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_readGlobal, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, key, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_writeGlobal, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, key, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_includeGlobal, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, path, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_accessors, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_createLazy, 0, 2, IS_OBJECT, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, initialize, IS_CALLABLE, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_arrayOf, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, receiver, IS_MIXED, 0, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_returned, 0, 5, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, result, IS_MIXED, 0)
	ZEND_ARG_TYPE_MASK(0, implementer, MAY_BE_OBJECT|MAY_BE_STRING|MAY_BE_NULL, NULL)
	ZEND_ARG_TYPE_INFO(0, method, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, types, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, target, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_elements, 0, 2, Sharp\\Internal\\Sequence, 0)
	ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
	ZEND_ARG_TYPE_INFO(0, pairs, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_properties, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_methods arginfo_Sharp_Internal_properties

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_constructor, 0, 1, IS_ARRAY, 1)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_hiddenMethod, 0, 2, Closure, 1)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_typeArguments arginfo_Sharp_Internal_properties

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_typeKind, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_isNullable, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_nonNullable arginfo_Sharp_Internal_typeKind

#define arginfo_Sharp_Internal_typeParts arginfo_Sharp_Internal_properties

#define arginfo_Sharp_Internal_tupleNames arginfo_Sharp_Internal_properties

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_genericDefinition, 0, 1, IS_STRING, 1)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_typeParameters arginfo_Sharp_Internal_properties

#define arginfo_Sharp_Internal_baseType arginfo_Sharp_Internal_genericDefinition

#define arginfo_Sharp_Internal_interfaces arginfo_Sharp_Internal_properties

#define arginfo_Sharp_Internal_classFacts arginfo_Sharp_Internal_properties

#define arginfo_Sharp_Internal_enumBackingType arginfo_Sharp_Internal_genericDefinition

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_makeGenericType, 0, 2, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, arguments, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_enumCases arginfo_Sharp_Internal_properties

#define arginfo_Sharp_Internal_constants arginfo_Sharp_Internal_properties

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_constant, 0, 2, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_createInstance, 0, 2, IS_OBJECT, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, arguments, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_createDelegate, 0, 4, Closure, 0)
	ZEND_ARG_TYPE_INFO(0, readOn, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, method, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_createInvoker arginfo_Sharp_Internal_createDelegate

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_read, 0, 3, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, receiver, IS_OBJECT, 1)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, property, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_write, 0, 4, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, receiver, IS_OBJECT, 1)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, property, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_readField, 0, 3, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, receiver, IS_OBJECT, 1)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_writeField, 0, 4, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, receiver, IS_OBJECT, 1)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, field, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_defaultValue, 0, 3, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, method, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, position, IS_LONG, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_readModule arginfo_Sharp_Internal_includeGlobal

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_testModule, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, bindings, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_startTest, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_endTest, 0, 0, IS_VOID, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_runScoped, 0, 1, IS_MIXED, 0)
	ZEND_ARG_OBJ_INFO(0, body, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_building, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_build, 0, 2, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
	ZEND_ARG_OBJ_INFO(0, body, Closure, 0)
	ZEND_ARG_VARIADIC_TYPE_INFO(0, arguments, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_emit, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, event, IS_OBJECT, 0)
	ZEND_ARG_TYPE_INFO(0, dispatcher, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_listenersFor, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, event, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_listen, 0, 2, Sharp\\Listening, 0)
	ZEND_ARG_TYPE_INFO(0, event, IS_STRING, 0)
	ZEND_ARG_OBJ_INFO(0, listener, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_add, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_insert, 0, 3, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_set, 0, 3, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_remove, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_removeAt, 0, 2, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_removeLast, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(1, list, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_clear, 0, 1, IS_VOID, 0)
	ZEND_ARG_OBJ_TYPE_MASK(1, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_get, 0, 2, IS_MIXED, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map, MAY_BE_ARRAY, NULL)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_size, 0, 1, IS_LONG, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, table, Sharp\\Map|Sharp\\Set, 0, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_containsKey, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, table, Sharp\\Map|Sharp\\Set, 0, NULL)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_removeKey, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_OBJ_TYPE_MASK(1, table, Sharp\\Map|Sharp\\Set, 0, NULL)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_addKey, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_OBJ_INFO(1, table, Sharp\\Set, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_put, 0, 3, IS_VOID, 0)
	ZEND_ARG_OBJ_INFO(1, map, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_getOrPut, 0, 3, IS_MIXED, 0)
	ZEND_ARG_OBJ_INFO(1, map, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
	ZEND_ARG_OBJ_INFO(0, defaultValue, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_keys, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, table, Sharp\\Map|Sharp\\Set, 0, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_values, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, map, Sharp\\Map, 0)
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_pairs arginfo_Sharp_Internal_values

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_contains, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_indexOf, 0, 2, IS_LONG, 1)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_nonEmpty, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_entries, 0, 1, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, type, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_toSet, 0, 1, Sharp\\Set, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, type, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_filter, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_filterEntries, 0, 2, Sharp\\Map, 0)
	ZEND_ARG_OBJ_INFO(0, map, Sharp\\Map, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_filterElements, 0, 2, Sharp\\Set, 0)
	ZEND_ARG_OBJ_INFO(0, set, Sharp\\Set, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_map, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_flatMap, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_MASK_EX(arginfo_Sharp_Internal_sumOf, 0, 2, MAY_BE_LONG|MAY_BE_DOUBLE)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_first, 0, 2, IS_MIXED, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_any, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_Sharp_Internal_groupBy, 0, 2, Sharp\\Map, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, key, Closure, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, type, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_associateBy arginfo_Sharp_Internal_groupBy

#define arginfo_Sharp_Internal_firstOrNull arginfo_Sharp_Internal_first

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_sortedBy, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, order, Closure, MAY_BE_ARRAY|MAY_BE_NULL, "null")
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_sortedByDescending arginfo_Sharp_Internal_sortedBy

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_sortedWith, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, list, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, comparison, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_minBy, 0, 2, IS_MIXED, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, order, Closure, MAY_BE_ARRAY|MAY_BE_NULL, "null")
ZEND_END_ARG_INFO()

#define arginfo_Sharp_Internal_maxBy arginfo_Sharp_Internal_minBy

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Position___construct, 0, 0, 4)
	ZEND_ARG_TYPE_INFO(0, file, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, line, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, column, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, function, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Container_get, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, id, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Container_has, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, id, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Listening___construct, 0, 0, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Internal_Sequence___construct arginfo_class_Sharp_Listening___construct

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Internal_Sequence_from, 0, 1, Sharp\\Internal\\Sequence, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, source, Traversable, MAY_BE_ARRAY, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Internal_Sequence_filter, 0, 1, Sharp\\Internal\\Sequence, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Internal_Sequence_map, 0, 1, Sharp\\Internal\\Sequence, 0)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Internal_Sequence_take, 0, 1, Sharp\\Internal\\Sequence, 0)
	ZEND_ARG_TYPE_INFO(0, count, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Internal_Sequence_toList, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Internal_Sequence_getIterator, 0, 0, Iterator, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Internal_Listener___construct arginfo_class_Sharp_Listening___construct

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Internal_Listener_hear, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, event, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Internal_Delegate___construct arginfo_class_Sharp_Listening___construct

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Internal_Delegate_call, 0, 0, IS_MIXED, 0)
	ZEND_ARG_VARIADIC_TYPE_INFO(0, arguments, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_FUNCTION(Sharp_Internal_statics);
ZEND_FUNCTION(Sharp_Internal_keep);
ZEND_FUNCTION(Sharp_Internal_equals);
ZEND_FUNCTION(Sharp_Internal_jsonEncode);
ZEND_FUNCTION(Sharp_Internal_jsonDecode);
ZEND_FUNCTION(Sharp_Internal_parseAs);
ZEND_FUNCTION(Sharp_Internal_parseVariant);
ZEND_FUNCTION(Sharp_Internal_withTag);
ZEND_FUNCTION(Sharp_Internal_resolve);
ZEND_FUNCTION(Sharp_Internal_processorCount);
ZEND_FUNCTION(Sharp_Internal_compare);
ZEND_FUNCTION(Sharp_Internal_readGlobal);
ZEND_FUNCTION(Sharp_Internal_writeGlobal);
ZEND_FUNCTION(Sharp_Internal_includeGlobal);
ZEND_FUNCTION(Sharp_Internal_accessors);
ZEND_FUNCTION(Sharp_Internal_createLazy);
ZEND_FUNCTION(Sharp_Internal_arrayOf);
ZEND_FUNCTION(Sharp_Internal_returned);
ZEND_FUNCTION(Sharp_Internal_elements);
ZEND_FUNCTION(Sharp_Internal_properties);
ZEND_FUNCTION(Sharp_Internal_methods);
ZEND_FUNCTION(Sharp_Internal_constructor);
ZEND_FUNCTION(Sharp_Internal_hiddenMethod);
ZEND_FUNCTION(Sharp_Internal_typeArguments);
ZEND_FUNCTION(Sharp_Internal_typeKind);
ZEND_FUNCTION(Sharp_Internal_isNullable);
ZEND_FUNCTION(Sharp_Internal_nonNullable);
ZEND_FUNCTION(Sharp_Internal_typeParts);
ZEND_FUNCTION(Sharp_Internal_tupleNames);
ZEND_FUNCTION(Sharp_Internal_genericDefinition);
ZEND_FUNCTION(Sharp_Internal_typeParameters);
ZEND_FUNCTION(Sharp_Internal_baseType);
ZEND_FUNCTION(Sharp_Internal_interfaces);
ZEND_FUNCTION(Sharp_Internal_classFacts);
ZEND_FUNCTION(Sharp_Internal_enumBackingType);
ZEND_FUNCTION(Sharp_Internal_makeGenericType);
ZEND_FUNCTION(Sharp_Internal_enumCases);
ZEND_FUNCTION(Sharp_Internal_constants);
ZEND_FUNCTION(Sharp_Internal_constant);
ZEND_FUNCTION(Sharp_Internal_createInstance);
ZEND_FUNCTION(Sharp_Internal_createDelegate);
ZEND_FUNCTION(Sharp_Internal_createInvoker);
ZEND_FUNCTION(Sharp_Internal_read);
ZEND_FUNCTION(Sharp_Internal_write);
ZEND_FUNCTION(Sharp_Internal_readField);
ZEND_FUNCTION(Sharp_Internal_writeField);
ZEND_FUNCTION(Sharp_Internal_defaultValue);
ZEND_FUNCTION(Sharp_Internal_readModule);
ZEND_FUNCTION(Sharp_Internal_testModule);
ZEND_FUNCTION(Sharp_Internal_startTest);
ZEND_FUNCTION(Sharp_Internal_endTest);
ZEND_FUNCTION(Sharp_Internal_runScoped);
ZEND_FUNCTION(Sharp_Internal_building);
ZEND_FUNCTION(Sharp_Internal_build);
ZEND_FUNCTION(Sharp_Internal_emit);
ZEND_FUNCTION(Sharp_Internal_listenersFor);
ZEND_FUNCTION(Sharp_Internal_listen);
ZEND_FUNCTION(Sharp_Internal_add);
ZEND_FUNCTION(Sharp_Internal_insert);
ZEND_FUNCTION(Sharp_Internal_set);
ZEND_FUNCTION(Sharp_Internal_remove);
ZEND_FUNCTION(Sharp_Internal_removeAt);
ZEND_FUNCTION(Sharp_Internal_removeLast);
ZEND_FUNCTION(Sharp_Internal_clear);
ZEND_FUNCTION(Sharp_Internal_get);
ZEND_FUNCTION(Sharp_Internal_size);
ZEND_FUNCTION(Sharp_Internal_containsKey);
ZEND_FUNCTION(Sharp_Internal_removeKey);
ZEND_FUNCTION(Sharp_Internal_addKey);
ZEND_FUNCTION(Sharp_Internal_put);
ZEND_FUNCTION(Sharp_Internal_getOrPut);
ZEND_FUNCTION(Sharp_Internal_keys);
ZEND_FUNCTION(Sharp_Internal_values);
ZEND_FUNCTION(Sharp_Internal_pairs);
ZEND_FUNCTION(Sharp_Internal_contains);
ZEND_FUNCTION(Sharp_Internal_indexOf);
ZEND_FUNCTION(Sharp_Internal_nonEmpty);
ZEND_FUNCTION(Sharp_Internal_entries);
ZEND_FUNCTION(Sharp_Internal_toSet);
ZEND_FUNCTION(Sharp_Internal_filter);
ZEND_FUNCTION(Sharp_Internal_filterEntries);
ZEND_FUNCTION(Sharp_Internal_filterElements);
ZEND_FUNCTION(Sharp_Internal_map);
ZEND_FUNCTION(Sharp_Internal_flatMap);
ZEND_FUNCTION(Sharp_Internal_sumOf);
ZEND_FUNCTION(Sharp_Internal_first);
ZEND_FUNCTION(Sharp_Internal_any);
ZEND_FUNCTION(Sharp_Internal_groupBy);
ZEND_FUNCTION(Sharp_Internal_associateBy);
ZEND_FUNCTION(Sharp_Internal_firstOrNull);
ZEND_FUNCTION(Sharp_Internal_sortedBy);
ZEND_FUNCTION(Sharp_Internal_sortedByDescending);
ZEND_FUNCTION(Sharp_Internal_sortedWith);
ZEND_FUNCTION(Sharp_Internal_minBy);
ZEND_FUNCTION(Sharp_Internal_maxBy);
ZEND_METHOD(Sharp_Position, __construct);
ZEND_METHOD(Sharp_Container, get);
ZEND_METHOD(Sharp_Container, has);
ZEND_METHOD(Sharp_Listening, __construct);
ZEND_METHOD(Sharp_Internal_Sequence, __construct);
ZEND_METHOD(Sharp_Internal_Sequence, from);
ZEND_METHOD(Sharp_Internal_Sequence, filter);
ZEND_METHOD(Sharp_Internal_Sequence, map);
ZEND_METHOD(Sharp_Internal_Sequence, take);
ZEND_METHOD(Sharp_Internal_Sequence, toList);
ZEND_METHOD(Sharp_Internal_Sequence, getIterator);
ZEND_METHOD(Sharp_Internal_Listener, __construct);
ZEND_METHOD(Sharp_Internal_Listener, hear);
ZEND_METHOD(Sharp_Internal_Delegate, __construct);
ZEND_METHOD(Sharp_Internal_Delegate, call);

static const zend_function_entry ext_functions[] = {
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "statics"), zif_Sharp_Internal_statics, arginfo_Sharp_Internal_statics, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "keep"), zif_Sharp_Internal_keep, arginfo_Sharp_Internal_keep, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "equals"), zif_Sharp_Internal_equals, arginfo_Sharp_Internal_equals, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "jsonEncode"), zif_Sharp_Internal_jsonEncode, arginfo_Sharp_Internal_jsonEncode, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "jsonDecode"), zif_Sharp_Internal_jsonDecode, arginfo_Sharp_Internal_jsonDecode, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "parseAs"), zif_Sharp_Internal_parseAs, arginfo_Sharp_Internal_parseAs, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "parseVariant"), zif_Sharp_Internal_parseVariant, arginfo_Sharp_Internal_parseVariant, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "withTag"), zif_Sharp_Internal_withTag, arginfo_Sharp_Internal_withTag, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "resolve"), zif_Sharp_Internal_resolve, arginfo_Sharp_Internal_resolve, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "processorCount"), zif_Sharp_Internal_processorCount, arginfo_Sharp_Internal_processorCount, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "compare"), zif_Sharp_Internal_compare, arginfo_Sharp_Internal_compare, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "readGlobal"), zif_Sharp_Internal_readGlobal, arginfo_Sharp_Internal_readGlobal, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "writeGlobal"), zif_Sharp_Internal_writeGlobal, arginfo_Sharp_Internal_writeGlobal, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "includeGlobal"), zif_Sharp_Internal_includeGlobal, arginfo_Sharp_Internal_includeGlobal, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "accessors"), zif_Sharp_Internal_accessors, arginfo_Sharp_Internal_accessors, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "createLazy"), zif_Sharp_Internal_createLazy, arginfo_Sharp_Internal_createLazy, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "arrayOf"), zif_Sharp_Internal_arrayOf, arginfo_Sharp_Internal_arrayOf, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "returned"), zif_Sharp_Internal_returned, arginfo_Sharp_Internal_returned, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "elements"), zif_Sharp_Internal_elements, arginfo_Sharp_Internal_elements, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "properties"), zif_Sharp_Internal_properties, arginfo_Sharp_Internal_properties, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "methods"), zif_Sharp_Internal_methods, arginfo_Sharp_Internal_methods, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "constructor"), zif_Sharp_Internal_constructor, arginfo_Sharp_Internal_constructor, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "hiddenMethod"), zif_Sharp_Internal_hiddenMethod, arginfo_Sharp_Internal_hiddenMethod, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "typeArguments"), zif_Sharp_Internal_typeArguments, arginfo_Sharp_Internal_typeArguments, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "typeKind"), zif_Sharp_Internal_typeKind, arginfo_Sharp_Internal_typeKind, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "isNullable"), zif_Sharp_Internal_isNullable, arginfo_Sharp_Internal_isNullable, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "nonNullable"), zif_Sharp_Internal_nonNullable, arginfo_Sharp_Internal_nonNullable, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "typeParts"), zif_Sharp_Internal_typeParts, arginfo_Sharp_Internal_typeParts, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "tupleNames"), zif_Sharp_Internal_tupleNames, arginfo_Sharp_Internal_tupleNames, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "genericDefinition"), zif_Sharp_Internal_genericDefinition, arginfo_Sharp_Internal_genericDefinition, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "typeParameters"), zif_Sharp_Internal_typeParameters, arginfo_Sharp_Internal_typeParameters, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "baseType"), zif_Sharp_Internal_baseType, arginfo_Sharp_Internal_baseType, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "interfaces"), zif_Sharp_Internal_interfaces, arginfo_Sharp_Internal_interfaces, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "classFacts"), zif_Sharp_Internal_classFacts, arginfo_Sharp_Internal_classFacts, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "enumBackingType"), zif_Sharp_Internal_enumBackingType, arginfo_Sharp_Internal_enumBackingType, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "makeGenericType"), zif_Sharp_Internal_makeGenericType, arginfo_Sharp_Internal_makeGenericType, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "enumCases"), zif_Sharp_Internal_enumCases, arginfo_Sharp_Internal_enumCases, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "constants"), zif_Sharp_Internal_constants, arginfo_Sharp_Internal_constants, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "constant"), zif_Sharp_Internal_constant, arginfo_Sharp_Internal_constant, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "createInstance"), zif_Sharp_Internal_createInstance, arginfo_Sharp_Internal_createInstance, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "createDelegate"), zif_Sharp_Internal_createDelegate, arginfo_Sharp_Internal_createDelegate, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "createInvoker"), zif_Sharp_Internal_createInvoker, arginfo_Sharp_Internal_createInvoker, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "read"), zif_Sharp_Internal_read, arginfo_Sharp_Internal_read, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "write"), zif_Sharp_Internal_write, arginfo_Sharp_Internal_write, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "readField"), zif_Sharp_Internal_readField, arginfo_Sharp_Internal_readField, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "writeField"), zif_Sharp_Internal_writeField, arginfo_Sharp_Internal_writeField, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "defaultValue"), zif_Sharp_Internal_defaultValue, arginfo_Sharp_Internal_defaultValue, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "readModule"), zif_Sharp_Internal_readModule, arginfo_Sharp_Internal_readModule, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "testModule"), zif_Sharp_Internal_testModule, arginfo_Sharp_Internal_testModule, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "startTest"), zif_Sharp_Internal_startTest, arginfo_Sharp_Internal_startTest, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "endTest"), zif_Sharp_Internal_endTest, arginfo_Sharp_Internal_endTest, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "runScoped"), zif_Sharp_Internal_runScoped, arginfo_Sharp_Internal_runScoped, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "building"), zif_Sharp_Internal_building, arginfo_Sharp_Internal_building, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "build"), zif_Sharp_Internal_build, arginfo_Sharp_Internal_build, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "emit"), zif_Sharp_Internal_emit, arginfo_Sharp_Internal_emit, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "listenersFor"), zif_Sharp_Internal_listenersFor, arginfo_Sharp_Internal_listenersFor, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "listen"), zif_Sharp_Internal_listen, arginfo_Sharp_Internal_listen, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "add"), zif_Sharp_Internal_add, arginfo_Sharp_Internal_add, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "insert"), zif_Sharp_Internal_insert, arginfo_Sharp_Internal_insert, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "set"), zif_Sharp_Internal_set, arginfo_Sharp_Internal_set, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "remove"), zif_Sharp_Internal_remove, arginfo_Sharp_Internal_remove, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "removeAt"), zif_Sharp_Internal_removeAt, arginfo_Sharp_Internal_removeAt, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "removeLast"), zif_Sharp_Internal_removeLast, arginfo_Sharp_Internal_removeLast, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "clear"), zif_Sharp_Internal_clear, arginfo_Sharp_Internal_clear, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "get"), zif_Sharp_Internal_get, arginfo_Sharp_Internal_get, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "size"), zif_Sharp_Internal_size, arginfo_Sharp_Internal_size, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "containsKey"), zif_Sharp_Internal_containsKey, arginfo_Sharp_Internal_containsKey, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "removeKey"), zif_Sharp_Internal_removeKey, arginfo_Sharp_Internal_removeKey, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "addKey"), zif_Sharp_Internal_addKey, arginfo_Sharp_Internal_addKey, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "put"), zif_Sharp_Internal_put, arginfo_Sharp_Internal_put, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "getOrPut"), zif_Sharp_Internal_getOrPut, arginfo_Sharp_Internal_getOrPut, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "keys"), zif_Sharp_Internal_keys, arginfo_Sharp_Internal_keys, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "values"), zif_Sharp_Internal_values, arginfo_Sharp_Internal_values, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "pairs"), zif_Sharp_Internal_pairs, arginfo_Sharp_Internal_pairs, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "contains"), zif_Sharp_Internal_contains, arginfo_Sharp_Internal_contains, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "indexOf"), zif_Sharp_Internal_indexOf, arginfo_Sharp_Internal_indexOf, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "nonEmpty"), zif_Sharp_Internal_nonEmpty, arginfo_Sharp_Internal_nonEmpty, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "entries"), zif_Sharp_Internal_entries, arginfo_Sharp_Internal_entries, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "toSet"), zif_Sharp_Internal_toSet, arginfo_Sharp_Internal_toSet, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "filter"), zif_Sharp_Internal_filter, arginfo_Sharp_Internal_filter, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "filterEntries"), zif_Sharp_Internal_filterEntries, arginfo_Sharp_Internal_filterEntries, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "filterElements"), zif_Sharp_Internal_filterElements, arginfo_Sharp_Internal_filterElements, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "map"), zif_Sharp_Internal_map, arginfo_Sharp_Internal_map, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "flatMap"), zif_Sharp_Internal_flatMap, arginfo_Sharp_Internal_flatMap, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "sumOf"), zif_Sharp_Internal_sumOf, arginfo_Sharp_Internal_sumOf, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "first"), zif_Sharp_Internal_first, arginfo_Sharp_Internal_first, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "any"), zif_Sharp_Internal_any, arginfo_Sharp_Internal_any, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "groupBy"), zif_Sharp_Internal_groupBy, arginfo_Sharp_Internal_groupBy, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "associateBy"), zif_Sharp_Internal_associateBy, arginfo_Sharp_Internal_associateBy, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "firstOrNull"), zif_Sharp_Internal_firstOrNull, arginfo_Sharp_Internal_firstOrNull, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "sortedBy"), zif_Sharp_Internal_sortedBy, arginfo_Sharp_Internal_sortedBy, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "sortedByDescending"), zif_Sharp_Internal_sortedByDescending, arginfo_Sharp_Internal_sortedByDescending, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "sortedWith"), zif_Sharp_Internal_sortedWith, arginfo_Sharp_Internal_sortedWith, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "minBy"), zif_Sharp_Internal_minBy, arginfo_Sharp_Internal_minBy, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "maxBy"), zif_Sharp_Internal_maxBy, arginfo_Sharp_Internal_maxBy, 0, NULL, NULL)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Position_methods[] = {
	ZEND_ME(Sharp_Position, __construct, arginfo_class_Sharp_Position___construct, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Container_methods[] = {
	ZEND_ME(Sharp_Container, get, arginfo_class_Sharp_Container_get, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Container, has, arginfo_class_Sharp_Container_has, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Listening_methods[] = {
	ZEND_ME(Sharp_Listening, __construct, arginfo_class_Sharp_Listening___construct, ZEND_ACC_PRIVATE)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Internal_Sequence_methods[] = {
	ZEND_ME(Sharp_Internal_Sequence, __construct, arginfo_class_Sharp_Internal_Sequence___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Internal_Sequence, from, arginfo_class_Sharp_Internal_Sequence_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Internal_Sequence, filter, arginfo_class_Sharp_Internal_Sequence_filter, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Internal_Sequence, map, arginfo_class_Sharp_Internal_Sequence_map, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Internal_Sequence, take, arginfo_class_Sharp_Internal_Sequence_take, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Internal_Sequence, toList, arginfo_class_Sharp_Internal_Sequence_toList, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Internal_Sequence, getIterator, arginfo_class_Sharp_Internal_Sequence_getIterator, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Internal_Listener_methods[] = {
	ZEND_ME(Sharp_Internal_Listener, __construct, arginfo_class_Sharp_Internal_Listener___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Internal_Listener, hear, arginfo_class_Sharp_Internal_Listener_hear, ZEND_ACC_PRIVATE)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Internal_Delegate_methods[] = {
	ZEND_ME(Sharp_Internal_Delegate, __construct, arginfo_class_Sharp_Internal_Delegate___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Internal_Delegate, call, arginfo_class_Sharp_Internal_Delegate_call, ZEND_ACC_PRIVATE)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Position(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Position", class_Sharp_Position_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);

	zval property_file_default_value;
	ZVAL_UNDEF(&property_file_default_value);
	zend_declare_typed_property(class_entry, ZSTR_KNOWN(ZEND_STR_FILE), &property_file_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_READONLY, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_STRING));

	zval property_directory_default_value;
	ZVAL_UNDEF(&property_directory_default_value);
	zend_string *property_directory_name = zend_string_init("directory", sizeof("directory") - 1, 1);
	zend_declare_typed_property(class_entry, property_directory_name, &property_directory_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_READONLY, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_STRING));
	zend_string_release(property_directory_name);

	zval property_line_default_value;
	ZVAL_UNDEF(&property_line_default_value);
	zend_declare_typed_property(class_entry, ZSTR_KNOWN(ZEND_STR_LINE), &property_line_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_READONLY, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_LONG));

	zval property_column_default_value;
	ZVAL_UNDEF(&property_column_default_value);
	zend_string *property_column_name = zend_string_init("column", sizeof("column") - 1, 1);
	zend_declare_typed_property(class_entry, property_column_name, &property_column_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_READONLY, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_LONG));
	zend_string_release(property_column_name);

	zval property_function_default_value;
	ZVAL_UNDEF(&property_function_default_value);
	zend_declare_typed_property(class_entry, ZSTR_KNOWN(ZEND_STR_FUNCTION), &property_function_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_READONLY, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_STRING));

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Container(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Container", class_Sharp_Container_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_ContainerError(zend_class_entry *class_entry_Error)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "ContainerError", NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_Error, ZEND_ACC_FINAL);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Listening(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Listening", class_Sharp_Listening_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Internal_Sequence(zend_class_entry *class_entry_IteratorAggregate)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp\\Internal", "Sequence", class_Sharp_Internal_Sequence_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);
	zend_class_implements(class_entry, 1, class_entry_IteratorAggregate);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Internal_Listener(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp\\Internal", "Listener", class_Sharp_Internal_Listener_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Internal_Delegate(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp\\Internal", "Delegate", class_Sharp_Internal_Delegate_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

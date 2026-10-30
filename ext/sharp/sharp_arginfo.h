/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 8df02186c8c85e437778a535df9c2f2aa5575b8d */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_accepts, 0, 4, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, type, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(1, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, path, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(1, problems, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_arrayOf, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, receiver, IS_MIXED, 0, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_readModule, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, path, IS_STRING, 0)
ZEND_END_ARG_INFO()

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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_emit, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, event, IS_OBJECT, 0)
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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_Sharp_Internal_sortedBy, 0, 2, IS_ARRAY, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, collection, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY, NULL)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
	ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, comparison, Closure, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Collection___construct, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_add, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_set, 0, 2, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_insert, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_removeAt, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_get, 0, 1, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Collection_entries, 0, 0, Sharp\\Map, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, typeArguments, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_has, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_contains arginfo_class_Sharp_Collection_add

#define arginfo_class_Sharp_Collection_remove arginfo_class_Sharp_Collection_add

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_put, 0, 2, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_clear arginfo_Sharp_Internal_endTest

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_count, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_keys, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_values arginfo_class_Sharp_Collection_keys

#define arginfo_class_Sharp_Collection_toList arginfo_class_Sharp_Collection_keys

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_TYPE_MASK_EX(arginfo_class_Sharp_Collection_filter, 0, 1, Sharp\\Map|Sharp\\Set, MAY_BE_ARRAY)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_map, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_flatMap arginfo_class_Sharp_Collection_map

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_MASK_EX(arginfo_class_Sharp_Collection_sumOf, 0, 1, MAY_BE_LONG|MAY_BE_DOUBLE)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_first, 0, 1, IS_MIXED, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_any, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Collection_groupBy, 0, 1, Sharp\\Map, 0)
	ZEND_ARG_OBJ_INFO(0, key, Closure, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, typeArguments, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_associateBy arginfo_class_Sharp_Collection_groupBy

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_sortedBy, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
	ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, comparison, Closure, 1, "null")
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Sequence___construct arginfo_class_Sharp_Collection___construct

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Sequence_from, 0, 1, Sharp\\Sequence, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, source, Traversable, MAY_BE_ARRAY, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Sequence_filter, 0, 1, Sharp\\Sequence, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Sequence_map, 0, 1, Sharp\\Sequence, 0)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Sequence_take, 0, 1, Sharp\\Sequence, 0)
	ZEND_ARG_TYPE_INFO(0, count, IS_LONG, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Sequence_toList arginfo_class_Sharp_Collection_keys

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_Sharp_Sequence_getIterator, 0, 0, Iterator, 0)
ZEND_END_ARG_INFO()

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

#define arginfo_class_Sharp_Listening___construct arginfo_class_Sharp_Collection___construct

#define arginfo_class_Sharp_Internal_Listener___construct arginfo_class_Sharp_Collection___construct

#define arginfo_class_Sharp_Internal_Listener_hear arginfo_Sharp_Internal_emit

ZEND_FUNCTION(Sharp_Internal_accepts);
ZEND_FUNCTION(Sharp_Internal_statics);
ZEND_FUNCTION(Sharp_Internal_keep);
ZEND_FUNCTION(Sharp_Internal_equals);
ZEND_FUNCTION(Sharp_Internal_arrayOf);
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
ZEND_FUNCTION(Sharp_Internal_sortedBy);
ZEND_METHOD(Sharp_Collection, __construct);
ZEND_METHOD(Sharp_Collection, add);
ZEND_METHOD(Sharp_Collection, set);
ZEND_METHOD(Sharp_Collection, insert);
ZEND_METHOD(Sharp_Collection, removeAt);
ZEND_METHOD(Sharp_Collection, get);
ZEND_METHOD(Sharp_Collection, entries);
ZEND_METHOD(Sharp_Collection, has);
ZEND_METHOD(Sharp_Collection, contains);
ZEND_METHOD(Sharp_Collection, remove);
ZEND_METHOD(Sharp_Collection, put);
ZEND_METHOD(Sharp_Collection, clear);
ZEND_METHOD(Sharp_Collection, count);
ZEND_METHOD(Sharp_Collection, keys);
ZEND_METHOD(Sharp_Collection, values);
ZEND_METHOD(Sharp_Collection, toList);
ZEND_METHOD(Sharp_Collection, filter);
ZEND_METHOD(Sharp_Collection, map);
ZEND_METHOD(Sharp_Collection, flatMap);
ZEND_METHOD(Sharp_Collection, sumOf);
ZEND_METHOD(Sharp_Collection, first);
ZEND_METHOD(Sharp_Collection, any);
ZEND_METHOD(Sharp_Collection, groupBy);
ZEND_METHOD(Sharp_Collection, associateBy);
ZEND_METHOD(Sharp_Collection, sortedBy);
ZEND_METHOD(Sharp_Sequence, __construct);
ZEND_METHOD(Sharp_Sequence, from);
ZEND_METHOD(Sharp_Sequence, filter);
ZEND_METHOD(Sharp_Sequence, map);
ZEND_METHOD(Sharp_Sequence, take);
ZEND_METHOD(Sharp_Sequence, toList);
ZEND_METHOD(Sharp_Sequence, getIterator);
ZEND_METHOD(Sharp_Position, __construct);
ZEND_METHOD(Sharp_Container, get);
ZEND_METHOD(Sharp_Container, has);
ZEND_METHOD(Sharp_Listening, __construct);
ZEND_METHOD(Sharp_Internal_Listener, __construct);
ZEND_METHOD(Sharp_Internal_Listener, hear);

static const zend_function_entry ext_functions[] = {
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "accepts"), zif_Sharp_Internal_accepts, arginfo_Sharp_Internal_accepts, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "statics"), zif_Sharp_Internal_statics, arginfo_Sharp_Internal_statics, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "keep"), zif_Sharp_Internal_keep, arginfo_Sharp_Internal_keep, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "equals"), zif_Sharp_Internal_equals, arginfo_Sharp_Internal_equals, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "arrayOf"), zif_Sharp_Internal_arrayOf, arginfo_Sharp_Internal_arrayOf, 0, NULL, NULL)
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
	ZEND_RAW_FENTRY(ZEND_NS_NAME("Sharp\\Internal", "sortedBy"), zif_Sharp_Internal_sortedBy, arginfo_Sharp_Internal_sortedBy, 0, NULL, NULL)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Collection_methods[] = {
	ZEND_ME(Sharp_Collection, __construct, arginfo_class_Sharp_Collection___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Collection, add, arginfo_class_Sharp_Collection_add, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, set, arginfo_class_Sharp_Collection_set, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, insert, arginfo_class_Sharp_Collection_insert, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, removeAt, arginfo_class_Sharp_Collection_removeAt, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, get, arginfo_class_Sharp_Collection_get, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, entries, arginfo_class_Sharp_Collection_entries, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, has, arginfo_class_Sharp_Collection_has, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, contains, arginfo_class_Sharp_Collection_contains, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, remove, arginfo_class_Sharp_Collection_remove, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, put, arginfo_class_Sharp_Collection_put, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, clear, arginfo_class_Sharp_Collection_clear, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, count, arginfo_class_Sharp_Collection_count, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, keys, arginfo_class_Sharp_Collection_keys, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, values, arginfo_class_Sharp_Collection_values, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, toList, arginfo_class_Sharp_Collection_toList, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, filter, arginfo_class_Sharp_Collection_filter, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, map, arginfo_class_Sharp_Collection_map, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, flatMap, arginfo_class_Sharp_Collection_flatMap, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, sumOf, arginfo_class_Sharp_Collection_sumOf, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, first, arginfo_class_Sharp_Collection_first, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, any, arginfo_class_Sharp_Collection_any, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, groupBy, arginfo_class_Sharp_Collection_groupBy, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, associateBy, arginfo_class_Sharp_Collection_associateBy, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, sortedBy, arginfo_class_Sharp_Collection_sortedBy, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Sequence_methods[] = {
	ZEND_ME(Sharp_Sequence, __construct, arginfo_class_Sharp_Sequence___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Sequence, from, arginfo_class_Sharp_Sequence_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Sequence, filter, arginfo_class_Sharp_Sequence_filter, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Sequence, map, arginfo_class_Sharp_Sequence_map, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Sequence, take, arginfo_class_Sharp_Sequence_take, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Sequence, toList, arginfo_class_Sharp_Sequence_toList, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Sequence, getIterator, arginfo_class_Sharp_Sequence_getIterator, ZEND_ACC_PUBLIC)
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

static const zend_function_entry class_Sharp_Internal_Listener_methods[] = {
	ZEND_ME(Sharp_Internal_Listener, __construct, arginfo_class_Sharp_Internal_Listener___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Internal_Listener, hear, arginfo_class_Sharp_Internal_Listener_hear, ZEND_ACC_PRIVATE)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Collection(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Collection", class_Sharp_Collection_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Sequence(zend_class_entry *class_entry_IteratorAggregate)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Sequence", class_Sharp_Sequence_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);
	zend_class_implements(class_entry, 1, class_entry_IteratorAggregate);

	return class_entry;
}

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

static zend_class_entry *register_class_Sharp_Internal_Listener(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp\\Internal", "Listener", class_Sharp_Internal_Listener_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

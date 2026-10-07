/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 99eb32d2518ef30a06f06e15bee0a9bd9d27b692 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Int_parse, 0, 1, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Int_tryParse, 0, 1, IS_LONG, 1)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Float_parse, 0, 1, IS_DOUBLE, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Float_tryParse, 0, 1, IS_DOUBLE, 1)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Collection___construct, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_add, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_set, 0, 2, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, index, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_get, 0, 1, IS_MIXED, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, key, BackedEnum, MAY_BE_LONG|MAY_BE_STRING, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_entries, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_delete, 0, 1, IS_VOID, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, key, BackedEnum, MAY_BE_LONG|MAY_BE_STRING, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_filter, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_filterValues arginfo_class_Sharp_Collection_filter

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_map, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, transform, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_MASK_EX(arginfo_class_Sharp_Collection_sumOf, 0, 1, MAY_BE_LONG|MAY_BE_DOUBLE)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_first, 0, 1, IS_MIXED, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_any, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_OBJ_INFO(0, predicate, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_groupBy, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, key, Closure, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Collection_associateBy arginfo_class_Sharp_Collection_groupBy

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Collection_sortedBy, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, selector, Closure, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(Sharp_Int, parse);
ZEND_METHOD(Sharp_Int, tryParse);
ZEND_METHOD(Sharp_Float, parse);
ZEND_METHOD(Sharp_Float, tryParse);
ZEND_METHOD(Sharp_Collection, __construct);
ZEND_METHOD(Sharp_Collection, add);
ZEND_METHOD(Sharp_Collection, set);
ZEND_METHOD(Sharp_Collection, get);
ZEND_METHOD(Sharp_Collection, entries);
ZEND_METHOD(Sharp_Collection, delete);
ZEND_METHOD(Sharp_Collection, filter);
ZEND_METHOD(Sharp_Collection, filterValues);
ZEND_METHOD(Sharp_Collection, map);
ZEND_METHOD(Sharp_Collection, sumOf);
ZEND_METHOD(Sharp_Collection, first);
ZEND_METHOD(Sharp_Collection, any);
ZEND_METHOD(Sharp_Collection, groupBy);
ZEND_METHOD(Sharp_Collection, associateBy);
ZEND_METHOD(Sharp_Collection, sortedBy);

static const zend_function_entry class_Sharp_Int_methods[] = {
	ZEND_ME(Sharp_Int, parse, arginfo_class_Sharp_Int_parse, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Int, tryParse, arginfo_class_Sharp_Int_tryParse, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Float_methods[] = {
	ZEND_ME(Sharp_Float, parse, arginfo_class_Sharp_Float_parse, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(Sharp_Float, tryParse, arginfo_class_Sharp_Float_tryParse, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Collection_methods[] = {
	ZEND_ME(Sharp_Collection, __construct, arginfo_class_Sharp_Collection___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(Sharp_Collection, add, arginfo_class_Sharp_Collection_add, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, set, arginfo_class_Sharp_Collection_set, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, get, arginfo_class_Sharp_Collection_get, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, entries, arginfo_class_Sharp_Collection_entries, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, delete, arginfo_class_Sharp_Collection_delete, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, filter, arginfo_class_Sharp_Collection_filter, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, filterValues, arginfo_class_Sharp_Collection_filterValues, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, map, arginfo_class_Sharp_Collection_map, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, sumOf, arginfo_class_Sharp_Collection_sumOf, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, first, arginfo_class_Sharp_Collection_first, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, any, arginfo_class_Sharp_Collection_any, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, groupBy, arginfo_class_Sharp_Collection_groupBy, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, associateBy, arginfo_class_Sharp_Collection_associateBy, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Collection, sortedBy, arginfo_class_Sharp_Collection_sortedBy, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Int(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Int", class_Sharp_Int_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Float(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Float", class_Sharp_Float_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);

	return class_entry;
}

static zend_class_entry *register_class_Sharp_Collection(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Collection", class_Sharp_Collection_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	return class_entry;
}

/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 9903180bdd81c714087bfa5c45242d2aabbf867c */

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

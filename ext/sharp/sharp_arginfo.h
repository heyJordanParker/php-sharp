/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: c64ae86a87970ad333231bfee8470db0bf4681f4 */

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

ZEND_METHOD(Sharp_Int, parse);
ZEND_METHOD(Sharp_Int, tryParse);
ZEND_METHOD(Sharp_Float, parse);
ZEND_METHOD(Sharp_Float, tryParse);

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

/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: a989d3b100784465a71758261724e4a0c093b8a6 */

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

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_Sharp_Position___construct, 0, 0, 4)
	ZEND_ARG_TYPE_INFO(0, file, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, line, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, column, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, function, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_Sharp_Environment___construct arginfo_class_Sharp_Collection___construct

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_Sharp_Environment_variable, 0, 1, IS_STRING, 1)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

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
ZEND_METHOD(Sharp_Position, __construct);
ZEND_METHOD(Sharp_Environment, __construct);
ZEND_METHOD(Sharp_Environment, variable);

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

static const zend_function_entry class_Sharp_Position_methods[] = {
	ZEND_ME(Sharp_Position, __construct, arginfo_class_Sharp_Position___construct, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static const zend_function_entry class_Sharp_Environment_methods[] = {
	ZEND_ME(Sharp_Environment, __construct, arginfo_class_Sharp_Environment___construct, ZEND_ACC_PUBLIC)
	ZEND_ME(Sharp_Environment, variable, arginfo_class_Sharp_Environment_variable, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_Sharp_Collection(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Collection", class_Sharp_Collection_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

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

static zend_class_entry *register_class_Sharp_Environment(void)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "Sharp", "Environment", class_Sharp_Environment_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES|ZEND_ACC_NOT_SERIALIZABLE);

	zval property_arguments_default_value;
	ZVAL_UNDEF(&property_arguments_default_value);
	zend_string *property_arguments_name = zend_string_init("arguments", sizeof("arguments") - 1, 1);
	zend_declare_typed_property(class_entry, property_arguments_name, &property_arguments_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_VIRTUAL, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_ARRAY));
	zend_string_release(property_arguments_name);

	zval property_currentDirectory_default_value;
	ZVAL_UNDEF(&property_currentDirectory_default_value);
	zend_string *property_currentDirectory_name = zend_string_init("currentDirectory", sizeof("currentDirectory") - 1, 1);
	zend_declare_typed_property(class_entry, property_currentDirectory_name, &property_currentDirectory_default_value, ZEND_ACC_PUBLIC|ZEND_ACC_VIRTUAL, NULL, (zend_type) ZEND_TYPE_INIT_MASK(MAY_BE_STRING));
	zend_string_release(property_currentDirectory_name);

	return class_entry;
}

/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright (c) Zend Technologies Ltd. (http://www.zend.com)           |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.00 of the Zend license,     |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.zend.com/license/2_00.txt.                                |
   | If you did not receive a copy of the Zend license and are unable to  |
   | obtain it through the world-wide-web, please send a note to          |
   | license@zend.com so we can mail you a copy immediately.              |
   +----------------------------------------------------------------------+
   | Authors: Benjamin Eberlei <kontakt@beberlei.de>                      |
   |          Martin Schröder <m.schroeder2007@gmail.com>                 |
   +----------------------------------------------------------------------+
*/

#include "zend.h"
#include "zend_API.h"
#include "zend_attributes.h"
#include "zend_attributes_arginfo.h"
#include "zend_exceptions.h"
#include "zend_smart_str.h"

ZEND_API zend_class_entry *zend_ce_attribute;
ZEND_API zend_class_entry *zend_ce_return_type_will_change_attribute;
ZEND_API zend_class_entry *zend_ce_allow_dynamic_properties;
ZEND_API zend_class_entry *zend_ce_sensitive_parameter;
ZEND_API zend_class_entry *zend_ce_sensitive_parameter_value;
ZEND_API zend_class_entry *zend_ce_override;
ZEND_API zend_class_entry *zend_ce_deprecated;
ZEND_API zend_class_entry *zend_ce_nodiscard;
ZEND_API zend_class_entry *zend_ce_delayed_target_validation;

static zend_object_handlers attributes_object_handlers_sensitive_parameter_value;

static HashTable internal_attributes;

uint32_t zend_attribute_attribute_get_flags(zend_attribute *attr, zend_class_entry *scope)
{
	// TODO: More proper signature validation: Too many args, incorrect arg names.
	if (attr->argc > 0) {
		zval flags;

		if (FAILURE == zend_get_attribute_value(&flags, attr, 0, scope)) {
			ZEND_ASSERT(EG(exception));
			return 0;
		}

		if (Z_TYPE(flags) != IS_LONG) {
			zend_throw_error(NULL,
				"Attribute::__construct(): Argument #1 ($flags) must be of type int, %s given",
				zend_zval_value_name(&flags)
			);
			zval_ptr_dtor(&flags);
			return 0;
		}

		uint32_t flags_l = Z_LVAL(flags);
		if (flags_l & ~ZEND_ATTRIBUTE_FLAGS) {
			zend_throw_error(NULL, "Invalid attribute flags specified");
			return 0;
		}

		return flags_l;
	}

	return ZEND_ATTRIBUTE_TARGET_ALL;
}

static zend_string *validate_allow_dynamic_properties(
		zend_attribute *attr, uint32_t target, zend_class_entry *scope)
{
	ZEND_ASSERT(scope != NULL);
	const char *msg = NULL;
	if (scope->ce_flags & ZEND_ACC_TRAIT) {
		msg = "Cannot apply #[\\AllowDynamicProperties] to trait %s";
	} else if (scope->ce_flags & ZEND_ACC_INTERFACE) {
		msg = "Cannot apply #[\\AllowDynamicProperties] to interface %s";
	} else if (scope->ce_flags & ZEND_ACC_READONLY_CLASS) {
		msg = "Cannot apply #[\\AllowDynamicProperties] to readonly class %s";
	} else if (scope->ce_flags & ZEND_ACC_ENUM) {
		msg = "Cannot apply #[\\AllowDynamicProperties] to enum %s";
	}
	if (msg != NULL) {
		return zend_strpprintf(0, msg, ZSTR_VAL(scope->name));
	}
	scope->ce_flags |= ZEND_ACC_ALLOW_DYNAMIC_PROPERTIES;
	return NULL;
}

static zend_string *validate_attribute(
	zend_attribute *attr, uint32_t target, zend_class_entry *scope)
{
	const char *msg = NULL;
	if (scope->ce_flags & ZEND_ACC_TRAIT) {
		msg = "Cannot apply #[\\Attribute] to trait %s";
	} else if (scope->ce_flags & ZEND_ACC_INTERFACE) {
		msg = "Cannot apply #[\\Attribute] to interface %s";
	} else if (scope->ce_flags & ZEND_ACC_ENUM) {
		msg = "Cannot apply #[\\Attribute] to enum %s";
	} else if (scope->ce_flags & ZEND_ACC_EXPLICIT_ABSTRACT_CLASS) {
		msg = "Cannot apply #[\\Attribute] to abstract class %s";
	}
	if (msg != NULL) {
		return zend_strpprintf(0, msg, ZSTR_VAL(scope->name));
	}
	return NULL;
}

static zend_string *validate_deprecated(
	zend_attribute *attr,
	uint32_t target,
	zend_class_entry *scope
) {
	if (target != ZEND_ATTRIBUTE_TARGET_CLASS) {
		/* Being used for a method or something, validation does not apply */
		return NULL;
	}
	if (!(scope->ce_flags & ZEND_ACC_TRAIT)) {
		const char *type = zend_get_object_type_case(scope, false);
		return zend_strpprintf(0, "Cannot apply #[\\Deprecated] to %s %s", type, ZSTR_VAL(scope->name));
	}

	scope->ce_flags |= ZEND_ACC_DEPRECATED;
	return NULL;

}

ZEND_METHOD(Attribute, __construct)
{
	zend_long flags = ZEND_ATTRIBUTE_TARGET_ALL;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(flags)
	ZEND_PARSE_PARAMETERS_END();

	ZVAL_LONG(OBJ_PROP_NUM(Z_OBJ_P(ZEND_THIS), 0), flags);
}

ZEND_METHOD(ReturnTypeWillChange, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(AllowDynamicProperties, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(SensitiveParameter, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

void zend_sensitive_parameter_value_init(zend_object *obj, zval *value)
{
	ZEND_ASSERT(obj->ce == zend_ce_sensitive_parameter_value);
	zend_update_property_ex(zend_ce_sensitive_parameter_value, obj, ZSTR_KNOWN(ZEND_STR_VALUE), value);
}

ZEND_METHOD(SensitiveParameterValue, __construct)
{
	zval *value;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	zend_sensitive_parameter_value_init(Z_OBJ_P(ZEND_THIS), value);
}

ZEND_METHOD(SensitiveParameterValue, getValue)
{
	ZEND_PARSE_PARAMETERS_NONE();

	ZVAL_COPY(return_value, OBJ_PROP_NUM(Z_OBJ_P(ZEND_THIS), 0));
}

ZEND_METHOD(SensitiveParameterValue, __debugInfo)
{
	ZEND_PARSE_PARAMETERS_NONE();

	RETURN_EMPTY_ARRAY();
}

static HashTable *attributes_sensitive_parameter_value_get_properties_for(zend_object *zobj, zend_prop_purpose purpose)
{
	return NULL;
}

ZEND_METHOD(Override, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

ZEND_METHOD(Deprecated, __construct)
{
	zend_string *message = NULL;
	zend_string *since = NULL;
	zval value;

	ZEND_PARSE_PARAMETERS_START(0, 2)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(message)
		Z_PARAM_STR_OR_NULL(since)
	ZEND_PARSE_PARAMETERS_END();

	if (message) {
		ZVAL_STR(&value, message);
	} else {
		ZVAL_NULL(&value);
	}
	zend_update_property_ex(zend_ce_deprecated, Z_OBJ_P(ZEND_THIS), ZSTR_KNOWN(ZEND_STR_MESSAGE), &value);

	/* The assignment might fail due to 'readonly'. */
	if (UNEXPECTED(EG(exception))) {
		RETURN_THROWS();
	}

	if (since) {
		ZVAL_STR(&value, since);
	} else {
		ZVAL_NULL(&value);
	}
	zend_update_property_ex(zend_ce_deprecated, Z_OBJ_P(ZEND_THIS), ZSTR_KNOWN(ZEND_STR_SINCE), &value);

	/* The assignment might fail due to 'readonly'. */
	if (UNEXPECTED(EG(exception))) {
		RETURN_THROWS();
	}
}

static zend_string *validate_nodiscard(
	zend_attribute *attr, uint32_t target, zend_class_entry *scope)
{
	ZEND_ASSERT(CG(in_compilation));
	const zend_string *prop_info_name = CG(context).active_property_info_name;
	if (prop_info_name != NULL) {
		// Applied to a hook
		return ZSTR_INIT_LITERAL("#[\\NoDiscard] is not supported for property hooks", 0);
	}
	zend_op_array *op_array = CG(active_op_array);
	op_array->fn_flags |= ZEND_ACC_NODISCARD;
	return NULL;
}

ZEND_METHOD(NoDiscard, __construct)
{
	zend_string *message = NULL;
	zval value;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_STR_OR_NULL(message)
	ZEND_PARSE_PARAMETERS_END();

	if (message) {
		ZVAL_STR(&value, message);
	} else {
		ZVAL_NULL(&value);
	}
	zend_update_property_ex(zend_ce_nodiscard, Z_OBJ_P(ZEND_THIS), ZSTR_KNOWN(ZEND_STR_MESSAGE), &value);

	/* The assignment might fail due to 'readonly'. */
	if (UNEXPECTED(EG(exception))) {
		RETURN_THROWS();
	}
}

static zend_attribute *get_attribute(HashTable *attributes, zend_string *lcname, uint32_t offset)
{
	if (attributes) {
		zend_attribute *attr;

		ZEND_HASH_PACKED_FOREACH_PTR(attributes, attr) {
			if (attr->offset == offset && zend_string_equals(attr->lcname, lcname)) {
				return attr;
			}
		} ZEND_HASH_FOREACH_END();
	}

	return NULL;
}

static zend_attribute *get_attribute_str(HashTable *attributes, const char *str, size_t len, uint32_t offset)
{
	if (attributes) {
		zend_attribute *attr;

		ZEND_HASH_PACKED_FOREACH_PTR(attributes, attr) {
			if (attr->offset == offset && zend_string_equals_cstr(attr->lcname, str, len)) {
				return attr;
			}
		} ZEND_HASH_FOREACH_END();
	}

	return NULL;
}

ZEND_API zend_attribute *zend_get_attribute(HashTable *attributes, zend_string *lcname)
{
	return get_attribute(attributes, lcname, 0);
}

ZEND_API zend_attribute *zend_get_attribute_str(HashTable *attributes, const char *str, size_t len)
{
	return get_attribute_str(attributes, str, len, 0);
}

ZEND_API zend_attribute *zend_get_parameter_attribute(HashTable *attributes, zend_string *lcname, uint32_t offset)
{
	return get_attribute(attributes, lcname, offset + 1);
}

ZEND_API zend_attribute *zend_get_parameter_attribute_str(HashTable *attributes, const char *str, size_t len, uint32_t offset)
{
	return get_attribute_str(attributes, str, len, offset + 1);
}

ZEND_API zend_result zend_get_attribute_value(zval *ret, zend_attribute *attr, uint32_t i, zend_class_entry *scope)
{
	if (i >= attr->argc) {
		return FAILURE;
	}

	ZVAL_COPY_OR_DUP(ret, &attr->args[i].value);

	if (Z_TYPE_P(ret) == IS_CONSTANT_AST) {
		if (SUCCESS != zval_update_constant_ex(ret, scope)) {
			zval_ptr_dtor(ret);
			return FAILURE;
		}
	}

	return SUCCESS;
}

/* PHP#: pushes the dummy call frame an attribute's arguments and constructor run in, when `filename` is given, and
 * returns it for zend_attribute_frame_pop, or NULL when none is pushed. It makes the code look as if it ran where the
 * attribute `attribute_data` occurs: at its line of `filename`, with its strict types, and in the scope of the
 * declaration the attribute is written on, so an argument and the constructor build where `new` of them could be written
 * there: an `internal` class read in its own namespace builds, spec section 5. */
ZEND_API zend_execute_data *zend_attribute_frame_push(zend_attribute *attribute_data, zend_class_entry *scope, zend_string *filename)
{
	if (!filename) {
		return NULL;
	}

	zend_function dummy_func;
	zend_op *opline;

	memset(&dummy_func, 0, sizeof(zend_function));

	zend_execute_data *call = zend_vm_stack_push_call_frame_ex(
		ZEND_MM_ALIGNED_SIZE_EX(sizeof(zend_execute_data), sizeof(zval)) +
		ZEND_MM_ALIGNED_SIZE_EX(sizeof(zend_op), sizeof(zval)) +
		ZEND_MM_ALIGNED_SIZE_EX(sizeof(zend_function), sizeof(zval)),
		0, &dummy_func, 0, NULL);

	opline = (zend_op*)(call + 1);
	memset(opline, 0, sizeof(zend_op));
	opline->opcode = ZEND_DO_FCALL;
	opline->lineno = attribute_data->lineno;

	call->opline = opline;
	call->call = NULL;
	call->return_value = NULL;
	call->func = (zend_function*)(call->opline + 1);
	call->prev_execute_data = EG(current_execute_data);

	memset(call->func, 0, sizeof(zend_function));
	call->func->type = ZEND_USER_FUNCTION;
	call->func->op_array.fn_flags =
		attribute_data->flags & ZEND_ATTRIBUTE_STRICT_TYPES ? ZEND_ACC_STRICT_TYPES : 0;
	call->func->op_array.fn_flags |= ZEND_ACC_CALL_VIA_TRAMPOLINE;
	call->func->op_array.filename = filename;
	call->func->op_array.scope = scope;

	EG(current_execute_data) = call;

	return call;
}

/* PHP#: pops the frame zend_attribute_frame_push pushed, if it pushed one. */
ZEND_API void zend_attribute_frame_pop(zend_execute_data *call)
{
	if (call) {
		EG(current_execute_data) = call->prev_execute_data;
		zend_vm_stack_free_call_frame(call);
	}
}

ZEND_API zend_result zend_get_attribute_object(zval *obj, zend_class_entry *attribute_ce, zend_attribute *attribute_data, zend_class_entry *scope, zend_string *filename)
{
	zend_execute_data *call = zend_attribute_frame_push(attribute_data, scope, filename);

	zval *args = NULL;
	HashTable *named_params = NULL;

	zend_result result = FAILURE;

	uint32_t argc = 0;
	if (attribute_data->argc) {
		args = emalloc(attribute_data->argc * sizeof(zval));

		for (uint32_t i = 0; i < attribute_data->argc; i++) {
			zval val;
			if (FAILURE == zend_get_attribute_value(&val, attribute_data, i, scope)) {
				result = FAILURE;
				goto out;
			}
			if (attribute_data->args[i].name) {
				if (!named_params) {
					named_params = zend_new_array(0);
				}
				zend_hash_add_new(named_params, attribute_data->args[i].name, &val);
			} else {
				ZVAL_COPY_VALUE(&args[i], &val);
				argc++;
			}
		}
	}

	result = object_init_with_constructor(obj, attribute_ce, argc, args, named_params);

 out:
	for (uint32_t i = 0; i < argc; i++) {
		zval_ptr_dtor(&args[i]);
	}

	efree(args);

	if (named_params) {
		zend_array_destroy(named_params);
	}

	zend_attribute_frame_pop(call);

	return result;
}

/* The object the attribute `data` of the table `attributes` makes, as ReflectionAttribute::newInstance makes it: its
 * class must be an attribute class that allows `target`, and a user attribute that is not repeatable must appear once. */
ZEND_API zend_result zend_get_attribute_instance(zval *obj, HashTable *attributes, zend_attribute *data, zend_class_entry *scope, uint32_t target, zend_string *filename)
{
	zend_class_entry *ce;
	zend_attribute *marker;

	if (NULL == (ce = zend_lookup_class(data->name))) {
		zend_throw_error(NULL, "Attribute class \"%s\" not found", ZSTR_VAL(data->name));
		return FAILURE;
	}

	if (NULL == (marker = zend_get_attribute_str(ce->attributes, ZEND_STRL("attribute")))) {
		zend_throw_error(NULL, "Attempting to use non-attribute class \"%s\" as attribute", ZSTR_VAL(data->name));
		return FAILURE;
	}

	/* This code can be reached under one of three possible conditions:
	 * - the attribute is an internal attribute, and it had the target and
	 *   and repetition validated already
	 * - the attribute is an internal attribute and repetition was validated
	 *   already, the internal validator might have been run if the target was
	 *   correct, but any error would have been stored in
	 *   `zend_attribute.validation_error` instead of being thrown due to the
	 *   presence of #[DelayedTargetValidation]
	 * - the attribute is a user attribute, and neither target nor repetition
	 *   have been validated.
	 */
	uint32_t flags = zend_attribute_attribute_get_flags(marker, ce);
	if (EG(exception)) {
		return FAILURE;
	}

	/* No harm in always running target validation, for internal attributes
	 * without #[DelayedTargetValidation] it isn't necessary but will always
	 * succeed. */
	if (!(target & flags)) {
		zend_string *location = zend_get_attribute_target_names(target);
		zend_string *allowed = zend_get_attribute_target_names(flags);

		zend_throw_error(NULL, "Attribute \"%s\" cannot target %s (allowed targets: %s)",
			ZSTR_VAL(data->name), ZSTR_VAL(location), ZSTR_VAL(allowed)
		);

		zend_string_release(location);
		zend_string_release(allowed);

		return FAILURE;
	}

	if (data->validation_error != NULL) {
		/* Delayed validation errors should only be set for internal attributes. */
		ZEND_ASSERT(ce->type == ZEND_INTERNAL_CLASS);
		/* Delayed validation errors should only be set when
		 * #[\DelayedTargetValidation] is used. Searching for the attribute is
		 * more expensive than just an assertion and so we don't worry about it
		 * for non-debug builds. See discussion on GH-18817. */
#if ZEND_DEBUG
		zend_attribute *delayed_target_validation = zend_get_attribute_str(
			attributes,
			"delayedtargetvalidation",
			strlen("delayedtargetvalidation")
		);
		ZEND_ASSERT(delayed_target_validation != NULL);
#endif
		zend_throw_exception(zend_ce_error, ZSTR_VAL(data->validation_error), 0);
		return FAILURE;
	}

	/* Repetition validation is done even if #[DelayedTargetValidation] is used
	 * and so can be skipped for internal attributes. */
	if (ce->type == ZEND_USER_CLASS) {
		if (!(flags & ZEND_ATTRIBUTE_IS_REPEATABLE)) {
			if (zend_is_attribute_repeated(attributes, data)) {
				zend_throw_error(NULL, "Attribute \"%s\" must not be repeated", ZSTR_VAL(data->name));
				return FAILURE;
			}
		}
	}

	return zend_get_attribute_object(obj, ce, data, scope, filename);
}

static const char *target_names[] = {
	"class",
	"function",
	"method",
	"property",
	"class constant",
	"parameter",
	"constant"
};

ZEND_API zend_string *zend_get_attribute_target_names(uint32_t flags)
{
	smart_str str = { 0 };

	for (uint32_t i = 0; i < (sizeof(target_names) / sizeof(char *)); i++) {
		if (flags & (1 << i)) {
			if (smart_str_get_len(&str)) {
				smart_str_appends(&str, ", ");
			}

			smart_str_appends(&str, target_names[i]);
		}
	}

	return smart_str_extract(&str);
}

ZEND_API bool zend_is_attribute_repeated(HashTable *attributes, zend_attribute *attr)
{
	zend_attribute *other;

	ZEND_HASH_PACKED_FOREACH_PTR(attributes, other) {
		if (other != attr && other->offset == attr->offset) {
			if (zend_string_equals(other->lcname, attr->lcname)) {
				return 1;
			}
		}
	} ZEND_HASH_FOREACH_END();

	return 0;
}

static void attr_free(zval *v)
{
	zend_attribute *attr = Z_PTR_P(v);
	bool persistent = attr->flags & ZEND_ATTRIBUTE_PERSISTENT;

	zend_string_release(attr->name);
	zend_string_release(attr->lcname);
	if (attr->validation_error != NULL) {
		zend_string_release(attr->validation_error);
	}

	for (uint32_t i = 0; i < attr->argc; i++) {
		if (attr->args[i].name) {
			zend_string_release(attr->args[i].name);
		}
		if (persistent) {
			zval_internal_ptr_dtor(&attr->args[i].value);
		} else {
			zval_ptr_dtor(&attr->args[i].value);
		}
	}

	pefree(attr, persistent);
}

ZEND_API zend_attribute *zend_add_attribute(HashTable **attributes, zend_string *name, uint32_t argc, uint32_t flags, uint32_t offset, uint32_t lineno)
{
	bool persistent = flags & ZEND_ATTRIBUTE_PERSISTENT;
	if (*attributes == NULL) {
		*attributes = pemalloc(sizeof(HashTable), persistent);
		zend_hash_init(*attributes, 8, NULL, attr_free, persistent);
	}

	zend_attribute *attr = pemalloc(ZEND_ATTRIBUTE_SIZE(argc), persistent);

	if (persistent == ((GC_FLAGS(name) & IS_STR_PERSISTENT) != 0)) {
		attr->name = zend_string_copy(name);
	} else {
		attr->name = zend_string_dup(name, persistent);
	}

	attr->lcname = zend_string_tolower_ex(attr->name, persistent);
	attr->validation_error = NULL;
	attr->flags = flags;
	attr->lineno = lineno;
	attr->offset = offset;
	attr->argc = argc;

	/* Initialize arguments to avoid partial initialization in case of fatal errors. */
	for (uint32_t i = 0; i < argc; i++) {
		attr->args[i].name = NULL;
		ZVAL_UNDEF(&attr->args[i].value);
	}

	zend_hash_next_index_insert_ptr(*attributes, attr);

	return attr;
}

static void free_internal_attribute(zval *v)
{
	pefree(Z_PTR_P(v), 1);
}

ZEND_API zend_internal_attribute *zend_mark_internal_attribute(zend_class_entry *ce)
{
	zend_internal_attribute *internal_attr;
	zend_attribute *attr;

	if (ce->type != ZEND_INTERNAL_CLASS) {
		zend_error_noreturn(E_ERROR, "Only internal classes can be registered as compiler attribute");
	}

	ZEND_HASH_FOREACH_PTR(ce->attributes, attr) {
		if (zend_string_equals(attr->name, zend_ce_attribute->name)) {
			internal_attr = pemalloc(sizeof(zend_internal_attribute), 1);
			internal_attr->ce = ce;
			internal_attr->flags = Z_LVAL(attr->args[0].value);
			internal_attr->validator = NULL;

			zend_string *lcname = zend_string_tolower_ex(ce->name, 1);
			zend_hash_update_ptr(&internal_attributes, lcname, internal_attr);
			zend_string_release(lcname);

			return internal_attr;
		}
	} ZEND_HASH_FOREACH_END();

	zend_error_noreturn(E_ERROR, "Classes must be first marked as attribute before being able to be registered as internal attribute class");
}

ZEND_API zend_internal_attribute *zend_internal_attribute_register(zend_class_entry *ce, uint32_t flags)
{
	zend_attribute *attr = zend_add_class_attribute(ce, zend_ce_attribute->name, 1);
	ZVAL_LONG(&attr->args[0].value, flags);

	return zend_mark_internal_attribute(ce);
}

ZEND_API zend_internal_attribute *zend_internal_attribute_get(zend_string *lcname)
{
	return zend_hash_find_ptr(&internal_attributes, lcname);
}

void zend_register_attribute_ce(void)
{
	zend_internal_attribute *attr;

	zend_hash_init(&internal_attributes, 8, NULL, free_internal_attribute, 1);

	zend_ce_attribute = register_class_Attribute();
	attr = zend_mark_internal_attribute(zend_ce_attribute);
	attr->validator = validate_attribute;

	zend_ce_return_type_will_change_attribute = register_class_ReturnTypeWillChange();
	zend_mark_internal_attribute(zend_ce_return_type_will_change_attribute);

	zend_ce_allow_dynamic_properties = register_class_AllowDynamicProperties();
	attr = zend_mark_internal_attribute(zend_ce_allow_dynamic_properties);
	attr->validator = validate_allow_dynamic_properties;

	zend_ce_sensitive_parameter = register_class_SensitiveParameter();
	zend_mark_internal_attribute(zend_ce_sensitive_parameter);

	memcpy(&attributes_object_handlers_sensitive_parameter_value, &std_object_handlers, sizeof(zend_object_handlers));
	attributes_object_handlers_sensitive_parameter_value.get_properties_for = attributes_sensitive_parameter_value_get_properties_for;

	/* This is not an actual attribute, thus the zend_mark_internal_attribute() call is missing. */
	zend_ce_sensitive_parameter_value = register_class_SensitiveParameterValue();
	zend_ce_sensitive_parameter_value->default_object_handlers = &attributes_object_handlers_sensitive_parameter_value;

	zend_ce_override = register_class_Override();
	zend_mark_internal_attribute(zend_ce_override);

	zend_ce_deprecated = register_class_Deprecated();
	attr = zend_mark_internal_attribute(zend_ce_deprecated);
	attr->validator = validate_deprecated;

	zend_ce_nodiscard = register_class_NoDiscard();
	attr = zend_mark_internal_attribute(zend_ce_nodiscard);
	attr->validator = validate_nodiscard;

	zend_ce_delayed_target_validation = register_class_DelayedTargetValidation();
	attr = zend_mark_internal_attribute(zend_ce_delayed_target_validation);
}

void zend_attributes_shutdown(void)
{
	zend_hash_destroy(&internal_attributes);
}

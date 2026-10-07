#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "zend_exceptions.h"
#include "zend_smart_str.h"
#include "zend_system_id.h"
#include "php_sharp.h"
#include "sharp_arginfo.h"
#include "sharp_unit.h"
#include "sharp_build_id.h"

#define SHARP_FILE_EXTENSION ".sharp"

#define SHARP_KIND_IS_ZEND_KIND(kind) \
	ZEND_STATIC_ASSERT((zend_ast_kind) SHARP_AST_##kind == ZEND_AST_##kind, "SHARP_AST_" #kind " differs from ZEND_AST_" #kind);
SHARP_KINDS(SHARP_KIND_IS_ZEND_KIND)

static zend_op_array *(*sharp_next_compile_file)(zend_file_handle *file_handle, int type);

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index);

static zend_string *sharp_string(const sharp_unit *unit, sharp_str text)
{
	return zend_string_init(unit->texts + text.offset, text.len, 0);
}

static zend_ast *sharp_translate_zval(const sharp_unit *unit, const sharp_node *node)
{
	zval value;

	switch (node->value) {
		case SHARP_NULL:
			ZVAL_NULL(&value);
			break;
		case SHARP_FALSE:
			ZVAL_FALSE(&value);
			break;
		case SHARP_TRUE:
			ZVAL_TRUE(&value);
			break;
		case SHARP_LONG:
			ZVAL_LONG(&value, node->long_value);
			break;
		case SHARP_DOUBLE:
			ZVAL_DOUBLE(&value, node->double_value);
			break;
		case SHARP_STRING:
			ZVAL_STR(&value, sharp_string(unit, node->text));
			break;
		EMPTY_SWITCH_DEFAULT_CASE();
	}

	return zend_ast_create_zval_ex(&value, node->attr);
}

static zend_ast *sharp_translate_list(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *list = zend_ast_create_list(0, kind);

	for (uint32_t i = 0; i < node->child_count; i++) {
		list = zend_ast_list_add(list, sharp_translate(unit, unit->children[node->first_child + i]));
	}
	list->attr = node->attr;

	return list;
}

static zend_ast *sharp_translate_decl(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *child[5];

	ZEND_ASSERT(node->child_count == 5);
	for (uint32_t i = 0; i < 5; i++) {
		child[i] = sharp_translate(unit, unit->children[node->first_child + i]);
	}

	CG(zend_lineno) = node->end_line;
	return zend_ast_create_decl(kind, node->attr, node->line, NULL, sharp_string(unit, node->text),
		child[0], child[1], child[2], child[3], child[4]);
}

static zend_ast_attr sharp_operator_attr(zend_ast_kind kind, uint32_t attr)
{
	switch (kind) {
		case ZEND_AST_BINARY_OP:
		case ZEND_AST_ASSIGN_OP:
			return attr == ZEND_ADD || attr == ZEND_SUB || attr == ZEND_MUL || attr == ZEND_POW
				? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
		case ZEND_AST_CAST:
			return attr == IS_LONG ? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
		case ZEND_AST_UNARY_MINUS:
		case ZEND_AST_PRE_INC:
		case ZEND_AST_PRE_DEC:
		case ZEND_AST_POST_INC:
		case ZEND_AST_POST_DEC:
			return attr | ZEND_SHARP_OPERATOR_SYNTAX;
		default:
			return attr;
	}
}

static zend_ast *sharp_translate_fixed(const sharp_unit *unit, const sharp_node *node, zend_ast_kind kind)
{
	zend_ast *child[6] = {0};
	uint32_t child_count = kind >> ZEND_AST_NUM_CHILDREN_SHIFT;
	zend_ast *ast;

	ZEND_ASSERT(node->child_count == child_count);
	for (uint32_t i = 0; i < child_count; i++) {
		child[i] = sharp_translate(unit, unit->children[node->first_child + i]);
	}

	ast = zend_ast_create_ex(kind, sharp_operator_attr(kind, node->attr), child[0], child[1], child[2], child[3], child[4], child[5]);
	ast->lineno = node->line;

	return ast;
}

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index)
{
	const sharp_node *node;
	zend_ast_kind kind;
	zend_ast *ast;

	if (index == UINT32_MAX) {
		return NULL;
	}

	node = &unit->nodes[index];
	kind = (zend_ast_kind) node->kind;

	if (kind == ZEND_AST_ZVAL) {
		CG(zend_lineno) = node->line;
		return sharp_translate_zval(unit, node);
	}
	if ((kind >> ZEND_AST_IS_LIST_SHIFT) & 1) {
		ast = sharp_translate_list(unit, node, kind);
		ast->lineno = node->line;
		return ast;
	}
	if (kind >= ZEND_AST_FUNC_DECL && kind <= ZEND_AST_PROPERTY_HOOK) {
		return sharp_translate_decl(unit, node, kind);
	}
	return sharp_translate_fixed(unit, node, kind);
}

static int sharp_parse(void)
{
	zend_string *path = zend_get_compiled_filename();
	const char *source = (const char *) LANG_SCNG(yy_start);
	size_t length = LANG_SCNG(yy_limit) - LANG_SCNG(yy_start);
	sharp_unit *unit = sharp_lower(ZSTR_VAL(path), ZSTR_LEN(path), source, length);
	bool diagnosed = unit->diagnostic_count != 0;
	bool failed = false;

	zend_try {
		if (diagnosed) {
			const sharp_diagnostic *diagnostic = &unit->diagnostics[0];

			CG(zend_lineno) = diagnostic->line;
			zend_throw_exception_ex(
				diagnostic->severity == SHARP_PARSE_ERROR ? zend_ce_parse_error : zend_ce_compile_error,
				0, "%.*s", (int) diagnostic->message.len, unit->texts + diagnostic->message.offset);
		} else {
			CG(ast) = sharp_translate(unit, unit->root);
			CG(zend_lineno) = unit->nodes[unit->root].end_line;
		}
	} zend_catch {
		failed = true;
	} zend_end_try();

	sharp_unit_free(unit);

	if (failed) {
		zend_bailout();
	}

	return diagnosed ? FAILURE : SUCCESS;
}

static bool sharp_is_sharp_file(const zend_string *filename)
{
	size_t length = sizeof(SHARP_FILE_EXTENSION) - 1;

	return ZSTR_LEN(filename) > length
		&& memcmp(ZSTR_VAL(filename) + ZSTR_LEN(filename) - length, SHARP_FILE_EXTENSION, length) == 0;
}

static zend_op_array *sharp_compile_file(zend_file_handle *file_handle, int type)
{
	if (!file_handle->filename || !sharp_is_sharp_file(file_handle->filename)) {
		return sharp_next_compile_file(file_handle, type);
	}

	return zend_compile_file_with(file_handle, type, sharp_parse);
}

typedef enum {
	SHARP_PARSED,
	SHARP_NOT_A_STRING,
	SHARP_NOT_A_NUMBER,
	SHARP_OUT_OF_RANGE,
} sharp_parse_result;

/* The white space C#'s int.Parse skips: U+0009 to U+000D and U+0020. */
static bool sharp_is_space(char c)
{
	return c == ' ' || (c >= '\t' && c <= '\r');
}

static const char *sharp_skip_digits(const char *p)
{
	while (*p >= '0' && *p <= '9') {
		p++;
	}

	return p;
}

/* Accepts what C#'s int.Parse accepts: [ws][sign]digits[ws], in ASCII digits only. */
static sharp_parse_result sharp_parse_int(const zval *value, zend_long *result)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return SHARP_NOT_A_STRING;
	}

	const char *p = Z_STRVAL_P(value);
	const char *end = p + Z_STRLEN_P(value);
	while (sharp_is_space(*p)) {
		p++;
	}
	bool negative = *p == '-';
	if (*p == '-' || *p == '+') {
		p++;
	}
	const char *digits = p;
	p = sharp_skip_digits(p);
	if (p == digits) {
		return SHARP_NOT_A_NUMBER;
	}
	const char *digits_end = p;
	while (sharp_is_space(*p)) {
		p++;
	}
	if (p != end) {
		return SHARP_NOT_A_NUMBER;
	}

	zend_ulong limit = negative ? (zend_ulong) ZEND_LONG_MAX + 1 : (zend_ulong) ZEND_LONG_MAX;
	zend_ulong magnitude = 0;
	for (p = digits; p < digits_end; p++) {
		zend_ulong digit = *p - '0';
		if (magnitude > (limit - digit) / 10) {
			return SHARP_OUT_OF_RANGE;
		}
		magnitude = magnitude * 10 + digit;
	}
	*result = negative ? (zend_long) (0 - magnitude) : (zend_long) magnitude;

	return SHARP_PARSED;
}

/* Accepts [ws][sign](digits[.digits] | .digits)([eE][sign]digits)?[ws], so no thousands separator, NaN or Infinity. */
static sharp_parse_result sharp_parse_float(const zval *value, double *result)
{
	if (Z_TYPE_P(value) != IS_STRING) {
		return SHARP_NOT_A_STRING;
	}

	const char *p = Z_STRVAL_P(value);
	const char *end = p + Z_STRLEN_P(value);
	while (sharp_is_space(*p)) {
		p++;
	}
	const char *number = p;
	if (*p == '-' || *p == '+') {
		p++;
	}
	const char *integer = p;
	p = sharp_skip_digits(p);
	if (*p == '.') {
		const char *fraction = ++p;
		p = sharp_skip_digits(p);
		if (p == fraction) {
			return SHARP_NOT_A_NUMBER;
		}
	} else if (p == integer) {
		return SHARP_NOT_A_NUMBER;
	}
	if (*p == 'e' || *p == 'E') {
		p++;
		if (*p == '-' || *p == '+') {
			p++;
		}
		const char *exponent = p;
		p = sharp_skip_digits(p);
		if (p == exponent) {
			return SHARP_NOT_A_NUMBER;
		}
	}
	while (sharp_is_space(*p)) {
		p++;
	}
	if (p != end) {
		return SHARP_NOT_A_NUMBER;
	}

	*result = zend_strtod(number, NULL);

	return zend_finite(*result) ? SHARP_PARSED : SHARP_OUT_OF_RANGE;
}

/* Shows the string the way an unhandled match case does, so zend.exception_ignore_args hides request data. */
static ZEND_COLD void sharp_throw_parse_error(sharp_parse_result parsed, const zval *value, const char *number, const char *range)
{
	if (parsed == SHARP_NOT_A_STRING) {
		zend_argument_value_error(1, "must be a string, %s given", zend_zval_value_name(value));
		return;
	}

	zend_long max_len = EG(exception_string_param_max_len);
	smart_str shown = {0};
	if (EG(exception_ignore_args) || max_len == 0) {
		smart_str_appends(&shown, "string");
	} else {
		smart_str_append_scalar(&shown, value, max_len);
	}
	smart_str_0(&shown);

	if (parsed == SHARP_OUT_OF_RANGE) {
		zend_argument_error(zend_ce_arithmetic_error, 1, "must hold %s, %s given", range, ZSTR_VAL(shown.s));
	} else {
		zend_argument_value_error(1, "must hold %s, %s given", number, ZSTR_VAL(shown.s));
	}

	smart_str_free(&shown);
}

ZEND_METHOD(Sharp_Int, parse)
{
	zval *value;
	zend_long result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_parse_result parsed = sharp_parse_int(value, &result);
	if (parsed != SHARP_PARSED) {
		sharp_throw_parse_error(parsed, value, "an int", "an int from PHP_INT_MIN to PHP_INT_MAX");
		RETURN_THROWS();
	}

	RETURN_LONG(result);
}

ZEND_METHOD(Sharp_Int, tryParse)
{
	zval *value;
	zend_long result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (sharp_parse_int(value, &result) != SHARP_PARSED) {
		RETURN_NULL();
	}

	RETURN_LONG(result);
}

ZEND_METHOD(Sharp_Float, parse)
{
	zval *value;
	double result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	sharp_parse_result parsed = sharp_parse_float(value, &result);
	if (parsed != SHARP_PARSED) {
		sharp_throw_parse_error(parsed, value, "a float", "a float from -PHP_FLOAT_MAX to PHP_FLOAT_MAX");
		RETURN_THROWS();
	}

	RETURN_DOUBLE(result);
}

ZEND_METHOD(Sharp_Float, tryParse)
{
	zval *value;
	double result;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();

	if (sharp_parse_float(value, &result) != SHARP_PARSED) {
		RETURN_NULL();
	}

	RETURN_DOUBLE(result);
}

static PHP_MINIT_FUNCTION(sharp)
{
	register_class_Sharp_Int();
	register_class_Sharp_Float();
	sharp_init();
	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_next_compile_file = zend_compile_file;
	zend_compile_file = sharp_compile_file;

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "Mago commit", SHARP_MAGO_COMMIT);
	php_info_print_table_end();
}

zend_module_entry sharp_module_entry = {
	STANDARD_MODULE_HEADER,
	"sharp",
	NULL,
	PHP_MINIT(sharp),
	NULL,
	NULL,
	NULL,
	PHP_MINFO(sharp),
	PHP_VERSION,
	STANDARD_MODULE_PROPERTIES
};

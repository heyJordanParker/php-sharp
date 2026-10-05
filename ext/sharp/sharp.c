#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "zend_exceptions.h"
#include "zend_system_id.h"
#include "php_sharp.h"
#include "sharp_bridge.h"
#include "sharp_build_id.h"

#define SHARP_FILE_EXTENSION ".sharp"

#define SHARP_KIND(kind) case SHARP_AST_##kind: return ZEND_AST_##kind

static zend_ast_kind sharp_zend_kind(enum sharp_kind kind)
{
	switch (kind) {
		SHARP_KIND(ZVAL);
		SHARP_KIND(METHOD);
		SHARP_KIND(CLASS);
		SHARP_KIND(ARG_LIST);
		SHARP_KIND(STMT_LIST);
		SHARP_KIND(PARAM_LIST);
		SHARP_KIND(CONST_DECL);
		SHARP_KIND(VAR);
		SHARP_KIND(CONST);
		SHARP_KIND(UNARY_PLUS);
		SHARP_KIND(UNARY_MINUS);
		SHARP_KIND(UNARY_OP);
		SHARP_KIND(PRE_INC);
		SHARP_KIND(PRE_DEC);
		SHARP_KIND(POST_INC);
		SHARP_KIND(POST_DEC);
		SHARP_KIND(RETURN);
		SHARP_KIND(PROP);
		SHARP_KIND(ASSIGN);
		SHARP_KIND(ASSIGN_OP);
		SHARP_KIND(BINARY_OP);
		SHARP_KIND(GREATER);
		SHARP_KIND(GREATER_EQUAL);
		SHARP_KIND(AND);
		SHARP_KIND(OR);
		SHARP_KIND(DECLARE);
		SHARP_KIND(NAMESPACE);
		SHARP_KIND(NAMED_ARG);
		SHARP_KIND(METHOD_CALL);
		SHARP_KIND(STATIC_CALL);
		SHARP_KIND(CONST_ELEM);
		SHARP_KIND(PARAM);
		SHARP_KIND(COALESCE);
		SHARP_KIND(ASSIGN_COALESCE);
		SHARP_KIND(NULLSAFE_PROP);
		SHARP_KIND(NULLSAFE_METHOD_CALL);
		SHARP_KIND(IF);
		SHARP_KIND(IF_ELEM);
		SHARP_KIND(WHILE);
		SHARP_KIND(DO_WHILE);
		SHARP_KIND(BREAK);
		SHARP_KIND(CONTINUE);
		SHARP_KIND(FOR);
		SHARP_KIND(EXPR_LIST);
		SHARP_KIND(FOREACH);
	}

	ZEND_UNREACHABLE();
}

static zend_op_array *(*sharp_next_compile_file)(zend_file_handle *file_handle, int type);

static zend_ast *sharp_translate(const sharp_unit *unit, uint32_t index);

static zend_string *sharp_string(sharp_str text)
{
	return zend_string_init(text.ptr, text.len, 0);
}

static zend_ast *sharp_translate_zval(const sharp_node *node)
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
			ZVAL_STR(&value, sharp_string(node->text));
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
	return zend_ast_create_decl(kind, node->attr, node->line, NULL, sharp_string(node->text),
		child[0], child[1], child[2], child[3], child[4]);
}

static zend_ast_attr sharp_operator_attr(zend_ast_kind kind, uint32_t attr)
{
	switch (kind) {
		case ZEND_AST_BINARY_OP:
		case ZEND_AST_ASSIGN_OP:
			return attr == ZEND_ADD || attr == ZEND_SUB || attr == ZEND_MUL ? attr | ZEND_SHARP_OPERATOR_SYNTAX : attr;
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
	kind = sharp_zend_kind(node->kind);

	if (kind == ZEND_AST_ZVAL) {
		CG(zend_lineno) = node->line;
		return sharp_translate_zval(node);
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
				0, "%.*s", (int) diagnostic->message.len, diagnostic->message.ptr);
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

static PHP_MINIT_FUNCTION(sharp)
{
	sharp_init();
	zend_add_system_entropy("sharp", "SHARP_BUILD_ID", SHARP_BUILD_ID, sizeof(SHARP_BUILD_ID) - 1);

	sharp_next_compile_file = zend_compile_file;
	zend_compile_file = sharp_compile_file;

	return SUCCESS;
}

static PHP_MINFO_FUNCTION(sharp)
{
	sharp_str commit = sharp_mago_commit();
	char *value = estrndup(commit.ptr, commit.len);

	php_info_print_table_start();
	php_info_print_table_row(2, "Mago commit", value);
	php_info_print_table_end();

	efree(value);
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

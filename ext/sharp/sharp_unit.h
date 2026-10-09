#ifndef SHARP_UNIT_H
#define SHARP_UNIT_H

#include <stdarg.h>
#include <stdbool.h>
#include <stddef.h>
#include <stdint.h>
#include <stdlib.h>

#if defined(__BYTE_ORDER__) && __BYTE_ORDER__ != __ORDER_LITTLE_ENDIAN__
#error "a .sharpc file is little-endian, and ext/sharp reads it by casting its bytes"
#endif

#define SHARP_UNIT_MAGIC "\x53\x48\x41\x52\x50\x43\x00\x00"
#define SHARP_UNIT_ABI "\xf3\x32\x83\xfc\xc4\xca\xf4\x6e\x01\xc3\x2b\xc3\x2a\x9d\xf7\x67"
#define SHARP_MAGO_COMMIT "48408ea38b3b6d27b78bc9a8c1c412688bfab2ed"

#define SHARP_KINDS(X) \
  X(ZVAL) \
  X(CONSTANT) \
  X(OP_ARRAY) \
  X(ZNODE) \
  X(FUNC_DECL) \
  X(CLOSURE) \
  X(METHOD) \
  X(CLASS) \
  X(ARROW_FUNC) \
  X(PROPERTY_HOOK) \
  X(ARG_LIST) \
  X(ARRAY) \
  X(ENCAPS_LIST) \
  X(EXPR_LIST) \
  X(STMT_LIST) \
  X(IF) \
  X(SWITCH_LIST) \
  X(CATCH_LIST) \
  X(PARAM_LIST) \
  X(CLOSURE_USES) \
  X(PROP_DECL) \
  X(CONST_DECL) \
  X(CLASS_CONST_DECL) \
  X(NAME_LIST) \
  X(TRAIT_ADAPTATIONS) \
  X(USE) \
  X(TYPE_UNION) \
  X(TYPE_INTERSECTION) \
  X(ATTRIBUTE_LIST) \
  X(ATTRIBUTE_GROUP) \
  X(MATCH_ARM_LIST) \
  X(MODIFIER_LIST) \
  X(MAGIC_CONST) \
  X(TYPE) \
  X(CONSTANT_CLASS) \
  X(CALLABLE_CONVERT) \
  X(VAR) \
  X(CONST) \
  X(UNPACK) \
  X(UNARY_PLUS) \
  X(UNARY_MINUS) \
  X(CAST) \
  X(CAST_VOID) \
  X(EMPTY) \
  X(ISSET) \
  X(SILENCE) \
  X(SHELL_EXEC) \
  X(PRINT) \
  X(INCLUDE_OR_EVAL) \
  X(UNARY_OP) \
  X(PRE_INC) \
  X(PRE_DEC) \
  X(POST_INC) \
  X(POST_DEC) \
  X(YIELD_FROM) \
  X(CLASS_NAME) \
  X(GLOBAL) \
  X(UNSET) \
  X(RETURN) \
  X(LABEL) \
  X(REF) \
  X(HALT_COMPILER) \
  X(ECHO) \
  X(THROW) \
  X(GOTO) \
  X(BREAK) \
  X(CONTINUE) \
  X(PROPERTY_HOOK_SHORT_BODY) \
  X(DIM) \
  X(PROP) \
  X(NULLSAFE_PROP) \
  X(STATIC_PROP) \
  X(CALL) \
  X(CLASS_CONST) \
  X(ASSIGN) \
  X(ASSIGN_REF) \
  X(ASSIGN_OP) \
  X(BINARY_OP) \
  X(GREATER) \
  X(GREATER_EQUAL) \
  X(AND) \
  X(OR) \
  X(ARRAY_ELEM) \
  X(NEW) \
  X(INSTANCEOF) \
  X(YIELD) \
  X(COALESCE) \
  X(ASSIGN_COALESCE) \
  X(STATIC) \
  X(WHILE) \
  X(DO_WHILE) \
  X(IF_ELEM) \
  X(SWITCH) \
  X(SWITCH_CASE) \
  X(DECLARE) \
  X(USE_TRAIT) \
  X(TRAIT_PRECEDENCE) \
  X(METHOD_REFERENCE) \
  X(NAMESPACE) \
  X(USE_ELEM) \
  X(TRAIT_ALIAS) \
  X(GROUP_USE) \
  X(ATTRIBUTE) \
  X(MATCH) \
  X(MATCH_ARM) \
  X(NAMED_ARG) \
  X(PARENT_PROPERTY_HOOK_CALL) \
  X(PIPE) \
  X(SHARP_TYPE_ARGS) \
  X(METHOD_CALL) \
  X(NULLSAFE_METHOD_CALL) \
  X(STATIC_CALL) \
  X(CONDITIONAL) \
  X(TRY) \
  X(CATCH) \
  X(PROP_GROUP) \
  X(CONST_ELEM) \
  X(CLASS_CONST_GROUP) \
  X(CONST_ENUM_INIT) \
  X(FOR) \
  X(FOREACH) \
  X(ENUM_CASE) \
  X(PROP_ELEM) \
  X(PARAM)


#define SHARP_T_FILE 347

// One value per `zend_ast_kind`, named as that kind without `ZEND_` and equal to it, so `ext/sharp` casts it.
enum sharp_kind
#if __STDC_VERSION__ >= 202311L
  : uint16_t
#endif // __STDC_VERSION__ >= 202311L
 {
  SHARP_AST_ZVAL = 64,
  SHARP_AST_CONSTANT = 65,
  SHARP_AST_OP_ARRAY = 66,
  SHARP_AST_ZNODE = 67,
  SHARP_AST_FUNC_DECL = 68,
  SHARP_AST_CLOSURE = 69,
  SHARP_AST_METHOD = 70,
  SHARP_AST_CLASS = 71,
  SHARP_AST_ARROW_FUNC = 72,
  SHARP_AST_PROPERTY_HOOK = 73,
  SHARP_AST_ARG_LIST = 128,
  SHARP_AST_ARRAY = 129,
  SHARP_AST_ENCAPS_LIST = 130,
  SHARP_AST_EXPR_LIST = 131,
  SHARP_AST_STMT_LIST = 132,
  SHARP_AST_IF = 133,
  SHARP_AST_SWITCH_LIST = 134,
  SHARP_AST_CATCH_LIST = 135,
  SHARP_AST_PARAM_LIST = 136,
  SHARP_AST_CLOSURE_USES = 137,
  SHARP_AST_PROP_DECL = 138,
  SHARP_AST_CONST_DECL = 139,
  SHARP_AST_CLASS_CONST_DECL = 140,
  SHARP_AST_NAME_LIST = 141,
  SHARP_AST_TRAIT_ADAPTATIONS = 142,
  SHARP_AST_USE = 143,
  SHARP_AST_TYPE_UNION = 144,
  SHARP_AST_TYPE_INTERSECTION = 145,
  SHARP_AST_ATTRIBUTE_LIST = 146,
  SHARP_AST_ATTRIBUTE_GROUP = 147,
  SHARP_AST_MATCH_ARM_LIST = 148,
  SHARP_AST_MODIFIER_LIST = 149,
  SHARP_AST_MAGIC_CONST = 0,
  SHARP_AST_TYPE = 1,
  SHARP_AST_CONSTANT_CLASS = 2,
  SHARP_AST_CALLABLE_CONVERT = 3,
  SHARP_AST_VAR = 256,
  SHARP_AST_CONST = 257,
  SHARP_AST_UNPACK = 258,
  SHARP_AST_UNARY_PLUS = 259,
  SHARP_AST_UNARY_MINUS = 260,
  SHARP_AST_CAST = 261,
  SHARP_AST_CAST_VOID = 262,
  SHARP_AST_EMPTY = 263,
  SHARP_AST_ISSET = 264,
  SHARP_AST_SILENCE = 265,
  SHARP_AST_SHELL_EXEC = 266,
  SHARP_AST_PRINT = 267,
  SHARP_AST_INCLUDE_OR_EVAL = 268,
  SHARP_AST_UNARY_OP = 269,
  SHARP_AST_PRE_INC = 270,
  SHARP_AST_PRE_DEC = 271,
  SHARP_AST_POST_INC = 272,
  SHARP_AST_POST_DEC = 273,
  SHARP_AST_YIELD_FROM = 274,
  SHARP_AST_CLASS_NAME = 275,
  SHARP_AST_GLOBAL = 276,
  SHARP_AST_UNSET = 277,
  SHARP_AST_RETURN = 278,
  SHARP_AST_LABEL = 279,
  SHARP_AST_REF = 280,
  SHARP_AST_HALT_COMPILER = 281,
  SHARP_AST_ECHO = 282,
  SHARP_AST_THROW = 283,
  SHARP_AST_GOTO = 284,
  SHARP_AST_BREAK = 285,
  SHARP_AST_CONTINUE = 286,
  SHARP_AST_PROPERTY_HOOK_SHORT_BODY = 287,
  SHARP_AST_DIM = 512,
  SHARP_AST_PROP = 513,
  SHARP_AST_NULLSAFE_PROP = 514,
  SHARP_AST_STATIC_PROP = 515,
  SHARP_AST_CALL = 516,
  SHARP_AST_CLASS_CONST = 517,
  SHARP_AST_ASSIGN = 518,
  SHARP_AST_ASSIGN_REF = 519,
  SHARP_AST_ASSIGN_OP = 520,
  SHARP_AST_BINARY_OP = 521,
  SHARP_AST_GREATER = 522,
  SHARP_AST_GREATER_EQUAL = 523,
  SHARP_AST_AND = 524,
  SHARP_AST_OR = 525,
  SHARP_AST_ARRAY_ELEM = 526,
  SHARP_AST_NEW = 527,
  SHARP_AST_INSTANCEOF = 528,
  SHARP_AST_YIELD = 529,
  SHARP_AST_COALESCE = 530,
  SHARP_AST_ASSIGN_COALESCE = 531,
  SHARP_AST_STATIC = 532,
  SHARP_AST_WHILE = 533,
  SHARP_AST_DO_WHILE = 534,
  SHARP_AST_IF_ELEM = 535,
  SHARP_AST_SWITCH = 536,
  SHARP_AST_SWITCH_CASE = 537,
  SHARP_AST_DECLARE = 538,
  SHARP_AST_USE_TRAIT = 539,
  SHARP_AST_TRAIT_PRECEDENCE = 540,
  SHARP_AST_METHOD_REFERENCE = 541,
  SHARP_AST_NAMESPACE = 542,
  SHARP_AST_USE_ELEM = 543,
  SHARP_AST_TRAIT_ALIAS = 544,
  SHARP_AST_GROUP_USE = 545,
  SHARP_AST_ATTRIBUTE = 546,
  SHARP_AST_MATCH = 547,
  SHARP_AST_MATCH_ARM = 548,
  SHARP_AST_NAMED_ARG = 549,
  SHARP_AST_PARENT_PROPERTY_HOOK_CALL = 550,
  SHARP_AST_PIPE = 551,
  SHARP_AST_SHARP_TYPE_ARGS = 552,
  SHARP_AST_METHOD_CALL = 768,
  SHARP_AST_NULLSAFE_METHOD_CALL = 769,
  SHARP_AST_STATIC_CALL = 770,
  SHARP_AST_CONDITIONAL = 771,
  SHARP_AST_TRY = 772,
  SHARP_AST_CATCH = 773,
  SHARP_AST_PROP_GROUP = 774,
  SHARP_AST_CONST_ELEM = 775,
  SHARP_AST_CLASS_CONST_GROUP = 776,
  SHARP_AST_CONST_ENUM_INIT = 777,
  SHARP_AST_FOR = 1024,
  SHARP_AST_FOREACH = 1025,
  SHARP_AST_ENUM_CASE = 1026,
  SHARP_AST_PROP_ELEM = 1027,
  SHARP_AST_PARAM = 1536,
};
#if __STDC_VERSION__ >= 202311L
typedef enum sharp_kind sharp_kind;
#else
typedef uint16_t sharp_kind;
#endif // __STDC_VERSION__ >= 202311L

enum sharp_value
#if __STDC_VERSION__ >= 202311L
  : uint8_t
#endif // __STDC_VERSION__ >= 202311L
 {
  SHARP_NULL,
  SHARP_FALSE,
  SHARP_TRUE,
  SHARP_LONG,
  SHARP_DOUBLE,
  SHARP_STRING,
};
#if __STDC_VERSION__ >= 202311L
typedef enum sharp_value sharp_value;
#else
typedef uint8_t sharp_value;
#endif // __STDC_VERSION__ >= 202311L

// The first bytes of a `.sharpc` file. Every 16-byte hash is xxh3-128 in canonical big-endian order.
typedef struct {
  // `SHARP_UNIT_MAGIC`.
  uint8_t magic[8];
  // `SHARP_UNIT_ABI` of the checker that wrote the file.
  uint8_t abi[16];
  // The build ID of the checker that wrote the file.
  uint8_t checker[16];
  // Names the compiled code.
  uint8_t key[16];
  uint8_t source_hash[16];
  uint64_t source_size;
  uint32_t input_count;
  uint32_t node_count;
  uint32_t children_count;
  // A `SHARP_AST_STMT_LIST`.
  uint32_t root;
  uint32_t texts_size;
  uint32_t facts_size;
} sharp_unit_header;

// `len` bytes of UTF-8 at `offset` in the unit's texts, not NUL-terminated.
typedef struct {
  uint32_t offset;
  uint32_t len;
} sharp_str;

// A file whose edit makes the compiled file's source due for a check.
typedef struct {
  // Workspace-relative, with `/` separators.
  sharp_str path;
  uint64_t size;
  int64_t mtime_ns;
  uint8_t hash[16];
} sharp_input;

typedef struct {
  sharp_kind kind;
  // `zend_ast` attr: flags, modifiers, operator, `ZEND_NAME_FQ`.
  uint32_t attr;
  // First line.
  uint32_t line;
  // Closing line, for declarations.
  uint32_t end_line;
  // Index into `children[]`.
  uint32_t first_child;
  // List kinds are variadic.
  uint32_t child_count;
  // `SHARP_AST_ZVAL` only.
  sharp_value value;
  int64_t long_value;
  double double_value;
  // Names, string values, doc comments.
  sharp_str text;
} sharp_node;

#endif  /* SHARP_UNIT_H */

/* The hash table a Sharp\Map and a Sharp\Set hold, from php-ds v1.8.0's src/ds/ds_htable.c, whose MIT notice
 * sharp_table.h carries. The buckets, chains, rehash, growth and shrinking are php-ds's. The hash and the key match
 * are PHP#'s: sharp_hash and sharp_equal below. */

#include "sharp_table.h"
#include "php_sharp.h"
#include "zend_enum.h"

#define SHARP_TABLE_INVALID ((uint32_t) -1)
#define SHARP_TABLE_HEAD(table, hash) ((table)->lookup[(hash) & ((table)->capacity - 1)])

static void sharp_table_reset_lookup(sharp_table *table)
{
	memset(table->lookup, 0xff, table->capacity * sizeof(uint32_t));
}

static void sharp_table_realloc(sharp_table *table, uint32_t capacity)
{
	table->buckets = erealloc(table->buckets, capacity * sizeof(sharp_table_bucket));
	table->lookup = erealloc(table->lookup, capacity * sizeof(uint32_t));
	table->capacity = capacity;
}

/* Puts bucket, at index, at the head of its hash's chain. */
static void sharp_table_link(sharp_table *table, sharp_table_bucket *bucket, uint32_t index)
{
	uint32_t *head = &SHARP_TABLE_HEAD(table, SHARP_TABLE_HASH(bucket));

	SHARP_TABLE_NEXT(bucket) = *head;
	*head = index;
}

/* Rebuilds every chain, moving the live buckets down over the deleted ones. */
static void sharp_table_rehash(sharp_table *table)
{
	uint32_t live = 0;

	sharp_table_reset_lookup(table);
	for (uint32_t index = 0; index < table->next; index++) {
		sharp_table_bucket *bucket = &table->buckets[index];
		if (SHARP_TABLE_DELETED(bucket)) {
			continue;
		}
		if (live != index) {
			table->buckets[live] = *bucket;
		}
		sharp_table_link(table, &table->buckets[live], live);
		live++;
	}
	table->next = live;
}

/* Called when no bucket is open: a table with enough deleted buckets reuses them, any other doubles. */
static void sharp_table_grow(sharp_table *table)
{
	if (table->next <= table->size + (table->size >> 5)) {
		sharp_table_realloc(table, table->capacity << 1);
	}
	sharp_table_rehash(table);
}

void sharp_table_init(sharp_table *table, uint32_t size)
{
	uint32_t capacity = SHARP_TABLE_MIN_CAPACITY;

	/* One bucket stays open, so an insert never fills the table. */
	while (capacity <= size) {
		capacity <<= 1;
	}
	table->buckets = ecalloc(capacity, sizeof(sharp_table_bucket));
	table->lookup = emalloc(capacity * sizeof(uint32_t));
	table->capacity = capacity;
	table->size = 0;
	table->next = 0;
	sharp_table_reset_lookup(table);
}

void sharp_table_copy(sharp_table *table, const sharp_table *source)
{
	table->buckets = emalloc(source->capacity * sizeof(sharp_table_bucket));
	table->lookup = emalloc(source->capacity * sizeof(uint32_t));
	table->capacity = source->capacity;
	table->size = source->size;
	table->next = source->next;
	memcpy(table->lookup, source->lookup, source->capacity * sizeof(uint32_t));
	for (uint32_t index = 0; index < source->next; index++) {
		const sharp_table_bucket *from = &source->buckets[index];
		sharp_table_bucket *to = &table->buckets[index];
		if (SHARP_TABLE_DELETED(from)) {
			ZVAL_UNDEF(&to->key);
			ZVAL_UNDEF(&to->value);
		} else {
			ZVAL_COPY(&to->key, &from->key);
			ZVAL_COPY(&to->value, &from->value);
			SHARP_TABLE_HASH(to) = SHARP_TABLE_HASH(from);
		}
		SHARP_TABLE_NEXT(to) = SHARP_TABLE_NEXT(from);
	}
}

void sharp_table_destroy(sharp_table *table)
{
	/* A destructor an entry runs may read the table, so it reads an empty one. */
	sharp_table_bucket *buckets = table->buckets;
	uint32_t next = table->next;

	table->buckets = NULL;
	table->size = 0;
	table->next = 0;
	for (uint32_t index = 0; index < next; index++) {
		zval_ptr_dtor(&buckets[index].key);
		zval_ptr_dtor(&buckets[index].value);
	}
	efree(buckets);
	efree(table->lookup);
	table->lookup = NULL;
	table->capacity = 0;
}

/* The bucket in hash's chain that holds a key equal to key, through prev the one before it. */
static bool sharp_table_search(sharp_table *table, zval *key, uint32_t hash, uint32_t *found, uint32_t *prev)
{
	*prev = SHARP_TABLE_INVALID;
	for (uint32_t index = SHARP_TABLE_HEAD(table, hash); index != SHARP_TABLE_INVALID;) {
		sharp_table_bucket *bucket = &table->buckets[index];
		bool equal = false;
		if (SHARP_TABLE_HASH(bucket) == hash && !sharp_equal(&bucket->key, key, &equal)) {
			return false;
		}
		if (equal) {
			*found = index;
			return true;
		}
		*prev = index;
		index = SHARP_TABLE_NEXT(bucket);
	}
	*found = SHARP_TABLE_INVALID;

	return true;
}

sharp_table_bucket *sharp_table_find(sharp_table *table, zval *key)
{
	uint32_t hash, found, prev;

	if (!table->buckets || !sharp_hash(key, &hash) || !sharp_table_search(table, key, hash, &found, &prev)) {
		return NULL;
	}

	return found == SHARP_TABLE_INVALID ? NULL : &table->buckets[found];
}

bool sharp_table_put(sharp_table *table, zval *key, zval *value)
{
	uint32_t hash, found, prev;

	if (!sharp_hash(key, &hash) || !sharp_table_search(table, key, hash, &found, &prev)) {
		return false;
	}
	if (found != SHARP_TABLE_INVALID) {
		/* The old value is released last, so a destructor it runs sees the changed table. */
		zval old;
		ZVAL_COPY_VALUE(&old, &table->buckets[found].value);
		ZVAL_COPY_DEREF(&table->buckets[found].value, value);
		zval_ptr_dtor(&old);
		return true;
	}

	if (table->next == table->capacity) {
		sharp_table_grow(table);
	}
	uint32_t index = table->next++;
	sharp_table_bucket *bucket = &table->buckets[index];
	ZVAL_COPY_DEREF(&bucket->key, key);
	ZVAL_COPY_DEREF(&bucket->value, value);
	SHARP_TABLE_HASH(bucket) = hash;
	sharp_table_link(table, bucket, index);
	table->size++;

	return true;
}

bool sharp_table_remove(sharp_table *table, zval *key)
{
	uint32_t hash, found, prev;

	if (!sharp_hash(key, &hash) || !sharp_table_search(table, key, hash, &found, &prev)) {
		return false;
	}
	if (found == SHARP_TABLE_INVALID) {
		return true;
	}

	sharp_table_bucket *bucket = &table->buckets[found];
	if (prev == SHARP_TABLE_INVALID) {
		SHARP_TABLE_HEAD(table, hash) = SHARP_TABLE_NEXT(bucket);
	} else {
		SHARP_TABLE_NEXT(&table->buckets[prev]) = SHARP_TABLE_NEXT(bucket);
	}

	/* The entry is released last, so a destructor it runs sees the changed table. */
	zval old_key, old_value;
	ZVAL_COPY_VALUE(&old_key, &bucket->key);
	ZVAL_COPY_VALUE(&old_value, &bucket->value);
	ZVAL_UNDEF(&bucket->key);
	ZVAL_UNDEF(&bucket->value);
	table->size--;
	zval_ptr_dtor(&old_key);
	zval_ptr_dtor(&old_value);

	return true;
}

/* Mixes a 64-bit value into 32 bits, as php-ds hashes a double's bits. */
static uint32_t sharp_hash_bits(uint64_t bits)
{
	return (uint32_t) (bits ^ (bits >> 32));
}

static uint32_t sharp_hash_combine(uint32_t hash, uint32_t next)
{
	return hash * 31 + next;
}

/* A double whose value is a whole number an int holds hashes as that int, since 5 == 5.0 (spec section 19), and -0.0
 * as 0. */
static uint32_t sharp_hash_double(double number)
{
	if (ZEND_DOUBLE_FITS_LONG(number) && (double) (zend_long) number == number) {
		return sharp_hash_bits((uint64_t) (zend_long) number);
	}

	uint64_t bits;
	memcpy(&bits, &number, sizeof bits);

	return sharp_hash_bits(bits);
}

/* The class's own method of name, or NULL when it declares none. */
static zend_function *sharp_method(zend_class_entry *ce, const char *name, size_t length)
{
	return zend_hash_str_find_ptr(&ce->function_table, name, length);
}

/* hash() of a type that declares it with operator == (spec section 19), as an int. */
static bool sharp_hash_declared(zend_object *object, zend_function *method, uint32_t *hash)
{
	zval result;

	zend_call_known_instance_method_with_0_params(method, object, &result);
	if (EG(exception)) {
		zval_ptr_dtor(&result);
		return false;
	}
	if (Z_TYPE(result) != IS_LONG) {
		zend_type_error("%s::hash(): Return value must be of type int, %s returned", ZSTR_VAL(object->ce->name), zend_zval_value_name(&result));
		zval_ptr_dtor(&result);
		return false;
	}
	*hash = sharp_hash_bits((uint64_t) Z_LVAL(result));

	return true;
}

/* A struct with no declared pair hashes from its stored properties in declaration order, as its generated
 * op_Equality compares them. */
static bool sharp_hash_struct(zend_object *object, uint32_t *hash)
{
	zend_property_info *info;

	*hash = (uint32_t) ZSTR_HASH(object->ce->name);
	ZEND_HASH_MAP_FOREACH_PTR(&object->ce->properties_info, info) {
		if (info->flags & (ZEND_ACC_STATIC | ZEND_ACC_VIRTUAL)) {
			continue;
		}
		uint32_t property;
		zval *value = OBJ_PROP(object, info->offset);
		if (Z_TYPE_P(value) != IS_UNDEF && !sharp_hash(value, &property)) {
			return false;
		}
		*hash = sharp_hash_combine(*hash, Z_TYPE_P(value) == IS_UNDEF ? 0 : property);
	} ZEND_HASH_FOREACH_END();

	return true;
}

bool sharp_hash(zval *value, uint32_t *hash)
{
	ZVAL_DEREF(value);
	switch (Z_TYPE_P(value)) {
		case IS_NULL:
			*hash = 1;
			return true;
		case IS_FALSE:
			*hash = 2;
			return true;
		case IS_TRUE:
			*hash = 3;
			return true;
		case IS_LONG:
			*hash = sharp_hash_bits((uint64_t) Z_LVAL_P(value));
			return true;
		case IS_DOUBLE:
			*hash = sharp_hash_double(Z_DVAL_P(value));
			return true;
		case IS_STRING:
			*hash = (uint32_t) ZSTR_HASH(Z_STR_P(value));
			return true;
		case IS_ARRAY: {
			/* A List, in order. */
			zval *element;
			*hash = 4;
			ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(value), element) {
				uint32_t next;
				if (!sharp_hash(element, &next)) {
					return false;
				}
				*hash = sharp_hash_combine(*hash, next);
			} ZEND_HASH_FOREACH_END();
			return true;
		}
		case IS_OBJECT: {
			zend_object *object = Z_OBJ_P(value);
			if (object->ce == sharp_ce_map || object->ce == sharp_ce_set) {
				/* Entries combine regardless of order, so two equal Maps in different orders hash alike. */
				sharp_table *table = sharp_table_of(object);
				*hash = object->ce == sharp_ce_map ? 5 : 6;
				SHARP_TABLE_FOREACH(table, bucket) {
					uint32_t entry = SHARP_TABLE_HASH(bucket);
					if (object->ce == sharp_ce_map) {
						uint32_t of_value;
						if (!sharp_hash(&bucket->value, &of_value)) {
							return false;
						}
						entry = sharp_hash_combine(entry, of_value);
					}
					*hash += entry;
				} SHARP_TABLE_FOREACH_END();
				return true;
			}
			/* hash() counts only beside an op_Equality, the pair spec section 19 declares together. */
			zend_function *declared = sharp_method(object->ce, "hash", strlen("hash"));
			if (declared && !(declared->common.fn_flags & ZEND_ACC_STATIC)
					&& sharp_method(object->ce, "op_equality", strlen("op_equality"))) {
				return sharp_hash_declared(object, declared, hash);
			}
			if (instanceof_function(object->ce, sharp_ce_struct)) {
				return sharp_hash_struct(object, hash);
			}
			/* An enum case is one object, and any other object without hash() is itself. */
			*hash = sharp_hash_bits((uint64_t) (uintptr_t) object);
			return true;
		}
		default:
			zend_type_error("A Map key or a Set element can't be of type %s", zend_zval_value_name(value));
			return false;
	}
}

/* Two Maps or two Sets with the same entries, regardless of order. */
static bool sharp_equal_tables(zend_object *a, zend_object *b, bool *equal)
{
	sharp_table *left = sharp_table_of(a), *right = sharp_table_of(b);

	*equal = left->size == right->size;
	if (!*equal) {
		return true;
	}
	SHARP_TABLE_FOREACH(left, bucket) {
		sharp_table_bucket *other = sharp_table_find(right, &bucket->key);
		if (EG(exception)) {
			return false;
		}
		if (!other) {
			*equal = false;
			return true;
		}
		if (a->ce == sharp_ce_map && !sharp_equal(&bucket->value, &other->value, equal)) {
			return false;
		}
		if (!*equal) {
			return true;
		}
	} SHARP_TABLE_FOREACH_END();

	return true;
}

/* Two Lists of equal length, element by element. */
static bool sharp_equal_arrays(HashTable *a, HashTable *b, bool *equal)
{
	*equal = zend_hash_num_elements(a) == zend_hash_num_elements(b);
	if (!*equal) {
		return true;
	}

	zend_ulong index;
	zend_string *key;
	zval *element;
	ZEND_HASH_FOREACH_KEY_VAL(a, index, key, element) {
		zval *other = key ? zend_hash_find(b, key) : zend_hash_index_find(b, index);
		if (!other) {
			*equal = false;
			return true;
		}
		if (!sharp_equal(element, other, equal)) {
			return false;
		}
		if (!*equal) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

bool sharp_equal(zval *a, zval *b, bool *equal)
{
	ZVAL_DEREF(a);
	ZVAL_DEREF(b);

	/* An int and a float compare as two floats (spec section 19), so NAN equals nothing and -0.0 equals 0.0. */
	if ((Z_TYPE_P(a) == IS_LONG || Z_TYPE_P(a) == IS_DOUBLE) && (Z_TYPE_P(b) == IS_LONG || Z_TYPE_P(b) == IS_DOUBLE)) {
		*equal = Z_TYPE_P(a) == IS_LONG && Z_TYPE_P(b) == IS_LONG
			? Z_LVAL_P(a) == Z_LVAL_P(b)
			: zval_get_double(a) == zval_get_double(b);
		return true;
	}
	if (Z_TYPE_P(a) != Z_TYPE_P(b)) {
		*equal = false;
		return true;
	}

	switch (Z_TYPE_P(a)) {
		case IS_STRING:
			*equal = zend_string_equals(Z_STR_P(a), Z_STR_P(b));
			return true;
		case IS_ARRAY:
			return sharp_equal_arrays(Z_ARRVAL_P(a), Z_ARRVAL_P(b), equal);
		case IS_OBJECT: {
			zend_object *left = Z_OBJ_P(a), *right = Z_OBJ_P(b);
			if (left == right) {
				*equal = true;
				return true;
			}
			if (left->ce != right->ce && !instanceof_function(right->ce, left->ce) && !instanceof_function(left->ce, right->ce)) {
				/* Two sibling classes compare by the operator == both inherit, the one PHP#'s == between them runs. */
				zend_function *ours = sharp_method(left->ce, "op_equality", strlen("op_equality"));
				zend_function *theirs = sharp_method(right->ce, "op_equality", strlen("op_equality"));
				if (!ours || !theirs || ours->common.scope != theirs->common.scope) {
					*equal = false;
					return true;
				}
			}
			if (left->ce == sharp_ce_map || left->ce == sharp_ce_set) {
				return sharp_equal_tables(left, right, equal);
			}
			/* A declared operator == and a struct's generated one both lower to op_Equality. */
			zend_function *declared = sharp_method(left->ce, "op_equality", strlen("op_equality"));
			if (!declared || !(declared->common.fn_flags & ZEND_ACC_STATIC)) {
				*equal = false;
				return true;
			}
			zval result, arguments[2];
			ZVAL_COPY_VALUE(&arguments[0], a);
			ZVAL_COPY_VALUE(&arguments[1], b);
			zend_call_known_function(declared, NULL, declared->common.scope, &result, 2, arguments, NULL);
			if (EG(exception)) {
				zval_ptr_dtor(&result);
				return false;
			}
			*equal = Z_TYPE(result) == IS_TRUE;
			zval_ptr_dtor(&result);
			return true;
		}
		default:
			/* null, true and false each equal only themselves. */
			*equal = true;
			return true;
	}
}

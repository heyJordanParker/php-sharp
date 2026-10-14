/* The hash table a Sharp\Map and a Sharp\Set hold, from php-ds v1.8.0's src/ds/ds_htable.h. Its notice:
 *
 * The MIT License (MIT)
 * Copyright (c) 2016 Rudi Theunissen
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of
 * the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
 * OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * PHP# keeps the buckets in insertion order, the collision chains through a separate lookup array, and the rehash that
 * doubles the capacity. A key is found by its hash, then by PHP#'s == (spec section 19) within its chain, where php-ds
 * compares with === and Hashable::equals(). */

#ifndef SHARP_TABLE_H
#define SHARP_TABLE_H

#include "php.h"

/* A power of 2. */
#define SHARP_TABLE_MIN_CAPACITY 8

typedef struct {
	zval key;
	zval value;
} sharp_table_bucket;

/* The key's u2 holds its hash, and the value's u2 the index of the next bucket in its chain, as in php-ds. */
#define SHARP_TABLE_HASH(bucket) (Z_NEXT((bucket)->key))
#define SHARP_TABLE_NEXT(bucket) (Z_NEXT((bucket)->value))
#define SHARP_TABLE_DELETED(bucket) (Z_ISUNDEF((bucket)->key))

typedef struct {
	sharp_table_bucket *buckets;
	/* The first bucket of each hash's chain, by the hash's low bits. */
	uint32_t *lookup;
	/* The next open bucket. */
	uint32_t next;
	/* How many buckets hold an entry. */
	uint32_t size;
	uint32_t capacity;
} sharp_table;

/* Each entry in insertion order, skipping deleted buckets. */
#define SHARP_TABLE_FOREACH(table, bucket) do { \
	sharp_table_bucket *_end = (table)->buckets + (table)->next; \
	for (sharp_table_bucket *bucket = (table)->buckets; bucket < _end; bucket++) { \
		if (SHARP_TABLE_DELETED(bucket)) { \
			continue; \
		}

#define SHARP_TABLE_FOREACH_END() } } while (0)

void sharp_table_init(sharp_table *table, uint32_t size);
void sharp_table_copy(sharp_table *table, const sharp_table *source);
void sharp_table_destroy(sharp_table *table);

/* The bucket that holds key, or NULL when none does or after an exception hash() or == threw. */
sharp_table_bucket *sharp_table_find(sharp_table *table, zval *key);
/* Sets key's value, inserting key when the table holds no equal one. False after an exception, with the table
 * unchanged. */
bool sharp_table_put(sharp_table *table, zval *key, zval *value);
/* Removes key's entry, when the table holds one. False after an exception, with the table unchanged. */
bool sharp_table_remove(sharp_table *table, zval *key);

/* A Sharp\Map or a Sharp\Set: its table, then the object. A Set holds each element as a key with a null value. */
typedef struct {
	sharp_table table;
	zend_object std;
} sharp_table_object;

extern zend_class_entry *sharp_ce_map;
extern zend_class_entry *sharp_ce_set;
extern zend_class_entry *sharp_ce_struct;

static inline sharp_table *sharp_table_of(zend_object *object)
{
	return &((sharp_table_object *) ((char *) object - XtOffsetOf(sharp_table_object, std)))->table;
}

/* PHP#'s == on two values, spec section 19. False with equal unset after an exception an op_Equality threw. */
bool sharp_equal(zval *a, zval *b, bool *equal);
/* A hash that is the same for any two values sharp_equal calls equal. False after an exception a hash() threw. */
bool sharp_hash(zval *value, uint32_t *hash);

#endif

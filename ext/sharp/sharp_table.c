/* The hash table a Sharp\Map and a Sharp\Set hold, from php-ds v1.8.0's src/ds/ds_htable.c, whose MIT notice
 * sharp_table.h carries. The buckets, chains, rehash, growth and shrinking are php-ds's. The hash and the key match
 * are PHP#'s: sharp_hash and sharp_key_equal below. */

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
			/* An entry plain PHP wrote into through a reference only the table holds stays unchecked in the copy, in a
			 * reference of its own, so a write into one copy never reaches the other. */
			if (Z_ISREF(from->value) && Z_REFCOUNT(from->value) == 1) {
				ZVAL_NEW_REF(&to->value, Z_REFVAL(from->value));
				Z_TRY_ADDREF_P(Z_REFVAL(to->value));
			} else {
				ZVAL_COPY(&to->value, &from->value);
			}
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

static bool sharp_key_equal(zval *a, zval *b, bool *equal);

/* The bucket in hash's chain that holds a key equal to key, through prev the one before it. */
static bool sharp_table_search(sharp_table *table, zval *key, uint32_t hash, uint32_t *found, uint32_t *prev)
{
	*prev = SHARP_TABLE_INVALID;
	for (uint32_t index = SHARP_TABLE_HEAD(table, hash); index != SHARP_TABLE_INVALID;) {
		sharp_table_bucket *bucket = &table->buckets[index];
		bool equal = false;
		if (SHARP_TABLE_HASH(bucket) == hash && !sharp_key_equal(&bucket->key, key, &equal)) {
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

/* Whether the double `number` is a whole number an int holds exactly. ZEND_DOUBLE_FITS_LONG holds for NAN, so NAN is
 * tested first, and the cast is never reached for it. */
static bool sharp_double_is_long(double number)
{
	return !zend_isnan(number) && ZEND_DOUBLE_FITS_LONG(number) && (double) (zend_long) number == number;
}

/* A double that is a whole number an int holds exactly hashes as that int, since 5 is 5.0 as a key (spec section 12),
 * and -0.0 as 0. Every NAN is one key, so each NAN payload hashes alike. */
static uint32_t sharp_hash_double(double number)
{
	if (sharp_double_is_long(number)) {
		return sharp_hash_bits((uint64_t) (zend_long) number);
	}
	if (zend_isnan(number)) {
		number = ZEND_NAN;
	}

	uint64_t bits;
	memcpy(&bits, &number, sizeof bits);

	return sharp_hash_bits(bits);
}

/* Whether the keys `a` and `b` are one key of a Map or one element of a Set (spec section 12): as == compares them,
 * except that every NAN is one key, as C#'s Double.Equals finds it, where NAN == NAN is false. -0.0 and 0.0 are one
 * key, as == already says. False after an exception an op_Equality threw. */
static bool sharp_key_equal(zval *a, zval *b, bool *equal)
{
	zval *left = a, *right = b;
	ZVAL_DEREF(left);
	ZVAL_DEREF(right);
	if (Z_TYPE_P(left) == IS_DOUBLE && Z_TYPE_P(right) == IS_DOUBLE
			&& zend_isnan(Z_DVAL_P(left)) && zend_isnan(Z_DVAL_P(right))) {
		*equal = true;
		return true;
	}

	return sharp_equal(left, right, equal);
}

/* The class's own method of name, or NULL when it declares none. */
static zend_function *sharp_method(zend_class_entry *ce, const char *name, size_t length)
{
	return zend_hash_str_find_ptr(&ce->function_table, name, length);
}

/* A declared operator == two objects may compare by, with the class each of its parameters takes. A class is found
 * once it is loaded, and no object is an instance of a class that is not. */
typedef struct {
	zend_function *function;
	zend_class_entry *takes[2];
} sharp_equality;

/* The operators == a class, its parent classes and its PHP# interfaces declare, each once. */
typedef struct {
	uint32_t count;
	uint32_t capacity;
	sharp_equality operators[];
} sharp_class_equalities;

/* The operator == the PHP# interface `iface` declares, spec section 19: the static method `op_Equality@Iface` of its
 * trait `Iface\Defaults`, which no class replaces, or NULL. */
static zend_function *sharp_interface_equality(const zend_class_entry *iface)
{
	if (iface->type != ZEND_USER_CLASS || !sharp_is_sharp_file(iface->info.user.filename)) {
		return NULL;
	}

	zend_string *trait_name = zend_string_concat2(ZSTR_VAL(iface->name), ZSTR_LEN(iface->name), ZEND_STRL("\\Defaults"));
	zend_string *lowercase = zend_string_tolower(trait_name);
	zend_class_entry *trait = zend_hash_find_ptr(EG(class_table), lowercase);
	zend_string_release(lowercase);
	zend_string_release(trait_name);
	if (!trait) {
		return NULL;
	}

	zend_string *method = zend_string_concat2(ZEND_STRL("op_equality@"), ZSTR_VAL(iface->name), ZSTR_LEN(iface->name));
	zend_string *method_key = zend_string_tolower(method);
	zend_function *declared = zend_hash_find_ptr(&trait->function_table, method_key);
	zend_string_release(method_key);
	zend_string_release(method);

	return declared && (declared->common.fn_flags & ZEND_ACC_STATIC) ? declared : NULL;
}

/* Adds `function` to the operators `*found` holds, once, growing it as it fills. */
static void sharp_class_equality_add(sharp_class_equalities **found, zend_function *function)
{
	for (uint32_t i = 0; i < (*found)->count; i++) {
		if ((*found)->operators[i].function == function) {
			return;
		}
	}
	if ((*found)->count == (*found)->capacity) {
		(*found)->capacity *= 2;
		*found = erealloc(*found, sizeof(sharp_class_equalities) + (*found)->capacity * sizeof(sharp_equality));
	}
	(*found)->operators[(*found)->count++] = (sharp_equality) {function, {NULL, NULL}};
}

/* The operators == objects of `ce` compare by, found the first time the request compares one and kept until it ends,
 * so a Set or a Map of them looks no name up again. */
static sharp_class_equalities *sharp_class_equalities_of(zend_class_entry *ce)
{
	HashTable *known = sharp_equality_operators();
	zend_ulong key = (zend_ulong) (uintptr_t) ce;
	sharp_class_equalities *found = zend_hash_index_find_ptr(known, key);
	if (found) {
		return found;
	}

	found = emalloc(sizeof(sharp_class_equalities) + 2 * sizeof(sharp_equality));
	found->count = 0;
	found->capacity = 2;
	for (zend_class_entry *declaring = ce; declaring; declaring = declaring->parent) {
		zend_function *own = sharp_method(declaring, "op_equality", strlen("op_equality"));
		if (own && own->common.scope == declaring && (own->common.fn_flags & ZEND_ACC_STATIC)) {
			sharp_class_equality_add(&found, own);
		}
	}
	for (uint32_t i = 0; i < ce->num_interfaces; i++) {
		zend_function *declared = sharp_interface_equality(ce->interfaces[i]);
		if (declared) {
			sharp_class_equality_add(&found, declared);
		}
	}

	return zend_hash_index_add_new_ptr(known, key, found);
}

/* The class whose instances the parameter at `position` of `equality` takes: the class it names, or, for a `mixed`
 * one, as a struct's generated == takes, the class that declares it, whose own instances it compares. NULL for a
 * parameter that takes no object, such as an `int` or a union, or names a class not loaded yet. */
static zend_class_entry *sharp_equality_parameter(sharp_equality *equality, uint32_t position)
{
	if (equality->takes[position]) {
		return equality->takes[position];
	}

	const zend_type *type = &equality->function->common.arg_info[position].type;
	if (ZEND_TYPE_HAS_NAME(*type)) {
		equality->takes[position] = zend_lookup_class_ex(ZEND_TYPE_NAME(*type), NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
	} else if (!ZEND_TYPE_IS_SET(*type) || ZEND_TYPE_PURE_MASK(*type) == MAY_BE_ANY) {
		equality->takes[position] = equality->function->common.scope;
	}

	return equality->takes[position];
}

/* Whether the two parameters of `equality` take `a` and `b`. */
static bool sharp_equality_takes(sharp_equality *equality, zend_object *a, zend_object *b)
{
	if (equality->function->common.num_args < 2) {
		return false;
	}
	zend_object *objects[2] = {a, b};
	for (uint32_t position = 0; position < 2; position++) {
		zend_class_entry *taken = sharp_equality_parameter(equality, position);
		if (!taken || !instanceof_function(objects[position]->ce, taken)) {
			return false;
		}
	}

	return true;
}

/* Whether each parameter of `narrow` takes no more than the parameter of `wide` in its position. Both take the same two
 * objects, so each has its class. */
static bool sharp_equality_narrower(const sharp_equality *narrow, const sharp_equality *wide)
{
	for (uint32_t position = 0; position < 2; position++) {
		if (!instanceof_function(narrow->takes[position], wide->takes[position])) {
			return false;
		}
	}

	return true;
}

/* Whether `candidate` takes no more than every operator of `sides` that takes `a` and `b`. */
static bool sharp_equality_narrowest(
	const sharp_equality *candidate, sharp_class_equalities *const sides[2], zend_object *a, zend_object *b)
{
	for (uint32_t side = 0; side < 2 && sides[side]; side++) {
		for (uint32_t i = 0; i < sides[side]->count; i++) {
			sharp_equality *other = &sides[side]->operators[i];
			if (sharp_equality_takes(other, a, b) && !sharp_equality_narrower(candidate, other)) {
				return false;
			}
		}
	}

	return true;
}

/* PHP#'s operator == on the objects `a` and `b`, spec section 19, as the checker chooses one from the types of two
 * sides: of the operators their classes, the classes' parents and their PHP# interfaces declare, the one that takes
 * both and whose parameters take no more than those of every other that does. So two implementers of an interface
 * compare by the interface's ==, as a Set or a Map of the interface keys them. NULL when none takes both, or two tie. */
static zend_function *sharp_object_equality(zend_object *a, zend_object *b)
{
	sharp_class_equalities *const sides[2] = {
		sharp_class_equalities_of(a->ce),
		b->ce == a->ce ? NULL : sharp_class_equalities_of(b->ce),
	};

	for (uint32_t side = 0; side < 2 && sides[side]; side++) {
		for (uint32_t i = 0; i < sides[side]->count; i++) {
			sharp_equality *candidate = &sides[side]->operators[i];
			if (sharp_equality_takes(candidate, a, b) && sharp_equality_narrowest(candidate, sides, a, b)) {
				return candidate->function;
			}
		}
	}

	return NULL;
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

/* A struct with no declared pair, or an enum case that carries data, hashes from its class and its stored
 * properties in declaration order, as the generated op_Equality compares them. A generic struct's hidden slot of its
 * type arguments is no property, and two keys of one Map share their type. */
static bool sharp_hash_struct(zend_object *object, uint32_t *hash)
{
	zend_property_info *info;

	*hash = (uint32_t) ZSTR_HASH(object->ce->name);
	ZEND_HASH_MAP_FOREACH_PTR(&object->ce->properties_info, info) {
		if (info->flags & (ZEND_ACC_STATIC | ZEND_ACC_VIRTUAL | ZEND_ACC_SHARP_HIDDEN)) {
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
			/* hash() counts only beside an operator ==, the pair spec section 19 declares together. */
			zend_function *declared = sharp_method(object->ce, "hash", strlen("hash"));
			if (declared && !(declared->common.fn_flags & ZEND_ACC_STATIC) && sharp_object_equality(object, object)) {
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

	/* Numbers compare by value (spec section 19): two floats as IEEE 754 does, so NAN equals nothing and -0.0 equals
	 * 0.0, and an int and a float exactly, as Python and Rust compare them, so 2^53 + 1 and 2^53 differ though the float
	 * nearest 2^53 + 1 is 2^53, and == stays transitive. */
	if ((Z_TYPE_P(a) == IS_LONG || Z_TYPE_P(a) == IS_DOUBLE) && (Z_TYPE_P(b) == IS_LONG || Z_TYPE_P(b) == IS_DOUBLE)) {
		if (Z_TYPE_P(a) == IS_LONG && Z_TYPE_P(b) == IS_LONG) {
			*equal = Z_LVAL_P(a) == Z_LVAL_P(b);
		} else if (Z_TYPE_P(a) == IS_DOUBLE && Z_TYPE_P(b) == IS_DOUBLE) {
			*equal = Z_DVAL_P(a) == Z_DVAL_P(b);
		} else {
			zend_long whole = Z_TYPE_P(a) == IS_LONG ? Z_LVAL_P(a) : Z_LVAL_P(b);
			double number = Z_TYPE_P(a) == IS_DOUBLE ? Z_DVAL_P(a) : Z_DVAL_P(b);
			*equal = sharp_double_is_long(number) && (zend_long) number == whole;
		}
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
			if (left->ce == sharp_ce_map || left->ce == sharp_ce_set) {
				if (right->ce != left->ce) {
					*equal = false;
					return true;
				}
				return sharp_equal_tables(left, right, equal);
			}
			/* A declared operator ==, a struct's generated one and an interface's, chosen as PHP#'s == chooses one. */
			zend_function *declared = sharp_object_equality(left, right);
			if (!declared) {
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

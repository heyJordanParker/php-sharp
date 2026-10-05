# 26. Collection operations

## Decision

`List` and `Map` both run as plain PHP arrays, so the engine cannot tell them apart when the code runs. Every collection operation therefore has one meaning on both, decided by how the code is written:

- A `Map` read is handled where it is read, with `??`, `?.`, `is`, `as`, `match` or `get`. A bare `x[i]` throws `OutOfRangeException` on a missing index or key, and a bare `Map` read is a compile error that names `??` and `get`.
- `x[k] = v` inserts or replaces a key, and is `Map`-only. `set` replaces a `List` element and throws `OutOfRangeException` past the end.
- A method name means one thing on every collection. `remove` removes by value and `delete` removes a key. `filter` renumbers what it keeps and `filterValues` keeps the keys.
- Two imported extensions with one name, one on a `List` and one on a `Map`, are a compile error.

A key read back out of a `Map<string, TValue>` is typed `int|string`, because PHP stores an all-digit string key as an int. This covers the key of `for (const [key, value] of map)` and of `keys()`. Reads by key and every value keep their types.

## Options

### Chosen: how the code is written decides what an operation does

```csharp
const first = lines[0];                       // compiles; runs: throws OutOfRangeException when lines is empty
lines[0] = line;                              // compile error: write lines.set(0, line)
lines.set(0, line);                           // compiles; runs: replaces the element
lines.remove(line);                           // compiles; runs: removes by value

int? maybe = prices[plan];                    // compile error: handle a missing plan with ??, or read it with get
int? maybe = prices.get(plan);                // compiles; runs: null when plan is missing
plans["pro"] = pro;                           // compiles; runs: inserts or replaces
plans.delete("pro");                          // compiles; runs: removes the key
plans.filterValues(p => p.active);            // compiles; runs: keeps the keys
for (const plan of plans) { … }               // compiles; runs: a Map's values
```

Each line runs the same code whether the array holds a `List` or a `Map`, so nothing depends on what the engine cannot see.

### Rejected: a hidden List-or-Map mark on each array

```csharp
Map<int, int> stock = Legacy.stock();         // plain PHP built it with array_map, so it carries no mark
stock.remove(2);                              // compiles; runs: removes the value 2, as on a List, not the key 2
```

Plain PHP drops the mark whenever it rebuilds an array, as `array_filter` and `array_map` do, and an empty array loses it too. A `Map` with keys 0 to n then acts as a `List`, with no error.

### Rejected: deciding by shape

```csharp
Map<int, string> seats = [:];
seats[7] = "Ana";                             // compiles; runs: throws OutOfRangeException, because the empty array is a list
```

PHP's `array_is_list` is true for every empty array, so the first insert into an empty `Map<int, TValue>` throws.

### Rejected: the checker's types decide what the engine emits

```csharp
plans.remove("pro");                          // compiles to a key removal only because the checker knows plans is a Map
```

The engine compiles one file at a time and cannot see the checker's types, as sections 4, 11 and 18 already require. Types never change the code the engine emits.

### Chosen: a `Map<string, TValue>` key reads back as `int|string`

```csharp
void reserve(string sku, int count) { … }

for (const [sku, count] of stock) {           // stock is Map<string, int>, so sku is int|string
    this.reserve(sku, count);                 // compile error: sku is int|string, not string
    this.reserve((string)sku, count);         // compiles
}
```

The type says what PHP actually stores, so a `"5"` key that comes back as `5` never reaches a `string` parameter unconverted.

### Rejected: marking string keys in the engine

```php
$stock = Inventory::stock();   // a PHP# Map<string, int> whose key "5" the engine marked as a string
$stock["5"];                   // plain PHP looks up the int 5, so it misses the marked key
```

Plain PHP looks up `"5"` as the int `5`, so it cannot read back a key the engine marked as a string.

## Precedent

- **Chosen, one meaning for `[]` and `get`:** Rust, where `[]` panics and `get` gives an `Option` on `Vec` and `HashMap` alike, and Python, where `[]` raises on a missing index or key on `list` and `dict` alike.
- **Chosen, `set`:** Java's `list.set(i, x)`.
- **Chosen, `filterValues` and a `Map` read giving `TValue?`:** Kotlin's `Map.filterValues`, which keeps the keys, and Kotlin's `map[key]`, which gives `V?`.
- **Chosen, `delete`:** TypeScript's `Map.delete`.
- **Chosen, the `int|string` key:** PHP's own arrays, which store an all-digit string key as an int.
- **Rejected, a hidden mark:** HHVM's `vec` and `dict`.
- **Rejected, deciding by shape:** PHP's `array_is_list`.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)

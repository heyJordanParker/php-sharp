# 26. Collection operations

## Decision

Each collection kind does the natural thing with an operation. The checker's types reach the running program (decision 29), so the engine knows whether a collection is a `List` or a `Map`:

- `lines.remove(line)` removes a value, and `plans.remove("pro")` removes a key.
- `lines.filter(…)` renumbers what it keeps, and `plans.filter(…)` gives a `Map` that keeps its keys.
- A `Map` read is handled where it is read, with `??`, `?.`, `is`, `as`, `match` or `get`. A bare `x[i]` throws `OutOfRangeException` on a missing index or key, and a bare `Map` read is a compile error that names `??` and `get`.
- `x[k] = v` inserts or replaces a key, and is `Map`-only. `set` replaces a `List` element and throws `OutOfRangeException` past the end.
- Two imported extensions with one name, one on a `List` and one on a `Map`, are allowed. The engine knows each collection's kind, so the receiver's type picks the extension, as in C# and Kotlin.

A key read back out of a `Map<string, TValue>` is a `string`. PHP stores an all-digit string key such as `"5"` as the int `5`, and the engine hands it back as `"5"` because it knows the key type. Plain PHP reading the same array still sees `5`. This covers the key of `for (const [key, value] of map)` and of `keys()`. A key with an `int` or `string` backing value, such as a backed enum, arrives as its key type in a loop, with the type written or not (decision 27).

## Options

### Chosen: each collection kind does the natural thing

```csharp
lines.remove(line);                           // compiles; runs: removes the value line
plans.remove("pro");                          // compiles; runs: removes the key "pro"
lines.filter(l => !l.refunded);               // compiles; a List<Line>, renumbered
plans.filter(p => p.active);                  // compiles; a Map<string, Plan>, keys kept

const first = lines[0];                       // compiles; runs: throws OutOfRangeException when lines is empty
lines[0] = line;                              // compile error: write lines.set(0, line)
lines.set(0, line);                           // compiles; runs: replaces the element
int? maybe = prices[plan];                    // compile error: handle a missing plan with ??, or read it with get
int? maybe = prices.get(plan);                // compiles; runs: null when plan is missing
plans["pro"] = pro;                           // compiles; runs: inserts or replaces
for (const plan of plans) { … }               // compiles; runs: a Map's values
```

A method does what its name means on that kind of collection, as in C#, Java, Kotlin and Swift. The engine knows each collection's kind from the checker's types (decision 29).

### Rejected: one name per meaning on every collection

```csharp
lines.remove(line);                           // removes by value
plans.delete("pro");                          // removes a key: a second name, because remove meant "by value"
plans.filterValues(p => p.active);            // keeps the keys: a second name, because filter renumbered
```

This was the rule while the engine compiled each file alone. The engine could not tell a `List` from a `Map` when the code ran, so each name had to mean the same thing on both. Typed compilation (decision 29) removed that limit.

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

### Chosen: a `Map<string, TValue>` key reads back as a `string`

```csharp
void reserve(string sku, int count) { … }

for (const [sku, count] of stock) {           // stock is Map<string, int>, so sku is a string, even for the key "5"
    this.reserve(sku, count);                 // compiles
}
```

PHP stores the key `"5"` as the int `5`. The engine knows the key type, so it hands the key back as `"5"`. Plain PHP reading the same array still sees `5`.

### Rejected: the key typed `int|string`

```csharp
for (const [sku, count] of stock) {           // sku is int|string
    this.reserve((string)sku, count);         // compiles; every key read back needs the conversion
}
```

This was the rule while the engine compiled each file alone and could not turn the key back into a `string`. Typed compilation (decision 29) removed that limit.

### Rejected: marking string keys in the engine

```php
$stock = Inventory::stock();   // a PHP# Map<string, int> whose key "5" the engine marked as a string
$stock["5"];                   // plain PHP looks up the int 5, so it misses the marked key
```

Plain PHP looks up `"5"` as the int `5`, so it cannot read back a key the engine marked as a string.

## Precedent

- **Chosen, each kind does the natural thing:** C#'s `List.Remove(item)` and `Dictionary.Remove(key)`, Java's and Kotlin's `list.remove(element)` and `map.remove(key)`, and Swift's `Dictionary.filter`, which returns a `Dictionary`.
- **Chosen, one meaning for `[]` and `get`:** Rust, where `[]` panics and `get` gives an `Option` on `Vec` and `HashMap` alike, and Python, where `[]` raises on a missing index or key on `list` and `dict` alike.
- **Chosen, `set`:** Java's `list.set(i, x)`.
- **Chosen, a `Map` read giving `TValue?`:** Kotlin's `map[key]`, which gives `V?`.
- **Chosen, a string key read back as a `string`:** C#, Java and Kotlin, whose string-keyed maps hand back the key they stored.
- **Rejected, one name per meaning:** Python and TypeScript.
- **Rejected, the `int|string` key:** PHP's own arrays, which store an all-digit string key as an int.
- **Rejected, a hidden mark:** HHVM's `vec` and `dict`.
- **Rejected, deciding by shape:** PHP's `array_is_list`.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)
- [Section 27, PHP# files](../spec.md#27-php-files)

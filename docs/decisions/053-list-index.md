# 53. `List` index

## Decision

A `List` index is an `int`. Any wider index is a compile error. `items[-1]` throws `OutOfRangeException`, and the last item is `items.last()`.

## Options

### Chosen: an `int` index, and no negative index

```csharp
List<string> items = ["a", "b", "c"];
items[0];                         // "a"
items[-1];                        // throws OutOfRangeException: a List has no index -1
items.last();                     // "c"
items[id];                        // compile error when id is int|string: a List index is an int
```

A `List` and the PHP array under it agree on every index, and the checker refuses every index type a `List` can never hold.

### Rejected: a negative index counts from the end

```csharp
items[-1];                        // would give "c"
```

PHP reads `$a[-1]` as the key -1. The same array would give `"c"` in PHP# and a missing key in plain PHP, so the two languages would disagree on one value.

### Rejected: a wider index accepted, and refused when it runs

```csharp
items[id];                        // would compile when id is int|string, and throw when id holds a string
```

The code type-checks, then fails on a key a `List` can never hold. The checker knows the index type, so the error belongs at compile time.

## Precedent

- **Chosen:** Kotlin, whose `List` takes an `Int` index, throws on a negative one, and has `last()`.
- **Rejected, negative index:** Python.

## Spec

- [Section 12, Collections](../spec.md#12-collections)

# 27. Map keys with a backing value

## Decision

A `Map` key is `int`, `string`, or any type with an `int` or `string` backing value. A backed enum is one today, and `public struct Username : string` declares one with the same header an enum uses. `counts[status]` runs as `$counts[$status->value]`, a loop over the `Map` gives each key as the key type, as in `for (const [status, n] of counts)`, with the type written or not, and plain PHP receives the backing values. `keys()` and `entries()` on such a `Map` are not specified yet.

## Options

### Chosen: a type with a backing value can be a key

```csharp
Map<Status, int> counts = [:];
counts[status] = 1;                        // compiles; runs: $counts[$status->value] = 1
for (const [Status status, int n] of counts) { … }   // compiles; status arrives as a Status

public struct Username : string
{
    public Username(public string value { get; }) { }
}
Map<Username, Order> byUser = [:];         // compiles; keyed by username.value
```

The key keeps its type in both directions, and plain PHP receives the same array it would build by hand.

### Chosen: a key arrives as its type, from the checker's types

```csharp
for (const [status, n] of counts) { … }                      // compiles; status arrives as a Status
for (const [Status status, int n] of counts) { … }           // compiles; writing the type stays allowed
const byStatus = orders.groupBy(o => o.status);              // Map<Status, List<Order>>, inferred
for (const [status, group] of byStatus) { … }                // compiles; status arrives as a Status
```

The stored key is only its value, such as `"open"`. The checker's types reach the running program (decision 29), inferred ones too, so the engine turns the value back into a `Status` for a declared `Map` and an inferred one alike.

### Rejected: the loop names the key's type

```csharp
for (const [Status status, int n] of counts) { … }   // compiles
for (const [status, n] of counts) { … }              // compile error: name the key's type: write const [Status status, int n]
```

This was the rule while each file compiled alone. The type written in the loop was the only way the engine learned to rebuild the key. Decision 29 removed that limit.

### Rejected: only a declared key type reaches the engine

```csharp
Map<Status, int> counts = [:];                       // a written type the engine could carry
const byStatus = orders.groupBy(o => o.status);      // Map<Status, List<Order>>, inferred: the engine never sees Status
for (const [status, group] of byStatus) { … }        // runs: status is the string "open", not a Status
```

A `Map` built by `groupBy` or any other inferred call would hand back its keys as plain values.

### Rejected: a user-defined conversion operator, as in C#

```csharp
public static implicit operator string(Username u) => u.value;   // not PHP#: a conversion the call never shows
byUser[username] = order;                                        // compiles; runs: converts silently, and keys() gives strings, not Usernames
```

The conversion goes one way, so the `Map` loses the key's type, and every call site can hide a conversion. PHP# has no user-defined conversion operators, so a type converts only through a property or method it declares, such as `username.value`.

### Rejected: keys limited to `int|string`

```csharp
Map<Status, int> counts = [:];             // compile error: a Map's keys are int or string
Map<string, int> counts = [:];
counts[status.value] = 1;                  // compiles
for (const [key, n] of counts) { … }       // compiles; key is int|string, not a Status
```

Every read and write converts by hand, and a key read back loses its type.

## Precedent

- **Chosen:** Swift, whose enums with raw values convert both ways through `rawValue` and `init?(rawValue:)`. Hack, whose enums are their backing values when the code runs, so `dict<Status, int>` works.
- **Chosen, no conversion operators:** Kotlin, Swift and Rust, which have none.
- **Rejected, a user-defined conversion operator:** C#'s `implicit operator`.
- **Rejected, keys limited to `int|string`:** PHP's own arrays, which refuse an enum as a key.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)
- [Section 20, Enums](../spec.md#20-enums)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)
- [Section 27, PHP# files](../spec.md#27-php-files)

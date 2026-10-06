# 27. Map keys with a backing value

## Decision

A `Map` key is `int`, `string`, or any type with an `int` or `string` backing value. A backed enum is one today, and `public struct Username : string` declares one with the same header an enum uses. `counts[status]` runs as `$counts[$status->value]`, a loop over the `Map` names the key's type, as in `for (const [Status status, int n] of counts)`, and the key arrives as that type. Leaving the type out is a compile error that names the fix, and plain PHP receives the backing values. `keys()` and `entries()` on such a `Map` are not specified yet.

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

### Chosen: a loop names the key's type

```csharp
for (const [Status status, int n] of counts) { … }   // compiles; status arrives as a Status
for (const [status, n] of counts) { … }              // compile error: name the key's type: write const [Status status, int n]
```

Each file compiles alone, and the stored key is only its value, such as `"open"`. The engine cannot see that `counts` holds `Status` keys, so the type written in the loop is what turns the value back into a `Status`.

### Rejected: the engine keeps the declared key type

```csharp
Map<Status, int> counts = [:];                       // a written type the engine could carry
const byStatus = orders.groupBy(o => o.status);      // Map<Status, List<Order>>, inferred: the engine never sees Status
for (const [status, group] of byStatus) { … }        // runs: status is the string "open", not a Status
```

Written type arguments reach the running program, and inferred ones do not (section 11). A `Map` built by `groupBy` or any other inferred call would hand back its keys as plain values.

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

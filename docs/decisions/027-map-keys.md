# 27. Map keys with a backing value

## Decision

A `Map` key is `int`, `string`, or any type with an `int` or `string` backing value. A backed enum is one today, and `public struct Username : string` declares one with the same header an enum uses. `counts[status]` runs as `$counts[$status->value]`, keys read back as the key type, and plain PHP receives the backing values.

## Options

### Chosen: a type with a backing value can be a key

```csharp
Map<Status, int> counts = [:];
counts[status] = 1;                        // compiles; runs: $counts[$status->value] = 1
for (const [status, n] of counts) { … }    // compiles; status reads back as a Status

public struct Username : string
{
    public Username(public string value { get; }) { }
}
Map<Username, Order> byUser = [:];         // compiles; keyed by username.value
```

The key keeps its type in both directions, and plain PHP receives the same array it would build by hand.

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
- [Section 20, Enums](../spec.md#20-enums)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

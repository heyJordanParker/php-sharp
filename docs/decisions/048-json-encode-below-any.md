# 48. `Json.encode` below `Any`

## Decision

`Json.encode` follows the value's static type as far as it is written (decision 39). Below an `Any`, a value encodes as PHP sees it, because an array carries no mark that says `List` or `Map` (decision 26). So any list-shaped array there, such as an empty `Map` or a `Map<int, V>` with keys 0 to n, encodes as a JSON array. To keep a `Map` a JSON object, write its type or use a struct.

## Options

### Chosen: the written type, then PHP's view

```csharp
Map<string, int> none = [:];
Json.encode(none);                                         // compiles; runs: {}
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                                        // compiles; runs: {"counts":[]}, because counts sits below Any
Map<string, Map<string, int>> typed = ["counts": none];
Json.encode(typed);                                        // compiles; runs: {"counts":{}}
```

Every value can be encoded, and the written type decides the shape wherever it reaches. Where the code wrote `Any`, the running program holds only PHP's array.

### Rejected: refusing `Json.encode` on a type that contains `Any`

```csharp
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                                        // not PHP#: a compile error, because loose's type contains Any
```

Every payload with a loose part needs a struct or a fully written type before it can be encoded, even when no part of it is an empty `Map` or a `Map` with keys 0 to n.

## Precedent

- **Chosen:** System.Text.Json, which serializes by the declared type and, below a property typed `object`, by the value's runtime type. kotlinx.serialization, which encodes by the declared type's serializer.
- **Rejected:** refusing to encode any value whose type contains `Any`.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

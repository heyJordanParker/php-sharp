# 48. `Json.encode` below `Any`

## Decision

`Json.encode` follows the value's PHP# type (decision 39). A `Map` is a JSON object and a `List` is a JSON array wherever it is, below an `Any` too, even when empty, and even when a `Map`'s keys run from 0 to n. A `Map` is an engine object the engine tells apart from a `List`, which is a PHP array (spec section 12).

## Options

### Chosen: the value's kind, everywhere

```csharp
Map<string, int> none = [:];
Json.encode(none);                                         // compiles; runs: {}
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                                        // compiles; runs: {"counts":{}}, though counts sits below Any
List<int> empty = [];
Map<string, Any> listed = ["counts": empty];
Json.encode(listed);                                       // compiles; runs: {"counts":[]}
```

Every value can be encoded, and the running program knows the kind of every collection, so no written type is needed to keep a `Map` an object.

### Rejected: the written type, then PHP's view

```csharp
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                                        // not PHP#: {"counts":[]}, because counts sits below Any
```

This was the choice while a `Map` ran as a PHP array, which carries no mark that says `List` or `Map`. Code had to write a `Map`'s type or use a struct to keep it a JSON object.

### Rejected: refusing `Json.encode` on a type that contains `Any`

```csharp
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                                        // not PHP#: a compile error, because loose's type contains Any
```

Every payload with a loose part needs a struct or a fully written type before it can be encoded.

## Precedent

- **Chosen:** System.Text.Json, which serializes by the declared type and, below a property typed `object`, by the value's runtime type. Swift's `JSONEncoder`, where a `Dictionary` is always a JSON object.
- **Rejected:** PHP's own `json_encode` of an array, which gives `[]` for an empty one whatever it stands for. Refusing to encode any value whose type contains `Any`.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

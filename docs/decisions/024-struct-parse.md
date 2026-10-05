# 24. Parsing a payload into a struct

## Decision

Every struct has a static `parse(Map<string, Any?>)`, which throws one error that lists every bad field, and a static `tryParse`, which gives null. Classes do not get them. The keys are the main constructor's parameter names, and `[Key("…")]` renames one.

## Options

### Chosen: every struct parses, with no opt-in

```csharp
public struct RenewRequest
{
    public RenewRequest(
        public int customerId { get; },
        [Key("plan_code")] public Plan plan { get; },
        public string? coupon { get; },
    ) { }
}

RenewRequest request = RenewRequest.parse(payload);     // throws: "customerId: expected int, got string 'abc'; plan_code: missing"
RenewRequest? maybe = RenewRequest.tryParse(payload);   // null on any bad field
```

- A nested struct parses the same way.
- A `List` checks each element.
- An enum parses from its value.
- A missing key for a `T?` parameter reads as null.

The names follow `Int.parse` and `Int.tryParse`, so one pair of names covers parsing everywhere. One error lists every bad field, so a caller fixes the whole payload at once.

`Key` names what the attribute does: a struct reads a `Map`, and the attribute renames the key a parameter reads. A project with its own `Key` renames one of them on import, as in `import Cache.Key as CacheKey;` (section 23).

### Rejected: opt in with an attribute

```csharp
[Parseable]                                             // not PHP#: an opt-in marker
public struct RenewRequest
{
    public RenewRequest(public int customerId { get; }) { }
}

RenewRequest request = RenewRequest.parse(payload);     // compile error without [Parseable]
```

Every struct that receives outside data needs the marker first, and a struct without it cannot be parsed.

## Precedent

- **Chosen:** C#'s `System.Text.Json`, which reads any type with no opt-in, and PHP's Valinor.
- **Rejected, opt in with an attribute:** Swift's `Codable`, Rust's `#[derive(Deserialize)]`, Kotlin's `@Serializable`, and spatie/laravel-data.
- **Chosen, the name `Key`:** Swift's `CodingKeys`.
- **Rejected names:** C#'s `JsonPropertyName` and Kotlin's `SerialName`, which are tied to a format, Rust's `serde(rename)`, and Pydantic's `alias`.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)

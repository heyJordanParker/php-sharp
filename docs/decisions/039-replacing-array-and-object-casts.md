# 39. Replacing `(array)` and `(object)`

## Decision

`(array)` and `(object)` are compile errors, each naming its replacement. A struct's `parse` and `tryParse` read an object's public properties as well as a `Map<string, Any?>`. The standard library's `List.wrap(T|List<T> value)` wraps a value in a list, and the checker refuses it when `T` could itself be a list. A JSON object is a `Map` literal for a one-off payload, or a declared struct for a shape that repeats. `Json.encode` encodes by the value's PHP# type as far as the type is written, so there a `Map` is a JSON object and a `List` is a JSON array, even when empty. Below an `Any`, a value encodes as PHP sees it (decision 48).

## Options

### Chosen: a struct's `parse` reads an object

```csharp
OrderRow order = OrderRow.parse(row);             // compiles; runs: throws "id: expected int, got string 'abc'"
```

The object's data is checked once, field by field, and arrives typed.

### Rejected: a dynamic `Map.fromObject`

```csharp
Map<string, Any?> values = Map.fromObject(row);   // not PHP#: every field stays unchecked until something reads it
```

Every field stays `Any?` until code checks it, where `parse` checks every field at once and names each bad one.

### Chosen: `List.wrap`

```csharp
List<string> tags = List.wrap(value);             // compiles: value is string|List<string>
List<List<int>> rows = List.wrap(numbers);        // compile error: T is List<int>, itself a list; write numbers is List<int> one ? [one] : numbers
```

The common case, a value that is never itself a list, is one call. The checker refuses the case where wrapping would guess.

### Rejected: the `is` idiom alone

```csharp
List<string> tags = value is string one ? [one] : value;   // compiles, as the only way to write it
```

Every call site repeats the idiom, even when the value can never be a list.

### Chosen: a `Map` literal or a struct, encoded by type

```csharp
string json = Json.encode(["id": 1, "email": email]);   // compiles; runs: {"id":1,"email":"…"}
Map<string, int> none = [:];
Json.encode(none);                                       // runs: {}
```

The checker knows whether a value is a `Map` or a `List`, so an empty `Map`, or one whose keys run from 0 to n, still encodes as a JSON object. PHP's `json_encode` would give `[]`.

### Rejected: anonymous objects

```csharp
const payload = new { id: 1, email: email };            // not PHP#: C#'s anonymous type
```

It adds a new kind of type, one with no name, beside classes, structs and `Map`s.

## Precedent

- **Chosen, `parse` reads an object:** Pydantic's `model_validate(obj, from_attributes=True)` and Jackson's `convertValue`.
- **Chosen, `List.wrap`:** Laravel's `Arr::wrap` and Lodash's `castArray`.
- **Chosen, encoding by type:** kotlinx.serialization and C# records.
- **Rejected, anonymous objects:** C#'s `new { … }`.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

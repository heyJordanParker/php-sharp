# 27. Map keys

## Decision

A `Map` key is `int`, `string`, a backed enum, or any struct, by its whole value, with nothing declared. `counts[status]` runs as `$counts[$status->value]`. Two structs are one key when they are `==`. A struct that holds a class instance, at any depth, can't key a `Map`. A loop over the `Map` gives each key as the key type, as in `for (const [status, n] of counts)`, with the type written or not. Plain PHP receives an enum key's backing value and a generated key text for a struct key. A struct gets JSON key text only from its `toString()`, so `Json.encode` of a `Map` keyed by a struct without `toString()` is a compile error. `keys()` and `entries()` on such a `Map` are not specified yet.

## Options

### Chosen: any struct is a key by its whole value

```csharp
public struct Username
{
    public Username(public string value { get; }) { }
}
Map<Username, Order> byUser = [:];                  // compiles: nothing declared

public struct Slot
{
    public Slot(public Date day { get; }, public int hour { get; }) { }
}
Map<Slot, Booking> bookings = [:];                  // compiles: two fields key together

public struct Cart
{
    public Cart(public Customer owner { get; }) { }  // Customer is a class
}
Map<Cart, int> totals = [:];                        // compile error: Cart holds a Customer, a class instance
```

A struct is a value already (section 10), and `==` already compares it field by field (section 19), so its whole value is the key. Nothing is written to make it one. Jordan ruled this on 2026-10-12.

### Chosen: a struct holding a class instance can't key a `Map`

Rust's `HashMap` needs every field of a key to be `Hash`, and Swift's `Dictionary` needs every field to be `Hashable`. Section 19's `Set`, which hashes an object by its class's `hash()`, decides any later extension.

### Chosen: JSON key text comes only from `toString()`

```csharp
public struct Username
{
    public Username(public string value { get; }) { }
    public string toString() => this.value;
}
Json.encode(byUser);                                // {"ada": {…}}: the key text is toString()
Json.encode(bookings);                              // compile error: Slot declares no toString(), so its key has no JSON text
```

A JSON object's key is text, and a struct has no single text of its own. `toString()` names it, so the text a reader sees is the one the code chose.

### Chosen: an enum key is its backing value

```csharp
Map<Status, int> counts = [:];
counts[status] = 1;                                 // compiles; runs: $counts[$status->value] = 1
```

Plain PHP receives the same array it would build by hand.

### Chosen: a key arrives as its type, from the checker's types

```csharp
for (const [status, n] of counts) { … }                      // compiles; status arrives as a Status
for (const [Status status, int n] of counts) { … }           // compiles; writing the type stays allowed
const byStatus = orders.groupBy(o => o.status);              // Map<Status, List<Order>>, inferred
for (const [status, group] of byStatus) { … }                // compiles; status arrives as a Status
```

The stored key is only its value, such as `"open"`. The checker's types reach the running program (decision 29), inferred ones too, so the engine turns the value back into a `Status` for a declared `Map` and an inferred one alike.

### Rejected: a struct declares a backing value with the enum header

```csharp
public struct Username : string
{
    public Username(public string value { get; }) { }
}
Map<Username, Order> byUser = [:];                  // keyed by username.value
```

This was the rule until 2026-10-12. Jordan rejected the header: "I'm not a massive fan of public struct Pair : string". It also made a key of only one field, so a struct of two fields needed a hand-built text key.

### Rejected: a fixed field name, or a one-field limit

```csharp
public struct Username
{
    public Username(public string value { get; }) { }   // only a field named value could key a Map
}
public struct Slot
{
    public Slot(public Date day { get; }, public int hour { get; }) { }
}
Map<Slot, Booking> bookings = [:];                  // compile error under a one-field limit
```

Jordan rejected both on 2026-10-12. Either makes a field's name or count decide whether a value can be a key, which no other rule of a struct depends on.

### Rejected: a key through `toString()`

```csharp
Map<Slot, Booking> bookings = [:];                  // keyed by slot.toString()
for (const [slot, booking] of bookings) { … }       // runs: slot is the text, not a Slot
```

A text key reads back as text, so the `Map` loses the key's type, and two structs that print the same text become one key. `toString()` keeps one job: the JSON text of a key.

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
for (const [key, n] of counts) { … }       // compiles; key is a string, not a Status
```

Every read and write converts by hand, and a key read back loses its type.

## Precedent

- **Chosen, a struct keys by its whole value:** Swift's `Hashable` structs, Rust's `#[derive(Hash, Eq)]`, C#'s record structs, Java's records, Kotlin's data classes and Go's comparable structs.
- **Chosen, a class instance can't key by value:** Rust's `HashMap`, which needs every field to be `Hash`, and Swift's `Dictionary`, which needs every field to be `Hashable`.
- **Chosen, JSON key text from `toString()`:** Jackson, which writes a map key through `toString()`. serde and Go's `encoding/json` refuse a key that is not text.
- **Chosen, an enum key is its backing value:** Swift, whose enums with raw values convert both ways through `rawValue` and `init?(rawValue:)`. Hack, whose enums are their backing values when the code runs, so `dict<Status, int>` works.
- **Chosen, no conversion operators:** Kotlin, Swift and Rust, which have none.
- **Rejected, a user-defined conversion operator:** C#'s `implicit operator`.
- **Rejected, keys limited to `int|string`:** PHP's own arrays, which refuse an enum or an object as a key.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)
- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)
- [Section 20, Enums](../spec.md#20-enums)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)
- [Section 27, PHP# files](../spec.md#27-php-files)

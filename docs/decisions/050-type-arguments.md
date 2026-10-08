# 50. Type arguments

## Decision

A type argument is fixed by the call's arguments or by the declared type the result goes into. A type argument that neither fixes is a compile error. Plain PHP generics, declared with PHPDoc's `@template`, keep their fallback.

## Options

### Chosen: fixed by the arguments or the declared type, and an error otherwise

```csharp
public class Cache
{
    public T get<T>(string key) { … }
}

List<Order> orders = cache.get("orders");               // compiles: the declared type fixes T as List<Order>
const orders = cache.get<List<Order>>("orders");        // compiles: the call names T
inbox.holds(orders, message);                           // compiles: orders fixes TItem as Order
const orders = cache.get("orders");                     // compile error: nothing fixes T; write cache.get<List<Order>>("orders")
```

Generics are reified (decision 7), so every type argument reaches the running program. Each one is a type the code chose, either through an argument, through the declared type, or written out.

### Rejected: an unfixed type argument falls back to its bound

```csharp
public T get<T : Model>(string key) { … }

const order = cache.get("orders");                      // compiles: T falls back to Model
```

PHPStan and Psalm do this for PHPDoc generics. Reified generics would then carry a type nobody chose, and `value is T` would test `Model` when the code runs.

### Rejected: fixed by the arguments only

```csharp
List<Order> orders = cache.get("orders");               // compile error: the arguments do not fix T
List<Order> orders = cache.get<List<Order>>("orders");  // compiles
```

Every call whose type argument appears only in the result names the type twice, once in the declaration and once in the call.

## Precedent

- **Chosen:** C#'s error CS0411, and Swift, which infers a generic parameter from the contextual type of the result.
- **Rejected, fallback to the bound:** PHPStan and Psalm.
- **Rejected, arguments only:** C#, which infers from the arguments and never from the result's declared type.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

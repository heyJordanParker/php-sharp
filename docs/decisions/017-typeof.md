# 17. `typeof(X)`

## Decision

`typeof(X)` is one form. The checker types it `Class<X>`, and plain PHP receives the class-name string.

## Options

### Chosen: one form

```csharp
Class<Order> type = typeof(Order);   // compiles: the checker types it Class<Order>
type.attributes<Listen>();           // compiles
typeof(TItem);                       // compiles: generics are reified
legacy.register(typeof(Order));      // compiles; runs: plain PHP receives "App\Store\Order"
```

### Rejected: a class pointer plus `nameof`

```csharp
Class<Order> type = typeof(Order);   // compiles: a class pointer, for PHP# code
string name = nameof(Order);         // not PHP#: a second form, for the class-name string
legacy.register(typeof(Order));      // compile error: plain PHP takes the string, so write nameof(Order)
```

Every place that passes a class to plain PHP must pick the right form, and the two forms name the same class.

## Precedent

- **Rejected:** Hack, which splits a class into a class pointer and `nameof`.

## Spec

[Section 25, Referring to classes](../spec.md#25-referring-to-classes)

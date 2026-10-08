# 52. `nameof`

## Decision

`nameof(Order.total)` names a property. It is typed, and checked by the compiler. It sits beside `typeof(Order)`. A bare `Order.total` is never a property reference, so `Class.y` always reads a static value.

## Options

### Chosen: `nameof(Order.total)`

```csharp
query.orderBy(nameof(Order.total));         // compiles: a typed reference to Order's total property
query.orderBy(nameof(Order.totl));          // compile error: Order has no property totl
Checkout.maximum;                           // compiles: reads the static maximum
```

A reader sees a property reference at a glance, and `Class.y` keeps one meaning: a static value.

### Rejected: a bare `Order.total` as the reference

```csharp
query.orderBy(Order.total);                 // would compile: a reference to the instance property total
Checkout.maximum;                           // would compile: reads the static maximum
```

This was the old section 6.3. A reader can't tell a static read from a property reference without looking up the class.

### Rejected: a string

```csharp
query.orderBy("total");                     // compiles
```

The compiler never sees a property in a string, so a rename of `total` breaks this call when it runs, and no error says so.

### Rejected: Kotlin's `Order::total`

```csharp
query.orderBy(Order::total);                // parse error: PHP# has no ::
```

PHP# removed `::`, and every member access uses `.` (section 4).

## Precedent

- **Chosen:** C#'s `nameof`.
- **Rejected, a string:** Laravel's `orderBy('total')`.
- **Rejected, `::`:** Kotlin's property references.

## Spec

- [Section 4, Member access](../spec.md#4-member-access)
- [Section 6.3, Typed property references](../spec.md#63-typed-property-references)

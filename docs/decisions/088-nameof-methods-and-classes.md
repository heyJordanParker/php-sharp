# 88. `nameof` of a method and a class

Jordan's ruling of 11-11. It extends [decision 52](052-nameof.md).

## Decision

`nameof(Order.total)` names a property, a field or a method that `Order` declares or inherits, instance or static, readable where `nameof` is written, and gives its name as a string. `nameof(Counted)` gives the class's own name, `"Counted"`. `Class<T>.name()` gives a class's full name with PHP#'s dots, `"App.Orders.Counted"`, as the spec writes class names.

## Options

### Chosen: `nameof` names a method and a class, and `Class<T>.name()` gives the full name

```csharp
Route.get("/orders", nameof(OrdersController.index));        // compiles: "index", checked against OrdersController
nameof(OrdersController.indx);                               // compile error: OrdersController declares or inherits no property, field or method indx
nameof(OrdersController.audit);                              // compile error: audit is private, so nameof can't name it here
nameof(Counted);                                             // "Counted"
Class.of(row).name();                                        // "App.Orders.Counted"
```

A router or an action lookup that takes a method's name as a string gets one the checker checks, so a rename breaks the build instead of the request. A message that names a class reads it from the class itself.

### Rejected: a method reference with a name, as Kotlin's `::index.name`

```csharp
OrdersController.index.name;                                 // OrdersController.index reads a static value, and an instance method has none without an object
```

`OrdersController.index` reads a static value (section 4), so an instance method has no value to take a name from.

### Rejected: PHP's backslashes in `name()`

```csharp
Class.of(row).name();                                        // would be "App\Orders\Counted"
```

Jordan left the separator open. The dots are the form PHP# code and the spec write a class name in, as `"App.RushOrder"` and every PHP# error message write it. A plain PHP library that compares PHP's form takes the class value itself, which reaches it as PHP's class-name string (decision 17).

## Precedent

- **Chosen:** C#'s `nameof`, which names a variable, a type or a member, as in `nameof(List<int>.Add)`, Swift's `#selector`, a compiler-checked method name, and C#'s `Type.FullName`.
- **Rejected:** Kotlin's callable references, and Hack's `nameof`, which names classes alone.

## Spec

- [Section 6.3, Typed property references](../spec.md#63-typed-property-references)
- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

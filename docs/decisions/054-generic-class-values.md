# 54. Class values of generic classes

## Decision

A class value can carry its type arguments or leave them out. `typeof(PaginatedList<Order>)` is a `Class<PaginatedList<Order>>`, and `new (pages)()` creates a `PaginatedList<Order>`. `Class<PaginatedList>` creates with `new (any)<Order>()`. `new (any)()` on an open generic class is a compile error. Generics are reified, with no interim restriction. `new (expr)(args)` is the form for every class value, so section 25's `new type(key)` is written `new (type)(key)`.

## Options

### Chosen: a class value carries its type arguments or leaves them out

```csharp
public class Pages
{
    public PaginatedList<Order> fresh()
    {
        Class<PaginatedList<Order>> pages = typeof(PaginatedList<Order>);
        return new (pages)();                                                       // compiles: a PaginatedList<Order>
    }

    public PaginatedList<Order> ofOrders(Class<PaginatedList> any) => new (any)<Order>();   // compiles: a PaginatedList<Order>
    public Any open(Class<PaginatedList> any) => new (any)();                              // compile error: PaginatedList is generic, so new names its type arguments
}
```

Every object a class value creates has the type arguments reified generics need (decision 7), either from the class value or from `new`.

### Rejected: a class value of a generic class refused by `new`

```csharp
Class<PaginatedList<Order>> pages = typeof(PaginatedList<Order>);
new (pages)();                                                                      // compile error: a class value of a generic class can't be created with new
```

This is an interim state. Generics are reified, so the class value already knows every type argument `new` needs.

### Rejected: class values erased

```csharp
Class<PaginatedList> pages = typeof(PaginatedList<Order>);                         // the type argument is dropped
```

Java's `List.class` works this way. No `PaginatedList<Order>` class value exists, so `new` through it creates an object with no type argument.

## Precedent

- **Chosen:** C#'s `typeof(List<int>)` for a closed generic type and `typeof(List<>)` for an open one.
- **Rejected, erased:** Java's `List.class`.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

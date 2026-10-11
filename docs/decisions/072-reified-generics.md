# 72. Reified generics in the engine

## Decision

The running program carries every type argument, as decisions 7 and 29 require. An object holds its class's type arguments from `new`, through `clone`, serialize and unserialize, and a generic method's own type arguments ride on its call. Plain PHP reads an object's with `ReflectionObject::getTypeArguments()`, and a call from plain PHP into a PHP# method is checked against the parameter's type arguments.

## Options

### Chosen: type arguments ride on the object and on the call

```csharp
const page = new PaginatedList<Order>(rows);      // the object holds the interned App.Order
const first = repository.first<Order>(rows);      // the call hands App.Order to the method
```

```php
(new ReflectionObject($page))->getTypeArguments();   // ['App.Order']
```

Each runtime type is one interned descriptor of its one type text, as `App.PaginatedList<Set<int>>`, which Mago writes and the engine parses. Equal types are one pointer, and plain PHP pays one flag test on a plain object and nothing on a plain call.

### Rejected: class metadata in a per-process table keyed by the class

Opcache serves a class to processes that never compiled it, so a table filled at compile time would be empty there.

### Rejected: an opline between `NEW` and the constructor call

It reaches the new object only through `EX(call)->This`, which a class without a constructor leaves empty.

## Precedent

- **Chosen, the object:** C#, whose runtime builds one type per set of type arguments and keeps it on every object.
- **Chosen, the call:** C#'s shared generic code and Swift's generic functions, which take their type arguments as a hidden argument of the call.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

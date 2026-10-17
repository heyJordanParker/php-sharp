# 72. Reified generics in the engine

## Decision

The running program carries every type argument, as decisions 7 and 29 require. An object holds its class's type arguments from `new`, through `clone`, serialize and unserialize, and a generic method's own type arguments ride on its call. Plain PHP reads an object's with `ReflectionObject::getTypeArguments()`. A value that enters PHP# from plain PHP is checked against its declared type arguments: a call into a PHP# method against the parameter's, a plain PHP call's return against the call's type wherever PHP# takes it, and a write from plain PHP against the property's: in an instance property's `set` hook, and in PHP's own typed property check for a static property, which PHP gives no hooks, through a reference and reflection too.

A method value of a generic method takes its type arguments from the parameter types of the function type it goes into, as C#'s method group conversion does, and one that nothing fixes is a compile error, as C#'s CS0411 is.

A union of two generic classes, or of one class with other type arguments, compiles, since the engine tells them apart. A union is refused where it holds one type twice: a type written twice, two function types, which all run as one closure, or a collection beside an `Iterable` of its element type. Two of `List`, `Map` and `Set` are refused too, because a type is one collection, as in C#, Java, Kotlin and Swift, and a value that may be either kind still runs every method both have (spec section 12).

A call site keeps a type list the request made in its run-time cache, as it keeps one the process holds, because every user run-time cache lives for one request.

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

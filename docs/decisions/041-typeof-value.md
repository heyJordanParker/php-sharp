# 41. The class of a value

## Decision

`typeof` also takes a value. `typeof(order)` gives a `Class<Order>` that holds the object's runtime class, which may be a subclass of `Order`. A bare name inside `typeof` is a local's value when a local with that name is in scope, and a type otherwise, the same rule pattern matching uses. Plain PHP receives the class-name string. It replaces PHP's `$order::class` and `get_class($order)`.

## Options

### Chosen: `typeof(value)`

```csharp
Class<Order> type = typeof(order);              // compiles: order's runtime class, which may be a subclass of Order
const fresh = new type(id);                     // compiles: a new object of that class
Log.info("saved", ["class": typeof(order)]);    // compiles; runs: plain PHP receives the class-name string
```

One keyword gives a class, from a type name or from a value, and both forms give a `Class<T>`.

### Rejected: `getType()` on every object, as in C#

```csharp
Class<Order> type = order.getType();   // not PHP#: C#'s GetType() on every object
node.getType();                        // php-parser's Node declares its own getType(), which returns a string
```

Plain PHP classes already declare their own `getType()`, such as nikic/php-parser's `Node` and Symfony's `ArgumentMetadata`. A method on every object would collide with theirs, and the same call would mean two things.

## Precedent

- **Chosen:** Swift's `type(of:)`, TypeScript's `typeof x` and Python's `type(x)`.
- **Rejected:** C#'s `GetType()`.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

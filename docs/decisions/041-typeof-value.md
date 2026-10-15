# 41. The class of a value

## Decision

`Class.of(value)` gives the class of the object a value holds. For a value of type `Order`, it is a `Class<Order>` that holds the object's runtime class, which may be a subclass of `Order`. `typeof` takes a type only, as a pattern does, so `typeof(order)` on a local is a compile error that names `Class.of(order)`. It replaces PHP's `$order::class` and `get_class($order)`.

## Options

### Chosen: `Class.of(value)`

```csharp
Class<Order> type = Class.of(order);            // compiles: order's runtime class, which may be a subclass of Order
const fresh = new (type)(id);                   // compiles: a new object of that class
Log.info("saved", ["class": Class.of(order)]);  // compiles
typeof(order);                                  // compile error: typeof takes a type; write Class.of(order)
```

`typeof` names a type and `Class.of` reads a value, so a bare name never means one or the other by what is in scope.

### Rejected: `typeof(value)`

```csharp
Class<Order> type = typeof(order);              // would compile: order is a local, so typeof reads its value
typeof(Order);                                  // the class Order, unless a local named Order is in scope
```

A bare name inside `typeof` would be a local's value when a local with that name is in scope, and a type otherwise. Jordan ruled that a pattern takes types and constants only, and `typeof` follows the same rule.

### Rejected: `getType()` on every object, as in C#

```csharp
Class<Order> type = order.getType();   // not PHP#: C#'s GetType() on every object
node.getType();                        // php-parser's Node declares its own getType(), which returns a string
```

Plain PHP classes already declare their own `getType()`, such as nikic/php-parser's `Node` and Symfony's `ArgumentMetadata`. A method on every object would collide with theirs, and the same call would mean two things.

## Precedent

- **Chosen:** Swift's `type(of:)` and Python's `type(x)`.
- **Rejected, `typeof(value)`:** TypeScript's `typeof x`.
- **Rejected, `getType()`:** C#'s `GetType()`.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

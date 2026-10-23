# 35. Object

Superseded in part by [decision 83](083-php-through-the-standard-library.md): `global.` does not exist in PHP# code, so the code below reaches `spl_object_id` through a project's plain PHP class, as spec section 24 writes `Handles.of(value)`.

## Decision

`Object` holds any object, and nothing else. It compiles to PHP's `object`, and `Object?` adds null. It has no members until `is` narrows it, it passes where `Any` is expected, and a plain PHP value typed `object` arrives as `Object`.

## Options

### Chosen: `Object`

```csharp
public static int identity(Object value) => global.spl_object_id(value);   // compiles
public static int identityOf(Any value) => global.spl_object_id(value);    // compile error: Any may hold an int, and spl_object_id takes an object
Ids.identity(5);                                                    // compile error: 5 is not an object
```

It names the set PHP already checks when the code runs, so a signature tells plain PHP callers the truth.

### Rejected: `AnyObject`

```csharp
public static int identity(AnyObject value) => global.spl_object_id(value);   // not PHP#: Swift's name
```

PHP developers already write `object`, and `AnyObject` is a new word for the same set.

## Precedent

- **Chosen:** TypeScript's `object` beside `unknown`.
- **Rejected:** Swift's `AnyObject` beside `Any`.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

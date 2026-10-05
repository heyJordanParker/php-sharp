# 15. Union types

## Decision

A union type is written inline, such as `int|string`, anywhere a type goes.

## Options

### Chosen: inline unions

```csharp
public User find(int|string id)
{
    if (id is int) { return User.byNumber(id); }   // compiles: is narrows id to int
    return User.bySlug(id);                         // compiles: id is a string here
}
```

The union compiles to PHP's own union type, so plain PHP sees the same signature.

### Rejected: named unions only

```csharp
public union Id = int|string;              // not PHP#: a union declared once, by name
public User find(Id id) { … }              // compiles
public User find(int|string id) { … }      // compile error: a union needs a name
```

Plain PHP writes its unions inline, such as `int|string`, so every union PHP# reads from plain PHP would need a name first.

### Rejected: no unions

```csharp
public User find(Any id) { … }             // compiles; the checker no longer knows id is an int or a string
public User findByNumber(int id) { … }     // or one method per type
public User findBySlug(string id) { … }
```

A plain PHP parameter or return typed `int|string` could not be written in PHP#.

## Precedent

- **Chosen:** PHP 8, TypeScript, Python and Scala 3.
- **Rejected, named unions only:** Hack's case types.
- **Rejected, no unions:** Rust, Swift, Kotlin and C#.

## Spec

[Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

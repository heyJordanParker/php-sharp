# 12. Equality

## Decision

`==` exists only where a type declares it. Structs, enums, strings, numbers and collections compare by value, and `===` means the same object.

## Options

### Chosen: `==` only where the type declares it

```csharp
public abstract class DatabaseEntity
{
    public static bool operator ==(DatabaseEntity a, DatabaseEntity b) => a.id == b.id;
    public int hash() => this.id;
}

order == sameOrderLoadedAgain;   // compiles; runs: Order inherits DatabaseEntity's ==, so this compares ids
cart == otherCart;               // compile error: Cart declares no ==
cart === cart;                   // compiles; runs: true, the same object
point == otherPoint;             // compiles; runs: structs compare by value
```

### Rejected: PHP's property-by-property `==`

```csharp
cart == otherCart;               // compiles; runs: compares every property, recursing into the objects they hold
order == otherOrder;             // compiles; runs: throws Error "Nesting level too deep - recursive dependency?"
                                 // when each order holds lines that point back at it
```

Two objects are equal when their properties happen to match, which is rarely what a class means, and objects that link to each other crash the comparison.

### Rejected: identity unless overridden

```csharp
cart == otherCart;               // compiles; runs: false unless both are the same object, even when their contents match
lines.contains(line);            // compiles; runs: compares identity when Line forgot to override ==
```

A forgotten override compiles and gives a wrong answer at runtime.

## Precedent

- **Chosen:** Swift's `Equatable` and Rust's `PartialEq`.
- **Rejected, property by property:** PHP.
- **Rejected, identity unless overridden:** C#, Java, Kotlin, Python and JavaScript.

## Spec

[Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)

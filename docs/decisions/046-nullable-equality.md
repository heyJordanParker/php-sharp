# 46. `==` on nullable types

## Decision

`==` and `!=` on a nullable type are lifted. Null equals only null, and two non-null values use the type's own `==`, including a declared `operator ==`. A class's `operator ==` takes two non-null values, so it never sees a null. `x != null` is the normal null check and never runs user code. `x is null` and `x is not null` stay valid as ordinary patterns.

## Options

### Chosen: lifted `==` and `!=`

```csharp
Money? price = null;
price == null;                   // compiles; runs: true, without running Money's ==
price == Money.zero;             // compiles; runs: false, without running Money's ==
total == Money.zero;             // compiles; runs: Money's ==, because total is a Money
if (price != null) { … }         // compiles: the normal null check
if (price is not null) { … }     // compiles: an ordinary pattern
```

The null check every PHP, C#, Java, Kotlin and TypeScript programmer writes works as it reads, and an `operator ==` is written for two real values.

### Rejected: `is null` and `is not null` as the only null check

```csharp
if (price != null) { … }         // compile error: write price is not null
if (price is not null) { … }     // compiles
```

Every null check needs a pattern, and the familiar `!= null` becomes an error. The pattern form exists to keep a user-defined `==` away from null, and lifting already does that.

## Precedent

- **Chosen:** C#'s lifted operators on nullable values, and Kotlin's `==`, which checks for null before it calls `equals`.
- **Rejected:** Python's PEP 8 rule that comparisons to `None` use `is` and `is not`, and C#'s style analyzers that prefer `is null`.

## Spec

- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)
- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

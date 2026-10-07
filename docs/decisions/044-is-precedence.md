# 44. Where `is` and `as` bind

## Decision

`is` and `as` bind with the relational comparisons `<`, `<=`, `>` and `>=`, as in C#, not in PHP's `instanceof` place above `!`. So `!entity is HasDesign` parses as `(!entity) is HasDesign`, which is a compile error, because `!` takes a `bool`. The error reads "write entity is not HasDesign". A negative test is written `is not`, as section 21 already has it.

## Options

### Chosen: C#'s place, with the comparisons

```csharp
if (entity is not HasDesign) { … }       // compiles
if (!entity is HasDesign) { … }          // compile error: write entity is not HasDesign
```

A negative test has one spelling, and a `!` in front of a type test is an error whose message names the fix.

### Rejected: PHP's `instanceof` place

```csharp
if (!entity is HasDesign) { … }          // not PHP#: means !(entity is HasDesign)
```

The line reads two ways, as "not (entity is HasDesign)" and as "(not entity) is HasDesign", and only the precedence table says which one runs.

### Rejected: Kotlin's `!is`

```csharp
if (entity !is HasDesign) { … }          // not PHP#: Kotlin's !is
```

`!is` would be a second spelling of `is not`, which section 21 already uses, including to create a variable (decision 20).

## Precedent

- **Chosen:** C#'s `is` and `is not`, which bind with the relational operators.
- **Rejected:** PHP's `instanceof`, which binds tighter than `!`, and Kotlin's `!is`.

## Spec

- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)
- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

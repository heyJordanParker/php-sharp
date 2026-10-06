# 32. Bitwise operators

## Decision

`|`, `&`, `^`, `~`, `<<`, `>>` and their compound forms take `int` only. A condition must be a `bool`, so `if (permissions & WRITE)` is a compile error, and `isAdmin | isOwner` is a compile error that names `||`. A shift by a negative count throws `ArithmeticError`. The bitwise operators bind tighter than comparisons, as in Go, Rust and Swift, so `permissions & WRITE != 0` means `(permissions & WRITE) != 0`.

## Options

### Chosen: `int` only, with the bitwise operators above comparisons

```csharp
const READ = 1;
const WRITE = 2;
let permissions = READ | WRITE;               // compiles
if (permissions & WRITE != 0) { … }           // compiles: means (permissions & WRITE) != 0
if ((permissions & WRITE) != 0) { … }         // compiles: the same meaning
if (permissions & WRITE) { … }                // compile error: int is not bool
const shown = isAdmin | isOwner;              // compile error: | takes int; write ||
```

A flag test reads the way it is said, with no parentheses. PHP# binds `&`, `^` and `|` differently from PHP and C#, so a PHP expression that relied on C's order changes meaning. In practice, those expressions are the bug that C's order causes.

### Rejected: `int` only, with C's order

```csharp
if (permissions & WRITE != 0) { … }     // compile error: & takes int, and WRITE != 0 is a bool; write (permissions & WRITE) != 0
if ((permissions & WRITE) != 0) { … }   // compiles
```

Every operator binds as in PHP and C#, and the type rules turn the misparse into a compile error. Every flag test then needs its parentheses.

## Precedent

- **Chosen:** Go, Rust and Swift, which all put `&` above `==`. PHP#'s full table keeps PHP's order everywhere else, which is Rust's order for these operators.
- **Rejected, C's order:** C#, Java, TypeScript, PHP and Hack.

## Spec

- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)
- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

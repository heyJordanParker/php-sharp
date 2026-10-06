# 45. `not` beside `or`

## Decision

In a pattern, `not` beside `or` needs parentheses, in `is` and in `match` arms alike. `result is not Paid or Refunded` is a compile error that reads "write `not (Paid or Refunded)`, or `(not Paid) or Refunded`". `result is not (Paid or Refunded)` compiles. `not` beside `and` needs no parentheses, because it reads the way it binds: `code is not null and not ""` means `(not null) and (not "")`.

## Options

### Chosen: parentheses for `not` beside `or`

```csharp
if (result is not Paid or Refunded) { … }      // compile error: write not (Paid or Refunded), or (not Paid) or Refunded
if (result is not (Paid or Refunded)) { … }    // compiles: neither Paid nor Refunded
if (result is (not Paid) or Refunded) { … }    // compiles: the reading C# gives the first line
if (code is not null and not "") { … }         // compiles: (not null) and (not "")
```

The line that reads two ways does not compile, and the error names both readings. The line that reads one way, with `and`, compiles as it reads.

### Rejected: C#'s binding, `not` tighter than `or`

```csharp
if (result is not Paid or Refunded) { … }      // not PHP#: means (not Paid) or Refunded
```

The line reads as "neither Paid nor Refunded", but it runs as "not Paid", because every `Refunded` result is already not `Paid`. The `or Refunded` does nothing, and a warning is the most C# gives.

## Precedent

- **Chosen:** C#'s compiler, which since Visual Studio 2022 17.13 warns that a pattern after `or` is redundant (CS9336) when `not` binds first. PHP# makes the case an error.
- **Rejected:** C# 9's pattern combinators, where `not` binds tighter than `or` with no parentheses needed.

## Spec

- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

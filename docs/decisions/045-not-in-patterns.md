# 45. `not` beside `or`

Superseded: [section 21, Pattern matching](../spec.md#21-pattern-matching) holds the current rule, from the spec refusal audit's ruling of 11-14: `not` binds tighter than `or` and `and`, so `result is not Paid or Refunded` compiles and means `(not Paid) or Refunded`, and `not (Paid or Refunded)` puts `not` over the whole `or`. The rest of this record is the decision as it stood before.

## Decision

In a pattern, `not` beside `or` needs parentheses, in `is` and in `match` arms alike. `result is not Paid or Refunded` is a compile error that reads "write `not (Paid or Refunded)`, or `(not Paid) or Refunded`". `result is not (Paid or Refunded)` compiles. `not` beside `and` needs no parentheses, because it reads the way it binds: `code is not null and not ""` means `(not null) and (not "")`.

## Options

### Chosen: parentheses for `not` beside `or`

```csharp
if (result is not Paid or Refunded) { … }      // compile error: write not (Paid or Refunded), or (not Paid) or Refunded
if (result is not (Paid or Refunded)) { … }    // compiles: neither Paid nor Refunded
if (result is (not Paid) or Refunded) { … }    // compiles: the first line, with not binding first
if (code is not null and not "") { … }         // compiles: (not null) and (not "")
```

The line that reads two ways does not compile, and the error names both readings. The line that reads one way, with `and`, compiles as it reads.

### Rejected: `not` tighter than `or`

```csharp
if (result is not Paid or Refunded) { … }      // not PHP#: means (not Paid) or Refunded
```

The line reads as "neither Paid nor Refunded", but it runs as "not Paid", because every `Refunded` result is already not `Paid`. The `or Refunded` does nothing, and at most a warning would say so.

## Spec

- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

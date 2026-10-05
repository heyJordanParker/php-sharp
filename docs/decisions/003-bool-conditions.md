# 3. Conditions are `bool`

## Decision

Every condition must be a `bool`, so PHP's truthiness never applies.

## Options

### Chosen: a condition must be a `bool`

```csharp
if (items.count()) { … }              // compile error: int is not bool
if (items.count() > 0) { … }          // compiles
if (request.input("flag")) { … }      // compile error: string is not bool
if (request.input("flag") == "1") { … }   // compiles
```

### Rejected: PHP's truthiness

```csharp
if (items.count()) { … }              // compiles; runs the block when the count is not 0
if (request.input("flag")) { … }      // compiles; skips the block for "", "0" and null, runs it for "false"
if (order.discount) { … }             // compiles; skips the block for 0, 0.0 and null alike
```

Truthiness makes `"0"` false and `"false"` true, and puts 0 and null in one branch. The checker cannot tell a count test from a null test.

## Precedent

- **Chosen:** C# and Swift. Hack accepts truthiness and its linter reports it.
- **Rejected:** PHP.

## Spec

[Section 21, Pattern matching](../spec.md#21-pattern-matching)

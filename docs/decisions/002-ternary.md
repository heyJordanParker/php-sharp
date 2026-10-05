# 2. The ternary

## Decision

The ternary is `c ? a : b` only. PHP's two-operand `a ?: b` is gone, because `??` covers null.

## Options

### Chosen: `c ? a : b` and `??`

```csharp
const label = order.paid ? "Paid" : "Open";   // compiles: order.paid is a bool
const name = user.nickname ?? user.email;     // compiles; runs: email only when nickname is null, so "" stays ""
const name = user.nickname ?: user.email;     // compile error: PHP# has no ?:
```

### Rejected: keep PHP's `a ?: b`

```csharp
const name = user.nickname ?: user.email;     // compiles; runs: email when nickname is null, "" or "0"
const shown = user.isAdmin ?: user.isOwner;   // compiles; runs: the same as user.isAdmin || user.isOwner
```

`?:` tests truthiness, so it replaces `""` and `"0"` along with null. Conditions are `bool` in PHP# ([decision 3](003-bool-conditions.md)), so on a `bool` it only repeats `||`.

## Precedent

- **Chosen:** C#, which has `c ? a : b` and `??` and no `?:`.
- **Rejected:** PHP.

## Spec

[Section 21, Pattern matching](../spec.md#21-pattern-matching)

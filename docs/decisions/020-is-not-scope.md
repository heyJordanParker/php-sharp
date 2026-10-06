# 20. Variables created by `is not`

## Decision

A variable created by `is not` stays in scope after an `if` whose block always exits, as in C#. A block always exits when every path through it ends in `return`, `throw`, `break` or `continue`. No new syntax.

## Options

### Chosen: the variable outlives an `if` that always exits

```csharp
public Receipt checkout(Map<string, Any?> payload, string plan)
{
    if (payload["orderId"] is not int orderId) { throw new BadPayload("orderId"); }
    if (prices[plan] is not int price) { return Receipt.unknownPlan(plan); }
    return charge(orderId, price);                          // orderId and price are both known here
}
```

Each check leaves early, and the code after it reads the variable at the top level of the method.

### Rejected: the variable ends with its `if`

```csharp
public Receipt checkout(Map<string, Any?> payload, string plan)
{
    if (payload["orderId"] is int orderId) {
        if (prices[plan] is int price) {
            return charge(orderId, price);
        }
        return Receipt.unknownPlan(plan);
    }
    throw new BadPayload("orderId");
}
```

Each check nests the rest of the method one level deeper, and each failure is handled far from its check.

## Precedent

- **Chosen:** C#'s `is not` with definite assignment. Swift's `guard let` and Rust's `let … else` give the same result with a statement of their own.

## Spec

- [Section 3, Scope](../spec.md#3-scope)
- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

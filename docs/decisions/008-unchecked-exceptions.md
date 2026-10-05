# 8. Exceptions are not declared

## Decision

Methods declare no thrown exceptions. The analyzer's `check_throws`, which reports exceptions missing from a `@throws` tag, skips `.sharp` files.

## Options

### Chosen: no declared exceptions

```csharp
public Order load(int id)
{
    const row = this.rows.find(id) ?? throw new OrderMissing(id);   // compiles: no throws clause
    return new Order(row);
}

const order = this.orders.load(id);   // compiles: the caller writes no catch and no declaration

public Result<Order, PaymentError> charge(Cart cart) { … }   // an expected failure is in the return type
```

An exception means a bug. A failure the caller must handle is a `Result`, so the return type already says it.

### Rejected: checked exceptions

```csharp
public Order load(int id) throws OrderMissing { … }   // not PHP#: a throws clause

const order = this.orders.load(id);   // compile error: catch OrderMissing or declare it
```

Every caller up the stack repeats the clause or wraps the exception. A function passed to `map` cannot throw at all unless `map` has a second copy ([decision 6](006-function-argument-effects.md)).

## Precedent

- **Chosen:** C# and Kotlin. Swift's `throws` names no exception type unless the code opts in.
- **Rejected:** Java's checked exceptions.

## Spec

[Section 31, Expected failures as values](../spec.md#31-expected-failures-as-values)

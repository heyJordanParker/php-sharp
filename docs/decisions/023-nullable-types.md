# 23. Nullable types

## Decision

A type holds null only when it is written with `?`, for parameters, return types, properties and locals alike: `Customer?` may hold null, and `Customer` never does. A `?` or a null check that cannot matter is a compile error, because it misstates the code:

- a null check, `?.` or `??` on a value whose type has no `?`
- a nullable parameter that the method rejects on every path
- a nullable return type on a method that never returns null

## Options

### Chosen: `?` marks every nullable type, and a `?` that cannot matter is a compile error

```csharp
public void renew(Customer customer, Plan plan)
{
    if (customer != null) { … }                     // compile error: customer is Customer, so it can never be null
    int price = plan.price ?? 0;                     // compile error: plan.price is int, so ?? never applies
}
public void notify(Customer? customer)
{
    Customer c = customer ?? throw new NotFound();   // compile error: notify rejects null on every path; declare it Customer and check at the caller
}
public Customer? current() { return this.customer; } // compile error: current never returns null, so its type is Customer
```

Each type says whether null can reach it, so a reader can trust the types.

### Rejected: the same checks as warnings

```csharp
public void renew(Customer customer, Plan plan)
{
    if (customer != null) { … }   // compiles; warns: customer can never be null
}
```

The code compiles and still misstates what can be null, and the next reader copies the needless check.

## Precedent

- **Chosen, the rule:** Kotlin, Swift, TypeScript's `strictNullChecks`, and C#'s nullable reference types.
- **Chosen, a check on a value that is never null:** typescript-eslint's `no-unnecessary-condition`, Psalm's `RedundantCondition`, and Kotlin's warnings on a useless `?.` or `?:`.
- **Chosen, a nullable parameter rejected on every path:** IntelliJ's data-flow inspections. No compiler has this error.
- **Chosen, a nullable return that is never null:** Kotlin's "Redundant nullable return type" inspection.
- **Rejected, warnings:** the linters, inspections and Kotlin warnings named for each error, which all leave the code compiling.

## Spec

- [Section 14.4, Null operators](../spec.md#144-null-operators)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

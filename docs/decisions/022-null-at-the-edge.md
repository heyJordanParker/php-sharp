# 22. Null at the edge

## Decision

Null is checked once, where outside data enters, such as a controller. Business methods take non-null types, such as `Customer` instead of an id and a lookup, and a fixed set of keys is an enum. This is guidance, with no syntax.

## Options

### Chosen: resolve each value once at the edge

```csharp
public class Billing
{
    public Receipt renew(Customer customer, Plan plan) { … }   // no null checks: both are known
}

public class RenewalController
{
    public Receipt renew(int customerId, string planCode)
    {
        Customer customer = Customer.find(customerId) ?? throw new NotFound("customer");
        Plan plan = Plan.tryFrom(planCode) ?? throw new NotFound("plan");
        return this.billing.renew(customer, plan);
    }
}
```

The controller turns outside data into types once. Every method behind it receives values that exist.

### Rejected: an id and a lookup in each business method

```csharp
public class Billing
{
    public Receipt renew(int customerId, string planCode)
    {
        Customer customer = Customer.find(customerId) ?? throw new NotFound("customer");
        int price = prices[planCode] ?? throw new UnknownPlan(planCode);
        …
    }
}
```

Each method repeats the lookup and the null check, and each caller can pass an id that matches nothing.

## Precedent

- **Chosen:** "parse, don't validate" from Haskell, Elm's decoders, Rust's `serde`, Swift's `Codable`, TypeScript's `zod`, and Laravel's route model binding.

## Spec

[Section 14.5, Null at the edge](../spec.md#145-null-at-the-edge)

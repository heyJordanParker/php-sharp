# 21. Leaving early after `??`

## Decision

`??` may be followed by `return`, `continue` or `break`, as it already may by `throw`. `return` takes a value when the method returns one.

## Options

### Chosen: `return`, `continue` and `break` after `??`

```csharp
public void renewAll(List<int> customerIds, string plan)
{
    int price = this.prices[plan] ?? return;                // no such plan: nothing to renew
    for (const id of customerIds) {
        Customer customer = this.customers[id] ?? continue; // skip ids with no customer
        this.charge(customer, price);
    }
}
```

Every early exit has the same form as `?? throw`, on the line that reads the value.

### Rejected: only `?? throw`

```csharp
public void renewAll(List<int> customerIds, string plan)
{
    if (this.prices[plan] is not int price) { return; }
    for (const id of customerIds) {
        if (this.customers[id] is not Customer customer) { continue; }
        this.charge(customer, price);
    }
}
```

`throw` reads the value on one line, and every other early exit needs an `if`.

### Rejected: a block that runs only when the value is not null

```csharp
this.customers[id]?.let(customer => this.charge(customer, price));   // not PHP#: a let block after ?.
```

This is a second way to do what `is Type name` already does.

## Spec

[Section 14.4, Null operators](../spec.md#144-null-operators)

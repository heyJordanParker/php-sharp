# 19. Reading a Map

## Decision

`map[key]` has the type `TValue?`, so code handles a missing key at the read. A `List` read past the end throws `OutOfRangeException`, and so does a write past the end. Appending is `add`.

## Options

### Chosen: a read gives `TValue?`

```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices["pro"];                                  // compile error: prices["pro"] is int?, not int
int price = prices["pro"] ?? 0;                             // compiles: 0 when "pro" is missing
int price = prices[plan] ?? throw new UnknownPlan(plan);    // compiles: throws when plan is missing
if (prices[plan] is int price) { charge(price); }           // compiles: runs only when plan is present
```

Data with fixed keys is a class, so a `Map` holds keys that come from outside, where a missing key is normal. The type makes every read say what happens then.

### Rejected: a read gives `TValue` and throws when the key is missing

```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices[plan];   // compiles; runs: throws when plan is missing
```

Nothing at the read shows that a key can be missing, so the normal case for a `Map` surfaces as an exception at runtime.

## Precedent

- **Chosen:** Swift's `Dictionary`, Kotlin's `Map`, and TypeScript with `noUncheckedIndexedAccess`.
- **Rejected:** C#'s `KeyNotFoundException` and Hack's `dict`.

## Spec

[Section 12, Collections](../spec.md#12-collections)

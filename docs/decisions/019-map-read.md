# 19. Reading a Map

## Decision

A `Map` read is handled where it is read, with `??`, `?.`, `is`, `as`, `match` or `get`, so code handles a missing key at the read. A bare `map[key]` is a compile error that names `??` and `get`, and `get` gives `TValue?`. A bare `x[i]` throws `OutOfRangeException` on a missing index or key, and `set` throws it past the end of a `List`. Appending is `add`. Decision 26 gives every collection operation one meaning on `List` and `Map`.

## Options

### Chosen: a read gives `TValue?`

```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices["pro"];                                  // compile error: handle a missing "pro" with ??, or read it with get
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

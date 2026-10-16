# 33. Iterable

## Decision

`Iterable<T>` is anything a loop can read, and it compiles to PHP's `iterable`. `List<T>` and `Set<T>` are `Iterable<T>`, and a `Map<TKey, TValue>` is an `Iterable<[TKey, TValue]>` of its entries, as a loop over it reads them, and `values()` gives its values. It has lazy operations, such as `filter`, `map` and `take`, that run only when `toList()` or a loop reads the result. A lazy plain PHP source is checked element by element as it is read.

A PHP# `Iterable` carries its element type while the code runs, as every PHP# generic does, so `is Iterable<Order>` reads no element. A plain PHP `Traversable` carries none, so it is only an `Iterable<Any?>`.

## Options

### Chosen: `Iterable<T>` with lazy operations

```csharp
public int tally(Iterable<Order> orders)
{
    let n = 0;
    for (const order of orders) { n += 1; }   // compiles
    return n;
}

Iterable<Order> orders = Order.query().cursor();
List<string> numbers = orders.filter(o => o.paid).map(o => o.number).take(100).toList();   // compiles; runs: reads rows only until it has 100
```

A lazy source stays lazy through a chain of operations, so a query over millions of rows reads only what the chain needs. Each element is still checked where it enters PHP# (decision 7).

### Rejected: an `Iterable` that only a loop reads

```csharp
Iterable<Order> orders = Order.query().cursor();
List<string> numbers = [];
for (const order of orders) {                 // compiles; every chain is written as a loop by hand
    if (!order.paid) { continue; }
    numbers.add(order.number);
    if (numbers.count() == 100) { break; }
}
orders.filter(o => o.paid);                   // compile error: an Iterable has no filter
```

Every `filter`, `map` and `take` over a lazy source becomes a loop written by hand, or a copy into a `List` that reads every row first.

## Precedent

- **Chosen:** C#'s `IEnumerable<T>` with LINQ, and Kotlin's `Sequence<T>`, whose operations run only when a terminal operation reads them.
- **Chosen, a `Map` as the `Iterable` of its entries:** C#'s `Dictionary<TKey, TValue>`, an `IEnumerable<KeyValuePair<TKey, TValue>>`, and Swift's `Dictionary`, a `Sequence` of `(key, value)` tuples.
- **Chosen, a carried element type:** C#'s reified `is IEnumerable<Order>`, which reads no element.
- **Rejected, loop only:** Java's `Iterable<T>`, TypeScript's `Iterable<T>` and Hack's `Traversable<T>`, which a loop reads and which carry no lazy operations of their own.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)

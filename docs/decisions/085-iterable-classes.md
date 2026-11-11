# 85. Iterable classes

Jordan's ruling of 11-11.

## Decision

A class is an `Iterable<T>` when its header names `Iterable<T>` and it declares `Iterable<T> elements()`, whose body returns an `Iterable` or uses `yield`. A loop, `toList()` and the lazy operations read `elements()`, and the object is an `Iterable<T>` everywhere one is taken. A class that names `Iterable<T>` and declares no `elements()` is a compile error that names the member. Plain PHP receives the object as a `Traversable` whose `foreach` reads `elements()`: an element that is a `[key, value]` tuple arrives as that key and value, as a `Map` reaches plain PHP as the array of its entries, and any other element arrives under the keys 0, 1, 2 and on.

Only a class names `Iterable<T>` in its header. The engine links the class as PHP's `IteratorAggregate`, whose `getIterator()` PHP# writes, so the class declares no `getIterator()` of its own.

## Options

### Chosen: the class names `Iterable<T>` and declares `elements()`

```csharp
public class Bag<TItem> : Iterable<TItem>
{
    private List<TItem> items = [];

    public void add(TItem item) { this.items.add(item); }

    public Iterable<TItem> elements() => this.items;
}

for (const order of bag) { … }                          // compiles: a Bag<Order> is an Iterable<Order>
bag.filter(o => o.paid).toList();                       // compiles: Iterable<T>'s lazy operations
report.tally(bag);                                      // compiles: tally takes an Iterable<Order>
public class Crate : Iterable<int> { }                  // compile error: Crate is an Iterable<int> and declares or inherits no elements()
```

```php
$bag instanceof Traversable;                // true
foreach ($bag as $i => $order) { }          // $i is 0, 1, 2 and on
```

The object carries its element type, as every PHP# generic does, so `bag is Iterable<Order>` reads no element (decision 33). The one member a class writes says what a loop reads and names no PHP type PHP# hides.

### Rejected: PHP's `IteratorAggregate` among the root classes

```csharp
public class Bag : IteratorAggregate
{
    public Traversable getIterator() => …;    // Traversable is no type PHP# names, and it carries no element type
}
```

`getIterator()` returns a `Traversable`, which PHP# code can't name (decision 83) and which carries no element type, so a loop over the object reads only `Any?` (decision 33).

## Precedent

- **Chosen:** C#'s `IEnumerable<T>` with `GetEnumerator()` and `yield return`, Java's `Iterable<T>.iterator()`, Swift's `Sequence.makeIterator()` and Rust's `IntoIterator`.
- **Rejected:** Hack's `HH\Iterable`, PHP's `IteratorAggregate` given an element type.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)

# 42. `yield`

## Decision

A method that returns `Iterable<T>` may produce its values lazily with `yield value;`, and each yielded value is checked against `T`. `yield ...other;` produces every element of another `Iterable<T>`. It replaces PHP's `yield from` and reuses PHP#'s spread. The method compiles to a PHP generator. Its body runs only when a loop or `toList()` reads the result, so an error inside it surfaces at the loop. PHP's `send()` and two-way generators are not part of PHP#, and async is a separate future design.

## Options

### Chosen: `yield` in a method that returns `Iterable<T>`

```csharp
import Illuminate.Support.Facades.File;

public class OrderImport
{
    public OrderImport(private List<Order> pending, private Order latest) { }

    public Iterable<List<string>> rows(string path)
    {
        for (const string line of File.lines(path)) {
            yield line.parseCsv();                   // compiles: each row is a List<string>
        }
    }

    public Iterable<Order> all()
    {
        yield ...this.pending;                       // compiles: every pending order, in order
        yield this.latest;                           // compiles: then the latest one
    }

    public List<List<string>> preview() => this.rows("orders.csv").take(100).toList();   // runs: reads only the first 100 lines
}
```

A lazy source is one ordinary method, written in the order its values come out. The lazy operations of `Iterable<T>` (decision 33) work on it unchanged.

### Rejected: a hand-written `Iterator` class

```csharp
public class CsvRows : Iterator<List<string>>   // not PHP#: Rust's and Java's Iterator
{
    public CsvRows(string path) { … }            // opens the file and keeps the position in a field
    public List<string>? next() { … }            // reads one line, parses it and moves the position
}
```

Every lazy source becomes a class that keeps its position in fields between `next()` calls. The code no longer reads in the order the values come out.

## Precedent

- **Chosen:** C#'s `yield return` iterators, and Python's and JavaScript's generators.
- **Rejected:** Rust's `Iterator` and Java's `Iterator`, each a type with `next()`.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)

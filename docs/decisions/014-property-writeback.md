# 14. Changing a value held in a property

## Decision

Changing a collection or struct held in a property writes it back through the property's `set`.

## Options

### Chosen: write back through `set`

```csharp
public class Order
{
    public List<Line> lines { get; private set; } = [];
    public Point origin { get; set; }

    public void add(Line line) { this.lines.add(line); }   // compiles; runs: reads lines, changes the copy, writes it back through the private set
    public void shift() { this.origin.x = 5; }             // compiles; runs: the same, through origin's set
}

order.lines.add(line);    // compile error outside Order: lines has a private set
let copy = order.lines;
copy.add(line);           // compiles; runs: changes only copy
```

The `set` still decides who may change the property, so `{ get; private set; }` keeps outside code from changing the collection.

### Rejected: a compile error

```csharp
this.origin.x = 5;        // compile error: origin returns a copy

let origin = this.origin;
origin.x = 5;
this.origin = origin;     // compiles: three lines for one change
```

### Rejected: a runtime error (PHP's hooked properties)

```csharp
public void add(Line line) { this.lines.add(line); }   // compiles; runs: throws Error "Indirect modification of Order::$lines is not allowed"
```

The checker accepts a change that always fails when it runs.

## Precedent

- **Chosen:** Swift.
- **Rejected, a compile error:** C#'s error CS1612.
- **Rejected, a runtime error:** PHP 8.5's hooked properties. `$order->lines[] = $line` on a hooked array property throws `Error: Indirect modification of Order::$lines is not allowed`.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 13, `readonly`](../spec.md#13-readonly)

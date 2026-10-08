# 51. `readonly`

## Decision

`readonly` before a type means the value can only be read. `readonly` after a method's parameters means the method doesn't change `this`. `readonly` on a field, a promoted constructor parameter or a class is a compile error that names `{ get; }`, so `private readonly Money total` is one. `readonly struct` is a compile error too, because every struct is already read-only (decision 64). Any plain PHP method can be called through a readonly value, because PHP# checks PHP# code only.

## Options

### Chosen: `readonly` marks values and methods, and plain PHP methods stay callable

```csharp
import Illuminate.Database.Eloquent.Model;

public class Order : Model
{
    public string number { get; set; }
    public Money totalIn(string currency) readonly { … }
    public void markPaid() { … }
}

public void render(readonly Order order)
{
    order.totalIn("USD");                               // compiles: totalIn is marked readonly
    order.markPaid();                                   // compile error: markPaid is not marked readonly
    order.save();                                       // compiles: save is a plain PHP method of Model
}

public class Invoice
{
    private readonly Money total;                       // compile error: declare a get-only property with { get; }
}

public readonly struct Size { … }                       // compile error: every struct is already read-only; write struct
```

`readonly` has one meaning: this value, or `this`, is only read. A get-only property already stops reassignment, so storage needs no second keyword.

### Rejected: PHP's write-once `readonly` field and `readonly class`

```csharp
public class Invoice
{
    public Invoice(public readonly Money total) { }     // would compile: total is written once
}

public readonly class Receipt { … }                     // would compile: every property is written once
```

One keyword would mean two things: a value that can only be read, and a field that can be written once. `{ get; }` already does the second job.

### Rejected: unmarked plain PHP methods refused through a readonly value

```csharp
public void render(readonly Order order)
{
    order.getKey();                                     // compile error: getKey is not marked readonly
}
```

PHP has no marker for a method that doesn't change its object, so every plain PHP call through a readonly value would fail, `getKey()` included.

## Precedent

- **Chosen:** Kotlin's platform types, which leave Java code unchecked, for plain PHP methods.
- **Rejected, write-once fields:** PHP 8.1's `readonly` properties and PHP 8.2's `readonly` classes.

## Spec

- [Section 13, `readonly`](../spec.md#13-readonly)

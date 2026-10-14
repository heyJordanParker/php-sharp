# 51. `readonly`

## Decision

`readonly` before a type means the value can only be read. `readonly` after a method's parameters means the method doesn't change `this`, and it is the method's last clause, after `on` and `uses`. Operators, `hash()` and `toString()` are readonly by definition. `readonly` holds only types PHP# checks, so a plain PHP class under it is a compile error, and so is a call of a plain PHP method through a readonly value. `readonly` on a field, a promoted constructor parameter or a class is a compile error that names `{ get; }`, so `private readonly Money total` is one. `readonly struct` is a compile error too, because every struct is already read-only (decision 64).

## Options

### Chosen: `readonly` marks values and methods, and holds only what PHP# checks

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
    order.save();                                       // compile error: save is a plain PHP method of Model, which PHP# does not check
}

public void show(readonly Model model) { … }           // compile error: Model is a plain PHP class

public class Invoice
{
    private readonly Money total;                       // compile error: declare a get-only property with { get; }
}

public readonly struct Size { … }                       // compile error: every struct is already read-only; write struct
```

`readonly` has one meaning: this value, or `this`, is only read. A promise PHP# can't check is no promise, so it covers only PHP# code. A get-only property already stops reassignment, so storage needs no second keyword.

### Rejected: plain PHP methods stay callable through a readonly value

```csharp
public void render(readonly Order order)
{
    order.save();                                       // would compile: save is a plain PHP method of Model
}
```

`save()` writes the order to the database and changes its key, so a readonly order would change. A reader of `render(readonly Order order)` could no longer trust the mark.

### Rejected: PHP's write-once `readonly` field and `readonly class`

```csharp
public class Invoice
{
    public Invoice(public readonly Money total) { }     // would compile: total is written once
}

public readonly class Receipt { … }                     // would compile: every property is written once
```

One keyword would mean two things: a value that can only be read, and a field that can be written once. `{ get; }` already does the second job.

## Precedent

- **Chosen:** C++'s `const` member functions, marked after the parameters, and `const` references, through which only those run. Rust's `&` references, through which nothing is changed. TypeScript's `readonly`, checked by the compiler and gone at runtime, as PHP# erases its mark for the engine.
- **Rejected, plain PHP callable:** Kotlin's platform types, which leave Java code unchecked.
- **Rejected, write-once fields:** PHP 8.1's `readonly` properties and PHP 8.2's `readonly` classes.

## Spec

- [Section 13, `readonly`](../spec.md#13-readonly)

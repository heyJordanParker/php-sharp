# 13. `new Self(…)`

## Decision

`new Self(…)` compiles only when the class's constructor is marked `required`. Every subclass then keeps a constructor `new Self(…)` can call, adding only parameters with defaults. `required` is optional for a class that never writes `new Self(…)`.

## Options

### Chosen: a `required` constructor

```csharp
public abstract class DatabaseEntity
{
    public required DatabaseEntity(Row row) { … }
    public static Self fromSchema(Any value, VerifiedUser caller) { … return new Self(row); }   // compiles: the constructor is required
}

public class Order : DatabaseEntity
{
    public Order(Row row, Clock? clock = null) : super(row) { … }   // compiles: new Self(row) can call it
}

public class Invoice : DatabaseEntity
{
    public Invoice(Row row, Clock clock) : super(row) { … }         // compile error: new Self(row) cannot supply clock
}

const order = Order.fromSchema(value, caller);   // compiles; typed as Order
```

### Rejected: fail at runtime

```csharp
public class Invoice : DatabaseEntity
{
    public Invoice(Row row, Clock clock) : super(row) { … }   // compiles
}

Invoice.fromSchema(value, caller);   // compiles; runs: throws ArgumentCountError, because new Self(row) passes no clock
```

The subclass that breaks the call compiles, and the error appears only when that subclass is created.

### Rejected: a static factory per class

```csharp
public interface Creatable
{
    static abstract Self create(Row row);                       // not PHP#: a static member each class implements
}

public class Order : DatabaseEntity, Creatable
{
    public static Order create(Row row) => new Order(row);      // compiles; every class writes its own factory
}
```

Every class repeats a factory that only calls its constructor.

## Precedent

- **Chosen:** Swift's `required init`.
- **Rejected, fail at runtime:** PHP's `new static`.
- **Rejected, a static factory per class:** C#'s static abstract interface members.

## Spec

- [Section 9, Constructors](../spec.md#9-constructors)
- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

# 49. Events

## Decision

An event is a type. `public event Paid(int orderId, Money amount);` inside `Order` declares `Order.Paid`, and `emit new Paid(this.id, this.total);` raises it. Any code may raise any event type. A method listens with a trailing `on` clause that lists its events, and `vendor/bin/mago compile` finds every one at build time. `Events.on<T>` listens until the handle it returns goes out of scope. `emit` has the effect `Events`, which code without a body allows with `uses Events`. The app's dispatcher decides when listeners run.

This replaces the earlier design, where an event was a member of its owner, named as `Order.paid`, and only the owner could raise it.

## Options

### Chosen: event types, `emit`, and a trailing `on` clause

```csharp
public interface OrderEvent { int orderId { get; } }

public class Order
{
    public event Paid(int orderId, Money amount) : OrderEvent;
    public event Refunded(int orderId, Money amount) : OrderEvent;

    public void markPaid()
    {
        …
        emit new Paid(this.id, this.total);                             // compiles; any code may emit an event
    }
}

public class OrderNotices
{
    public void deliver(Order.Paid e) on Order.Paid { … }               // compiles
    public void send(OrderEvent e) on Order.Paid, Order.Refunded { … }  // compiles; two events, read through the type they share
    public void refresh() on Order.Paid { … }                           // compiles; no parameter
    public void audit(OrderEvent e) on OrderEvent { … }                 // compiles; every event that implements OrderEvent
}

public class ImportScreen
{
    public void import(string path)
    {
        const listening = Events.on<RowImported>(e => this.progress.advance());   // compiles; stops when import returns
        this.importer.run(path);
    }
}
```

The compiler sees every listener. A wrong event name, or a parameter type the listed events don't share, is a compile error, and a reader follows `on Order.Paid` from an `emit` to every listener. The clause lists the events apart from the parameter, so a listener can pick specific events through a broader type, or take no parameter at all. Its one cost is a single-event listener that reads the event, which names the event twice.

`emit` has the effect `Events`, so a method's effects show that it runs listeners. Code without a body allows it with `uses Events`:

```csharp
public interface Checkout
{
    void complete(Order order) uses Events;                             // compiles; implementations may emit
}

public class StoreCheckout : Checkout
{
    public void complete(Order order) { emit new Order.Paid(order.id, order.total); }   // compiles; fits uses Events
}
```

### Rejected: an event as a member of its owner

```csharp
public class Order
{
    public event OrderPaid paid;                                        // not PHP#: the earlier design
    void settle() { emit paid(new OrderPaid(this)); }                   // only Order could emit it
}

public void deliver(OrderPaid e) on Order.paid { … }                    // not PHP#
```

`on Order.paid` names exactly one event of one class. A listener could not hear a family of events or a Laravel event, and no class but `Order` could raise the event.

### Rejected: runtime-only subscription

```csharp
public class Startup
{
    public void boot()
    {
        Events.on<Order.Paid>(e => this.receipts.deliver(e));           // not PHP#: the only way to listen
    }
}
```

Every permanent listener is a line in startup code. A forgotten line silently never runs, and nothing reports it.

### Rejected: the parameter marker `deliver(on Order.Paid e)`

```csharp
public void deliver(on Order.Paid e) { … }                              // not PHP#
public void send(on OrderEvent e) { … }                                 // not PHP#: hears every OrderEvent, not only Paid and Refunded
```

The marker ties the events a listener hears to its parameter's type. A listener cannot pick two events through a type they share, and a listener that needs no data still declares a parameter.

### Rejected: the class-level `Listens<T>` interface

```csharp
public class Receipts : Listens<Order.Paid>                             // not PHP#
{
    public void handle(Order.Paid e) { … }
}
```

A class has one `handle` method, so it listens to one event type. A class that reacts to `Order.Paid` and `Order.Refunded` in different ways becomes two classes.

### Rejected: an implicit event parameter

```csharp
public void deliver() on Order.Paid { this.mail.send(new Receipt(event.orderId)); }   // not PHP#: event is never declared
```

The body reads a name the method never declares, so its type is written nowhere a reader can see.

### Rejected: `emit` as no effect

```csharp
public interface Checkout
{
    void complete(Order order);                                         // not PHP#: pure, yet its implementation emits
}

public class StoreCheckout : Checkout
{
    public void complete(Order order) { emit new Order.Paid(order.id, order.total); }   // a listener for Order.Paid writes to the database
}
```

A method whose signature reaches nothing could run listeners that reach the database.

### Rejected: attributes

```csharp
[ListensTo(Order.Paid)]                                                 // not PHP#
public void deliver(Order.Paid e) { … }
```

Attributes are for consumers, such as an app's own `[Retry]`, not for the core language.

## Precedent

- **Chosen, the event type:** C#'s positional records and Kotlin's data classes, whose parameters become properties.
- **Chosen, the trailing clause:** VB.NET's `Handles a.Click, b.Click`, which lists events after a method's signature.
- **Chosen, listing events and allowing no parameter:** Spring's `@EventListener({A.class, B.class})`.
- **Chosen, the scoped handle:** Rust's `tokio` broadcast receiver, which stops when it is dropped, and C#'s `using var`.
- **Chosen, `emit` as an effect:** Koka's `effect fun emit(msg : string) : ()` and Unison's `Stream` ability, where emitting shows in a function's type.
- **Rejected, runtime-only subscription:** C#'s `+=` on an event.
- **Rejected, `emit` as no effect:** C#, where raising an event shows nowhere in a method's signature.
- **Rejected, the class-level interface:** MediatR's `INotificationHandler<OrderPaid>` and actix's `Handler<OrderPaid>`.

## Spec

- [Section 15, Events](../spec.md#15-events)
- [Section 29, Effects](../spec.md#29-effects)

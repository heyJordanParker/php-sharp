# 99. A value that moves between cases is a `machine`

The language owner's rulings of 11-19 and 11-20.

## Decision

A value that moves between cases is declared as a `machine`. It lists its cases as an enum does, each with an optional value, and a case's block holds the moves out of it. The checker refuses every other way to change the value, checks the graph of cases and moves, and proves each invariant, a law whose one parameter is a case, after every entry point and every move. A machine marked `[Tag]` stores as a family does, and a backed machine's values name its cases there, so its cases take no `[Key]`.

## Options

### Chosen: one construct that holds the cases and their moves

```csharp
public machine OrderStatus : string
{
    case Draft = "draft"
    {
        public OrderStatus.Paid pay(int cents) => OrderStatus.Paid(cents);
    }

    final case Paid(int cents) = "paid";

    public static OrderStatus.Draft open() => OrderStatus.Draft;
}
```

Each move is written once, in the case it leaves, so the graph a reviewer reads is the code that runs. A forgotten rule can't fall back to an enum, because the keyword is the type.

### Rejected: rules on an enum or a sealed interface

An enum with a transition method, or a sealed interface whose classes give the next state, stops none of the mistakes a state machine invites: a status set directly, a move from the wrong state, data that doesn't match the state, and moves spread over many files.

### Rejected: a graph file beside the state classes

Every move is written twice, once in the graph and once in the class that runs it, and the two drift apart.

### Rejected: an optional marker attribute

A forgotten marker leaves a plain enum that compiles, so the checks fall away silently.

### Rejected: events raised on assignment

An event fired by an assignment is a hidden effect, and it fires before anything is saved.

### Rejected: `stored()` and `restore()`

A machine stores as a `[Tag]` family does, through `Json.encode`, `parse` and `Json.decode`, which check each invariant of the case they read. A second form would be a second way to store the same value.

### Rejected: provenance of a restored value

A record of how a value was restored contradicts a case with no fields being one shared object, and saving a value atomically is storage's job.

### Rejected: nested states, parallel states, delays and `can`

Nested states flatten into one machine's cases, and parallel states are two machines on the holder. A delay is a scheduled job that calls a move. `can` asks what `is` already asks.

### Rejected: `[Key]` on each case of a backed machine

A backed machine would write each name twice, as `[Key("paid")] case Paid(int cents) = "paid"`, and the two could differ. The value names the case, and `[Key]` there is a compile error that names the value.

## Spec

- [Section 20.2, Machines](../spec.md#202-machines)
- [Section 28, Verification](../spec.md#28-verification)

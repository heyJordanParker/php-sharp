# 29. Typed compilation

## Decision

A `.sharp` file runs only after the checker accepts it, and a type error stops it from running, as in C# and Java. The checker's types reach the running program, inferred ones too. A generic method can test a type parameter its call inferred, `typeof(TItem)` works on every type parameter, and a loop over a `Map` keyed by a backed enum gives its keys back as the enum with no type written. This reverses the earlier model, where each file compiled alone from its own text.

## Options

### Chosen: the checker's types reach the running program

```csharp
public class Inbox
{
    public bool holds<TItem>(List<TItem> items, Any value) => value is TItem;   // compiles
}

inbox.holds(orders, message);                   // compiles; runs: TItem is Order, inferred from orders
for (const [status, n] of counts) { … }         // compiles; runs: status is a Status
const byStatus = orders.groupBy(o => o.status);
for (const [status, group] of byStatus) { … }   // compiles; runs: an inferred Map's keys are Status too
```

Code writes a type only when the checker cannot work it out. The cost is in how code runs. The checker must accept a file before it runs, in development and in every deployment, and a missing or stale result stops the program. Editing one file can change how the files that inferred its types run.

### Rejected: each file compiles alone (Hack's model)

```csharp
inbox.holds(orders, message);                        // compile error: holds uses TItem as a class, so write it: holds<Order>(…)
inbox.holds<Order>(orders, message);                 // compiles
for (const [Status status, int n] of counts) { … }   // the key's type is written so the engine can rebuild it
```

The engine sees one file's text, so a type the checker inferred never reaches it. A call must write every type argument the body uses as a class, and the signature does not show which calls must.

### Rejected: pass a `Class<T>` argument (Java's model)

```csharp
public bool holds<TItem>(Class<TItem> type, List<TItem> items, Any value) => value is type;   // not PHP#: the class travels as an argument
inbox.holds(typeof(Order), orders, message);
```

The caller writes `typeof(Order)` only so the engine can run, and the signature names the class twice.

## Precedent

- **Chosen:** C#, whose runtime builds a specialized generic type for each type argument, and Swift, which passes type metadata. Both compile before the code runs, as Java does.
- **Rejected, each file compiles alone:** Hack's `reify`, whose type arguments must be written at every call.
- **Rejected, a `Class<T>` argument:** Java's `Class<T>`, and Kotlin's `KClass<T>` outside `reified` functions.
- **Earlier decisions:** this extends decision 7 to inferred type arguments, supersedes decision 26's rejection of "the checker's types decide what the engine emits", and changes decision 27's loop rule.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)
- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)
- [Section 27, PHP# files](../spec.md#27-php-files)

# 7. Type arguments at runtime

## Decision

Generic classes and methods keep their written type arguments at runtime. A collection's elements are checked once, where the collection crosses in from plain PHP, and `as` to a collection type checks every element and gives null on a wrong one. Decision 29 carries inferred type arguments at runtime too.

## Options

### Chosen: written type arguments at runtime, elements checked at the border

```csharp
const page = new PaginatedList<Order>(rows);
if (page is PaginatedList<Order>) { … }       // compiles; runs: true, read from the type argument new wrote

List<Line> lines = legacy.lines();            // plain PHP returned it: every element is checked here, once
lines = Cart.withShipping(lines, shipping);   // PHP# to PHP#: nothing is checked, because the checker proved it

const tags = payload as List<string> ?? throw new BadPayload(payload);   // checks every element, and throws on a wrong one
```

### Rejected: every collection tagged, every write checked

```csharp
List<Line> lines = [];
for (const item of cart.items) {
    lines.add(item.line);                     // compiles; runs: checks that item.line is a Line, on every pass
}
```

Every collection carries its element type, and every write checks the element, including the writes the checker already proved.

### Rejected: type arguments erased

```csharp
if (page is PaginatedList<Order>) { … }       // compile error: type arguments do not exist at runtime
List<Line> lines = legacy.lines();            // compiles; runs: nothing is checked, so a wrong element fails later, where it is used
```

### Rejected: erased, with an opt-in `reify`

```csharp
public class PaginatedList<reify TItem> { … }   // not PHP#: a class opts in with a marker
if (page is PaginatedList<Order>) { … }          // compiles only when PaginatedList wrote the marker
```

Each class author decides whether its type arguments exist at runtime, and every caller must know which choice the author made.

## Precedent

- **Chosen:** Typed Racket's checks where typed and untyped code meet, Hack's like types, and TypeScript projects validating input with zod.
- **Rejected, every write checked:** C#.
- **Rejected, erased:** Java, TypeScript, Kotlin and Python.
- **Rejected, opt-in `reify`:** Kotlin's `reified` and Hack's `reify`.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 21, Pattern matching](../spec.md#21-pattern-matching)

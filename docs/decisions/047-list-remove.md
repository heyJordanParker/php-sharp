# 47. `List.remove(value)`

## Decision

`List.remove(value)` removes the first element equal to `value` by `==` (section 19), renumbers the list, and returns `bool`: true when it removed one. A `List` whose element type has no `==` cannot call `remove` (section 12).

## Options

### Chosen: the first equal element, and a `bool`

```csharp
List<string> tags = ["a", "b", "a"];
tags.remove("a");                     // compiles; runs: true, and tags is ["b", "a"]
tags.remove("z");                     // compiles; runs: false, and tags is unchanged
```

One call removes one element, and the result says whether there was one to remove. Removing every equal element stays one line, with `filter`.

### Rejected: every equal element

```csharp
tags.remove("a");                     // not PHP#: removes both "a" elements, and tags is ["b"]
```

Code that means to drop one of two equal elements, such as one of two identical cart lines, has no way to say so.

## Precedent

- **Chosen:** Kotlin's `MutableList.remove(element)`, C#'s `List<T>.Remove(item)` and Java's `List.remove(Object)`, which each remove the first equal element and return a boolean.
- **Rejected:** Swift's `removeAll(where:)`, which removes every matching element.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)

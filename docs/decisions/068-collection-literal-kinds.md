# 68. A literal of the other collection

## Decision

A `List` literal where a `Map` is declared, or a `Map` literal where a `List` is declared, is a compile error. `[]` and `[a, b]` write a `List`, and `[:]` and `[key: value]` write a `Map`, whatever the place declares. This holds at a declaration, an assignment, a default, a return or an argument, and for a literal on the right of `??` or `??=`, in a `? :` branch or in a `match` arm.

## Options

### Chosen: refuse the literal of the other collection

```csharp
Map<string, int> counts = [];                  // compile error: `[]` is an empty List. An empty Map is written `[:]`.
List<string> tags = [:];                       // compile error: `[:]` is an empty Map. An empty List is written `[]`.
Map<int, string> names = ["a"];                // compile error: A Map literal is written `[key: value]`.
```

A literal means one collection wherever it is written, so a reader knows what `[]` holds without looking up its place. The error names the literal the place needs.

### Rejected: the declared type decides

```csharp
Map<string, int> counts = [];                  // would compile: an empty Map
List<string> tags = [:];                       // would compile: an empty List
Map<int, string> names = ["a"];                // would compile: a Map with the key 0
```

PHP has one array, so `[]` and `[:]` lower to the same value, and the place could pick its kind. A literal would then mean two things, and `["a"]` would quietly become a `Map` keyed by position.

## Precedent

- **Rejected:** PHP, whose `[]` is any array.

## Spec

- [Section 12, Collections](../spec.md#12-collections)

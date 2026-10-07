# 31. Spread in collection literals

## Decision

A spread works inside a collection literal, and the collection's type decides what it means. `[...open, ...closed]` appends `List`s. `[...config, "root": ""]` and `[...defaults, ...overrides]` copy a `Map`'s entries: a later key wins, and no key is renumbered. A literal that spreads a `List` and a `Map` together is a compile error, and only a `List` spreads into a call.

## Options

### Chosen: spread in both, decided by the collection's type

```csharp
List<Line> lines = [...open, ...closed];                  // compiles; runs: closed's lines follow open's
Map<string, string> options = [...config, "root": ""];    // compiles; runs: array_replace(config, ["root" => ""])
Map<string, int> limits = [...defaults, ...overrides];    // compiles; runs: array_replace(defaults, overrides)
const mixed = [...lines, ...options];                     // compile error: a literal cannot spread a List and a Map together
image.resize(...options);                                 // compile error: only a List spreads into a call
```

One form merges both collections. The engine knows each collection's type (decision 29), so a `Map` spread keeps every key, where PHP's spread would renumber a `"5"` key to `0`.

### Rejected: spread in `List` literals only, and `putAll` for a `Map`

```csharp
Map<string, string> options = [...config, "root": ""];    // compile error: a spread goes only in a List literal
let merged = defaults;
merged.putAll(overrides);                                 // compiles
```

Every `Map` merge takes two statements and a method name, while a `List` merge takes one literal.

### Rejected: `+` merges two collections

```csharp
Map<string, int> merged = defaults + overrides;           // not PHP#: which value wins for a key in both?
```

PHP's array `+` keeps the left value for a key in both, and Kotlin's map `+` keeps the right one. Each meaning surprises a reader who knows the other.

## Precedent

- **Chosen:** JavaScript's array and object spread, and Dart's spread in list and map literals. PHP's `array_replace` for the merge that keeps keys.
- **Rejected, `List`-only spread:** TypeScript's array spread with Kotlin's `putAll`, and C# 12's `[..a, b]`.
- **Rejected, `+`:** PHP's array union and Kotlin's `Map.plus`.

## Spec

- [Section 7, Methods](../spec.md#7-methods)
- [Section 12, Collections](../spec.md#12-collections)

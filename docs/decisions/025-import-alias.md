# 25. Renaming an import

## Decision

`import X.Y as Z;` renames an import in this file only, and compiles to PHP's `use X\Y as Z;`. Two `import` lines with the same name in one file are a compile error, so a file renames one of them.

## Options

### Chosen: `as` on the import line

```csharp
import Cache.Key as CacheKey;                                    // compiles: renamed in this file only

public struct Entry
{
    public Entry(
        [Key("cache_key")] public CacheKey key { get; },         // compiles: Key stays the standard attribute, CacheKey is the library class
    ) { }
}
```

```csharp
import Cache.Key;
import Search.Key;                                               // compile error: Key is already imported
```

One line imports the class and names it. The standard library's names are imported by default, and `import Cache.Key;` would shadow the standard `Key`. The struct `[Key]` attribute (decision 24) relies on the rename, because a project with its own `Key` keeps the attribute by renaming its class.

### Rejected: a separate alias declaration

```csharp
using CacheKey = Cache.Key;                                      // not PHP#: C#'s using alias, a second statement beside import
```

A file then has two statements that bring in a name, and a full name appears outside the `namespace` and `import` lines that section 23 reserves for them.

## Precedent

- **Chosen:** PHP's `use Cache\Key as CacheKey`, Kotlin's `import cache.Key as CacheKey`, TypeScript's `import { Key as CacheKey }` and Python's `import … as …`.
- **Chosen, default imports:** Kotlin, which imports `kotlin.*` into every file.
- **Rejected:** C#'s `using CacheKey = Cache.Key;`.

## Spec

[Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)

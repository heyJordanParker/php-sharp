# 69. Capitalized namespaces

## Decision

Every part of a `namespace` line starts with a capital letter, A to Z. `namespace App.store;` is a compile error on `store`, and `namespace App.Store;` compiles. The error reads as the one for a lowercase type, because one rule covers both: "`store` must start with a capital letter: PHP# capitalizes every namespace and every type except the built-in ones." An `import` line keeps the letters it names, because it names a namespace another file declares, and plain PHP vendor namespaces are often lowercase. Decision 59 names namespaces PascalCase. This decision makes the first letter of each part a compile error, as section 24 does for types.

Section 5 makes a file's namespace match its folder, so every source folder under a namespace root is capitalized as well, as `app/Shop/` holds `App.Shop` in decision 56.

## Options

### Chosen: every part of a namespace is capitalized

```csharp
namespace App.Store.Orders;                 // compiles
namespace App.store;                        // compile error: `store` must start with a capital letter
namespace app.Store;                        // compile error: `app` must start with a capital letter
namespace App._Store;                       // compile error: `_Store` must start with a capital letter

import vendor.lib.Box;                      // compiles: an import names another file's namespace
```

A full name reads the same way in every file: `App.Store.Order` is capitalized at every part, whether the part is a namespace or a type. One project can't hold both `App.Store` and `App.store`, which PHP compares ignoring case and would treat as one namespace.

### Rejected: only types are capitalized

```csharp
namespace App.Store.Orders;                 // compiles
namespace App.store;                        // would compile
namespace app.Store;                        // would compile
namespace App._Store;                       // would compile

import vendor.lib.Box;                      // compiles
```

Two files in one project could write `App.Store` and `App.store`. PHP would run both as one namespace, and the names would stop reading as one language. A full name such as `App.store.Order` would mix casings in one line.

## Precedent

- **Chosen:** .NET's [Framework Design Guidelines, Names of Namespaces](https://learn.microsoft.com/en-us/dotnet/standard/design-guidelines/names-of-namespaces): "DO use PascalCasing, and separate namespace components with periods (e.g., `Microsoft.Office.PowerPoint`)." PHP# enforces with an error what .NET states as a guideline.
- **Rejected:** PHP, which checks no letter in a namespace and compares namespaces ignoring case.

## Spec

- [Section 5, Access modifiers](../spec.md#5-access-modifiers)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

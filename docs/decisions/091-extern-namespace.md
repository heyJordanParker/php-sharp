# 91. `extern namespace`

Jordan's ruling of 11-11. It extends [decision 83](083-php-through-the-standard-library.md). [Decision 97](097-one-way-to-name-a-library-s-root-names.md) amends it: the line may name a root function or constant as well as a class, writes its namespace in full or through an import, and may give a namespace a class has to the folder that holds such a class.

## Decision

`extern namespace WordPress for WP_Post;` in a declaration file gives the folder that declares the namespace-less class `WP_Post` the PHP# namespace `WordPress`. That folder's namespace-less classes and functions are then `import WordPress;`, `WordPress.add_action(…)` and `import WordPress.WP_Post;`, and run PHP's `\add_action` and `\WP_Post`. It extends decision 83's rule that a namespace-less function belongs to the namespace of the classes beside it to a library whose classes have none. The named class must be namespace-less and declared by a library, or it is a compile error. Two lines that name one folder, one namespace given to two folders, and a namespace a class already has are compile errors, since a class of the namespace is PHP's root class of that name. `extern WordPress.get_post uses Database;` declares an effect as every `extern` does.

## Options

### Chosen: a declaration names a class of the folder and the namespace it takes

```csharp
// app/Stubs/WordPress.sharp
namespace App.Stubs;

import WordPress;

extern namespace WordPress for WP_Post;              // compiles: WP_Post's folder is the namespace WordPress
extern WordPress.get_post uses Database;             // compiles: get_post() has the effect Database
```

```csharp
import WordPress;
import WordPress.WP_Post;

WordPress.add_action("init", () => { });             // compiles: runs PHP's \add_action
```

The library names no namespace, so the project names one, once, through a class PHP# can check, and every call reads `Name.member(…)` as any library's does.

### Rejected: `global.` for a namespace-less library

```csharp
global.add_action("init", () => { });                // global names no class (decision 83)
```

Decision 83 removed `global.`, since PHP's names would leak into PHP# code and escape a module's `imports` list.

### Rejected: the folder's own name as the namespace

```csharp
import WpIncludes;                                   // the folder wp-includes, a name no code of the library writes
```

A folder's name is no namespace the library declares (decision 83), and one library moves its files between folders.

## Precedent

- **Chosen:** C#'s `extern alias`, which gives an assembly a root namespace the code imports, Rust's `extern crate … as …`, and TypeScript's `declare module`, which gives a library without types the module name code imports.

## Spec

- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)

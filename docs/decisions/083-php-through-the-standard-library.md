# 83. PHP is reached only through the standard library

Jordan's rulings of 10-22. This replaces [decision 80](080-global-calls-of-replaced-functions.md) and the `global.` and replacement-table parts of [decision 76](076-plain-php-functions.md).

## Decision

**A function or constant a library declares outside any namespace belongs to the namespace of the library's classes in the same folder.** Laravel declares `abort()` in `Illuminate/Foundation/helpers.php` beside the classes of `Illuminate\Foundation`, so after `import Illuminate.Foundation;` the call is `Foundation.abort(404)`, the value is `Foundation.abort`, and `extern Foundation.now uses Clock;` declares `now()`'s effect. The namespace comes from the classes beside the file, never from the folder's name, so Laravel's `collect()` in `Illuminate/Collections/helpers.php` is `Support.collect(…)`. The call runs PHP's `\abort(404)`, and an import of the namespace is held to a module's `imports` list as any import is.

**`global.` does not exist in PHP# code.** `global` is an ordinary name, so `global.levenshtein(a, b)` is the error `gadget.levenshtein(a, b)` gets: `global` names no class, and no namespace the file imports.

**PHP is reached only through the standard library,** as Rust reaches the platform through `std` and Swift through Foundation. The standard library's own files, the files of the package named `heyjordanparker/php-sharp-composer`, keep `global.` to call PHP's built-ins. A built-in the standard library has no member for is called from a plain PHP class of the project.

**A bare call of a PHP function is the error a misspelled name gets** (Jordan's rule of 10-18: a form PHP# never writes fails as a typo does). PHP# has no top-level functions, so `trim(name)`, `levenshtein(a, b)` and `render(html)` each name no function, with no message that recognizes PHP's function or names what to write instead. A bare PHP constant, a bare `extern trim;` and an `import strlen;` are the same. The checker keeps no table of PHP's functions and their replacements, and creating a PHP built-in class is creating a plain PHP class, with the effects its `extern` lists.

## Options

### Chosen: PHP through the standard library, and a library's root functions through its namespace

```csharp
// app/Http/CartsController.sharp
namespace App.Http;

import App.Support.Words;
import Illuminate.Foundation;

public class CartsController
{
    public string clean(string name) => name.trim();                        // compiles: the standard library's member
    public int distance(string a, string b) => Words.distance(a, b);       // compiles: the project's plain PHP class calls levenshtein
    public Any? missing() => Foundation.abort(404, "Cart not found.");     // compiles: Laravel's abort(), through Illuminate.Foundation
    public int raw(string a, string b) => global.levenshtein(a, b);        // compile error: `global` names no class, and no namespace this file imports
    public string bare(string name) => trim(name);                          // compile error: Function `trim` does not exist
}
```

PHP# code never names a PHP function, constant or argument order, so a reader and an Agent need to know only PHP# and the libraries the project imports. A library's root function reads as every other library function does, `Name.member(…)`, and its import is checked against a module's list, so a helper in PHP's root namespace no longer escapes the list.

### Rejected: `global.` for the built-ins the standard library has no member for

```csharp
public int distance(string a, string b) => global.levenshtein(a, b);       // compiles
public int timeout() => global.CURLOPT_TIMEOUT;                             // compiles
```

PHP's names, argument orders and constants leak into PHP# code, and every built-in the library lacks becomes a second way to write PHP#. Laravel's `global.app(…)` escapes a module's `imports` list, since `global` is no namespace a module lists.

### Rejected: messages that name the replacement, from a table in the checker

```csharp
public string bare(string name) => trim(name);                              // compile error: write name.trim()
```

The checker keeps a copy of the library's names in its own table, which goes stale as the library changes, and the message teaches PHP's function names by recognizing them. A form PHP# never writes fails as a typo does, so the error is the one a misspelled name gets.

### Rejected: a root function through the namespace its folder names

```csharp
import Illuminate.Collections;                                              // Laravel declares no class in Illuminate\Collections
public Any? all() => Collections.collect([1]);
```

A folder's name is no namespace PHP declares, so the import names nothing a class of the library uses, and a project imports a namespace the library's own code never writes.

## Precedent

- **Chosen:** Rust's `std` and Swift's Foundation as the only way to the platform. Kotlin, whose top-level functions belong to the package of their file, called through it from Java.
- **Rejected:** C#'s `global::`, Rust's `extern "C"` and C#'s P/Invoke, which reach any foreign function from any code.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)

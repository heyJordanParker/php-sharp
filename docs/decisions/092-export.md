# 92. `[Export]`

Jordan's ruling of 11-11.

## Decision

`import Sharp.Interop.Export;` then `[Export("auth_redirect")]` on a static method makes PHP see a global function `auth_redirect()` that calls the method, with its parameters and its result. It is an attribute, never a keyword, and the spec states it only in the section on reaching PHP. On an instance method it is a compile error, and a second export of one name in the project is a compile error that names both. Every exported function is declared when the app starts, before any library loads, so a library that declares its own only `if (! function_exists(…))` finds it and skips its own. The Composer plugin writes the functions to `vendor/composer/autoload_sharp_exports.php` at each dump, from each `.sharp` file's `[Export]` attributes, and PHP#'s `autoload.php`, which Composer's autoloader loads with PHP#'s package, declares them. Each function passes its arguments, named ones included, to the method from a strict file, so they cross into the method's types as PHP# checks them, and returns the method's result.

## Options

### Chosen: an attribute on a static method

```csharp
import Sharp.Interop.Export;

public static class Auth
{
    [Export("auth_redirect")]
    public static void redirect() { … }               // compiles: PHP's auth_redirect() calls it

    [Export("auth_check")]
    public bool check() => true;                      // compile error: only a static method is exported
}
```

A plain PHP library that lets a project replace one of its global functions, as WordPress's pluggable functions do, finds the project's function first, and PHP# code still calls the method as `Auth.redirect()`.

### Rejected: an `export` keyword

```csharp
public static export void redirect() { … }            // a keyword every reader learns for one interop job
```

A keyword grows the language for a job only interop code has, where an attribute is read only where it is written.

## Precedent

- **Chosen:** Rust's `#[no_mangle] pub extern "C" fn`, C#'s `[UnmanagedCallersOnly(EntryPoint = …)]`, Go's `//export` and Kotlin/Native's `@CName`, each of which names a function foreign code calls by its symbol.

## Spec

- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)

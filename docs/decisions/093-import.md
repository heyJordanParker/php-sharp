# 93. `[Import]`

Jordan's ruling of 11-11. It replaces [decision 83](083-php-through-the-standard-library.md)'s rule that a built-in the standard library has no member for is called from a plain PHP class of the project.

## Decision

`import Sharp.Interop.Import;` then `[Import("levenshtein")] public static int between(string a, string b);` on a body-less static method makes each call run PHP's `levenshtein()`. The signature is written in PHP# types, each argument and the result cross as any plain PHP value does and are checked there, and the declaration states its effects with `uses`, as any body-less declaration does. A name PHP declares no function for throws at the first call, naming the function, and a second `[Import]` of one function is a compile error.

## Options

### Chosen: an attribute on a body-less static method

```csharp
import Sharp.Interop.Import;

public static class Words
{
    [Import("levenshtein")]
    public static int between(string a, string b);    // compiles: Words.between("kitten", "sitting") runs levenshtein, 3

    [Import("file_get_contents")]
    public static string? read(string path) uses Files;   // compiles: the declaration states the effect
}
```

A PHP# project has no plain PHP files, so the built-ins the standard library lacks are reached from PHP# itself, through a declaration whose types the checker reads and the engine checks, and PHP's name appears once, in the attribute.

### Rejected: a plain PHP class of the project

```php
final class Words
{
    public static function distance(string $from, string $to): int { return levenshtein($from, $to); }
}
```

This was decision 83's rule. A PHP# project would keep plain PHP files for it alone, and the class's PHP types check less than PHP#'s.

### Rejected: `global.` calls

```csharp
global.levenshtein(a, b);                             // global names no class (decision 83)
```

PHP's names and argument orders would appear wherever a built-in is called.

## Precedent

- **Chosen:** C#'s `[DllImport] static extern`, Rust's `extern "C" { fn … }` and Kotlin/JS's `external fun`, each a typed declaration of a foreign function the code calls as its own.
- **Rejected:** C#'s `global::` and decision 83's plain PHP class.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)

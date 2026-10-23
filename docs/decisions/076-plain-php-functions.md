# 76. Calling plain PHP functions

Superseded in part by [decision 83](083-php-through-the-standard-library.md): `global.` does not exist in PHP# code, and a function a library declares in PHP's root namespace is called through the namespace of the library's classes beside it, as `Foundation.abort(404)`. A bare call or a bare PHP constant is the error a misspelled name gets, and the checker keeps no table of replacements. PHP's own functions and constants are reached only through the standard library, which alone keeps `global.`.

Superseded in part by [decision 80](080-global-calls-of-replaced-functions.md): outside the standard library, a call through `global` of a function the standard library replaces is a compile error with its bare call's message, as `global.trim(name)` names `name.trim()`. Only the standard library calls such a function, so it is no longer "the same way, with no exemption".

## Decision

A plain PHP function is called through its namespace, as a member. After `import Vendor.Pdf;`, the call is `Pdf.render(html)`. A function in PHP's root namespace is called as `global.now()`. Calling a function by its bare name, and importing a single function, are compile errors. `extern` names a function the same way, as `extern Pdf.render uses Files;` and `extern global.now uses Clock;`.

A plain PHP constant is read through its namespace the same way. `global.PHP_EOL`, `global.CURLOPT_TIMEOUT` and `global.STDERR` read PHP's root constants, and `Pdf.DEFAULT_DPI` reads the library's after `import Vendor.Pdf;`. A bare uppercase name is always the code's own constant, so a bare PHP constant such as `PHP_EOL` is a compile error that says to write `global.PHP_EOL`.

The error for a bare call names the standard library member that does the same work, such as `name.trim()` for `trim(name)`. One table in the checker maps each PHP function to that member, and also covers the syntax PHP# removed, such as `echo`. A function with no member gets `global.name(…)` in its message, and a function with a member gets its row in the pull request that ships the member.

The standard library calls PHP the same way, with no exemption. This replaces decision 40's "Once the standard library wraps a PHP function, calling that function from `.sharp` code outside the standard library is a compile error that names the method. Functions not wrapped yet stay callable", and the `[Replaces]` attribute that marked which standard library method wraps which function.

PHP#'s Composer package ships an `extern global.name` declaration for each PHP 8.5 built-in function, as decision 43 requires. `sharp/bin/builtins` writes them from PHPStan's php-8-stubs, and its rules give each function one effect, or none, by its extension and its name. A plain PHP library gets no shipped declaration, so a project declares it.

## Options

### Chosen: every call names its namespace, and the checker's table names the replacement

```csharp
// app/Invoices/Printer.sharp
namespace App.Invoices;

import Vendor.Pdf;

public class Printer
{
    public string print(string html) => Pdf.render(html);            // compiles: the function is called through its namespace
    public int size(string html) => global.strlen(html);              // compiles: a root function goes through global
    public string draft(string html) => render(html);                 // compile error: write Pdf.render(…)
    public string label(string name) => trim(name);                   // compile error: write name.trim()
    public string line(string text) => text + global.PHP_EOL;         // compiles: a root constant goes through global
    public int timeout() => global.CURLOPT_TIMEOUT;                   // compiles
    public int dpi() => Pdf.DEFAULT_DPI;                              // compiles: the library's constant goes through its namespace
    public string bare(string text) => text + PHP_EOL;                // compile error: write global.PHP_EOL
}
```

Every call outside the current class reads `Name.member(…)`, so a reader tells a plain PHP function from a method of the class without looking anything up. A constant reads the same way, so a bare uppercase name is always the code's own constant, never one PHP falls back to. The message for a bare call is the same before and after the standard library grows a member, apart from the member it names.

### Rejected: `[Replaces]` on the standard library method that wraps a function

```csharp
public static class Text
{
    [Replaces("strtoupper")]                                         // not PHP#: the attribute marks the wrap
    public static string upper(this string text) => strtoupper(text);  // the library calls the function bare
}

public class Label
{
    public string title(string name) => strtoupper(name);            // compile error: write name.upper()
    public string pad(string name) => str_pad(name, 10);             // compiles: nothing wraps str_pad yet
}
```

A bare call compiles or fails depending on whether some standard library method wraps the function yet, so each new wrap breaks code that compiled before. The standard library needs an exemption to call the functions it wraps. Each wrap carries an attribute line, and the checker re-reads every standard library file whenever one changes.

### Rejected: importing a single function

```csharp
import Vendor.Pdf.render;                                            // not PHP#: PHP's `use function`

public class Printer
{
    public string print(string html) => render(html);                // reads like a method of Printer
}
```

The call reads like a method of the class or a local function value. Only the import line, far from the call, tells them apart.

## Precedent

- **Chosen:** Go, which calls a package's functions as `pdf.Render(html)`. Python after `import pdf`. Java calling Kotlin's top-level functions through their file's class, and C# calling F# modules. C#'s `global::`, which names the root namespace.
- **Rejected:** PHP's `use function` and Kotlin's import of a single top-level function.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)

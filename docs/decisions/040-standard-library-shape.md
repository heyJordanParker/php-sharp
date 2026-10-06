# 40. The standard library's shape

## Decision

The standard library lives under one root, `Sharp`, with the topic modules `Sharp.Text`, `Sharp.Math`, `Sharp.Json`, `Sharp.IO`, `Sharp.Time`, `Sharp.Net` and `Sharp.Data`. Only `Sharp` is imported by default, together with the standard library's extensions on `string`, `int`, `float`, `List`, `Map` and `Set`. A static class in a topic module needs one import, such as `import Sharp.Json.Json;`.

A PHP function is reached through the type it works on, as an extension method such as `name.trim()`. Otherwise it goes through a static class in its module, such as `Math.max(a, b)`. The compiler inlines a standard-library method whose body is one call, so `name.trim()` runs as `trim($name)`. Once the standard library wraps a PHP function, calling that function from `.sharp` code outside the standard library is a compile error that names the method. Functions not wrapped yet stay callable.

`extern` means "implemented outside PHP#", both for a plain PHP declaration and for a native body. A native body is written in Rust behind a C interface and compiled into the engine. Only the standard library declares native bodies. One generator writes the C header from the `.sharp` declarations, and the engine refuses to start if a declared native body is missing.

## Options

### Chosen: topic modules under `Sharp`, PHP functions as inlined methods, one `extern`, native bodies in the engine

```csharp
import Sharp.Json.Json;

public class Webhook
{
    public string name(string raw) => raw.trim();                            // compiles with no import: runs as trim($raw)
    public string body(Map<string, Any> payload) => Json.encode(payload);   // compiles
    public int larger(int a, int b) => Math.max(a, b);                       // compile error: Math is not imported
    public string label(string raw) => trim(raw);                            // compile error: write raw.trim()
}
```

```csharp
// in the standard library's Sharp.Json
public static class Json
{
    public static extern Map<string, Any?> decode(string json);   // compiles: the engine holds the body
}

// in app/Stubs/Mailchimp.sharp
extern Mailchimp uses Http;                                       // compiles: the same keyword for plain PHP
```

A PHP function reads as a method on its value, and each topic costs one import line. The inlined method adds no call when the program runs. One keyword covers every body that lives outside PHP#.

### Rejected: every class directly under `Sharp`, all imported

```csharp
public class Webhook
{
    public string body(Map<string, Any> payload) => Json.encode(payload);   // compiles with no import
    public int larger(int a, int b) => Math.max(a, b);                       // compiles with no import
}
```

Every name the standard library adds enters every file. A file's imports no longer show which topics it uses, and each new standard-library class can be shadowed by a project class with the same name.

### Rejected: an `inline` keyword

```csharp
public static class Math
{
    public static inline int max(int a, int b) => …;   // not PHP#: the author marks each method that inlines
}
```

The author marks each method by hand, and a mark can be missing or wrong. The compiler already sees which bodies are one call.

### Rejected: a second keyword for native bodies

```csharp
public static native Map<string, Any?> decode(string json);   // not PHP#: Java's native
extern Mailchimp uses Http;                                    // plain PHP keeps extern
```

Two keywords name one idea: the body lives outside PHP#.

### Rejected for now: per-package native libraries through FFI

```csharp
[DllImport("libslug")]                               // not PHP#: C#'s DllImport
public static extern string slug(string title);
```

A call through PHP's FFI is slower than a call to a built-in function. PHP disables FFI for web requests by default, and a fault in the library crashes the process.

## Precedent

- **Chosen, namespaces:** .NET's `System.*`, Kotlin's `kotlin.*` with `kotlin.text` and `kotlin.collections` imported by default, and Rust's `std::*`.
- **Chosen, inlining:** the .NET and JVM JITs and rustc, which inline small methods with no keyword.
- **Chosen, `extern`:** C#'s `extern`.
- **Chosen, native bodies:** .NET's `InternalCall`, the JDK's `native` methods and PHP's own built-in functions.
- **Rejected:** Swift, which imports every standard-library name. Kotlin's `inline` keyword. Java's `native` and Kotlin's `external`. C#'s `DllImport`.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)

# 37. Position

## Decision

PHP's magic constants `__DIR__`, `__FILE__`, `__LINE__`, `__FUNCTION__`, `__METHOD__`, `__NAMESPACE__` and `__CLASS__` are removed, along with every other `__Something__` form. `Position`, a standard-library type imported by default, has `file`, `directory`, `line`, `column` and `function`. `function` is the fully qualified dotted name, such as `App.Reports.logSlow`, because a short name is ambiguous across namespaces. `Position.current()` gives the current position in a body. As a parameter's default, it gives the caller's position, and a plain PHP caller gets the position where the parameter is declared.

## Options

### Chosen: one typed value, `Position`

```csharp
const stubs = Position.current().directory + "/stubs";                  // compiles
public static void logSlow(string message, Position caller = Position.current()) { … }
Reports.logSlow("slow query");                                          // compiles; runs: caller is this call's own position
const here = __FILE__;                                                  // parse error
```

One value carries every fact about a place in the source, and the same value reports a caller's position when it is a parameter's default.

### Rejected: PHP's constants, without `__CLASS__`

```csharp
const stubs = __DIR__ + "/stubs";                                       // not PHP#: six separate untyped constants
```

Six constants say where the code itself sits, and none can report where a caller sits.

### Rejected: `#file` tokens

```csharp
public static void logSlow(string message, string file = #file, int line = #line) { … }   // not PHP#
```

`#` starts a comment in PHP, so `#file` would read as one.

### Rejected: caller attributes

```csharp
public static void logSlow(string message, [CallerFilePath] string file = "", [CallerLineNumber] int line = 0) { … }   // not PHP#
```

Each fact needs its own attribute and parameter, and each default states a value, such as `""`, that is never true.

### Rejected names

- **`SourceLocation`:** vague, because "location" says no more than "position" does on its own.
- **`Source`:** reads as a data source or as source text.
- **`StackFrame`:** names a runtime stack frame.
- **`Caller` and `CallSite`:** read wrong in a body, where the position is the code's own.

## Precedent

- **Rejected:** PHP's magic constants.

## Spec

- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 27, PHP# files](../spec.md#27-php-files)

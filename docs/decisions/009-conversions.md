# 9. Converting values

## Decision

`(int)`, `(float)` and `(string)` convert between numbers only. Strings become numbers through `Int.parse` and `Float.parse`, which throw, and `tryParse`, which gives null, all taking `Any?`. Classes narrow with `as`. `(bool)`, `(array)`, `(object)` and PHP's cast aliases do not exist, and `(int)` on an out-of-range float or NaN throws.

## Options

### Chosen: casts between numbers, parsing for strings

```csharp
const qty = Int.parse(request.input("qty"));         // int; runs: throws on "abc", on "12abc" and on null
const maybe = Int.tryParse(request.input("qty"));    // int?; runs: null on "abc"
const cents = (int)(price * 100);                    // runs: truncates toward zero
const whole = (int)ratio;                            // runs: throws ArithmeticError when ratio is NaN or too large
const admin = user as Admin;                         // Admin?; runs: null if user is not one
const flag = request.input("flag") == "1";           // replaces (bool)
const n = (int)request.input("qty");                 // compile error: a string becomes a number only by parsing
const on = (bool)count;                              // compile error: (bool) does not exist
```

### Rejected: PHP's casts

```csharp
const n = (int)"12abc";                              // compiles; runs: 12
const m = (int)"abc";                                // compiles; runs: 0
const whole = (int)ratio;                            // compiles; runs: 0 and a warning when ratio is NaN
const on = (bool)"0.0";                              // compiles; runs: true, while (bool)"0" is false
```

Each cast invents a value where the input has none, and the program runs on with it.

### Rejected: conversion methods only

```csharp
const cents = (price * 100).toInt();                 // not PHP#: a method in place of (int)
const qty = request.input("qty").toInt();            // not PHP#: parsing as a method on string
```

Every `(int)` between numbers that PHP code already writes is rewritten, though a cast between numbers invents no value.

## Precedent

- **Chosen:** C#'s casts between numbers, `int.Parse`, `int.TryParse` and `as`. Kotlin's `toIntOrNull` and `as?`.
- **Rejected, PHP's casts:** PHP.
- **Rejected, conversion methods only:** Kotlin's `toInt()` and Swift's `Int(…)`.

## Spec

[Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

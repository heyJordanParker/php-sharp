# 70. What a template shows

## Decision

A template's `${expr}` accepts `int`, `float`, `string`, `bool`, and a type that declares `string toString()`. A `bool` prints `true` or `false`, and a type with `toString()` prints what it returns. Every other type is a compile error, "A template shows `int`, `float`, `string`, `bool` or a type that declares `toString()`, and `Status` is none of them.", with the offending type's PHP# name in place of `Status`. A literal or narrowed type counts as its base type, so `1|2` is an `int` and `true` is a `bool`. A nullable value such as `int?` is refused until it is checked, as section 24 already states, and the same error names `int?`. `Any`, `Any?`, collections, function values, tuples, unions such as `int|string`, and enums and objects without `toString()` are refused. A plain PHP file keeps PHP's own interpolation, and converts an object whose class declares `toString()` to the same text.

Section 18 later added `toString()`, as Rust's `format!` takes any `Display` type. The compiled class gets PHP's `__toString()`, which calls `toString()`, so plain PHP's `(string) $sku` gives the template's text. PHP# code still never declares `__toString()`.

## Options

### Chosen: `int`, `float`, `string` and `bool`, and a `bool` prints its name

```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // bool: "Paid: true"
const c = `Status: ${status}`; // enum case: compile error
const d = `Items: ${items}`;   // List<int>: compile error
const e = `Order: ${order}`;   // class Order: compile error
```

Each value a template accepts has one text, and the reader knows it from the type. PHP# refuses `__toString()`, so an enum, a collection or an object has no text of its own. The error names the type when the file compiles, where PHP would stop at runtime with "Cannot cast object of type `Order` to `string` because it does not implement `Stringable`".

### Rejected: only numbers and strings

```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // would be a compile error
const c = `Status: ${status}`; // enum case: compile error
const d = `Items: ${items}`;   // List<int>: compile error
const e = `Order: ${order}`;   // class Order: compile error
```

Every `bool` would need `isPaid ? "true" : "false"` written out, for a text the language can give one way.

### Rejected: anything prints a default text

```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // bool: "Paid: true"
const c = `Status: ${status}`; // would compile: a default text for the case
const d = `Items: ${items}`;   // would compile: a default text for the list
const e = `Order: ${order}`;   // would compile: a default text for the object
```

A default text, such as JavaScript's `[object Object]`, reaches the page or the log, and the mistake shows only when someone reads the output. PHP has no default text for these values, so the language would have to define one for every kind of value.

## Precedent

- **Chosen:** Kotlin and C#, whose templates print a `bool` by its name.
  - Kotlin's [strings](https://kotlinlang.org/docs/strings.html#string-templates): "In string templates and string concatenation, Kotlin converts values to strings automatically." A `bool` prints `true`.
  - C#'s [interpolated strings](https://learn.microsoft.com/en-us/dotnet/csharp/language-reference/tokens/interpolated): "the compiler replaces items with interpolation expressions by the string representations of the expression results." A `bool`'s is [`Boolean.ToString`](https://learn.microsoft.com/en-us/dotnet/api/system.boolean.tostring): "This method returns the constants "True" or "False"."
  - Both print any other value through its `toString()` or `ToString()`. PHP# prints a type that declares `toString()` the same way, and refuses the rest, whose text is not built in.
- **Rejected, only numbers and strings:** Elm, which has no interpolation. Its `++` joins two strings, and its [`String` module](https://github.com/elm/core/blob/master/src/String.elm) converts numbers with `fromInt` and `fromFloat` and has no `fromBool`.
- **Rejected, anything prints a default text:** Swift, whose default interpolation takes a value of any type ([SE-0228](https://github.com/swiftlang/swift-evolution/blob/main/proposals/0228-fix-expressiblebystringinterpolation.md), `appendInterpolation<T>(_ value: T)`), and JavaScript, whose [template literals](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals) "coerce their expressions directly to strings".

## Spec

- [Section 18, Strings](../spec.md#18-strings)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

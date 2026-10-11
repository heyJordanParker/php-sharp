# 73. An `int` where a `float` is expected

## Decision

In PHP# code, an `int` value where a `float` is expected is a compile error that quotes the one fix for the code written, such as "`int` is not `float`: write 0.0" for `0` and "`int` is not `float`: convert with (float) count" for `count`. The rule covers every place a value lands, such as an assignment, an argument, a return, a property write, a list or map element, or a generic `T` that is `float`. A literal `0` is an `int`, as in Kotlin. Arithmetic that mixes an `int` and a `float`, such as `count * 1.5`, gives a `float` and compiles. Decision 74 sets the rule for an `int` from plain PHP: it becomes a `float` wherever PHP# expects a `float`.

## Options

### Chosen: refuse the int

```csharp
float total = 0;                     // compile error: `int` is not `float`: write 0.0
float total = 0.0;                   // compiles
float price = count;                 // compile error: `int` is not `float`: convert with (float) count
float price = (float) count;         // compiles: the conversion is written out
Cell<float> cell = Cell.empty();
cell.put(2);                         // compile error: `int` is not `float`: write 2.0
cell.put(2.0);                       // compiles
cell.put((float) n);                 // compiles
bool same = cell.get() == 2.0;       // true
```

Every change from `int` to `float` is written where it happens, so a reader sees it in the code.

### Rejected: an int converts to a float, as PHP converts it

```csharp
float total = 0;                     // would compile
float total = 0.0;                   // compiles
float price = count;                 // would compile
float price = (float) count;         // compiles: the conversion is written out
Cell<float> cell = Cell.empty();
cell.put(2);                         // would compile: the cell holds 2.0
cell.put(2.0);                       // compiles
cell.put((float) n);                 // compiles
bool same = cell.get() == 2.0;       // true
```

The conversion happens without a mark in the code. A `float` holds integers exactly only up to 2^53, so a larger `int` changes its value on the way, and nothing on the line shows it.

## Precedent

- **Chosen:** Kotlin and Swift, which both refuse an integer value where a floating-point type is expected.
  - Kotlin's [Numbers, Type conversion](https://kotlinlang.org/docs/numbers.html#type-conversion): "Numeric types are not subtypes of one another. Kotlin requires explicit conversions to avoid silent data loss and unexpected behavior." Its sample refuses a literal: `val one: Double = 1 // Error: initializer type mismatch`. Under [Mixed numeric expressions](https://kotlinlang.org/docs/numbers.html#mixed-numeric-expressions): "Kotlin does not support implicit conversion for assignments or function arguments. However, you can combine different numeric types in arithmetic expressions."
  - Swift's [The Basics, Integer and Floating-Point Conversion](https://docs.swift.org/swift-book/documentation/the-swift-programming-language/thebasics/#Integer-and-Floating-Point-Conversion): "Conversions between integer and floating-point numeric types must be made explicit." Swift differs on literals and on arithmetic. A literal takes the expected type, because "number literals don't have an explicit type in and of themselves", so `let total: Double = 0` compiles. Arithmetic on an `Int` and a `Double` is refused: "Without this conversion in place, the addition would not be allowed."
- **Rejected:** PHP, whose strict mode still converts. The [PHP manual, Strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict): "The only exception to this rule is that an int value will pass a float type declaration."

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

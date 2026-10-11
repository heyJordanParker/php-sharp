# 73. An int where a float is expected

## Decision

In PHP# code, an `int` value where a `float` is expected is a compile error. That covers every place a declared type takes a value: declarations, assignments, property and parameter defaults, arguments and returns. A literal is refused too: write `5.0`, or convert with `(float) n`. Arithmetic that mixes an `int` and a `float`, as in `count * 1.5`, gives a `float` and compiles. `.php` files keep PHP's rules.

## Options

### Chosen: refuse the int

```csharp
float price = 5;               // compile error: `5` is an int, and a float is expected. Write `5.0`.
float price = 5.0;             // compiles
float total = count;           // compile error: `count` is an int, and a float is expected. Convert it with `(float) count`.
float total = (float) count;   // compiles
float half = count * 1.5;      // compiles: an int times a float is a float
```

Every change from `int` to `float` is written where it happens, so a reader sees it in the code. The value keeps the type it was declared with, as every other type does in PHP# code.

### Rejected: an int converts to a float, as PHP converts it

```csharp
float price = 5;               // compiles: 5 becomes 5.0
float price = 5.0;             // compiles
float total = count;           // compiles: count becomes a float
float total = (float) count;   // compiles
float half = count * 1.5;      // compiles: an int times a float is a float
```

The conversion happens without a mark in the code. A `float` holds integers exactly only up to 2^53, so a larger `int` changes its value on the way, and nothing on the line shows it.

## Precedent

- **Chosen:** Kotlin and Swift, which both refuse an integer value where a floating-point type is expected.
  - Kotlin's [Numbers, Type conversion](https://kotlinlang.org/docs/numbers.html#type-conversion): "Numeric types are not subtypes of one another. Kotlin requires explicit conversions to avoid silent data loss and unexpected behavior." Its sample refuses a literal: `val one: Double = 1 // Error: initializer type mismatch`. Under [Mixed numeric expressions](https://kotlinlang.org/docs/numbers.html#mixed-numeric-expressions): "Kotlin does not support implicit conversion for assignments or function arguments. However, you can combine different numeric types in arithmetic expressions."
  - Swift's [The Basics, Integer and Floating-Point Conversion](https://docs.swift.org/swift-book/documentation/the-swift-programming-language/thebasics/#Integer-and-Floating-Point-Conversion): "Conversions between integer and floating-point numeric types must be made explicit." Swift differs on the other two lines. A literal takes the expected type, because "number literals don't have an explicit type in and of themselves", so `let price: Double = 5` compiles. Arithmetic on an `Int` and a `Double` is refused: "Without this conversion in place, the addition would not be allowed."
- **Rejected:** PHP, whose strict mode still converts. The [PHP manual, Strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict): "The only exception to this rule is that an int value will pass a float type declaration."

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

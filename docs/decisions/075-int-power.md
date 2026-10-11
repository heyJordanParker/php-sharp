# 75. `int ** int`

## Decision

In PHP# code, `int ** int` is an `int`, and so is `int **= int`. A negative exponent throws `ArithmeticError`, "Integer raised to a negative power", at the operation, as an overflow throws (section 24). A negative literal exponent is a compile error: "`2 ** -1` is an `int` raised to a negative power, which throws: write `2.0 ** -1`". A `float` on either side gives a `float`, so `2.0 ** -1` and `2 ** -1.0` are `0.5`. Plain PHP code keeps PHP's rule, where `2 ** -1` is `0.5`.

PHP# keeps the `**` operator.

## Options

The type of `int ** int`:

### Chosen: an `int`, and a negative exponent throws

```csharp
let area = side ** 2;        // int
return 2 ** -1;              // compile error: `2 ** -1` is an `int` raised to a negative power, which throws: write `2.0 ** -1`
return a ** b;               // int. Throws ArithmeticError at runtime when b < 0
return 2.0 ** -1;            // 0.5
```

`10 ** decimals` is an `int`, as the code reads. A negative exponent has no `int` result, so it throws instead of turning the `int` into a `float`, which is the change section 24 already refuses for an overflow. A reader who wants a fraction writes the `float` on the line.

### Rejected: `**` always gives a `float`

```csharp
let area = side ** 2;        // float
return 2 ** -1;              // 0.5
return a ** b;               // float
```

Every whole-number power, such as `10 ** decimals`, needs a conversion back to `int`. A `float` holds integers exactly only up to 2^53, so a large power loses digits before the conversion.

### Rejected: an `int` for an exponent of 0 or more, a `float` for a negative one

```csharp
let area = side ** 2;        // int
return 2 ** -1;              // float: 0.5
return a ** b;               // int|float: the sign of b is unknown
```

The type depends on a value. Most exponents are not literals, so most powers are `int|float`, and every caller narrows the result before it uses it.

How PHP# writes a power:

### Chosen: keep `**`

```csharp
let factor = 10 ** decimals;
factor **= 2;
```

`**` is the only arithmetic operator a PHP developer would otherwise lose.

### Rejected: power as a method on the number

```csharp
let factor = 10.pow(decimals);
```

A PHP developer rewrites every `**` and `**=`, and PHP# gains a method where PHP already has an operator.

## Precedent

- **Chosen, an `int` that throws on a negative exponent:** Rust and C#'s `BigInteger.Pow`.
  - Rust's `i64::pow` takes its exponent as a `u32`, so a negative exponent does not compile. A floating-point power is a separate method on `f64`, `powi` or `powf`.
  - C#'s `BigInteger.Pow(BigInteger value, int exponent)` returns a `BigInteger` and throws `ArgumentOutOfRangeException` when the exponent is negative.
- **Rejected, always a `float`:** C#'s `Math.Pow`, Java's `Math.pow`, Kotlin and JavaScript.
  - C#'s `Math.Pow` and Java's `Math.pow` take two `double`s and return a `double`.
  - Kotlin's `pow` is defined on `Double` and `Float`, not on `Int`.
  - JavaScript's `**` on numbers gives a double-precision number, so `2 ** -1` is `0.5`.
- **Rejected, the type follows the exponent's sign:** Python and PHP.
  - Python's `2 ** 3` is the `int` 8, and `2 ** -1` is the `float` 0.5.
  - PHP's `2 ** 3` is `int(8)`, and `2 ** -1` is `float(0.5)`. PHP also gives a `float` when the result overflows.
- **Chosen, keep `**`:** PHP, Python, Ruby, JavaScript and Fortran all write a power as `**`.
- **Rejected, power without an operator:** Rust, Kotlin, C#, Swift, Java and Go have no power operator. Rust writes `i64::pow`, Kotlin `Double.pow`, C# `Math.Pow`, Swift `pow`, Java `Math.pow`, and Go `math.Pow`.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

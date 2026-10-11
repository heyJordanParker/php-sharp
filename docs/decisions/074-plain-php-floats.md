# 74. An `int` from plain PHP where PHP# expects a `float`

## Decision

An `int` from plain PHP becomes a `float` wherever PHP# expects a `float`, such as a parameter, a generic `T` that is `float`, the elements of a `List<float>` or a `Set<float>`, and the values of a `Map<K, float>`. PHP# code then sees what its type says, so `cell.get() == 2.0` is true. Decision 73 said only that plain PHP keeps PHP's rules. PHP's rule covers a `float` parameter, and PHP has no generics or typed collections, so it said nothing for a `List<float>` or a `T` that is `float`. Decision 73's rule for PHP# code is unchanged: there an `int` where a `float` is expected is a compile error.

## Options

### Chosen: every int becomes a float

```php
<?php declare(strict_types=1);

$price->set(5);              // PHP# set(float value): value is 5.0
$cell->put(2);               // PHP# Cell<float>: the cell holds 2.0
$report->total([1, 2, 3]);   // PHP# total(List<float> amounts): amounts is [1.0, 2.0, 3.0]
```

One rule covers every place a `float` is expected, and it is the rule PHP already applies to a `float` parameter. A plain PHP caller writes `5` or `[1, 2, 3]` as it does for plain PHP code, and PHP# code gets the `float` its type declares. A `float` holds integers exactly only up to 2^53, so a larger `int` changes its value on the way, as it does at a plain PHP `float` parameter.

### Rejected: convert only at a plain `float` parameter

```php
<?php declare(strict_types=1);

$price->set(5);              // value is 5.0
$cell->put(2);               // the cell holds 2.0: T is float, so this is a float parameter
$report->total([1, 2, 3]);   // TypeError: a List<float> can't hold int 1
```

The same `int` passes one PHP# method and throws at the next, depending on whether the `float` is a parameter or an element of a collection. Plain PHP code cannot see which one it is calling.

### Rejected: every int throws

```php
<?php declare(strict_types=1);

$price->set(5);              // throws TypeError: 5 is an int
$cell->put(2);               // throws TypeError: 2 is an int
$report->total([1, 2, 3]);   // throws TypeError: the elements are ints
```

A call that works against a plain PHP method with a `float` parameter throws against the same PHP# method, because PHP itself lets an `int` pass a `float` declaration. No language does this at its boundary with another language.

### Rejected: ints pass through unchanged

```php
<?php declare(strict_types=1);

$price->set(5);              // value is 5.0: PHP's own rule converts at a float parameter
$cell->put(2);               // the cell holds the int 2, under the type Cell<float>
$report->total([1, 2, 3]);   // amounts holds the ints 1, 2 and 3, under the type List<float>
```

The declared type is false. `cell.get()` declares a `float` and returns an `int`, so PHP# code cannot trust a type whose value came from plain PHP.

## Precedent

- **Chosen:** Swift, Rust's PyO3, serde_json, and PHP, which all turn an integer into a floating-point number where the other side expects one, including inside a list.
  - Swift's [SE-0170, NSNumber bridging and Numeric types](https://github.com/swiftlang/swift-evolution/blob/main/proposals/0170-nsnumber_bridge.md), implemented in Swift 4.0: "`as?` for NSNumber should mean "Can I safely express the value stored in this opaque box called a NSNumber as the value I want?"." Its Example 5 casts a JSON payload `as? [String : Double]` with the comment "But the responses are still integral!". An `NSArray` of the integer `NSNumber`s 1, 2 and 3 cast `as? [Double]` gives `[1.0, 2.0, 3.0]`.
  - PyO3, Rust's Python bindings, maps a Python `list[T]` to `Vec<T>` and a `float` to `f64` in its [conversion table](https://pyo3.rs/main/conversions/tables.html). Its [`f64` extraction](https://docs.rs/pyo3/0.29.3/src/pyo3/types/float.rs.html#122) calls `ffi::PyFloat_AsDouble`, which the [Python C API](https://docs.python.org/3/c-api/float.html#c.PyFloat_AsDouble) documents as: "If pyfloat is not a Python floating-point object but has a `__float__()` method, this method will first be called to convert pyfloat into a float." So the Python list `[1, 2, 3]` extracted as `Vec<f64>` gives `[1.0, 2.0, 3.0]`.
  - serde_json reads JSON through serde, whose [`f64` deserializer](https://docs.rs/serde_core/1.0.229/src/serde_core/de/impls.rs.html#458-464) accepts every integer: `num_as_self!(i8:visit_i8 i16:visit_i16 i32:visit_i32 i64:visit_i64);` and `num_as_self!(u8:visit_u8 u16:visit_u16 u32:visit_u32 u64:visit_u64);`. serde_json's [`Number::as_f64`](https://docs.rs/serde_json/1.0.152/serde_json/struct.Number.html#method.as_f64) "Represents the number as f64 if possible." So the JSON `[1, 2, 3]` read into a `Vec<f64>` gives `[1.0, 2.0, 3.0]`.
  - The [PHP manual, Strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict): "The only exception to this rule is that an int value will pass a float type declaration."
- **Rejected, convert only at a plain `float` parameter:** Kotlin called from Java, and Python's mypy.
  - A Kotlin `Double` parameter is a Java `double`, as in the [Kotlin docs' generated signature](https://kotlinlang.org/docs/java-to-kotlin-interop.html#overloads-generation) `Circle(int centerX, int centerY, double radius)`. Java converts an `int` argument there, because [JLS 5.3, Invocation Contexts](https://docs.oracle.com/javase/specs/jls/se21/html/jls-5.html#jls-5.3) allows "a widening primitive conversion". A `List<Double>` parameter takes no `List<Integer>`, because, in [Oracle's Java tutorial](https://docs.oracle.com/javase/tutorial/java/generics/inheritance.html), "Box<Integer> is not a subtype of Box<Number> even though Integer is a subtype of Number."
  - [mypy, Duck type compatibility](https://mypy.readthedocs.io/en/stable/duck_type_compatibility.html): "mypy considers an `int` object to be valid whenever a `float` object is expected." Lists stay strict, under [Invariance vs covariance](https://mypy.readthedocs.io/en/stable/common_issues.html#invariance-vs-covariance): "Most mutable generic collections are invariant."
- **Rejected, every int throws:** no language refuses an integer at its boundary with another language.
- **Rejected, ints pass through unchanged:** TypeScript with JavaScript, and Java's raw types.
  - The [TypeScript handbook, Erased Types](https://www.typescriptlang.org/docs/handbook/2/basic-types.html#erased-types): "Type annotations never change the runtime behavior of your program."
  - [JLS 4.8, Raw Types](https://docs.oracle.com/javase/specs/jls/se21/html/jls-4.html#jls-4.8): "The use of raw types is allowed only as a concession to compatibility of legacy code." [JLS 4.12.2](https://docs.oracle.com/javase/specs/jls/se21/html/jls-4.html#jls-4.12.2) names the result: "It is possible that a variable of a parameterized type will refer to an object that is not of that parameterized type. This situation is known as heap pollution."

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)
- [Section 11, Generics](../spec.md#11-generics)

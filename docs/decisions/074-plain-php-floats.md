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

### Rejected: convert at a `float` parameter, throw in a collection

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

- **Chosen:** PHP, which turns an integer into a floating-point number where a `float` is expected.
  - The [PHP manual, Strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict): "The only exception to this rule is that an int value will pass a float type declaration."

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)
- [Section 11, Generics](../spec.md#11-generics)

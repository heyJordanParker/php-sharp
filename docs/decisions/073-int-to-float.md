# 73. An `int` where a `float` is expected

## Decision

In PHP# code, an `int` value where a `float` is expected is a compile error that quotes the one fix for the code written, such as "`int` is not `float`: write 0.0" for `0` and "`int` is not `float`: convert with (float) count" for `count`. The rule covers every place a value lands, such as an assignment, an argument, a return, a property write, a list or map element, or a generic `T` that is `float`. A literal `0` is an `int`, whatever type the place expects. Arithmetic that mixes an `int` and a `float`, such as `count * 1.5`, gives a `float` and compiles. Decision 74 sets the rule for an `int` from plain PHP: it becomes a `float` wherever PHP# expects a `float`.

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

- **Rejected:** PHP, whose strict mode still converts. The [PHP manual, Strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict): "The only exception to this rule is that an int value will pass a float type declaration."

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

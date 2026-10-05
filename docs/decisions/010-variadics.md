# 10. Any number of arguments

## Decision

A method takes any number of arguments with PHP's `int ...values`, which arrive as a `List<int>`. A call spreads an existing list with `...list`.

## Options

### Chosen: PHP's `...`

```csharp
public static int sum(int ...values) { … }   // values arrives as a List<int>

Money.sum(1, 2, 3);       // compiles
Money.sum(...prices);     // compiles; spreads an existing list
max(...prices);           // compiles; spreads into plain PHP too
Money.sum(prices);        // compile error: a List<int> is not an int
```

The `...` at the call shows whether a list is one argument or many.

### Rejected: C#'s `params`

```csharp
public static void write(params List<Any?> values) { … }   // not PHP#: C#'s params

Log.write(items);           // compiles; runs: C# spreads items into the values, though the call reads as one value
Log.write(items, total);    // compiles; runs: two values, the first of them a list
```

When the argument is itself a list, the call reads both ways, and the compiler picks one.

### Rejected: no variadics

```csharp
public static int sum(List<int> values) { … }

Money.sum([1, 2, 3]);     // compiles: the caller builds the list
max(...prices);           // compile error: there is no spread
```

PHP's own variadic functions, such as `max` and `sprintf`, take one argument per value, so PHP# code could not pass them a list, nor override a plain PHP method declared with `...`.

## Precedent

- **Chosen:** PHP, TypeScript, Go and Java.
- **Rejected, `params`:** C#.
- **Rejected, no variadics:** Rust.

## Spec

[Section 7, Methods](../spec.md#7-methods)

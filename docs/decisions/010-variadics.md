# 10. Any number of arguments

## Decision

A method takes any number of arguments with PHP's `int ...values`, which arrive as a `List<int>`. A call spreads an existing list with `...list`. A spread of unknown length fills only a `...` parameter, and a list literal's spread fills parameters as its elements written out do.

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

### Chosen: a spread of unknown length fills only a `...` parameter

```csharp
public static List<T> pair<T>(T a, T b) => [a, b];
public static int tagged(int count, string ...tags) { … }

Pairs.pair(...numbers);       // compile error: numbers may not fill a and b. A spread of unknown length fills only a ... parameter
Pairs.pair(...[900, 2900]);   // compiles: a list literal's length is known
Pairs.tagged(...tags);        // compile error: tags may not fill count
Pairs.tagged(1, ...tags);     // compiles
```

The checker can't know which parameters a list of unknown length fills, so it can't check them, and a list too short for them fails only where the call runs. A `...` parameter takes any number of values, so every list fits it.

### Rejected: a spread of any length into any parameter

```csharp
Pairs.pair(...numbers);       // not PHP#: PHP and Python accept it, and throw where it runs if numbers holds too few values
```

## Precedent

- **Chosen:** PHP, TypeScript, Go and Java.
- **Chosen, a spread of unknown length only into a `...` parameter:** Kotlin, TypeScript, Go, C# and Java.
- **Rejected, `params`:** C#.
- **Rejected, no variadics:** Rust.
- **Rejected, a spread of any length into any parameter:** PHP and Python, which throw at run time.

## Spec

[Section 7, Methods](../spec.md#7-methods)

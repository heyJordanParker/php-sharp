# 71. Where a function type writes its effects

## Decision

A function type lists its effects inside its brackets, after its parameter types: `Function<Charge(Cart) uses Http>`. The effects belong to the type, so they go wherever the type goes: after `?`, as a type argument, and inside another function type's parameters. A comma after an effect names another effect, up to the type's `>`. The old placement, `Function<Charge(Cart)> uses Http charge`, is a parse error that shows the new form: "A function type lists its effects inside its brackets, as in `Function<Charge(Cart) uses Http>`." `uses` stays a name everywhere else, so a field, parameter or local named `uses` keeps compiling.

## Options

### Chosen: `uses` inside the brackets, after the parameter types

```csharp
Function<Charge(Cart) uses Http> charge;                        // compiles
Function<Charge(Cart) uses Http>? fallback;                      // compiles
Map<string, Function<Charge(Cart) uses Http, Mail>> handlers;    // compiles: Http and Mail are both effects
Function<int(Function<int(int) uses Http>)> apply;               // compiles
```

The type ends at its `>`, so everything about it sits inside its brackets, and each line reads one way. The `?` stays right after the type, as on every other type. Inside `Map<…>`, `Mail` can only be an effect, because the function type's `>` has not closed yet.

### Rejected: `uses` after the closing `>`

```csharp
Function<Charge(Cart)> uses Http charge;                         // would compile
Function<Charge(Cart)>? uses Http fallback;                      // reads two ways: the same type is also `Function<Charge(Cart)> uses Http? fallback`, where `?` reads as part of `Http`
Map<string, Function<Charge(Cart)> uses Http, Mail> handlers;    // reads two ways: `Mail` is a second effect or a third type argument of `Map`, so it needs parentheses
Function<int(Function<int(int)> uses Http)> apply;               // would compile, but the effect of the inner type sits among the outer type's parameters
```

The effects sit outside the type they describe. Inside a type argument list, a comma after an effect can start the next type argument, so a type with two effects needs parentheses there. Next to `?`, the nullable mark can go before or after the effects, and the second spelling reads as a nullable effect.

## Precedent

- **Chosen:** Swift's `throws` and Koka's effect rows, which both write a function's effects inside its function type.
  - Swift's [Types reference, Function Type](https://docs.swift.org/swift-book/documentation/the-swift-programming-language/types/): "Function types for functions that can throw or rethrow an error must include the `throws` keyword." Its grammar puts `throws` between the parameters and the result: "*function-type* → *attributes*? *function-type-argument-clause* `async`? *throws-clause*? `->` *type*", as in `(Cart) throws -> Charge`.
  - Koka's [book, Effect types](https://koka-lang.github.io/koka/doc/book.html): "A novel part about Koka is that it automatically infers all the _side effects_ that occur in a function." Under "Combining effects": "We can write such combination as a _row_ of effects as `<div,exn,ndet>`." The row is part of the function type, between the arrow and the result, as in `int -> exn int`.
- **Rejected:** Java's `throws`, which a method writes after its parameter list, outside any type. The [Java Language Specification, section 8.4](https://docs.oracle.com/javase/specs/jls/se21/html/jls-8.html#jls-8.4), writes "MethodHeader: Result MethodDeclarator [Throws]". Java has no function type with effects: `java.util.function.Function` cannot declare a checked exception.

## Spec

- [Section 14, Functions as values](../spec.md#14-functions-as-values)
- [Section 29, Effects](../spec.md#29-effects)

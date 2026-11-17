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

## Spec

- [Section 14, Functions as values](../spec.md#14-functions-as-values)
- [Section 29, Effects](../spec.md#29-effects)

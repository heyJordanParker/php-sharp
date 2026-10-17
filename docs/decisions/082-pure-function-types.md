# 82. Pure function types

## Decision

`uses none` states that a function type is pure. It is the empty effect list, and goes wherever a function type's `uses` goes. Decision 79 lets a method keep only a function whose type states its effects, so `uses none` lets a setter, a registration method or a builder keep a pure one.

- **A `uses none` parameter takes only a pure function.** Passing it a function with an effect is a compile error at the call, which names the effect, and the method may store, return and capture the parameter.
- **A function type without `uses` stays pure everywhere but a method's parameter,** so `uses none` there says nothing new.
- **`uses none` beside another effect is a compile error,** and so is `uses none` on a method, which is pure without `uses`.
- **An override takes on effects wherever the method it overrides does.** A call through the overridden method may pass a function with any effect, so the override's parameter takes on each caller's effects too, and may not state them, `uses none` included. A plain PHP `callable` or `Closure` parameter counts as one that takes on effects.

```csharp
public class Pricing
{
    private Function<int(int)> rate = (cents) => cents;          // a field: no uses means pure, as today

    public void setRate(Function<int(int) uses none> rate)
        => this.rate = rate;                                      // compiles: the type states it's pure

    public void apply(Function<int(int)> step)
        => this.rate = step;                                      // compile error: step takes on its caller's effects, so it can't be stored; take a Function<int(int) uses none>
}

pricing.setRate((cents) => cents * 2);                            // compiles: a pure lambda
pricing.setRate((cents) => this.rates.convert(cents));            // compile error: setRate takes a pure function, and this one has Http

public interface Rater
{
    void setRate(Function<int(int)> rate);                        // takes on each caller's effects
}

public class PureRater : Rater
{
    public void setRate(Function<int(int) uses none> rate) { }    // compile error: a call through Rater may pass a function with any effect
}
```

## Options

### Chosen: `uses none`

`uses none` reads as the empty list `uses` already writes, so there is no new idea to learn. The caller gets the error at the call that passes a function with an effect.

### Rejected: no way to write it, so a pure function is kept only through a constructor

```csharp
public void setRate(Function<int(int)> rate) => this.rate = rate;    // compile error: rate takes on its caller's effects
Pricing pricing = new Pricing((cents) => cents * 2);                  // a constructor property keeps it
```

Setters, registration methods and builders are common, and none of them could keep a pure function.

### Rejected: an override that states effects its overridden parameter takes on

```csharp
public class PureRater : Rater
{
    public void setRate(Function<int(int) uses none> rate) => this.rate = rate;   // would compile
}
```

`rater.setRate(f)` through `Rater` takes on `f`'s effects only for the call, and `PureRater` keeps `f` and runs its effects later, where no caller sees them.

## Precedent

- **Effects in a function's type:** Swift's `throws`, Kotlin's `suspend` and Rust's `Fn`, each of which a function type without it can't do.
- **An override takes every argument the method it overrides takes:** Java, C#, Kotlin and Swift require the same parameter types, and PHP allows wider ones.

## Spec

[Section 29, Effects](../spec.md#29-effects)

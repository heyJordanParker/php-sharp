# 79. Effects of a function parameter

## Decision

A method takes on the effects of each function it is given, with nothing written. A parameter whose function type states no effects, as `f` in `map(Function<TResult(T)> f)`, has at each call the effects of the function that call passes, and the method has its body's own effects plus those.

- **Such a parameter runs only inside the call.** Storing it in a field, a collection or a new object, returning it, and capturing it in a lambda that escapes are compile errors that name the type to take instead. Calling it, and passing it on to another method's function parameter, are allowed. A local may hold it, and the local is held to the same rules.
- **A parameter whose function type states its effects keeps them as a cap.** `Function<Charge(Cart) uses Http>` accepts a function with `Http` or no effects, the method has `Http` from calling it, and the parameter may be stored, returned and captured.
- **A function parameter in `uses` is a parse error that says to delete it,** with or without a body.
- **Passing the parameter to a plain PHP `callable` or `Closure` parameter keeps it inside the call.** A plain PHP library that keeps the callable is plain PHP running it later, and PHP# trusts the plain PHP side through its `extern`, as it does everywhere else. So `Events.on(listener)` takes on its listener's effects when it registers, through `Internal.listen`.

```csharp
// in the standard library's List<T>
public List<TResult> map<TResult>(Function<TResult(T)> f);          // map takes on the effects of the f it calls
public Charge charge(Cart cart, Function<Cart(Cart)> prepare)
    => this.gateway.charge(prepare(cart));                            // Http, plus prepare's effects
public Holder keep(Function<string()> body)
    => new Holder(body);                                              // compile error: body can't be stored; take a Function<string() uses Environment>
carts.map(c => this.gateway.charge(c));                               // has Http
```

## Options

### Chosen: the method takes on its functions' effects, and an unstated function never escapes

A call already shows what it passes, so the effects come from the call, as they do for a call of plain PHP. The function runs only while the call runs, so the effects the call takes on are every effect it can have. A function the method keeps states its effects in its type, which the checker holds each caller to.

### Rejected: `uses f`, as decision 6 chose

```csharp
public List<TResult> map<TResult>(Function<TResult(T)> f) uses f;
public Charge charge(Cart cart, Function<Cart(Cart)> prepare) uses prepare => this.gateway.charge(prepare(cart));
```

Every method that takes a function repeats its parameter's name, and a method that forgets it refuses every function with an effect. Code with a body otherwise writes no `uses`, so `uses f` was the only `uses` a body wrote.

### Rejected: a function parameter that takes on effects and may escape

```csharp
public Holder keep(Function<string()> body) => new Holder(body);   // would compile
```

`keep` has no effect at its call, and the `Holder` runs `body`'s `Environment` later, where no caller sees it. Laws would reason about code that reaches `Environment`.

## Precedent

- **Taking on effects:** Swift's `rethrows`, Kotlin's inline lambdas and Hack's `ctx $f`, the precedents of decision 6, which this keeps without the written name.
- **A function parameter that never escapes:** Swift. The [Swift book, Escaping Closures](https://docs.swift.org/swift-book/documentation/the-swift-programming-language/closures/#Escaping-Closures): "A closure is said to escape a function when the closure is passed as an argument to the function, but is called after the function returns." A closure parameter escapes only when its type is marked `@escaping`. Kotlin's [inline functions](https://kotlinlang.org/docs/inline-functions.html): "Inlinable lambdas can only be called inside inline functions or passed as inlinable arguments."

## Spec

[Section 29, Effects](../spec.md#29-effects)

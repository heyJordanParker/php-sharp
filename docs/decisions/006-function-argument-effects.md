# 6. Effects of a function argument

## Decision

A method that takes a function can have that function's effects. It writes `uses f`, where `f` is one of its function-typed parameters, with or without a body.

## Options

### Chosen: `uses f`

```csharp
// in the standard library's List<T>
public List<TResult> map<TResult>(Function<TResult(T)> f) uses f;

carts.map(c => c.total);                  // compiles; pure, so laws can reason about it
carts.map(c => this.gateway.charge(c));   // compiles; has Http, from the function passed as f

// a method with a body: Http from this.gateway, plus the effects of prepare
public Charge charge(Cart cart, Function<Cart(Cart)> prepare) uses prepare => this.gateway.charge(prepare(cart));
```

### Rejected: function arguments must be pure

```csharp
public List<TResult> map<TResult>(Function<TResult(T)> f);   // f must be pure

carts.map(c => c.total);                  // compiles
carts.map(c => this.gateway.charge(c));   // compile error: map takes a pure function
```

Every loop with an effect is written out by hand instead of with the collection methods.

### Rejected: two copies of every method

```csharp
public List<TResult> map<TResult>(Function<TResult(T)> f);
public List<TResult> mapWithHttp<TResult>(Function<TResult(T) uses Http> f) uses Http;

carts.map(c => this.gateway.charge(c));          // compile error: map takes a pure function
carts.mapWithHttp(c => this.gateway.charge(c));  // compiles; has Http
```

Each effect needs its own copy of every method that takes a function, and each library repeats the copies.

### Rejected: function arguments may do anything

```csharp
public List<TResult> map<TResult>(Function<TResult(T)> f);   // f may have any effect

carts.map(c => c.total);                  // compiles; counts as having every effect
```

No code that calls a collection method is ever pure, so laws cannot reason about list code.

## Precedent

- **Chosen:** Swift's `rethrows`, Kotlin's inline lambdas, and Hack's `ctx $f`.
- **Rejected, two copies:** Java. `Stream.map` takes a `Function` that cannot throw a checked exception, so throwing code needs a second interface or a wrapper.

## Spec

[Section 29, Effects](../spec.md#29-effects)

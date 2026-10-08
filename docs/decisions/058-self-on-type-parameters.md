# 58. `Self` on a type parameter

## Decision

`Self` on a receiver typed by a type parameter is that type parameter. In `TBox refill<TBox : Box<int>>(TBox box)`, `box.withValue(5)` returns a `TBox`, not `Box<int>`.

## Options

### Chosen: `Self` becomes the type parameter

```csharp
public class Box<T>
{
    public required Box(public T value { get; }) { }
    public Self withValue(T value) => new Self(value);
}

public TBox refill<TBox : Box<int>>(TBox box) => box.withValue(5);   // compiles: withValue returns a TBox
```

`Self` already means the class a call actually runs on (section 25). On a `TBox` receiver, that class is whatever `TBox` is, so correct code needs no cast.

### Rejected: the declared return type wins

```csharp
public TBox refill<TBox : Box<int>>(TBox box) => box.withValue(5);                                  // would be a compile error: withValue returns Box<int>
public TBox refill<TBox : Box<int>>(TBox box) => box.withValue(5) as TBox ?? throw new LogicException();   // would compile
```

Java resolves the call against the bound, so the result is `Box<int>`. Correct code then converts the value it already has, with a check that can never fail.

## Precedent

- **Chosen:** Rust, where `t.clone()` on a `T: Clone` returns a `T`.
- **Rejected:** Java, where a method declared on the bound returns the bound's type.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

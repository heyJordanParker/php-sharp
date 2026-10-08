# 60. `typeof` on any type argument

## Decision

`typeof(TItem)` works for any type argument, including `int`. It is a `Class<TItem>`, which can create objects, only when the bound is a class.

## Options

### Chosen: `typeof(TItem)` for every type argument

```csharp
public TItem make<TItem : Element>(string key)
{
    const type = typeof(TItem);
    return new (type)(key);                                      // compiles: TItem's bound is a class
}

public void log<TItem>(TItem value) { Log.info("type", ["class": typeof(TItem)]); }   // compiles, also when TItem is int

public TItem blank<TItem>()
{
    const type = typeof(TItem);
    return new (type)();                                         // compile error: TItem's bound is not a class
}
```

Every type argument reaches the running program (decision 7), so generic code can name it whatever it is. Creating an object needs a class, so only a class bound allows `new`.

### Rejected: `typeof` only on a type parameter with a class bound

```csharp
public void log<TItem>(TItem value) { Log.info("type", ["class": typeof(TItem)]); }   // would be a compile error: TItem may be int
```

Generic code over any type, such as a cache or a logger, could not name the type it holds, though the running program knows it.

## Precedent

- **Chosen:** C#, where `typeof(T)` works for every `T`, and `new T()` needs the `new()` constraint.
- **Rejected:** Hack's `classname<T>`, which names classes only.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

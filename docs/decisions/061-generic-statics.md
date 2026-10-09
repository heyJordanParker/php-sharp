# 61. Static members of generic classes

## Decision

Each type argument of a generic class has its own static members: `Counter<Order>.count` is separate from `Counter<User>.count`. A static member may use its class's type parameters.

## Options

### Chosen: static members per type argument

```csharp
public class Counter<T>
{
    public static int count { get; set; } = 0;
    public static List<T> seen { get; set; } = [];      // compiles: a static member may use T
}

Counter<Order>.count += 1;
Counter<User>.count;                                     // 0: Counter<User> has its own count
```

Generics are reified (decision 7), so `Counter<Order>` and `Counter<User>` are two classes when the code runs, and each keeps its own statics.

### Rejected: one set of static members shared by every type argument

```csharp
public class Counter<T>
{
    public static int count { get; set; } = 0;
    public static List<T> seen { get; set; } = [];      // would be a compile error: a static member can't use T
}

Counter<Order>.count += 1;
Counter<User>.count;                                     // would be 1: every Counter<…> shares one count
```

Every `Counter<…>` would share one static, so a static could not use `T`. A cache or registry per element type would become a hand-kept map keyed by class.

## Precedent

- **Chosen:** C#, where each closed generic type has its own static fields.
- **Rejected:** Java, whose erased generics share one static across every type argument.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

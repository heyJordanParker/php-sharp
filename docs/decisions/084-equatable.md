# 84. Equatable

## Decision

`==` and `!=` on a value of a type parameter compile only when its bound guarantees `==`: the built-in bound `Equatable`, or a class or interface that declares or inherits `operator ==`. The same rule covers every other place that compares a value of a type parameter with `==`: `contains`, `indexOf` and `remove` on a `List`, a `Map` key, a `Set` element and a value pattern.

Every type that has `==` (decision 12) satisfies `Equatable` with no declaration: numbers, strings, `bool`, enums whose cases' fields all have `==`, collections whose elements have `==`, structs whose fields have `==`, and classes that declare or inherit `operator ==`. A function, `Object`, `Any` and a class without `operator ==` do not. Each `operator ==` comes with `hash()`, so `Equatable` also keys a `Map` and fills a `Set`. A class value has `==` but keys no `Map`, so it does not satisfy `Equatable` either.

`Equatable` is a bound and never a value's type. It joins other bounds with `&`, as in `<TItem : Shareable & Equatable>`, and it adds no type the running code checks.

## Options

### Chosen: a built-in `Equatable` every type with `==` satisfies

```csharp
public static bool same<T : Equatable>(T a, T b) => a == b;             // compiles
public static bool sameUnbounded<T>(T a, T b) => a == b;                // compile error: T has no ==; bound it with Equatable, as in <T : Equatable>
public static bool sameEntity<T : DatabaseEntity>(T a, T b) => a == b;  // compiles: DatabaseEntity declares operator ==
Generic.same(point, samePoint);       // compiles; runs: true, structs compare by value
Generic.same(cartA, cartB);           // compile error: Cart is outside T's bound Equatable, since Cart declares no ==
```

The bound asks the same question `==` asks, so a type that compares with `==` passes as a type argument, and one that doesn't is refused at the call. No type writes anything new.

### Rejected: a bound each type declares, as `struct Point : Equatable`

```csharp
public struct Point : Equatable { … }   // would be needed before Generic.same(point, samePoint) compiles
Generic.same(5, 6);                     // int would need a declaration no code can write
```

Structs, enums and collections already have `==` with no code, so each would need a declaration that says nothing new, and the built-in types could never write one.

### Rejected: identity through a type parameter

```csharp
public static bool same<T>(T a, T b) => a == b;   // compiles
Generic.same(cartA, cartB);                       // compiles; runs: false unless both are the same object
```

A type parameter would compare a class that declares no `operator ==` by identity, which is the forgotten override decision 12 refuses.

## Precedent

- **Chosen:** Go's `comparable` and F#'s `equality`, which every type with `==` satisfies.
- **Rejected, declared:** Swift's `Equatable`, Rust's `PartialEq`, Haskell's `Eq` and C# 11's `IEqualityOperators`, which each type declares.
- **Rejected, identity:** Java, Kotlin, C#'s `where T : class` and TypeScript, which compare a type parameter's values by identity.

## Name

`Equatable`, after C#'s `IEquatable<T>` and Swift's `Equatable`, the names C# and PHP developers already read as "has `==`".

- **Rejected:** `Eq`, an abbreviation, and `EqualityComparable`, which is long.
- **Rejected:** Go's `comparable` and F#'s `equality`, which PHP# would write capitalized, and which read as an ordering and as an operation.
- **Rejected:** `CanEqual`, Scala's, which reads as a method.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)

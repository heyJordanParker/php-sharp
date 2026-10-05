# 4. Dividing two integers

## Decision

`/` on two integers gives an `int`, truncated toward zero.

## Options

### Chosen: truncate toward zero

```csharp
7 / 2                          // 3
-7 / 2                         // -3
7 / 2.0                        // 3.5: a float operand keeps float division
int perSeat = total / seats;   // compiles: int / int is int
```

### Rejected: PHP's int-or-float result

```csharp
6 / 2                          // 3, an int
7 / 2                          // 3.5, a float
int perSeat = total / seats;   // compile error: int / int is int|float
```

The result's type depends on the values, so the checker can only type it as a union, and every caller narrows it.

### Rejected: always a float

```csharp
6 / 2                                 // 3.0
int perSeat = total / seats;          // compile error: int / int is float
int perSeat = (int)(total / seats);   // compiles; a float holds integers exactly only up to 2^53
```

Every integer division then needs a cast back, and large integers lose precision on the way.

## Precedent

- **Chosen:** C#.
- **Rejected, int-or-float:** PHP. Hack types the result as `num`.
- **Rejected, always a float:** Python 3 and JavaScript.

## Spec

[Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

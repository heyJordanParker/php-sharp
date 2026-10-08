# 64. Structs never change

## Decision

A struct never changes. A method returns a changed copy, as in `Point q = p.moved(1, 0);`. `point.x = 5` is a compile error that names a method returning a changed copy. `readonly struct` is a compile error, because every struct is already read-only. This replaces section 10's copy-on-write rules and "`mutating` methods with `!` call syntax are left out for now".

## Options

### Chosen: a struct never changes

```csharp
public struct Point
{
    public int x { get; init; }
    public int y { get; init; }
    public Point moved(int dx, int dy) => this with { x: this.x + dx, y: this.y + dy };
}

Point q = p.moved(1, 0);              // compiles: q is a changed copy, and p is unchanged
p.x = 5;                              // compile error: a struct never changes; return a changed copy from a method, such as moved
public readonly struct Size { … }     // compile error: every struct is already read-only; write struct
```

A struct value means one thing for as long as anything holds it. A changed value always arrives through an assignment the reader can see.

### Rejected: properties that change, with copy-on-write

```csharp
public struct Point
{
    public int x { get; set; }
    public int y { get; set; }
}

let b = a;
b.x = 5;                              // would compile: b is copied here, and a is unchanged
this.origin.x = 5;                    // would compile: reads origin, changes the copy and writes it back through set
```

This was the old section 10. Every write needs a copy rule, and a write to a struct held in a property needs a write-back rule (decision 14).

### Rejected: `mutating` methods with `!` call syntax

```csharp
public struct Point
{
    public mutating void move(int dx, int dy) { this.x += dx; this.y += dy; }   // not PHP#
}

p.move!(1, 0);                        // not PHP#: changes p in place
```

php-src PR #13800 proposes this form. Struct methods would split into two kinds, and every call site would carry a marker for the kind that changes `this`.

## Precedent

- **Chosen:** Kotlin's data class `copy`.
- **Rejected, copy-on-write properties:** php-src PR #13800's structs, and Swift's structs.
- **Rejected, `mutating` methods:** Swift's `mutating` methods, and php-src PR #13800's `mutating` methods with `!` call syntax.

## Spec

- [Section 10, Structs](../spec.md#10-structs)
- [Section 13, `readonly`](../spec.md#13-readonly)

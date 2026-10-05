# 5. Collections are values

## Decision

`List`, `Map` and `Set` are copy-on-write values, as PHP's arrays are. Assigning or passing one copies it only when one side later writes to it.

## Options

### Chosen: copy-on-write values

```csharp
let b = a;
b.add(line);                                  // compiles; runs: b is copied here, and a is unchanged

public static List<Line> withShipping(List<Line> lines, Line shipping)
{
    lines.add(shipping);                      // changes the method's own copy
    return lines;
}

lines = Cart.withShipping(lines, shipping);   // the caller keeps the change by assigning it
```

### Rejected: shared references

```csharp
let b = a;
b.add(line);                                  // compiles; runs: a has line too, because a and b are one list

public static void addShipping(List<Line> lines, Line shipping)
{
    lines.add(shipping);                      // compiles; runs: changes the caller's list
}

public List<Line> lines => this.items;        // compiles; runs: any caller can change Order's lines
```

A class that hands out a collection must copy it first, or any caller can change it. A method's signature does not say whether it changes the list it receives.

### Rejected: immutable collections

```csharp
List<Line> lines = [];
lines.add(line);                              // compile error: List has no add
lines = lines.plus(line);                     // compiles; runs: a new list, and the old one is unchanged
```

Every change returns a new collection, so code that builds a collection reassigns it on every step. PHP's own arrays change in place, so every PHP method moved to PHP# is rewritten around reassignment.

### Rejected: ownership and borrowing

```csharp
let b = a;                                    // a moves into b
a.count();                                    // compile error: a was moved into b
let c = b.clone();                            // compiles: an explicit copy
```

Every parameter must also say whether it borrows the collection, borrows it to change it, or takes it. PHP has no such notion, so no plain PHP call can be typed this way.

## Precedent

- **Chosen:** Swift's `Array`, `Dictionary` and `Set`, PHP's own arrays, and Hack's `vec`, `dict` and `keyset`.
- **Rejected, shared references:** C#, Java, Kotlin and TypeScript. Hack shipped `Vector`, `Map` and `Set` as shared objects, then deprecated them for `vec`, `dict` and `keyset`.
- **Rejected, immutable collections:** Clojure and Scala.
- **Rejected, ownership and borrowing:** Rust.

## Spec

[Section 12, Collections](../spec.md#12-collections)

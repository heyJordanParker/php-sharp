# 95. Members reached by reflection

Jordan's ruling of 11-13. Spec section 5's too-wide warning skipped any member with an attribute. It now skips only a member whose attribute says a framework reaches it by reflection.

## Decision

`[ReachedByReflection]` from `Sharp` marks an attribute class whose attribute a framework reads to reach the member it marks by reflection, as a router reads `[Route]`. A public member that such an attribute marks never warns that it is too wide, since the checker never sees the framework's use. An `[Export]` method stays out of the warning on its own rule, since plain PHP calls it through the global function it declares ([decision 92](092-export.md)), which isn't reflection, so `Export` takes no mark. Any other attribute, as PHP's `[Deprecated]`, `[Import]` or `[Key]`, says nothing about who uses the member, so the member warns as one without an attribute does. A plain PHP attribute class takes the mark as `#[\Sharp\ReachedByReflection]`.

## Options

### Chosen: a mark on the attribute class

```csharp
import Attribute;

[Attribute(Attribute.TARGET_METHOD)]
[ReachedByReflection]
public class Route
{
}

public class Hooks
{
    [Route]
    public void refunded() { }      // no warning: the router reaches it

    [Deprecated]
    public void voided() { }        // warning: `Hooks.voided` is public, and nothing outside `App.Payments` uses it.
}
```

The attribute's author knows whether a framework reads it to reach the member, and says so once, on the class. Every member it marks then follows.

### Rejected: any attribute

```csharp
[Deprecated]
public void voided() { }            // no warning, though nothing reaches it
```

This was the rule before. An attribute that only describes the member, as `[Deprecated]` or `[Override]`, turned off a warning about its reach.

### Rejected: a mark on each member

```csharp
[Route]
[ReachedByReflection]
public void refunded() { }
```

Each route would repeat what its attribute already says.

## Precedent

- **Chosen:** .NET's `[DynamicallyAccessedMembers]`, which tells the trimmer that reflection reaches a type's members, so it keeps them. The standard library's own marks read the same way, as `[ComparedByEquality]` and `[EncodedAsJson]` say what is done to what they mark.
- **Rejected, any attribute:** none of .NET, Kotlin or Java treats an attribute or an annotation as a use.
- **Rejected, a mark on each member:** Android's `@Keep`, which ProGuard reads on each member it keeps.

## Spec

- [Section 5, Access modifiers](../spec.md#5-access-modifiers)

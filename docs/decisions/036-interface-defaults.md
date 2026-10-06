# 36. Default bodies in interfaces

## Decision

PHP# declares no traits. An interface can give a method a default body, and a default body sees only the interface's own members. Every field is declared in the class that owns it. A PHP# interface with default bodies compiles to a PHP interface plus a PHP trait that holds the default bodies. The trait is named `<Interface>\Defaults`, nested under the interface's name, so a plain PHP class that implements `HasDesign` writes `use HasDesign\Defaults;` to get the defaults. Plain PHP traits stay usable: they are listed in the class header and work as a type.

## Options

### Chosen: default bodies in interfaces

```csharp
public interface HasDesign
{
    List<string> claims { get; set; }                       // compiles: abstract, each class declares its storage
    string designColumn { get; }                            // compiles: abstract
    string designKey() => `design:${this.designColumn}`;    // compiles: a default body
    string designId() => `design:${this.id}`;               // compile error: a default body sees only HasDesign's members
}

public class Page : DatabaseEntity, HasDesign
{
    public List<string> claims { get; set; } = [];          // compiles: the storage lives in Page
    public string designColumn => "design";                 // compiles
}
```

Every field a class has is written in that class, so its body shows all of its state. A default body reaches only what the interface declares, so it works on every class that implements the interface.

### Rejected: traits with fields and a required base class

```csharp
public trait HasDesign                                      // not PHP#
{
    require extends DatabaseEntity;
    List<string> claims = [];                               // a field each class that uses HasDesign gets without declaring it
    public string designKey() => `design:${this.id}`;       // reads DatabaseEntity's id
}
```

Each class that uses the trait gains state its own body never shows, and the trait's code reaches into whatever the required base class holds.

## Precedent

- **Chosen:** C# 8's default interface methods, Java 8's default methods, Kotlin's interface bodies and Swift's protocol extensions.
- **Chosen, the trait's name:** Kotlin's `DefaultImpls`, the class its compiler nests in an interface to hold the default bodies for Java.
- **Rejected:** PHP's and Scala's traits, and Hack's `require extends`.

## Spec

- [Section 22, Inheritance](../spec.md#22-inheritance)

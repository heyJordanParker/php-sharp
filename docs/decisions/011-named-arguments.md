# 11. Named arguments

## Decision

Any parameter can be named at a call. An override that renames a parameter is a compile error, and so is a name that matches no parameter.

## Options

### Chosen: any parameter named, names fixed by the first declaration

```csharp
image.resize(width: 800, height: 600);    // compiles
Http.post(url, data: payload);            // compiles: plain PHP's parameters can be named too
image.resize(widht: 800, height: 600);    // compile error: resize has no parameter widht

public class Thumbnail : Image
{
    public override void resize(int w, int h) { … }   // compile error: w renames width, h renames height
}
```

A name means the same parameter on every class, so a named call works on any subclass.

### Rejected: an override may rename

```csharp
public class Thumbnail : Image
{
    public override void resize(int w, int h) { … }   // compiles
}

Image image = new Thumbnail();
image.resize(width: 800, height: 600);   // compiles; runs in PHP: throws Error "Unknown named parameter $width"
```

PHP checks the names against the class of the object, so a call that works on `Image` fails on `Thumbnail`. C# checks them against the declared type instead, so one parameter has a different name depending on how the caller holds the object.

### Rejected: named-only parameters

```csharp
public void resize(named int width, named int height) { … }   // not PHP#: a named-only marker

image.resize(800, 600);                   // compile error: width and height must be named
image.resize(width: 800, height: 600);    // compiles
```

Only parameters their author marked can be named, and plain PHP has no such marker, so a call into plain PHP could not name any parameter.

## Precedent

- **Chosen:** C#, where any parameter can be named, and Kotlin, which also warns when an override renames a parameter.
- **Rejected, renames allowed:** PHP and C#.
- **Rejected, named-only parameters:** Hack and Dart.

## Spec

- [Section 16, Naming a value](../spec.md#16-naming-a-value)
- [Section 22, Inheritance](../spec.md#22-inheritance)

# 87. Reading declared types

Jordan's ruling of 11-11.

## Decision

`typeof(Product).properties()` gives a `List<Property>` of every instance property and field the class declares, private ones included, in declaration order, the ones it inherits after its own. A static has no value on an object, so it is none. A `Property` has `name`, a `string`, `type`, a `Class<Any?>` that is the declared type as a type value, `nullable`, a `bool`, and `attributes<TAttribute : Object>()`, which reads the property's attributes as `Class<T>.attributes<T>()` reads a class's. `Class<T>.typeArguments()` gives a `List<Class<Any?>>`: `[typeof(string)]` for `typeof(List<string>)` and `[]` for `typeof(int)`, and is a member of every class value, a `Property`'s `type` included. Both live beside `attributes<T>()` in the standard library's `ClassMembers`.

## Options

### Chosen: the declared type as a type value

```csharp
public class Product
{
    [Field] public List<string> tags { get; set; } = [];
    [Field] public int? stock { get; set; } = null;
}

const tags = typeof(Product).properties()[0];
tags.name;                                       // "tags"
tags.type == typeof(List<string>);               // true
tags.type.typeArguments();                       // [typeof(string)]
tags.nullable;                                   // false
tags.attributes<Field>().count();                // 1
```

A schema, a serializer or a validator reads a property's declared type as the same value `typeof` gives, so it compares and takes it apart with `==` and `typeArguments()`, as it reads any other type.

### Rejected: the type as text, as Hack's `getTypeText()`

```csharp
tags.typeText;                                   // would be "List<string>"
tags.typeText == "List<string>";                 // agents compare strings, and a spacing or an import changes them
```

Code that reads the text compares strings, so it breaks on how a type is written and never learns a type's arguments.

### Rejected: property references, as Kotlin's `Product::tags`

```csharp
Product::tags.type;                              // would be a second way to name a property beside nameof(Product.tags)
```

A reference is a second form that names a property, beside `nameof` (section 6.3).

## Precedent

- **Chosen:** C#'s `Type.GetProperties()`, `PropertyInfo.PropertyType` and `Type.GetGenericArguments()`, Kotlin's `KClass.memberProperties`, `KProperty.returnType` and `KType.isMarkedNullable`, and Java's `Field.getGenericType()`.
- **Rejected, text:** Hack's `ReflectionProperty::getTypeText()`.
- **Rejected, references:** Kotlin's property references.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

# 100. Reading attributes as written

The language owner's rulings of 11-27: "okay, the attribute proposal is fine", and on its amendment, "if it follows C# it's fine".

## Decision

Each member's `attributes<TAttribute : Object>()` has a twin, `attributeData<TAttribute : Object>()`, on `ClassMembers`, `Method`, `Property`, `Field`, `Parameter`, `Constant`, `Constructor` and `EnumCase`. It selects the same attributes, in declaration order, and builds none of them. Each is an `AttributeData`: the attribute's `type`, and its `arguments`, the call of its constructor in parameter order, with each named argument at its parameter's place and each left-out parameter's default filled in. So a build step saves an attribute as data, and a later run rebuilds it with `typeof(T).createInstance(arguments)`, which may have every effect (section 29). `attributeData` is pure, since it runs no attribute's constructor.

The approved program, as amended:

```csharp
public final class AttributeData {
    public Class<Object> type { get; }                   // the attribute's class, as Access
    public List<Any?> arguments { get; }                 // the constructor call, in parameter order
}
```

```csharp
[Access(Level.Edit)]
[Access(typeof(System))]
public void publish() { … }

method.attributes<Access>();      // [Access {…}, Access {…}]: built objects, arguments gone
method.attributeData<Access>();   // [AttributeData { type: Access, arguments: [Level.Edit] },
                                  //  AttributeData { type: Access, arguments: [typeof(System)] }]

[Access(level: Level.Edit)]
data.arguments == [Level.Edit];                               // true, the same as for [Access(Level.Edit)]
```

```csharp
// build step: save every action's attributes once
for (const data in method.attributeData<Access>()) {
    saved.add([data.type, data.arguments]);
}

// boot: recreate them from the saved data
const access = savedType.createInstance(savedArguments);   // the same Access(Level.Edit)
```

## Options

### Chosen: the constructor call in parameter order

Each attribute is one value per constructor parameter, so `createInstance` rebuilds it from `arguments` alone, and `[Access(level: Level.Edit)]` and `[Access(Level.Edit)]` read the same.

### Rejected: positional and named arguments apart

The first approved shape had `arguments`, the positional ones, and `namedArguments`, a `Map` by parameter name. A named argument then needed a construction by name to rebuild the attribute, and two attributes that make the same call read differently.

## Precedent

- **Chosen:** .NET's `MemberInfo.GetCustomAttributesData()` beside `GetCustomAttributes<T>()`. ECMA-335 section II.23.3 stores an attribute's constructor arguments as fixed arguments in parameter order, and `CustomAttributeData.NamedArguments` holds only field and property sets, which PHP# attributes don't have. .NET wraps each argument with its type, because its metadata stores an enum as a number, while a PHP# argument is already its value.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)
- [Section 29, Effects](../spec.md#29-effects)

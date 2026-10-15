# 28. Overriding a plain PHP parent's property

## Decision

A class overrides a plain PHP parent's property with a property marked `override`, and writes its type like every PHP# property: `protected override string? table { get; set; } = "orders";`. The written type must fit the parent's `@var` type, or equal the parent's type when PHP declares one. The access level matches the parent's, `set` included. The override has `set`, because the parent's own code writes the property, and it may give `get` and `set` bodies. When the parent's property has no PHP type, the engine drops the written type as the class links, and runs the accessor bodies as PHP's hooks on the untyped property. Every override is a property: a field with `override` is an error (section 6.1).

## Options

### Chosen: a property, with the type written, which the engine drops for an untyped parent

```csharp
public class Order : Model
{
    protected override string? table { get; set; } = "orders";                    // compiles; runs: protected $table = 'orders', untyped as in Model
    protected override List<string> fillable { get; set; } = ["number", "total"]; // compiles
    protected override string? table { get; set => field = value.trim(); }       // compiles; runs as PHP's set hook on $table
    protected override table { get; set; } = "orders";                            // compile error: write the type
    protected override string? table = "orders";                                  // compile error: an override is a property
    protected override string? table { get; } = "orders";                         // compile error: Model's own code writes table, so its override has set
}
```

A property always has a type, with no exception. The checker proves the written type against the parent's. For an untyped parent, the running program does not enforce the type, so plain PHP code in `Model` can still store anything there. TypeScript makes the same trade.

An auto-property override lowers to the same PHP property a field does, `#[\Override] protected $table = 'orders';`. PHP runs hooks on a redeclared property whether the parent types it or not, so accessor bodies need no exception.

### Rejected: a field with `override`

```csharp
protected override string? table = "orders";             // not PHP#: an override is a property
```

C# overrides only properties, and a field takes no `virtual`. Two forms for one override would leave every reader to learn both.

### Rejected: `override` with no written type

```csharp
protected override table { get; set; } = "orders";       // not PHP#: the type comes from Model's @var
```

The override would be the one property whose type its own line does not show.

### Rejected: only Laravel's own attributes and methods

```csharp
[Table("orders"), Fillable("number", "total")]
public class Order : Model
{
    protected override Map<string, string> casts() => ["paid_at": "datetime"];   // compiles: decision 18 covers casts()
    protected override table = "orders";                                         // not PHP#
}
```

No Eloquent attribute covers `$with`, and other libraries' base classes have none.

## Precedent

- **Chosen:** C#, Swift and Java, which repeat the type on an override, and C#, which overrides only properties. TypeScript, which checks types it erases at runtime.
- **Rejected, a field:** Java, which hides a parent's field instead of overriding it.
- **Rejected, no written type:** Kotlin's and TypeScript's `override` on a property with an initial value and no written type.
- **Rejected, attributes only:** Laravel 13's class attributes.

## Spec

- [Section 6, Fields and properties](../spec.md#6-fields-and-properties)
- [Section 6.1, Property features](../spec.md#61-property-features)

# 28. Overriding a plain PHP parent's property

## Decision

A class overrides a plain PHP parent's property with `override`, and writes its type like every PHP# field: `protected override string? table = "orders";`. The written type must fit the parent's `@var` type, or equal the parent's type when PHP declares one. The access level matches the parent's, and the value is constant. When the parent's property has no PHP type, the engine drops the written type as the class links. A PHP# parent's property is overridden as a property (section 6.1).

## Options

### Chosen: the type is written, and the engine drops it for an untyped parent

```csharp
public class Order : Model
{
    protected override string? table = "orders";                    // compiles; runs: protected $table = 'orders', untyped as in Model
    protected override List<string> fillable = ["number", "total"]; // compiles
    protected override table = "orders";                            // compile error: write the type
}
```

A field always has a type, with no exception. The checker proves the written type against the parent's. For an untyped parent, the running program does not enforce the type, so plain PHP code in `Model` can still store anything there. TypeScript makes the same trade.

### Rejected: `override` with no written type

```csharp
protected override table = "orders";                     // not PHP#: the type comes from Model's @var
```

The override would be the one field whose type its own line does not show.

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

- **Chosen:** C#, Swift and Java, which repeat the type on an override. TypeScript, which checks types it erases at runtime.
- **Rejected, no written type:** Kotlin's and TypeScript's `override` on a property with an initial value and no written type.
- **Rejected, attributes only:** Laravel 13's class attributes.

## Spec

- [Section 6, Fields and properties](../spec.md#6-fields-and-properties)
- [Section 6.1, Property features](../spec.md#61-property-features)

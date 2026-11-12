# 86. `late` members

Jordan's ruling of 11-11.

## Decision

A `late` instance field or property with storage and no initial value may stay unset after the constructor. A read before its first write throws an `Error` that names the member as PHP# writes it, `App.Order.number`, and plain PHP's `ReflectionProperty::isInitialized()` gives false until that write, so a framework that fills the member after `new` tells a value it loaded from one it never loaded. A `late` static, a `late` member with an initial value, and a `late` computed or `lazy` property are compile errors.

## Options

### Chosen: `late`

```csharp
public class Order
{
    public late int id { get; private set; }               // compiles: the framework fills it after new
    public late string number { get; set; }                // compiles
    public late string? note { get; set; }                 // compiles: null is a loaded value, unset is not loaded
    public late string status { get; set; } = "open";      // compile error: a late member takes no initial value
    public static late int count { get; set; }             // compile error: a static is never late

    public string title() => `Order ${this.number}`;        // compiles; throws an Error that names App.Order.number when it is read before it is set
}
```

```php
$number = new ReflectionProperty(App\Order::class, 'number');
$number->isInitialized($order);      // false: not loaded
$number->setValue($order, 'A-12');
$number->isInitialized($order);      // true
```

A PHPUnit test's field set in `setUp()` and an ORM row filled through reflection after `new` declare their members without a dummy initial value, so a save writes only the columns the row loaded.

### Rejected: `required`, as C#'s `required` with `SetsRequiredMembers`

```csharp
public required string number { get; set; }             // every creation must set it in an object initializer
new Order { number = "A-12" };                          // the framework creates the row first, and fills it through reflection
```

`required` makes every creation set the member in an object initializer, while the framework creates the object first and fills it later.

## Precedent

- **Chosen:** Kotlin's `lateinit`, Hack's `<<__LateInit>>` and Dart's `late`, whose early read is a runtime error.
- **Rejected:** C#'s `required` with `[SetsRequiredMembers]`.

## Name

`late`, Dart's keyword for a member initialized after its declaration, which collides with no PHP# word.

- **Rejected:** Kotlin's `lateinit`, since PHP#'s `init` accessor means "set only while the object is created", the opposite.
- **Rejected:** `deferred`, which reads as deferred loading.

## Spec

- [Section 6, Fields and properties](../spec.md#6-fields-and-properties)

# 90. A plain PHP class's `__toString()` is its `toString()`

Jordan's ruling of 11-11.

## Decision

The checker reads a plain PHP class's `__toString(): string`, its own or inherited, as its `string toString()`. A template `${email}` and a call `email.toString()` compile for an object of such a class and run PHP's `__toString()`. PHP# code never names `__toString`, and a plain PHP class without one stays a template's compile error. Both run PHP's string conversion of the object, which runs a PHP# class's `toString()` too, so a value that holds such a class beside a PHP# class shows each. PHP gives an enum no string conversion, so a value that holds an enum beside such a class is a compile error.

## Options

### Chosen: `__toString()` is `toString()`

```php
final class Email
{
    public function __construct(private string $address) {}
    public function __toString(): string { return $this->address; }
}
```

```csharp
const label = `To: ${email}`;      // compiles: "To: ada@example.com"
email.toString();                  // compiles: runs PHP's __toString()
`Row: ${row}`;                     // compile error: Row declares no toString()
```

It is the other direction of section 18's rule that a PHP# class's `toString()` is PHP's `__toString()`, so one method means text on both sides.

### Rejected: `email.__toString()` in PHP# code

```csharp
email.__toString();                // a PHP name in PHP# code, and a second name for one method
```

PHP# refuses a member name that starts with `__`, and the call names PHP's magic method beside the `toString()` every PHP# type has.

## Precedent

- **Chosen:** Kotlin, which calls a Java class's `toString()` as its own, and Swift, where an Objective-C class's `description` is its `CustomStringConvertible.description`, which string interpolation reads.

## Spec

- [Section 18, Strings](../spec.md#18-strings)

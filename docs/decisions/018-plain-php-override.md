# 18. Overriding plain PHP

## Decision

A PHP# class replaces a plain PHP parent's method under PHP's own rule: the method is open unless PHP marks it `final`. Replacing it always says `override`, as for a PHP# parent, and the same compile errors apply.

## Options

### Chosen: open unless PHP marks it `final`, and `override` is required

```php
abstract class Report
{
    abstract protected function render(): string;
    public function title(): string { return 'Report'; }
    final public function id(): string { … }
}
```

```csharp
public class SalesReport : Report
{
    protected override string render() { … }          // compiles
    public override string title() => "Sales";         // compiles: title is not final in PHP
    public string title() => "Sales";                  // compile error: replaces Report.title, write override
    public override string id() { … }                  // compile error: id is final in Report
}
```

PHP has no `virtual`, so its author's only way to close a method is `final`. PHP# reads that mark and nothing else.

### Rejected: only `abstract` plain PHP methods can be replaced

```csharp
public class SalesReport : Report
{
    protected override string render() { … }          // compiles: render is abstract in Report
    public override string title() => "Sales";         // compile error: title is not abstract in Report
}

public class BillingServiceProvider : ServiceProvider
{
    public override void register() { … }              // compile error: register is not abstract in ServiceProvider
}
```

This applies C#'s closed-by-default rule to a parent that could never opt in. Frameworks offer extension points as ordinary methods with a default body, such as Eloquent's `casts()` and a service provider's `register()`, and no PHP# class could fill them in.

### Rejected: a library's `extern virtual` declaration opens a method

```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Support.ServiceProvider;

extern virtual ServiceProvider.register;   // not PHP#: opens register for PHP# subclasses
```

```csharp
public class BillingServiceProvider : ServiceProvider
{
    public override void register() { … }   // compiles only after the declaration above
}
```

Every library needs a declaration for each method it expects subclasses to replace before PHP# code can extend it.

## Precedent

- **Chosen:** Kotlin extending Java classes, Swift extending Objective-C classes, and Scala extending Java classes.
- **Rejected, only `abstract` methods:** C#'s closed-by-default methods.
- **Rejected, `extern virtual`:** TypeScript's `.d.ts` files.

## Spec

[Section 22, Inheritance](../spec.md#22-inheritance)

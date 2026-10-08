# 59. Names

## Decision

Namespaces, types, type parameters, events and enum cases are PascalCase. Methods, properties, fields, parameters and locals are camelCase. Constants are UPPER_SNAKE_CASE. Mago warns on a `.sharp` name that breaks the rule, through its existing naming lint rules and in their message shape.

## Options

### Chosen: TypeScript's casing

```csharp
namespace App.Billing;

import Illuminate.Database.Eloquent.Model;

public enum Status : string { case Paid = "paid"; }

public class Invoice<TLine : Line> : Model
{
    const MAX_LINES = 100;
    List<TLine> pendingLines = [];
    public string dueDate { get; set; }
    public event Paid(int orderId, Money amount);

    public Money totalIn(string currency)
    {
        const lineCount = this.pendingLines.count();
        this.getKey();                                   // Laravel's method, cased the same way as totalIn
        …
    }
}

public class Receipt
{
    public Money TotalIn(string currency) { … }          // compiles; Mago warns: Method name `TotalIn` should be in camel case.
}
```

Laravel and PHP's own classes name methods and properties in camelCase. A PHP# class that extends a Laravel class uses one casing for its own members and the ones it inherits.

### Rejected: C#'s PascalCase members

```csharp
public class Invoice : Model
{
    public Money TotalIn(string currency)
    {
        this.getKey();                                   // Laravel's method, camelCase beside PascalCase
        …
    }
}
```

Every class that extends a Laravel class would mix two casings, its own members in PascalCase and Laravel's in camelCase.

### Rejected: Rust's snake_case members

```csharp
public class Invoice : Model
{
    public Money total_in(string currency)
    {
        this.getKey();                                   // Laravel's method, camelCase beside snake_case
        …
    }
}
```

Every class that extends a Laravel class would mix two casings, for the same reason.

## Precedent

- **Chosen:** TypeScript.
- **Chosen, the warning:** Mago's naming lint rules, such as `method-name`, `class-name` and `constant-name`, and their message shape, as in "Method name `TotalIn` should be in camel case."
- **Rejected, PascalCase members:** C#.
- **Rejected, snake_case members:** Rust.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

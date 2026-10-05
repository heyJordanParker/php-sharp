# 1. Calling plain PHP

## Decision

Any PHP# code may call plain PHP, and the checker tracks each call as an effect.

## Options

### Chosen: any class calls plain PHP, and the call is an effect

```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    // compiles: OrderReport has the Database effect, which Laravel's extern declares for DB
    // runs: Laravel's query builder counts the open orders
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

### Rejected: only `foreign` classes call plain PHP

```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    // compile error: OrderReport is not a foreign class, so it cannot call DB
    public int openCount() => DB.table("orders").where("status", "open").count();
}

public foreign class Orders
{
    // compiles: a foreign class may call plain PHP
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

Most libraries are plain PHP. Under this option, every Laravel facade, helper and model method needs a `foreign` wrapper before PHP# code can use it, and every wrapper is created where the app starts and passed down through constructors.

## Precedent

- **Chosen:** Hack. A Hack function with no context list has the `defaults` context, which may call any code.
- **Rejected:** PHP#'s own spec before this decision.

## Spec

[Section 29, Effects](../spec.md#29-effects)

# 67. Generic objects that plain PHP creates

## Decision

A generic object that plain PHP creates without type arguments (`new PaginatedList($rows)`, or Laravel's container) has its bounds as its type arguments. Under `PaginatedList<TItem : DatabaseEntity>`, a raw page is a `PaginatedList<DatabaseEntity>`. It's checked where it enters PHP#: a parameter that needs `PaginatedList<Order>` throws `TypeError`. A method that takes any page is generic, as in `count<TItem : DatabaseEntity>(PaginatedList<TItem> page)`. PHP# code never creates one, because a type argument it can't fix or infer is a compile error (decision 50).

## Options

### Chosen: the bounds as type arguments, checked at entry

```php
$page = new PaginatedList($rows);                       // plain PHP: no type arguments, so the page is a PaginatedList<DatabaseEntity>
$report->show($page);                                   // throws TypeError: show needs a PaginatedList<Order>
$report->count($page);                                  // accepted: TItem is fixed as DatabaseEntity
```

```csharp
public class Report
{
    public void show(PaginatedList<Order> page) { … }
    public int count<TItem : DatabaseEntity>(PaginatedList<TItem> page) { … }
}
```

Plain PHP keeps creating generic PHP# classes as it creates any class, and the container keeps resolving them. A wrong object stops where it enters PHP#, as a wrong collection element does (section 12).

### Rejected: `new` must name type arguments

```php
$page = new PaginatedList($rows);                       // would throw: PaginatedList needs its type arguments
app(PaginatedList::class);                              // would throw for the same reason
```

Hack has this rule. It would break every plain PHP `new` of a generic PHP# class, and every container lookup of one, because PHP has no syntax for type arguments.

## Precedent

- **Chosen:** Java's raw types, but checked at entry instead of trusted.
- **Rejected:** Hack's rule that `new` names the type arguments of a reified generic class.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

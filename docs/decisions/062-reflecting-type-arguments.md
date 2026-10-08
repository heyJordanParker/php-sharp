# 62. Type arguments in plain PHP's reflection

## Decision

Plain PHP reads type arguments from objects only, with `ReflectionObject::getTypeArguments()`. A class value reaches plain PHP as a plain class-name string, so it carries none.

## Options

### Chosen: a reflection method

```php
$page = $orders->page(1);                                   // a PaginatedList<Order> made by PHP# code
(new ReflectionObject($page))->getTypeArguments();          // reads Order
get_class($page);                                           // "App\PaginatedList", as before
```

Generics are reified (decision 7), so the engine holds every type argument. A reflection method shows them to plain PHP and leaves every class name as PHP already sees it.

### Rejected: type arguments in the class name

```php
get_class($page);                                           // would be "App\PaginatedList<App\Order>"
get_class($page) === PaginatedList::class;                  // would be false
```

Plain PHP compares class names, keys arrays by them and autoloads by them. A name that carries type arguments breaks each of those for every generic object.

## Precedent

- **Chosen:** C#'s `Type.GetGenericArguments()`.

## Spec

- [Section 11, Generics](../spec.md#11-generics)

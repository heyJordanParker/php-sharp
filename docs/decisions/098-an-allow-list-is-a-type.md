# 98. An allow-list is a type

Jordan's ruling of 11-13. This amends [decision 89](089-bytes-exact-json-and-sql-identifiers.md), whose `Sql.identifier` it deletes.

## Decision

A string-backed enum case's `value` is a `literal-string` wherever the case comes from, a parameter, a variable or a collection's element included, because every backing value is a literal. So the names a query may write as SQL are an enum, and `query.orderByRaw(column.value)` passes a column chosen while the code runs to a plain PHP `literal-string` parameter. `Sql.identifier(name, allowed)`, the `Sharp.Data.Sql` class and the engine's class that ran it are deleted, so one way names an allow-list.

## Options

### Chosen: a string-backed enum

```csharp
public enum SortColumn : string
{
    case Name = "name";
    case CreatedAt = "created_at";
}

public Builder sorted(Builder query, SortColumn column) => query.orderByRaw(column.value);   // compiles
```

The type holds the allow-list, so the checker proves at compile time that only its names reach the SQL, and a caller can't pass a name it lacks.

### Rejected: a list checked while the code runs

```csharp
query.orderByRaw(Sql.identifier(column, ["name", "created_at"]));                   // compiles, and throws for any other name
```

This was decision 89's way. The allow-list was a value the code repeated at each call, and a name outside it failed only while the code ran.

## Precedent

- **Chosen:** Swift's and Rust's string-backed enums as the closed set of a query's columns, as jOOQ's generated `Field` constants and Diesel's column types are, and PHPStan and Psalm, which read a backed enum case's `value` as a literal.

## Spec

- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)

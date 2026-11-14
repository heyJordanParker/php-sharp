# 94. Reflection calls and sets members

Jordan's ruling of 11-13. It amends [decision 30](030-class-values.md), which refused a member named by a variable: reflection is the one sanctioned way to name a member by a value.

## Decision

`Method.invoke(Object receiver, List<Any?> arguments): Any?`, `Property.get(Object receiver): Any?` and `Property.set(Object receiver, Any? value): void` use the member that `Class<T>.methods()` or `properties()` gave. Each reaches the member as a direct use from the calling code would, by its access modifier, `internal` included. Each argument and value is checked against its declared type when the code runs, a wrong one throwing `TypeError` at the call, as a direct PHP# use is. A method that isn't private runs the receiver's override. All three may have every effect, so code that must keep to a `uses` can't call them: a call runs a method body, a read runs a computed property's `get`, and a write runs a `set`, and the caller sees none of that code. They spell "every effect" as a call into plain PHP without an `extern` does, so the effect system gains no new form.

## Options

### Chosen: reflection's calls, reads and writes, checked when the code runs

```csharp
public class Importer
{
    public void fill(Object row, Map<string, Any?> values)
    {
        for (const property of typeof(Row).properties()) {
            if (values.containsKey(property.name)) {
                property.set(row, values[property.name]);   // runs; throws TypeError for a value of another type
            }
        }
    }
}
```

A form builder, a serializer or an ORM reads a class's members and fills them by name. With reflection that keeps the language's rules, it does so without plain PHP's `$row->$column`, which skips them.

### Rejected: `get` with no effect

```csharp
public int total => this.ledger.sum();                  // a computed property's get may query the database (section 6.1)
typeof(Order).properties()[0].get(order);               // would read with no effect, though the get ran the query
```

A read runs a computed property's `get`, whose body may have any effect. A `get` with no effect would let that effect past every `uses`.

### Rejected: decision 30 alone, and plain PHP for the rest

```php
$row->$column = $value;                                 // plain PHP: no access modifier, no PHP# type checked
```

Every framework that maps names to members would keep a plain PHP file for it, and PHP# would check none of what it writes.

## Precedent

- **Chosen:** .NET's `MethodInfo.Invoke`, `PropertyInfo.GetValue` and `PropertyInfo.SetValue`, and Kotlin's `KCallable.call`, `KProperty1.get` and `KMutableProperty1.set`. Each checks its arguments when the code runs and runs the getter and setter.
- **Rejected, `get` with no effect:** none. .NET and Kotlin both run the getter.
- **Rejected, plain PHP:** PHP's variable members `$object->$name` and `$object->$name()`.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)
- [Section 29, Effects](../spec.md#29-effects)

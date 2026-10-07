# 30. Class values

## Decision

A class value works wherever a class name works. `new type(key)` needs a `required` constructor, or for a plain PHP class its own constructor. `type.defaultTag()` and `type.find(id)` call the static on the class the value holds. `Class`'s own members, such as `attributes`, win, and a class cannot declare a static named like one of them. A property named by a variable stays refused, and `order.getAttribute(column)` replaces `$order->$column`.

## Options

### Chosen: a class value works wherever a class name works

```csharp
public class FormBuilder
{
    Map<string, Class<Element>> elements = ["form": typeof(FormElement), "input": typeof(InputElement)];

    public Element make(string kind, string key)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);
        return new type(key);                                          // compiles: Element's constructor is required
    }

    public string tagFor(string kind)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);
        return type.defaultTag();                                      // compiles; runs: the static defaultTag of the class type holds
    }

    public Model? load(Class<Model> type, int id) => type.find(id);   // compiles; runs: Model's static find on the class type holds
}

public static string attributes() => "";                              // compile error: attributes is a member of Class
```

The checker's types reach the running program (decision 29), so the engine knows `type` holds a class and compiles `type.defaultTag()` as a static call. Without them, the engine could not tell that call from a method call on a string.

### Rejected: `new` on a class value only, and members through it refused

```csharp
new type(key);                                                             // compiles
type.defaultTag();                                                         // compile error: no static member through a class value; pass a function
Map<string, Function<string()>> tags = ["form": FormElement.defaultTag];   // compiles; the replacement
```

Every static a caller needs through a class value becomes a hand-kept map of functions.

### Rejected: no class values

```csharp
Map<string, Function<Element(string)>> makers = ["form": key => new FormElement(key)];   // compiles; replaces new type(key)
```

Every `new $class` becomes a hand-written map of lambdas, and a class name from Laravel can only be built through the container.

## Precedent

- **Chosen:** Hack's `classname<T>` and Swift's metatypes. Swift's `required init` for `new` on a class value. TypeScript's error 2699 for a static named like a member of the class value's own type.
- **Rejected, `new` only:** Hack's `new $cls()` with `<<__ConsistentConstruct>>`.
- **Rejected, no class values:** C# and Rust.

## Spec

- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)

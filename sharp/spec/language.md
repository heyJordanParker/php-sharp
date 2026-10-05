# PHP# language specification

PHP# is a per-file dialect of PHP. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely. This file is the single source of truth for PHP# syntax and rules.

- Every rule here was approved by the Architect.
- Only the Architect changes a rule.
- Proposals still waiting for his decision are listed under **Open** in their section.

## 1. Attributes

Attributes use square brackets and follow C#'s rules:

- several attributes in one bracket: `[Field("Name"), Searchable]`
- several brackets on one declaration
- positional and named arguments
- no parentheses when there are no arguments: `[Searchable]`
- explicit targets: `[return: NotNull]`

```csharp
[Field(label: "Name", render: Render.Heading, searchable: true)]
public string name { get; set; }
```

Named arguments use `label: "Name"`, the same "name: value" form used everywhere in the language (section 16). C#'s `Label = "Name"` is not allowed.

## 2. Variables

Variables have no `$`. A local is declared with `let`, which can be reassigned, or `const`, which cannot. The meanings match TypeScript.

```ts
const currency = this.tenant.currency;
let total = 0;
```

**A local can also be declared with its type written,** as in C#, when the right side cannot say it, such as `null`. A typed local can be reassigned like `let`, and `const` can carry a type too.

```csharp
let total = 0;              // type inferred, can be reassigned
Money? total = null;        // type written, can be reassigned
const plan = this.plan();   // type inferred, cannot be reassigned
const Plan plan = …;        // type written, cannot be reassigned
```

- In a class, `const` declares a constant. It is never reassigned, and its value is known before the code runs.
- These are removed:
  - `$$name` variable variables
  - `compact()`
  - `extract()`
  - `global`

## 3. Scope

A name lives from its declaration to the `}` that closes its block.

- Each loop pass gets a fresh binding.
- A closure captures the variable itself, not a copy.
- An inner block cannot redeclare a name an outer block declares. This is C#'s rule CS0136, and the checker rejects it before the code runs.

## 4. Member access

Every member access uses `.`. There is no `->` and no `::`.

```csharp
user.name
Str.slug(name)
```

A class cannot declare a static member and an instance member with the same name, so `Link.name` always has exactly one meaning. C# has the same rule.

**A member of the same object is always written with `this.`**, as in TypeScript, Python and Swift. That holds for fields, properties and methods alike. A bare name is always a local, a parameter, a class or a constant, so any line read on its own shows whether it touches the object. Static members are written with the class name.

```csharp
this.count += 1;
const plan = this.planner.plan(id);
Checkout.maximum;
```

**`Class.y` without a call reads whichever member `y` is:** a static property, a constant or an enum case. The engine compiles one file at a time, so it looks up the member's kind when the code runs. The same applies to members used as values (sections 6.3 and 14.3).

## 5. Access modifiers

| Modifier | Who can reach it |
|---|---|
| `private` | this class. This is the default for members. |
| `protected` | this class and its subclasses, in any namespace |
| `internal` | this namespace and every namespace below it. This is the default for classes. |
| `public` | everyone |

- **A namespace's inside is its folder.** A file's namespace must match its path, so no file can declare its way into a namespace.
- **Tests:** test folders may declare the namespace they test, which gives them `internal` access.
- **A member is never more visible than its class.**
- **Visibility is checked both ways.**
  - Too narrow is a compile error at the caller.
  - Too wide is a warning, for example a `public` member that nothing outside its namespace uses.
- **There is no named reach,** such as `internal(App\Tenant)`. A helper shared by sibling namespaces moves to their parent namespace.

## 6. Fields and properties

Fields and properties are separate concepts.

- **A field is storage.** It can only be `private` or `protected`. There are no public fields.
- **A property is the API.** It has `get` and `set` accessors, and each accessor carries its own access level.

```csharp
Map<string, Plan> cache = [];                                   // field
public int views { get; private set; }                          // auto-property
public string name { get; internal set => field = value.trim(); }
public string slug => Str.slug(name);                           // computed
```

**A field or an auto-property can have an initial value,** written after its declaration as in C#:

```csharp
Map<string, Plan> cache = [];                                   // field
public int views { get; private set; } = 0;                     // auto-property
public List<Tag> tags { get; set; } = new List<Tag>();
```

A constant initial value is stored as the member's default. Any other value is set at the start of the constructor, in the order the members are declared.

### 6.1 Property features

PHP# has all of C#'s property features:

- auto-properties
- a separate access level for each accessor
- get-only properties, which are set in the constructor
- `init`, which allows a set only while the object is being created
- computed properties, written `=> expr`
- accessor bodies, which use `field` for the hidden storage and `value` for the incoming value
- `required`, which the checker enforces wherever the object is created
- properties in interfaces, abstract properties, and overrides
- Reflection that tells fields from properties

### 6.2 Change observers

`willSet` runs before a write, and `didSet` runs after it.

```csharp
public string name { get; set; didSet => this.touch(); }
```

### 6.3 Typed property references

`Link.name` used as a value is a typed reference to that property. The checker rejects a misspelled or removed property wherever it is used.

```csharp
query.orderBy(Link.name);
```

### 6.4 Reusable property behaviors

A clause after the property names its behaviors. It reads as "get/set via Trimmed".

```csharp
public string name { get; set; } via Trimmed, Tracked;
```

- A behavior is a class that implements `get` and `set` once.
- The property forwards its accessors to one behavior instance per object, held in a hidden slot.
- Several behaviors run in the order they are written, each one wrapping the next.

### 6.5 `lazy`

`lazy` is a modifier, not a behavior. A lazy property computes its value on the first read, then keeps it and never recomputes it. `static lazy` computes once per process.

```csharp
public lazy Plan plan => this.planner.plan(this.id);
```

A `lazy` body may read anything, including properties that can change later. The value is computed once and kept, even if those inputs change, as in Swift and Kotlin. A value that must follow its inputs is a plain computed property (`=> expr`).

## 7. Methods

The return type comes first, and there is no keyword. Every declaration has the same order: modifiers, then the type, then the name.

```csharp
public Plan plan(Order order) { … }
public int total() => this.a + this.b;
public T first<T>(List<T> items) { … }
```

## 8. Functions

There are no top-level functions. Shared code lives in static methods on a class. PHP's built-in functions, such as `strlen`, and plain PHP functions, such as Laravel's `now()`, can still be called. Section 29 covers their effects.

## 9. Constructors

A constructor is named after its class. A parameter with an access modifier declares a member, and the parameter spells out which kind:

- **`private Planner planner`** declares a field.
- **`public Tenant tenant { get; }`** declares a property. A `public` parameter without accessor braces is a compile error, just as a public field is.
- **A parameter with no modifier** is an ordinary parameter.

```csharp
public StoreService(
    private Planner planner,
    [Field(label: "Tenant")] public Tenant tenant { get; } via Tracked,
) {
    if (!tenant.active) throw new InactiveTenant(tenant);
}
```

A member declared on a parameter supports everything the same declaration supports in the class body:

- attributes
- accessor bodies and a separate access level for each accessor
- `willSet` and `didSet`
- `via` behaviors

`lazy`, computed properties and `static` are not allowed on a parameter, because each of those has no incoming value to receive.

### 9.1 Named constructors

A class has one unnamed main constructor. Any other constructor has a name and passes control to the main one with `: this(…)`. Every creation is written `new Class…`.

```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }

let artifact = new Artifact.fromJson(json);
```

- A named constructor runs on the new object, so it can set get-only properties and `init` properties.
- The dependency container always uses the main constructor.
- There is no overloading by argument types.
- A static method that looks up an existing object, such as `Journey.forUuid`, stays a static method.

## 10. Structs

A `struct` is a value type.

- **Assigning or passing a struct shares it until someone writes to it.** The first write copies it if anything else still holds it, the same copy-on-write PHP uses for arrays.
- **A struct changes only through its properties,** as in `point.x = 5`.
- **A method cannot change `this`.** A method that "changes" a struct returns a new one, usually built with `with`.
- **A `readonly struct` cannot change at all.**
- **`==` compares values, and `===` compares identity.**
- **A struct cannot inherit or be inherited,** but it can implement interfaces.
- **A struct has no default value.** Every `required` property must be set when one is created.

```csharp
public struct Point
{
    public int x { get; set; }
    public int y { get; set; }
    public Point moved(int dx) => this with { x: this.x + dx };
}

let b = a;
b.x = 5;   // b is copied here, and a is unchanged
```

`with` copies an object or a struct and sets the listed properties through their `init` or `set` accessors. The original is unchanged.

**Reference:** php-src PR #13800, "Implement structs", implements this copy-on-write mechanism. Its `mutating` methods with `!` call syntax are left out for now, and can be added later without breaking code.

## 11. Generics

**Generics are reified.** The running program knows every type argument.

- Reflection shows `PaginatedList<Order>`.
- `page is PaginatedList<Order>` is checked while the code runs.
- A call from plain PHP that passes the wrong type throws a `TypeError`.

**Writing type arguments:**

- **`new` always names them:** `new PaginatedList<Order>(…)`.
- **A generic method call infers them** from the arguments it receives.

**Written type arguments are carried at runtime, and inferred ones are known to the checker only.** "Written" covers `new`, an explicit call such as `Json.decode<WebhookPayload>(body)`, and a declared type such as a property, a parameter or `List<Line> lines = …`. Every type, inferred or written, is known while code is checked, as in TypeScript. The engine compiles one file at a time, so it cannot see an inferred argument. A value created from an inferred argument, such as `const lines = [lineA, lineB]`, has no element type at runtime, and passes any runtime check for its collection type, because the checker has already proven it.

**Declaring type parameters:**

- **Names start with `T`:** `TItem`, `TKey`.
- **The bound is written inline:** `<TItem : DatabaseEntity>`.
- **Several bounds use `&`:** `<TItem : DatabaseEntity & Shareable>`.

```csharp
public class PaginatedList<TItem : DatabaseEntity> { … }
public PaginatedList<TItem> list<TItem : DatabaseEntity>(Query<TItem> query) { … }
```

### 11.1 Variance

Variance is declared on the type parameter. Classes and interfaces can both declare it.

- **`out TItem`:** the type only hands `TItem` out. A `PaginatedList<Order>` can then be used as a `PaginatedList<DatabaseEntity>`.
- **`in TItem`:** the type only takes `TItem` in. A `Validator<DatabaseEntity>` can then be used as a `Validator<Order>`.
- **No marker:** the type is invariant, and neither substitution is allowed.

The checker enforces the marker on every member. A member that breaks it is a compile error on that member's line.

When a missing marker blocks a substitution, the error names the marker to add:

```text
PaginatedList<Order> cannot be used as PaginatedList<DatabaseEntity>.
TItem is only returned by PaginatedList, so declare it `out TItem`.
```

There is no variance at the point of use, such as Java's `? extends T`.

## 12. Collections

PHP# has three collection types: `List<T>`, `Map<TKey, TValue>` and `Set<T>`.

- Their element types are reified, so a wrong element throws a `TypeError`.
- They are values, as structs (section 10) and PHP's own arrays are. Assigning or passing a collection copies it only when one side later writes to it.
- A `Map`'s keys are `int` or `string`, as a PHP array's keys are.

```csharp
let b = a;
b.add(line);   // b is copied here, and a is unchanged
```

**A method that changes a collection it was given changes its own copy.** It returns the collection when the caller should see the change:

```csharp
public static List<Line> withShipping(List<Line> lines, Line shipping)
{
    lines.add(shipping);
    return lines;
}

lines = Cart.withShipping(lines, shipping);   // the caller keeps the change
```

**Literals:**

```csharp
const lines = [lineA, lineB];                  // List<Line>
const plans = ["pro": pro, "team": team];      // Map<string, Plan>, the same name: value rule as section 16
const empty = [:];                             // an empty Map
Set<string> tags = ["vip"];                    // the declared type makes it a Set
```

PHP's `["key" => value]` is not used, because `=>` is the lambda arrow.

**A list passed where a `Set` or a tuple is expected becomes one.** The receiving parameter converts it on arrival, as PHP already converts arguments to a parameter's type:

```csharp
public static void apply(Set<string> tags) { … }
Tags.apply(["vip", "new"]);                      // arrives as a Set
```

**Methods** follow Kotlin's names. These are TypeScript's names plus the helpers TypeScript lacks:

```csharp
lines.filter(l => !l.refunded).sumOf(l => l.amount);
lines.map(l => l.name);
lines.first(l => l.free);
lines.any(l => l.free);
lines.contains(line);
lines.groupBy(l => l.product.id);
lines.associateBy(l => l.id);
lines.sortedBy(l => l.amount);
```

- Methods that read return a new collection.
- Methods that change a collection change it in place:

```csharp
lines.add(line);
lines.remove(line);
lines.insert(0, line);
lines.clear();
plans.set("pro", pro);
plans["pro"] = pro;
plans.remove("pro");
```

The complete method list is specified with the standard library.

## 13. `readonly`

`readonly` has one rule throughout the language:

- **Before a type,** it makes that value readonly.
- **After a method's parameters,** it makes `this` readonly inside that method.

```csharp
public void render(readonly Order order)          // the parameter
public readonly Customer customer => this.owner;  // the returned value
public Money totalIn(string currency) readonly    // this, inside the method
public readonly Customer buyer() readonly         // both
```

**Through a `readonly` value, only reading is allowed:**

- property `get` accessors
- methods marked `readonly`

Setters and unmarked methods are compile errors.

**Readonly reaches everything read through it.** A property read through a `readonly` value is also `readonly`, so `order.lines` is readonly when `order` is.

**A `readonly` method is checked as if `this` were a `readonly` value.** Inside it, `this` cannot be written, and only other `readonly` methods can be called on it.

**A missing marker is a compile error that names the fix:**

```text
render cannot call order.totalIn: order is readonly.
totalIn does not change Order, so declare it `totalIn(string currency) readonly`.
```

`readonly` is enforced by the checker. The engine sees ordinary methods, so plain PHP callers are not checked.

**Properties use `get` and `set`, not `readonly`.** A get-only property already cannot be reassigned. `readonly struct` keeps its meaning from section 10: every value of the struct is readonly.

## 14. Functions as values

### 14.1 Function types

A function type is written as `Function<ReturnType(ParameterTypes)>`, in the same order as a method declaration. One name covers every case, including functions that return `void`.

```csharp
Function<Money?(readonly Line, string)> priceOf
Function<void(Order)>? onPaid = null
public Function<bool(Order)> eligibleFor(Tenant tenant)
```

### 14.2 Lambdas

A lambda is written as a bare arrow. Parameter types are optional when the checker can infer them.

```csharp
lines.filter(l => !l.refunded);
const check = (Order order) => order.total >= minimum;
const price = (readonly Line line, string currency) => {
    if (line.free) return Money.zero(currency);
    return line.product.priceIn(currency);
};
```

A lambda captures the variables it uses from around it, with no `use` list. It captures the variable itself, not a copy, as C# does:

```csharp
let count = 0;
const increment = () => { count += 1; };
increment();   // count is now 1
```

### 14.3 Methods as values

A method named without parentheses is a function value. PHP writes this as `Str::slug(...)`.

```csharp
names.map(Str.slug);
```

### 14.4 Null operators

- **`a ?? b`:** `b` when `a` is null.
- **`a ??= b`:** assigns `b` only when `a` is null.
- **`a?.b`:** reads `b`, or gives null when `a` is null. PHP writes this as `?->`.
- **`f?.(x)`:** calls `f` only when it is not null.

## 15. Events

The language wires events. An app adds attributes, such as Dent's `[Retry]`, for its own policy.

```csharp
public class Order
{
    public event OrderPaid paid;                        // declared on its owner
    void settle() { emit paid(new OrderPaid(this)); }   // only Order can emit it
}

[Retry(Retry.ExponentialDays)]
public void deliver(OrderPaid e) on Order.paid { … }    // subscribes this method
```

**Declaring:** `event` declares an event on a class. Its type is the payload.

**Emitting:** `emit` raises the event. Only the class that declares it can emit it, and anywhere else is a compile error.

**Subscribing:** `on Owner.event` after a method's parameters subscribes that method. It sits in the same position as `readonly`.

- A renamed or removed event is a compile error at every `on` that names it.
- The parameter type must match the event's payload type.

**Wiring is automatic:**

1. **At build time,** the checker writes an index of every `on` subscription.
2. **At boot,** the runtime loads that index once.
3. **On `emit`,** the runtime passes the event and its handlers to the dispatcher.

**The dispatcher:**

- **By default,** it calls each handler immediately, in the same request.
- **An app can replace it** with its own, registered once at boot. Dent registers its durable bus, which builds listeners through Laravel's container, queues them and applies `[Retry]`.

**Open:** the API for registering a dispatcher. It is specified with the standard library.

## 16. Naming a value

Every place that names a value uses `name: value`:

- named arguments
- attribute arguments
- object initializers
- `with`

```csharp
new Money { amount: 500, currency: "USD" }
price with { amount: 400 }
new Artifact.fromJson(json: raw)
[Field(label: "Name")]
```

When a variable has the same name as the property, the value can be left out:

```csharp
const currency = tenant.currency;
new Money { amount: 500, currency }      // short for currency: currency
```

An object initializer runs after the constructor. It sets properties through their `set` or `init` accessors, so validation in those accessors runs. The checker requires every `required` property to be set.

## 17. Loops

`for … of` loops over a collection, as in TypeScript. The loop variable is declared with `const` or `let`, and each pass gets a fresh variable (section 3).

```csharp
for (const line of lines) { … }
for (const [key, plan] of plans) { … }
for (const [i, line] of lines.entries()) { … }
```

`for (x in y)` is a compile error that names `of`. It closes the TypeScript trap where `in` loops over keys.

These keep their C and PHP form:

- `while (…) { … }`
- `do { … } while (…);`
- `for (let i = 0; i < n; i++) { … }`

PHP's `foreach` is removed.

## 18. Strings

**Joining:** `+` joins strings. Joining a string with a number is a compile error, so `"1" + 1` cannot produce `"11"`. `+` in plain PHP files keeps its PHP meaning.

`+` decides what to do when it runs, as in JavaScript. Two strings join, two numbers add, and an object with `operator +` (section 19) calls it. The engine compiles one file at a time and cannot see other files' types, so the choice cannot be made earlier.

```csharp
const label = "Order " + order.number;
```

**Interpolation:** backtick templates with `${expr}` interpolate, as in TypeScript. They can span several lines, so they replace heredoc and nowdoc.

```csharp
const label = `Order ${order.number} for ${contact.name}`;
const html = `
    <div class="order">${order.number}</div>
`;
```

**Plain strings:** `"…"` and `'…'` never interpolate, so braces and `$` inside them are literal.

PHP's backtick shell execution is removed. `shell_exec()` stays.

## 19. Equality, comparison and operators

**Defaults, with no code:**

- **`==`** compares two objects property by property, as PHP does today.
- **`===`** is true only for the same object. It cannot be overridden.
- **`Set`** hashes its elements from the same properties that `==` compares.

**Overrides,** declared once on a type and inherited by its subclasses:

```csharp
public abstract class DatabaseEntity
{
    public static bool operator ==(DatabaseEntity a, DatabaseEntity b) => a.id === b.id;
    public int hash() => this.id;
}

public class Money
{
    public static int operator <=>(Money a, Money b) { … }
    public static Money operator +(Money a, Money b) { … }
}
```

- **Overloadable operators:** `+ - * / % **`, unary `-`, `==` and `<=>`. No other operator can be overloaded.
- **`!=`** is derived from `==`.
- **`< > <= >=` and sorting** are derived from `<=>`.
- **`==` and `hash()` go together:** overriding `==` without `hash()` is a compile error.

**Interface names** have no `I` prefix and no `Interface` suffix: `Comparable`, `Linkable`.

## 20. Enums

Plain enums keep PHP's shape:

```csharp
public enum Retry : string
{
    case None = "none";
    case ExponentialDays = "days";
}
```

An enum case can also carry data. This is a closed set, and every case is declared in one block:

```csharp
public enum PaymentResult
{
    case Paid(string transactionId);
    case Declined(string reason);
    case RequiresAction(Url redirect);
}

return PaymentResult.Declined(reason: "card expired");
```

An enum has exactly one of two shapes:

- **Backed:** every case has a value, as in `case None = "none"`, and no case carries data. This is PHP's backed enum.
- **Not backed:** any mix of plain cases and cases that carry data.

```csharp
public enum CheckoutState
{
    case Open;                                   // plain
    case Paying(string gateway);                 // carries data
    case Done(Order order);
}
```

A case with a value and a case with data in one enum is a compile error. Swift enforces the same rule.

- **Each case is its own type,** so a method can take `PaymentResult.Paid` alone.
- **At runtime,** each case is a real class, so Reflection and the Schema see it.
- **The checker** rejects a `match` over the enum that misses a case.

### 20.1 Sealed interfaces

A `sealed interface` is a closed set of classes. Its implementers must sit in the same namespace, and so in the same folder. The checker gathers the set from that namespace, so there is no list to maintain.

```csharp
public sealed interface Interaction { … }
public class PopupInteraction : Interaction { … }      // same namespace: allowed
```

- **Outside the namespace,** implementing it is a compile error.
- **A `match` over it** needs no `default`, as long as it handles every implementer.
- **Adding an implementer** makes every `match` that does not handle it a compile error.

Use an enum when the cases are small data. Use a sealed interface when each case is a full class with its own methods.

## 21. Pattern matching

`match` is the only branching construct on a value. `switch` is removed. `match` works both as an expression and, with block arms, as a statement. It compares strictly and never falls through.

```csharp
const message = match (result) {
    PaymentResult.Paid(string transactionId) when order.isTest => `Test payment ${transactionId}`,
    PaymentResult.Paid(string transactionId) => `Paid ${transactionId}`,
    PaymentResult.Declined d => `Declined: ${d.reason}`,
    PaymentResult.RequiresAction(Url redirect) => `Continue at ${redirect}`,
};

match (result) {
    PaymentResult.Paid(string transactionId) => {
        deliver(order);
        log(transactionId);
    },
    default => {},
}
```

**Arms** are written `pattern => value,`, and a block arm is written `pattern => { … },`. Arms are tried from top to bottom.

**Covering every case:**

- **On an enum with data,** the arms must cover every case, or the checker rejects the `match`.
- **On any other value,** `match` must have a `default` arm.
- **An arm with `when`** does not count toward covering a case.

**Patterns:**

| Pattern | Example |
|---|---|
| value | `200 =>` |
| comparison | `< 1000 =>`, `>= 1000 and < 10000 =>` |
| case unpacked by position | `PaymentResult.Paid(string transactionId) =>` |
| whole case, named | `PaymentResult.Paid p =>` |
| properties | `{ status: 200, body: string body } =>` |
| list | `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>` |

- **A pattern creates a variable** only through a typed declaration, such as `string transactionId`. A bare name compares against an existing value.
- **`and`, `or` and `not`** combine patterns. They exist only inside patterns.
- **`when` adds a condition** to an arm. The condition is an ordinary expression.

**`is` and `as`:**

```csharp
if (result is PaymentResult.Paid p) { … }
if (result is PaymentResult.Paid(string transactionId)) { … }
const paid = result as PaymentResult.Paid;                    // PaymentResult.Paid?, null if it is not one
const paid = result as PaymentResult.Paid ?? throw new NotPaid(result);
```

- **`is`** tests a value against any pattern, and creates the pattern's variables when it matches.
- **Narrowing:** after `is` without a name, a local variable or parameter counts as the tested type for the rest of the block. Assigning to it inside the block ends the narrowing. Properties are not narrowed, because they could change between the test and the use.

```csharp
if (entity is HasDesign) {
    render(entity.design);       // entity counts as HasDesign here
}
```
- **`as`** converts a value to a type, or gives null.

**Boolean operators:** `&&`, `||` and `!` exist only in expressions. PHP's `and`, `or` and `xor` operators are removed, so `=` can no longer bind before `and`.

**Conditions are `bool`.** `if`, `while`, `do … while`, `for`, `? :`, `&&`, `||`, `!` and `match`'s `when` take a `bool`. Any other type is a compile error, so PHP's truthiness never applies:

```csharp
if (items.count()) { … }        // compile error: int is not bool
if (items.count() > 0) { … }
```

**The ternary** `c ? a : b` gives `a` when `c` is true, and `b` otherwise.

- PHP's two-operand `a ?: b` does not exist. `??` covers null (section 14.4).
- `a ? b : c ? d : e` is a compile error, as in PHP 8. Parentheses say which nesting is meant: `a ? b : (c ? d : e)`.

## 22. Inheritance

The class header lists the base class and interfaces after `:`. The checker knows which name is the class.

```csharp
public class Page : DatabaseEntity, Linkable, Shareable { … }
```

**Methods are closed unless the base class opens them** with `virtual`, or by declaring them `abstract`. A subclass replaces a method only with a required `override`:

```csharp
public abstract class DatabaseEntity
{
    public virtual string label() { … }      // subclasses may replace it
    public void save() { … }                 // nobody may replace it
}

public class Page : DatabaseEntity
{
    public override string label() { … }
}
```

These are compile errors:

- a missing `override`
- `override` when the parent has no such method
- overriding a method that is not `virtual` or `abstract`

**Classes are open** unless marked `final`. `final override` stops an override chain at that class.

**`super`** calls the parent's version:

```csharp
public override string label() => super.label() + " (page)";
```

**Traits are listed in the header** with the base class and interfaces:

```csharp
public class Page : DatabaseEntity, Linkable, HasDesign { … }
```

**A trait is also a type.** It works everywhere an interface does:

```csharp
if (entity is HasDesign d) { … }
public void render(HasDesign owner) { … }
List<HasDesign> owners = [];
```

Reflection lists a class's traits, just as it lists the class's interfaces.

## 23. Namespaces and imports

Namespace parts are separated with `.`, and imports use `import`.

```csharp
namespace App.Tenant.Store;
import App.Shared.Schema.Entities.DatabaseEntity;
```

**Full names appear only in `namespace` and `import` lines.** Code uses the short imported name, so `.` in code is always member access. The last part of an import is always the class. A full name inside code is a compile error that names the import to add.

**A class cannot share its full name with a namespace.** `Store.sharp` beside a `Store/` folder is a compile error, because both would be `App.Tenant.Store`. Java's language specification has the same rule. `Store/Store.sharp` is allowed, because it is `App.Tenant.Store.Store`. The rule also covers plain PHP classes the checker sees.

## 24. Built-in types and `Any`

Built-in types are lowercase: `int`, `float`, `bool`, `string`, `void`, `null`. Every other type is capitalized: `List`, `Money`, `Any`.

**Integer overflow throws `ArithmeticError`** at the operation that overflows, as in Swift and C#'s `checked`. PHP's silent change to `float` does not happen in PHP# code.

**`/` on two integers truncates toward zero,** as in C#. `/` with a `float` operand stays float division.

```csharp
7 / 2          // 3
-7 / 2         // -3
7 / 2.0        // 3.5
```

- Division by zero throws `DivisionByZeroError`.
- `PHP_INT_MIN / -1` throws `ArithmeticError`, because the result overflows.
- `/=` follows the same rules.
- Like `+` (section 18), `/` chooses when it runs, from the types of its operands.

**`Any` holds a value of any type except null. `Any?` also allows null.** A value of type `Any` must be checked with `is`, `as` or `match` before it can be used:

```csharp
Any payload = Json.decode(body);
payload.order;                              // compile error: check what payload is first
if (payload is WebhookPayload) {
    payload.order.total;                    // narrowed to WebhookPayload
}
```

PHP's `mixed` is removed. Values coming from plain PHP that are typed `mixed` or untyped arrive as `Any?`. Decoding straight into a type, such as `Json.decode<WebhookPayload>(body)`, is the normal path. That decoding API is specified with the standard library.

## 25. Referring to classes

**`typeof(X)`** gives a typed class object, `Class<X>`, not a string. It works on type parameters, because generics are reified. PHP's `Order::class` string is removed.

```csharp
Class<Order> type = typeof(Order);
type.attributes<Listen>();
typeof(TItem);
```

**`Self`** means the class a static method was actually called on. It replaces PHP's `static`. PHP's `self` is removed: to mean the declaring class, write its name.

```csharp
public abstract class DatabaseEntity
{
    public static Self fromSchema(Any value, VerifiedUser caller) { return new Self(…); }
}

const order = Order.fromSchema(value, caller);   // typed as Order
```

`Self` replaces the C# workaround of passing a class to itself, as in `class Order : Entity<Order>`.

## 26. Extension methods

An extension adds methods and properties to an existing type from outside it. Extensions are declared in `extension(...)` blocks inside a static class, as in C# 14:

```csharp
public static class TextExtensions
{
    extension(string value)
    {
        public string slug() => Str.slug(value);
        public bool isBlank => value.trim() === "";
    }
}

import App.Shared.Text.TextExtensions;
title.slug();
title.isBlank;
```

- **An extension applies only in files that import its class.**
- **An extension cannot hide a real member.** If the type, or any class derived from it, has a member with that name, the extension is a compile error.
- **When the code runs, a real member wins,** and otherwise the extension imported in that file is called. The checker's rule above guarantees the two never compete.
- **Extensions can only reach the type's public members,** so access rules still hold.

## 27. PHP# files

A file's extension marks it as PHP#. The engine, Composer's autoloader, editors and the checker all choose the grammar from the filename.

A PHP# file has no opening tag. Its first line is code:

```csharp
namespace App.Tenant.Store;

import App.Shared.Schema.Entities.DatabaseEntity;
```

The extension is **`.sharp`**, as in `StoreService.sharp`.

Plain PHP files keep `.php` and `<?php`, and the two call each other freely. Composer's autoloader tries `.sharp` after `.php`, the same way it already tries `.hh` for Hack.

## 28. Verification

The checker verifies two kinds of facts before code runs:

- **Structure:** what code may depend on and do. Visibility and effects belong to the language. Code-shape and style rules stay plugins in the checker.
- **Values:** facts the code guarantees, written as laws with hand-written proofs, as in Bend, Lean and Agda.

Both kinds are written in Lean 4 and checked by Lean. A `.sharp` file holds only code.

**Laws hold only over pure code** (section 29). The checker translates pure PHP# code to Lean, and Lean's kernel checks the proofs. Bend's `--verdict` mode and Aeneas, which translates Rust to Lean, work the same way.

**Structure rules are checks that Lean runs.** The checker loads the code's structure, meaning its namespaces, imports and references, as data, and Lean runs each rule over it like a function. A broken rule reports every offending line. Laws, which cover every possible value, stay theorems proved by Lean's kernel. A structure rule only scans the facts that exist, where a kernel proof gives the same answer far more slowly. Structure rules replace architecture linters such as Dent's Mago `Module` rule, the way CodeQL queries and Mathlib's `#lint` checks do.

### 28.1 Rules files

Each namespace may have one rules file: a `.lean` file named after the namespace, placed beside its folder. Lean projects lay files out the same way, as with `Mathlib/Order.lean` beside `Mathlib/Order/`.

```text
app/Tenant/
├── Store.lean           <- Lean module App.Tenant.Store: every rule and law about this namespace
└── Store/
    ├── Refunds.sharp
    └── StoreService.sharp
```

```lean
-- app/Tenant/Store.lean
@[rule] def requires : Rule :=
  Sharp.importsOf "App.Tenant.Store" ⊆ ["App.Tenant.Community", "App.Shared.Schema"]

theorem refundNeverExceedsPaid (paid refunded amount : Int)
    (h1 : refunded ≤ paid) (h2 : amount ≤ App.Tenant.Store.Refunds.remaining paid refunded) :
    refunded + amount ≤ paid := by
  unfold App.Tenant.Store.Refunds.remaining at h2
  omega
```

- **Rules files are optional.** A namespace without one has no rules beyond the language's own.
- **A rule lives with the namespace it constrains.** "Shared never reaches Tenant" goes in `App/Shared.lean`. A rule about the whole application goes in `App.lean`.
- **Names match exactly.** Translated code keeps its PHP# names in Lean, with no prefix.
- **A failed rule is reported on the code that breaks it,** such as the offending `import` line, not only as a failed theorem.
- **The editor shows,** above each method, the laws that mention it.

**Open:** the API of the generated structure module (`Sharp.importsOf` and the rest).

## 29. Effects

An effect is anything a method does beyond computing its result: database, network, files, clock, randomness, mail. PHP# tracks effects through the objects a class holds, a model called object capabilities, which Scala 3, Effekt and Pony also use. It also records every call into plain PHP.

**Any PHP# code may call plain PHP,** including PHP's built-in functions and Laravel's facades, helpers and model methods. Most libraries are plain PHP, so this is how PHP# code uses them.

```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

- **The checker records a call into plain PHP as an effect,** so the method that makes it is never pure and never takes part in laws (section 28).
- **Stubs mark what is pure.** The engine's stubs mark PHP's built-in effect functions, such as PDO, curl, `file_put_contents`, `time()` and `random_int`. Every other built-in function is pure. A plain PHP function or method is an effect unless a stub file marks it pure. A call to something pure is not an effect, so pure code can make it.

**A `foreign` class turns an effect into an object** that code holds and passes on. Its methods call plain PHP, like any other code. Code that holds a `foreign` object has that effect by name, which `uses` can declare.

```csharp
import Illuminate.Support.Facades.Redis;

public foreign class Cache
{
    public string? get(string key) => Redis.get(key);
}
```

The standard library ships `Database`, `Http`, `Files`, `Clock` and `Random`. A project declares its own `foreign` classes the same way.

**A `foreign` object reaches other code only through constructors:**

```csharp
public class TenantCache
{
    public TenantCache(private Cache cache) { }
}
```

- **A class's effects** are the `foreign` classes it holds, directly or through its fields, and the plain PHP its methods call. The checker works them out from field and constructor types and from method bodies. Nothing is written down.
- **A `foreign` object** is created once, where the app starts, and handed down through constructors. Creating one anywhere else, or storing one in a static, is a compile error.

**Pure code** reaches no `foreign` object, calls no plain PHP that is not marked pure, and changes nothing it was given (section 13). Getters must be pure. Laws (section 28) reason only about pure code, and Lean cannot see inside `foreign` classes or plain PHP.

**Code without a body is pure unless it says `uses`.** This covers interface methods, abstract methods and function types. Every implementation is held to what the declaration allows:

```csharp
public interface PaymentGateway
{
    Money quote(Cart cart);                          // implementations must be pure
    Charge charge(Cart cart) uses Http;              // implementations may reach Http, nothing else
}

Function<Money(Offer)> priceOf                       // a pure function value
Function<Charge(Cart)> uses Http charge              // may reach Http
```

**Code with a body never writes `uses`.** Its effects enter through the constructor and through the plain PHP it calls, and its body shows both. The checker works out each method's effects, and the editor displays them.

## 30. Tuples

A tuple groups a fixed number of typed, named values without declaring a class. It uses TypeScript's brackets. Each element is declared type first, as everywhere in PHP#.

```csharp
public [Money total, int count, Date since] summarize() { return [total, count, since]; }

const [total, count, since] = order.summarize();     // unpack
const summary = order.summarize();
summary.total;                                        // read by name, as in C#
```

- **A tuple can have any number of elements.** The checker suggests a struct past four.
- **Names are required in a tuple type.** `[Money, int]` is a compile error.
- **A list literal becomes a tuple** when the declared type is a tuple.
- **Map entries are tuples:** `for (const [key, plan] of plans)`.

## 31. Expected failures as values

An expected failure, such as a declined card, is returned as a value. An exception means a bug, and it fails loudly.

```csharp
public enum Result<T, TError : Error>
{
    case Ok(T value);
    case Error(TError error);
}

public Result<Order, PaymentError> charge(Cart cart) { … return Result.Error(PaymentError.Declined(reason)); }
```

`Result` is an ordinary enum with data from the standard library, so `match`, `is` and the exhaustive-case check work on it unchanged.

**`try` passes a failure up to the caller.** If the call returns `Error`, the enclosing method returns that same `Error` immediately. Otherwise `try` gives the `Ok` value. Zig and Swift use `try` the same way.

```csharp
public Result<Receipt, PaymentError> checkout(Cart cart)
{
    const order = try gateway.charge(cart);      // Order, or return the failure here
    return Result.Ok(order.receipt());
}
```

`try`/`catch` blocks remain for exceptions, which are now mostly limited to the edges, such as wrapping a plain PHP exception.

**Every failure type implements `Error`,** a standard-library interface. One handler can then render any failure, such as an API turning it into an RFC 9457 response.

```csharp
public interface Error { string message { get; } }

public enum PaymentError : Error
{
    case Declined(string reason);
    case Expired;
    public string message => match (this) { … };
}
```

**Inside `.sharp` files, `Error` always means this interface.** PHP's global engine class `Error` is never named in PHP# code. Bugs are caught as `Throwable`, and engine subclasses such as `TypeError` keep their names.

## Undecided, in order

None. The standard library's APIs are specified with the library itself: event dispatch, JSON decoding, the complete collection methods, and the generated structure module for rules files.

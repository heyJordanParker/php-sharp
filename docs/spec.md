# PHP# language specification

PHP# is a per-file dialect of PHP. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely. This file is the single source of truth for PHP# syntax and rules.

[decisions/](decisions/) records why each rule was chosen.

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
  - superglobals such as `$_SERVER` and `$GLOBALS` (section 29)

## 3. Scope

A name lives from its declaration to the `}` that closes its block.

- Each loop pass gets a fresh binding.
- A closure captures the variable itself, not a copy.
- An inner block cannot redeclare a name an outer block declares. This is C#'s rule CS0136, and the checker rejects it before the code runs.
- A variable that `is not` creates stays in scope after an `if` whose block always exits (section 21).

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

**`Class.y` without a call always reads a static value:** a static property, a constant, an enum case, or a static method as a function value (section 14.3). A bare `Order.total` is never a property reference, which is written `nameof(Order.total)` (section 6.3).

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

- **A field is storage.** It can only be `private` or `protected`. There are no public fields, except an override of a plain PHP parent's `public` property (section 6.1).
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

**A `T?` field or settable auto-property with no initial value starts as null,** as in C# and Swift. A get-only `T?` property in the class body with no initial value is a compile error. The error names both fixes: an initial value, or a `set` accessor.

```csharp
private int? total;                      // compiles: starts as null
public string? nickname { get; set; }    // compiles: starts as null
public string? note { get; }             // compile error: give note an initial value, or a set accessor
public string? note { get; } = null;     // compiles
```

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

**A class can override a plain PHP parent's property** with `override`, and it writes the type like every PHP# field. Eloquent's `$table`, `$fillable` and `$timestamps` are overridden this way:

```csharp
namespace App.Store;

import Illuminate.Database.Eloquent.Model;

public class Order : Model
{
    protected override string? table = "orders";                   // compiles: fits Model's @var string|null
    protected override List<string> fillable = ["number", "total"]; // compiles
    protected override List<string> with = ["customer"];            // compiles
    public override bool timestamps = false;                        // compiles

    protected override table = "orders";                            // compile error: write the type
    public override List<string> fillable = ["number"];             // compile error: fillable is protected in Model
    protected override string? table = 5;                           // compile error: 5 is not string?
    protected override int timestamps = 0;                          // compile error: Model's timestamps is bool
    protected override List<string> fillable = this.columns();      // compile error: Model's constructor reads fillable
                                                                    // before Order's code runs, so the value must be constant
    protected string table = "orders";                              // compile error: Model declares table; write override
}
```

- **The written type must fit the parent's.** When PHP declares the parent's property with no type, the written type must be assignable to the parent's `@var` type, or to anything when there is no `@var`. The engine drops the written type when the class links, because PHP refuses a typed redeclaration there. When PHP declares the parent's property with a type, the written type must equal it, because PHP checks property types for invariance when the class links. The engine keeps it.
- **The access level is written and must match the parent's.** It is `public` when the parent's property is, as `timestamps` is in `Model`.
- **The value must be constant.** Section 6 sets any other initial value at the start of the constructor, and the parent's constructor may already have read it, as Eloquent's does.
- **A PHP# parent's field is overridden the same way,** measured against that field. A PHP# parent's property with accessor bodies is overridden as a property, as the list above says.

### 6.2 Change observers

`willSet` runs before a write, and `didSet` runs after it.

```csharp
public string name { get; set; didSet => this.touch(); }
```

### 6.3 Typed property references

`nameof(Order.total)` names a property. The reference is typed, and the checker rejects a misspelled or removed property wherever it is used. It sits beside `typeof(Order)` (section 25), as in C#'s `nameof`.

```csharp
query.orderBy(nameof(Order.total));   // compiles: a typed reference to Order's total property
query.orderBy(nameof(Order.totl));    // compile error: Order has no property totl
query.orderBy(Order.total);           // compile error: Order.total reads a static value, and total is an instance property
```

A bare `Order.total` is never a property reference, so `Class.y` always reads a static value (section 4).

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

**A method can take any number of arguments** with PHP's `...`. The parameter is written `int ...values`, and `values` arrives as a `List<int>`. A call spreads an existing list with `...list`.

```csharp
public static int sum(int ...values) { … }

Money.sum(1, 2, 3);
Money.sum(...prices);    // spreads an existing list
Math.max(0, ...prices);  // into the standard library too. The 0 gives max a value when prices is empty
```

- `...` is allowed only on the last parameter.
- A spread works the same into PHP# methods and into plain PHP.
- Only a `List` spreads into a call. Spreading a `Map` into a call is a compile error (section 12).
- An override of a plain PHP method declared with `...` declares that parameter with `...` too.

## 8. Functions

There are no top-level functions. Shared code lives in static methods on a class.

**A PHP function is reached through the type it works on,** as a method the standard library declares with an extension (section 26), as in `name.trim()`. Otherwise it goes through a static class in its standard library namespace (section 23), as in `Math.max(a, b)` and `Json.encode(body)`.

```csharp
import Sharp.Json.Json;
import Sharp.Math.Math;

public class Receipt
{
    public string label(string name) => name.trim();                        // compiles: runs as trim($name)
    public int larger(int a, int b) => Math.max(a, b);                      // compiles
    public string body(Map<string, Any> payload) => Json.encode(payload);   // compiles
    public string raw(string name) => trim(name);                           // compile error: write name.trim()
}
```

- **The compiler inlines a standard-library method whose body is one call,** as the .NET and JVM JITs and rustc inline small methods. So `name.trim()` runs as `trim($name)`, and no keyword marks the method.
- **Once the standard library wraps a PHP function, calling that function from `.sharp` code outside the standard library is a compile error that names the method.**
- **PHP functions the standard library does not wrap yet stay callable,** and so do plain PHP functions, such as Laravel's `now()`. Section 29 covers their effects.

**Output and exit are function calls.** `echo` and `print` are removed, and output goes through `printf` or `fwrite`. `die` is removed, because `die("…")` prints its message and exits with status 0, which reports success. `exit(code)` stays, as PHP 8.4's built-in function.

```csharp
public class Prune
{
    public static void run(int pruned, bool failed)
    {
        if (failed) {
            fwrite(STDERR, "Prune failed" + PHP_EOL);    // compiles: the message goes to STDERR
            exit(1);                                     // compiles: ends the process with status 1
        }
        printf("Pruned %d carts" + PHP_EOL, pruned);     // compiles
    }
}

echo "Pruned";                                           // compile error: write printf or fwrite
print "Pruned";                                          // compile error: write printf or fwrite
die("Prune failed");                                     // compile error: write the message to STDERR, then exit(1)
```

- `exit` skips every `finally` block, as Java's `System.exit` and C#'s `Environment.Exit` do.
- Printing has the effect `Console`, and `exit` has the effect `Process` (section 29), as Koka's `console` effect marks printing.

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

**A constructor marked `required`** is one that `new Self(…)` can call on the class and on every subclass. Section 25 gives its rules.

### 9.1 Named constructors

A class has one unnamed main constructor. Any other constructor has a name and passes control to the main one with `: this(…)`. Every creation is written `new Class…`.

```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }

let artifact = new Artifact.fromJson(json);
```

- A constructor calls its base class's constructor with `: super(…)`, as in `public Order(Row row) : super(row) { }`.
- A named constructor runs on the new object, so it can set get-only properties and `init` properties.
- The dependency container always uses the main constructor (section 32).
- There is no overloading by argument types.
- A static method that looks up an existing object, such as `Journey.forUuid`, stays a static method.

## 10. Structs

A `struct` is a value type.

- **A struct never changes.** A method returns a changed copy, usually built with `with`, as Kotlin's data class `copy` does. `point.x = 5` is a compile error that names a method returning a changed copy.
- **`readonly struct` is a compile error,** because every struct is already read-only.
- **`==` compares values.** `===` works on classes only (section 19).
- **A struct cannot inherit or be inherited,** but it can implement interfaces.
- **A struct has no default value.** Every `required` property must be set when one is created.

```csharp
public struct Point
{
    public int x { get; init; }
    public int y { get; init; }
    public Point moved(int dx, int dy) => this with { x: this.x + dx, y: this.y + dy };
}

Point q = p.moved(1, 0);              // compiles: q is a changed copy, and p is unchanged
p.x = 5;                              // compile error: a struct never changes; return a changed copy from a method, such as moved
public readonly struct Size { … }     // compile error: every struct is already read-only; write struct
```

`with` copies an object or a struct and sets the listed properties through their `init` or `set` accessors. The original is unchanged.

**A struct can declare a backing value** with the header an enum uses (section 20). `public struct Username : string` has a `string` backing value, so it can be a `Map` key (section 12).

```csharp
public struct Username : string
{
    public Username(public string value { get; }) { }
}
```

**Every struct has a static `parse`,** which reads a `Map<string, Any?>` or an object's public properties and throws one error that lists every bad field, and a static `tryParse`, which gives null instead. The names follow `Int.parse` and `Int.tryParse` (section 24). Classes do not get them.

```csharp
public struct RenewRequest
{
    public RenewRequest(
        public int customerId { get; },
        [Key("plan_code")] public Plan plan { get; },
        public string? coupon { get; },
    ) { }
}

RenewRequest request = RenewRequest.parse(payload);     // throws: "customerId: expected int, got string 'abc'; plan_code: missing"
RenewRequest? maybe = RenewRequest.tryParse(payload);   // null on any bad field
```

- The keys are the parameter names of the main constructor (section 9.1).
- `[Key("plan_code")]` renames the key a parameter reads.
- A nested struct parses the same way.
- A `List` checks each element.
- An enum parses from its value.
- A missing key for a `T?` parameter reads as null.

An object's public properties parse the same way as a `Map`'s keys, so a row object from plain PHP becomes a checked struct:

```csharp
public struct OrderRow
{
    public OrderRow(public int id { get; }, public string number { get; }) { }
}

OrderRow order = OrderRow.parse(row);   // row is a plain PHP object from a database query; throws: "id: expected int, got string 'abc'"
```

## 11. Generics

**Generics are reified.** The running program knows every type argument.

- Reflection shows `PaginatedList<Order>`.
- Plain PHP reads an object's type arguments with `ReflectionObject::getTypeArguments()`, as C#'s `Type.GetGenericArguments()` does. A class value reaches plain PHP as a plain class-name string (section 25), so it carries none.
- `page is PaginatedList<Order>` is checked while the code runs.
- A value of the wrong type throws a `TypeError` where it enters PHP# from plain PHP. Section 12 lists where a value enters.

**Each type argument of a generic class has its own static members,** as in C#. `Counter<Order>.count` is separate from `Counter<User>.count`, and a static member may use the class's type parameters:

```csharp
public class Counter<T>
{
    public static int count { get; set; } = 0;
    public static List<T> seen { get; set; } = [];      // compiles: a static member may use T
}

Counter<Order>.count += 1;
Counter<User>.count;                                     // 0: Counter<User> has its own count
```

**Writing type arguments:**

- **`new` always names them:** `new PaginatedList<Order>(…)`, or names a class value that carries them, as in `new (pages)()` (section 25).
- **A generic method call fixes them** from the arguments it receives, or from the declared type its result goes into. A type argument that neither fixes is a compile error, as in C#'s error CS0411 and in Swift.
- **Plain PHP generics,** declared with PHPDoc's `@template`, keep their fallback.

```csharp
public class Cache
{
    public T get<T>(string key) { … }
}

List<Order> orders = cache.get("orders");   // compiles: the declared type fixes T as List<Order>
const orders = cache.get("orders");         // compile error: nothing fixes T; write cache.get<List<Order>>("orders")
```

**A generic object that plain PHP creates without type arguments** (`new PaginatedList($rows)`, or Laravel's container) has its bounds as its type arguments, as Java's raw types do, but checked where it enters PHP# instead of trusted. Under `PaginatedList<TItem : DatabaseEntity>`, a raw page is a `PaginatedList<DatabaseEntity>`. A parameter that needs `PaginatedList<Order>` throws `TypeError`. A method that takes any page is generic. PHP# code never creates a raw object, because a type argument it can't fix or infer is a compile error.

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

**Every type argument is carried at runtime, written or inferred.** The checker's types reach the running program (section 27), so a generic method can use a type parameter that its call inferred:

```csharp
public class Inbox
{
    public bool holds<TItem>(List<TItem> items, Any value) => value is TItem;
}

inbox.holds(orders, message);   // compiles: TItem is inferred as Order, and value is TItem tests Order when the code runs
```

A collection's elements are still checked where it enters from plain PHP (section 12).

**Declaring type parameters:**

- **Names start with `T`:** `TItem`, `TKey`.
- **The bound is written inline:** `<TItem : DatabaseEntity>`.
- **Several bounds use `&`:** `<TItem : DatabaseEntity & Shareable>`.

```csharp
public class PaginatedList<TItem : DatabaseEntity> { … }
public PaginatedList<TItem> list<TItem : DatabaseEntity>(Query<TItem> query) { … }
```

**`Self` on a receiver typed by a type parameter is that type parameter** (section 25). In `TBox refill<TBox : Box<int>>(TBox box)`, `box.withValue(5)` returns a `TBox`, not `Box<int>`, as Rust's `t.clone()` on a `T: Clone` returns a `T`:

```csharp
public class Box<T>
{
    public required Box(public T value { get; }) { }
    public Self withValue(T value) => new Self(value);
}

public TBox refill<TBox : Box<int>>(TBox box) => box.withValue(5);   // compiles: withValue returns a TBox
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

- They are values, as structs (section 10) and PHP's own arrays are. Assigning or passing a collection copies it only when one side later writes to it.
- A `Map`'s key is an `int`, a `string`, or a type with an `int` or `string` backing value (see **Map keys** below).
- `List` and `Map` both run as plain PHP arrays. The engine knows which one it compiles from the checker's types (section 27), so each operation does what it naturally means on that kind of collection.

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

**A collection held in a property changes through the property's `set`.** `this.lines.add(line)` reads `lines`, changes the copy and writes it back through `set`. Code that cannot reach the `set` cannot change the collection, and a copy in a local changes only the local:

```csharp
public class Order
{
    public List<Line> lines { get; private set; } = [];
    public void add(Line line) { this.lines.add(line); }   // writes lines back through its private set
}

order.lines.add(line);    // compile error outside Order: lines has a private set
let copy = order.lines;
copy.add(line);           // changes only copy
```

**Element types are checked where a collection enters PHP# from plain PHP,** not on every write. Plain PHP can change only its own copy, so a wrong element reaches PHP# code only by crossing in:

- a plain PHP caller passes a collection to a PHP# method
- a typed assignment takes a value that plain PHP returned
- plain PHP writes a PHP# property

At each crossing, the runtime checks every element once and throws a `TypeError` that names the element. A call from PHP# to PHP# checks nothing at runtime, because the checker proved it.

`as` to a collection type checks every element too, wherever the value came from, and gives null if any element is wrong (section 21).

```csharp
List<Line> lines = legacy.lines();            // plain PHP returned it: every element is checked here
lines = Cart.withShipping(lines, shipping);   // PHP# to PHP#: nothing is checked
```

**Literals:**

```csharp
const lines = [lineA, lineB];                  // List<Line>
const plans = ["pro": pro, "team": team];      // Map<string, Plan>, the same name: value rule as section 16
Map<string, Plan> empty = [:];                 // an empty Map
Set<string> tags = ["vip"];                    // the declared type makes it a Set
```

PHP's `["key" => value]` is not used, because `=>` is the lambda arrow.

**An empty literal, `[]` or `[:]`, with no declared type is a compile error,** as in Swift, because nothing says what it holds:

```csharp
let messages = [];                             // compile error: an empty literal needs a type: write List<string> messages = []
List<string> messages = [];                    // compiles
```

**A `List` literal where a `Map` is declared, or a `Map` literal where a `List` is declared, is a compile error,** at a declaration, an assignment, a default, a return or an argument, because `[:]` and `[key: value]` write a `Map`, and `[]` and `[a, b]` write a `List` or a `Set`:

```csharp
Map<string, int> counts = [];                  // compile error: `[]` is an empty List. An empty Map is written `[:]`.
List<string> tags = [:];                       // compile error: `[:]` is an empty Map. An empty List is written `[]`.
Map<int, string> names = ["a"];                // compile error: A Map literal is written `[key: value]`.
```

**A spread copies a collection into a literal,** and the collection's type decides what it means:

```csharp
List<Line> lines = [...open, ...closed];                  // List: closed's lines follow open's
List<string> command = [binary, "artisan", name, ...arguments];
Map<string, string> options = [...config, "root": ""];    // Map: config's entries, then "root" set to ""
Map<string, int> limits = [...defaults, ...overrides];    // Map: a key in both takes overrides' value
const mixed = [...lines, ...options];                     // compile error: a literal cannot spread a List and a Map together
image.resize(...options);                                 // compile error: only a List spreads into a call
```

- A `List` spread appends its elements in order.
- A `Map` spread copies its entries. A later key replaces an earlier one, and no key is renumbered.
- A literal cannot spread a `List` and a `Map` together.
- Only a `List` spreads into a call (section 7).

**PHP's spread renumbers int keys, and PHP# does not.** A `Map<string, TValue>` stores an all-digit key such as `"5"` as the int `5`, so PHP's spread renumbers it:

```php
$defaults = ["5" => 10, "pro" => 20];
$overrides = ["5" => 30];
[...$defaults, ...$overrides];             // [0 => 10, "pro" => 20, 1 => 30]: the "5" key is gone
array_replace($defaults, $overrides);      // ["5" => 30, "pro" => 20]: what PHP# runs for a Map spread
```

A `Map` keeps its keys (decision 26), and the engine knows which collection a spread holds (section 27), so a `Map` spread runs as `array_replace` and every key survives.

**Indexing has one meaning on every collection:**

- A bare read, `x[i]`, throws `OutOfRangeException` when the index or key is missing.
- `x[k] = v` inserts or replaces a key. It is `Map`-only, so `lines[0] = line` is a compile error that names `set`.
- `set(i, v)` replaces a `List` element, and throws `OutOfRangeException` past the end. Appending is `add`.
- A `List` index is an `int`. Any wider index, such as `int|string` or `Any`, is a compile error. A negative index is never in a `List`, so `items[-1]` throws `OutOfRangeException`, and the last item is `items.last()`, as in Kotlin.

```csharp
const first = lines[0];       // throws OutOfRangeException when lines is empty
lines.set(0, line);           // throws OutOfRangeException when lines is empty
lines[0] = line;              // compile error: write lines.set(0, line)
plans["pro"] = pro;           // inserts or replaces
items[-1];                    // throws OutOfRangeException: write items.last()
items.last();                 // the last item
items[id];                    // compile error when id is int|string: a List index is an int
```

**A `Map` read is handled where it is read,** with `??`, `?.`, `is`, `as`, `match` or `get`. A bare `Map` read is a compile error that names `??` and `get`:

```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices["pro"];                                  // compile error: handle a missing "pro" with ??, or read it with get
int price = prices["pro"] ?? 0;                             // compiles: 0 when "pro" is missing
int price = prices[plan] ?? throw new UnknownPlan(plan);    // compiles: throws when plan is missing
if (prices[plan] is int price) { charge(price); }           // compiles: runs only when plan is present
int? maybe = prices.get(plan);                              // compiles: null when plan is missing
```

Data with fixed keys is a class, so a `Map` holds keys that come from outside, where a missing key is normal.

**A `Map` with nullable values reads as Kotlin's does:** a read from `Map<string, int?>` gives `int?`, so a missing key and a stored null read the same. `map.has(key)` tells them apart, as Kotlin's `containsKey` does.

```csharp
Map<string, int?> limits = ["pro": null];
limits["pro"] ?? 0;          // 0: the stored null
limits["team"] ?? 0;         // 0: the missing key
limits.has("pro");           // true
limits.has("team");          // false
```

**A key read back out of a `Map<string, TValue>` is a `string`.** PHP stores an all-digit string key, such as `"5"`, as the int `5`. The engine knows the key type (section 27), so it hands the key back as `"5"`. Plain PHP reading the same array still sees `5`. This covers the key of `for (const [key, value] of map)` and of `keys()`.

```csharp
void reserve(string sku, int count) { … }

for (const [sku, count] of stock) {   // stock is Map<string, int>, so sku is a string, even for the key "5"
    this.reserve(sku, count);
}
```

**Map keys:** a key is `int`, `string`, or any type with an `int` or `string` backing value. A backed enum is one (section 20). `public struct Username : string` declares one, with the same header an enum uses (section 10).

- `counts[status]` runs as `$counts[$status->value]`.
- A loop over the `Map` gives each key as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type, as in `const [Status status, int n]`, stays allowed. An inferred `Map`, such as a `groupBy` result, gives its keys back the same way, because the checker's types reach the running program (section 27).
- Plain PHP receives the backing values.

```csharp
public enum Status : string
{
    case Open = "open";
    case Paid = "paid";
    case Refunded = "refunded";

    public string label() => match (this) {
        Status.Open => "Awaiting payment",
        Status.Paid => "Paid",
        Status.Refunded => "Refunded",
    };
}

public class OrderStats
{
    public Map<Status, int> countByStatus(List<Order> orders)
    {
        Map<Status, int> counts = [:];
        for (const order of orders) {
            counts[order.status] = (counts[order.status] ?? 0) + 1;   // runs as $counts[$order->status->value]
        }
        return counts;
    }

    public List<string> report(List<Order> orders)
    {
        List<string> lines = [];
        for (const [Status status, int n] of this.countByStatus(orders)) {   // status arrives as a Status
            lines.add(`${status.label()}: ${n}`);
        }
        return lines;
    }
}

for (const [status, n] of stats.countByStatus(orders)) { … }   // compiles: status arrives as a Status, with no type written
for (const [status, group] of orders.groupBy(o => o.status)) { … }   // compiles: an inferred Map's keys arrive as Status too
Map<Username, Order> byUser = [:];                               // keyed by username.value
```

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
- `first` throws `OutOfRangeException` when nothing matches, as a bare index read does.
- A method that takes a function has that function's effects (section 29), so `lines.map(l => l.name)` is pure.
- Methods that change a collection change it in place:

```csharp
lines.add(line);
lines.insert(0, line);
lines.set(0, line);
lines.remove(line);           // by value: the first equal element, and true when one was removed
lines.clear();
plans["pro"] = pro;
plans.remove("pro");          // by key
```

**Each collection does the natural thing with a method:**

- `remove` removes a value from a `List` and a key from a `Map`.
- On a `List`, `remove(value)` removes the first element equal to `value` by `==` (section 19), renumbers the list, and returns `bool`: true when it removed one.
- `filter` renumbers what a `List` keeps, and keeps a `Map`'s keys.

```csharp
List<string> tags = ["a", "b", "a"];
tags.remove("a");                     // true, and tags is ["b", "a"]
tags.remove("z");                     // false, and tags is unchanged
lines.filter(l => l.free);            // List<Line>, renumbered
plans.filter(p => p.active);          // Map<string, Plan>, keys kept
```

`contains`, a `List`'s `remove`, `indexOf` and `Set<T>` need `==` on the element type (section 19). Without it, they are a compile error.

The complete method list is specified with the standard library.

**`Iterable<T>` is anything a loop can read,** and it compiles to PHP's `iterable`. `List<T>` and `Set<T>` are `Iterable<T>`, and a `Map<TKey, TValue>` is an `Iterable<TValue>` of its values (section 17):

```csharp
public class OrderReport
{
    public int tally(Iterable<Order> orders)
    {
        let n = 0;
        for (const order of orders) { n += 1; }
        return n;
    }

    public List<string> paidNumbers()
    {
        Iterable<Order> orders = Order.query().cursor();   // Laravel's LazyCollection, read as an Iterable<Order>
        return orders
            .filter(o => o.paid)
            .map(o => o.number)
            .take(100)
            .toList();                                      // runs here, and reads rows only until it has 100
    }
}

report.tally(cart.orders);                  // compiles: a List<Order> is an Iterable<Order>
report.tally(Order.query().cursor());       // compiles: rows are read one at a time
Iterable<Order> rows = Order.query().cursor();
rows[0];                                    // compile error: an Iterable has no index
```

- **Lazy operations,** such as `filter`, `map` and `take`, return an `Iterable<T>`. They run only when `toList()` or a loop reads the result.
- **A lazy plain PHP source,** such as a generator or Laravel's `LazyCollection`, is checked element by element as it is read, so each element is checked where it enters PHP# (section 11).
- **The lazy operations belong to `Iterable<T>`.** A value typed as a plain PHP class keeps that class's own methods until it is held as an `Iterable<T>`, as `orders` is above.

**`yield` produces an `Iterable<T>` lazily,** as C#'s `yield return` does. A method that returns `Iterable<T>` produces its next value with `yield value;`, and every element of another `Iterable<T>` with `yield ...other;`:

```csharp
import Illuminate.Support.Facades.File;

public class OrderImport
{
    public OrderImport(private List<Order> pending, private Order latest) { }

    public Iterable<List<string>> rows(string path)
    {
        for (const string line of File.lines(path)) {
            yield line.parseCsv();                   // compiles: each row is a List<string>
        }
    }

    public Iterable<Order> all()
    {
        yield ...this.pending;                       // every pending order, in order
        yield this.latest;                           // then the latest one
    }

    public List<List<string>> preview() => this.rows("orders.csv").take(100).toList();   // reads only the first 100 lines
}

importer.rows("missing.csv");                                    // runs nothing yet: the missing file throws when a loop reads the rows
public Iterable<Order> numbers() { yield this.latest.number; }   // compile error: a string is not an Order
public List<Order> recent() { yield this.latest; }               // compile error: yield needs a method that returns Iterable<T>
```

- **Each yielded value is checked against `T`.**
- **`yield ...other;` replaces PHP's `yield from`,** with PHP#'s spread. `other` is an `Iterable<T>`.
- **The body runs only when a loop or `toList()` reads the result,** so an error inside it surfaces at the loop, not at the call.
- **A method with `yield` compiles to a PHP generator.** PHP's `send()` and two-way generators are not part of PHP#, so `yield` is a statement and gives no value back. Async is a separate future design.

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
- any plain PHP method

Setters and unmarked PHP# methods are compile errors. Any plain PHP method can be called through a `readonly` value, because PHP# checks PHP# code only, as Kotlin's platform types leave a Java value unchecked:

```csharp
import Illuminate.Database.Eloquent.Model;

public class Order : Model
{
    public string number { get; set; }
    public Money totalIn(string currency) readonly { … }
    public void markPaid() { … }
}

public void render(readonly Order order)
{
    order.number;              // compiles: a get accessor
    order.totalIn("USD");      // compiles: totalIn is marked readonly
    order.markPaid();          // compile error: markPaid is not marked readonly
    order.number = "A-1";      // compile error: order is readonly
    order.save();              // compiles: save is a plain PHP method of Model, which PHP# does not check
}
```

**Readonly reaches everything read through it.** A property read through a `readonly` value is also `readonly`, so `order.lines` is readonly when `order` is.

- `order.lines.add(line)` writes `lines` back through its `set` (section 12), so through a `readonly` `order` it is a compile error.
- A copy in a local belongs to the local. After `let copy = order.lines`, `copy.add(line)` is allowed and leaves `order` unchanged.
- The objects inside that copy stay readonly.

**A `readonly` method is checked as if `this` were a `readonly` value.** Inside it, `this` cannot be written, and only other `readonly` methods can be called on it.

**A missing marker is a compile error that names the fix:**

```text
render cannot call order.totalIn: order is readonly.
totalIn does not change Order, so declare it `totalIn(string currency) readonly`.
```

`readonly` is enforced by the checker. The engine sees ordinary methods, so plain PHP callers are not checked.

**Fields, properties and classes use `{ get; }`, not `readonly`.** A get-only property already cannot be reassigned. `readonly` on a field, a promoted constructor parameter or a class is a compile error that names `{ get; }`:

```csharp
public class Invoice
{
    private readonly Money total;                       // compile error: declare a get-only property with { get; }
}

public class Bill
{
    public Bill(public readonly Money due) { }          // compile error: declare a get-only property with { get; }
}

public class Quote
{
    public Quote(public Money due { get; }) { }         // compiles
}

public readonly class Receipt { … }                     // compile error: declare its properties with { get; }
```

`readonly struct` is a compile error too, because every struct is already read-only (section 10).

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

A lambda that calls `add`, `set` or `remove` on a captured variable changes the variable itself:

```csharp
List<int> seen = [];
numbers.filter(n => { seen.add(n); return true; });   // seen now holds every number
```

### 14.3 Methods as values

A method named without parentheses is a function value. It is found when the code runs, so it works when the class lives in another file. PHP writes this as `Str::slug(...)`.

```csharp
names.map(Str.slug);
```

In a plain PHP class with both a property and a method of that name, the property wins, as in PHP.

**A field or property holding a `Function` is called like a method** when the class has no method with that name. A `.sharp` class cannot declare a method and a field or property with the same name, so `this.discount(…)` always has one meaning.

```csharp
private Function<int(int)> discount;
int paid = this.discount(price);       // calls the function in discount, since there is no discount method
```

The field or property is read after the arguments run, which differs from C#. An argument that replaces `discount` changes which function runs.

### 14.4 Null operators

- **`a ?? b`:** `b` when `a` is null.
- **`a ??= b`:** assigns `b` only when `a` is null.
- **`a?.b`:** reads `b`, or gives null when `a` is null. PHP writes this as `?->`.
- **`f?.(x)`:** calls `f` only when it is not null.
- **`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`:** leave when `a` is null. `return` takes a value when the method returns one.

```csharp
public void renewAll(List<int> customerIds, string plan)
{
    int price = prices[plan] ?? return;                     // no such plan: nothing to renew
    for (const id of customerIds) {
        Customer customer = customers[id] ?? continue;      // skip ids with no customer
        charge(customer, price);
    }
}
```

**A `?` or a null check that cannot matter is a compile error,** because it misstates the code. Section 24 gives the rule for `?`.

- a null check, `?.` or `??` on a value whose type has no `?`
- a nullable parameter that the method rejects on every path. The type drops the `?`, and the caller checks.
- a nullable return type on a method that never returns null

```csharp
public void renew(Customer customer, Plan plan)
{
    if (customer != null) { … }                     // compile error: customer is Customer, so it can never be null
    int price = plan.price ?? 0;                     // compile error: plan.price is int, so ?? never applies
}
public void notify(Customer? customer)
{
    Customer c = customer ?? throw new NotFound();   // compile error: notify rejects null on every path; declare it Customer and check at the caller
}
public Customer? current() { return this.customer; } // compile error: current never returns null, so its type is Customer
```

## 15. Events

An event says that something happened, such as an order being paid. `emit` raises it, and every listener for it runs. `vendor/bin/mago compile` finds every listener at build time (section 27), so nothing is wired by hand.

```csharp
public class Order
{
    public interface Event { int orderId { get; } }                   // declares Order.Event, the type both events share
    public event Paid(int orderId, Money amount) : Event;             // declares the event type Order.Paid
    public event Refunded(int orderId, Money amount) : Event;         // declares Order.Refunded

    public void markPaid()
    {
        …
        emit new Paid(this.id, this.total);                            // runs every listener for Order.Paid
    }
}

public class Receipts
{
    public void deliver(Order.Paid e) on Order.Paid { … }             // listens to every Order.Paid
}
```

**Declaring:** `event` declares an event type. `public event Paid(int orderId, Money amount);` inside `Order` declares `Order.Paid`, a class whose parameters become get-only properties, as C#'s positional records and Kotlin's data classes do. An event is declared inside the class that raises it, and an interface its events share is declared beside them, as `Order.Event` is, the way C# nests types. An event that no single class owns is declared on its own, in its own file:

```csharp
// app/Imports/RowImported.sharp
namespace App.Imports;

public event RowImported(int row);
```

**Emitting:** `emit` raises an event. Any code may raise any event type:

```csharp
emit new Paid(this.id, this.total);              // inside Order
emit new Order.Paid(order.id, order.total);      // anywhere else
```

**Plain PHP events:** `extern event` marks a plain PHP class, such as a Laravel event, as an event, so PHP# code can listen to it:

```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Queue.Events.JobFailed;

extern event JobFailed;
```

**Listening:** a trailing `on` clause after a method's parameters lists the events the method listens to, for as long as the app runs. The method takes zero parameters, or one parameter whose type every listed event shares. It takes no other parameter, and no parameter is implicit. The namespace's `Module.sharp` container creates the object each listener runs on.

```csharp
public class OrderNotices
{
    public void send(Order.Event e) on Order.Paid, Order.Refunded { … }    // two events, read through the type they share
    public void refresh() on Order.Paid { … }                              // no parameter: the event's data is not needed
    public void audit(Order.Event e) on Order.Event { … }                  // every event that implements Order.Event
    public void alert(JobFailed e) on JobFailed { … }                      // a plain PHP event marked with extern event
}
```

- **A listener on an interface hears every event that implements it.**
- **`vendor/bin/mago compile` finds every `on` at build time.** A wrong event name, or a parameter type the listed events don't share, is a compile error:

```csharp
public void send(Order.Paid e) on Order.Paid, Order.Refunded { … }      // compile error: Order.Refunded is not an Order.Paid
public void refresh() on Order.Payed { … }                               // compile error: Order has no event Payed
public void log(Order.Paid e, Logger logger) on Order.Paid { … }         // compile error: a listener takes only the event
```

**Listening for a while:** `Events.on<RowImported>(e => …)` starts a listener and returns a handle. The listener stops when the handle goes out of scope:

```csharp
public class ImportScreen
{
    public void import(string path)
    {
        const listening = Events.on<RowImported>(e => this.progress.advance());   // listens until import returns
        this.importer.run(path);
    }
}
```

**Effects:** `emit` has the effect `Events` (section 29), so a method that emits shows `Events` among its effects, and code without a body allows it with `uses Events`.

**Timing:** the default dispatcher runs listeners immediately, before `emit` returns. An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries, or not at all in tests.

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

**Any parameter can be named at a call,** including a call into plain PHP. An override keeps every parameter name of the method it overrides (section 22), so a name means the same parameter on every class.

A named argument that matches no parameter is a compile error, also on a method that takes any number of arguments (section 7).

```csharp
import Illuminate.Support.Facades.Http;

image.resize(width: 800, height: 600);
Http.post(url, data: payload);           // plain PHP: Laravel's post(string $url, $data = [])
```

An object initializer runs after the constructor. It sets properties through their `set` or `init` accessors, so validation in those accessors runs. The checker requires every `required` property to be set.

## 17. Loops

`for … of` loops over a collection or any `Iterable<T>` (section 12), as in TypeScript. The loop variable is declared with `const` or `let`, and each pass gets a fresh variable (section 3).

```csharp
for (const line of lines) { … }
for (const plan of plans) { … }                  // a Map's values
for (const [key, plan] of plans) { … }
for (const [i, line] of lines.entries()) { … }
```

A loop over a `Map` whose key has a backing value gives each key as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type stays allowed (section 12).

**A `const` loop variable can have its type written,** as in `for (const [Status status, int n] of counts)`. `let` with a written type is a parse error, because a typed local is written without `let` (section 2). A written `string` key over a `Map<string, TValue>` reads back as a `string`.

```csharp
for (const [Status status, int n] of counts) { … }   // compiles: counts is Map<Status, int>
for (const Line line of lines) { … }                 // compiles
for (let [Status status, int n] of counts) { … }     // parse error: let takes no written type
```

`for (x in y)` is a compile error that names `of`. It closes the TypeScript trap where `in` loops over keys.

These keep their C and PHP form:

- `while (…) { … }`
- `do { … } while (…);`
- `for (let i = 0; i < n; i++) { … }`

PHP's `foreach` is removed.

## 18. Strings

**Joining:** `+` joins strings. Joining a string with a number is a compile error, so `"1" + 1` cannot produce `"11"`. `+` in plain PHP files keeps its PHP meaning.

`+` decides what to do when it runs, as in JavaScript. Two strings join, two numbers add, and an object with `operator +` (section 19) calls it.

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

A template's `${expr}` accepts `int`, `float`, `string` and `bool`. A `bool` prints `true` or `false`. Every other type is a compile error, "A template shows `int`, `float`, `string` or `bool`, and `Status` is none of them.", with the offending type's name in place of `Status`. A literal or narrowed type counts as its base type, so `1|2` is an `int`. A union of two of them, such as `int|string`, is none of the four and is refused. A nullable value such as `int?` is refused until it is checked (section 24), and the same error names `int?`.

```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // bool: "Paid: true"
const c = `Status: ${status}`; // enum case: compile error
const d = `Items: ${items}`;   // List<int>: compile error
const e = `Order: ${order}`;   // class Order: compile error
```

A plain PHP file keeps PHP's own interpolation.

**Plain strings:** `"…"` and `'…'` never interpolate, so braces and `$` inside them are literal.

PHP's backtick shell execution is removed. `shell_exec()` stays.

## 19. Equality, comparison and operators

**`==` exists only where the type declares it:**

- **Structs, enums, strings, numbers and collections** compare by value with `==`, with no code.
- **A class** compares with `==` only if it or a parent declares `operator ==`. Otherwise `==` on it is a compile error.
- **`Any?`** compared with `==` against a struct, enum, string, number or collection compares by value, and is false when the types differ.
- **`===`** is true only for the same object. It works on classes only, and cannot be overridden.
- **`Set`** hashes a struct, enum, string, number or collection by its value, and an object by its class's `hash()`.

**Operators** are declared once on a type and inherited by its subclasses:

```csharp
public abstract class DatabaseEntity
{
    public static bool operator ==(DatabaseEntity a, DatabaseEntity b) => a.id == b.id;
    public int hash() => this.id;
}

public class Money
{
    public static bool operator ==(Money a, Money b) { … }   // a and b are never null
    public int hash() { … }
    public static int operator <=>(Money a, Money b) { … }
    public static Money operator +(Money a, Money b) { … }
}

order == sameOrderLoadedAgain;   // Order inherits DatabaseEntity's ==, so this compares ids
cart == otherCart;               // compile error: Cart declares no ==
cart === cart;                   // true: the same object
```

- **Overloadable operators:** `+ - * / % **`, unary `-`, `==` and `<=>`. No other operator can be overloaded.
- **`!=`** is derived from `==`.
- **`< > <= >=` and sorting** are derived from `<=>`.
- **Strings compare byte by byte,** as in Go. `<`, `>` and `sort` put `"10"` before `"9"` and capitals before lowercase, so `"10" < "9"` is true and `"Zebra" < "apple"` is true. The order agrees with `==`. `compareTo` gives -1, 0 or 1 in the same order, so `"b".compareTo("a")` is 1.
- **`==` and `hash()` go together:** declaring `==` without `hash()` is a compile error, because `Set` needs both.

**`==` and `!=` on a nullable type are lifted,** as C#'s operators on nullable values and Kotlin's `==` are. Null equals only null. Two non-null values use the type's own `==`, including a declared `operator ==`, so an `operator ==` takes two non-null values and never sees a null. `x != null` is the normal null check, and it never runs user code. `x is null` and `x is not null` stay valid as ordinary patterns (section 21).

```csharp
Money? price = null;
price == null;                   // true, without running Money's ==
price == Money.zero;             // false, without running Money's ==
total == Money.zero;             // total is a Money, so this runs Money's ==
if (price != null) { … }         // the normal null check
if (price is not null) { … }     // compiles: an ordinary pattern
```

**Bitwise operators** `|`, `&`, `^`, `~`, `<<` and `>>`, and their compound forms such as `|=`, take `int` only. Flags are ints joined with `|`:

```csharp
const READ = 1;
const WRITE = 2;
const DELETE = 4;
let permissions = READ | WRITE;               // compiles
permissions |= DELETE;                        // compiles
if (permissions & WRITE != 0) { … }           // compiles: means (permissions & WRITE) != 0
if ((permissions & WRITE) != 0) { … }         // compiles: the same meaning
if (permissions & WRITE) { … }                // compile error: int is not bool
const shown = isAdmin | isOwner;              // compile error: | takes int; write ||
const mask = 1 << count;                      // throws ArithmeticError when count is negative
```

- A condition is a `bool` (section 21), so `if (permissions & WRITE)` is a compile error.
- `|` between two `bool`s is a compile error that names `||`.
- A shift by a negative count throws `ArithmeticError`.
- The bitwise operators bind tighter than comparisons, as in Go, Rust and Swift. PHP and C# bind them looser, so there `permissions & WRITE != 0` reads as `permissions & (WRITE != 0)`.
- In a type, `|` and `&` keep their meaning as unions and bounds (sections 11 and 24).

**Precedence**, from the tightest to the loosest. Operators in one row bind equally.

| Operators | Grouping | Compared with PHP |
|---|---|---|
| `.`, `?.`, calls, indexing `[]`, `new`, `match` | left | PHP writes `->`, `?->` and `::` |
| `**` | right | same |
| unary `-`, `~`, `++`, `--`, casts `(int)`, `(float)`, `(string)` | right | same, with fewer casts (section 24) |
| `with` | left | PHP# only, in C#'s place |
| `!` | right | same |
| `*`, `/`, `%` | left | same |
| `+`, `-` | left | `+` also joins strings. PHP's `.` binds looser, below `<<` and `>>` |
| `<<`, `>>` | left | same |
| `&` | left | **differs:** PHP binds `&` looser than every comparison |
| `^` | left | **differs:** as `&` |
| `\|` | left | **differs:** as `&` |
| `<`, `<=`, `>`, `>=`, `is`, `as` | none | **differs:** `is` binds looser than PHP's `instanceof`, in C#'s place. `as` is PHP# only |
| `==`, `!=`, `===`, `<=>` | none | same |
| `&&` | left | same |
| `\|\|` | left | same |
| `??` | right | same |
| `? :` | none | same. Nesting without parentheses is a compile error (section 21) |
| `=`, `+=`, `-=`, `*=`, `/=`, `%=`, `**=`, `??=`, `&=`, `\|=`, `^=`, `<<=`, `>>=` | right | same, without `.=` |
| lambda `=>`, `throw` | right | the lambda is PHP#'s. `throw` is as in PHP 8 |

PHP's `and`, `xor` and `or` do not exist (section 21).

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

The header lists the backing type and the interfaces after `:`, as a class header does (section 22). A leading `int` or `string` is the backing type, and every name after it is an interface:

```csharp
public enum Status : string, HasLabel
{
    case Active = "a";
}
```

An enum case can also carry data. This is a closed set, and every case is declared in one block:

```csharp
public enum PaymentResult
{
    case Paid(string transactionId);
    case Declined(string reason);
    case RequiresAction(string redirect);
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
    PaymentResult.RequiresAction(string redirect) => `Continue at ${redirect}`,
};

match (result) {
    PaymentResult.Paid(string transactionId) => {
        deliver(order);
        log(transactionId);
    },
    default => {},
}
```

**Arms** are written `pattern => value,`, and a block arm is written `pattern => { … },`. Arms are tried from top to bottom. A `match` used as a statement takes block arms.

**Covering every case:**

- **On an enum,** arms that handle every case need no `default`. A missed case is a compile error that names it.
- **On a sealed interface,** the same holds for its implementers (section 20.1).
- **On any other value,** `match` must have a `default` arm.
- **An arm with `when`** does not count toward covering a case.

```csharp
string label = match (status) {
    Status.Active => "on",
    Status.Paused => "paused",         // compile error: This `match` misses `Status.Closed`.
};
string size = match (n) {
    < 10 => "small",                   // compile error: a match on an int needs a default arm
};
```

**Patterns:**

| Pattern | Example |
|---|---|
| value | `200 =>` |
| comparison | `< 1000 =>`, `>= 1000 and < 10000 =>` |
| case unpacked by position | `PaymentResult.Paid(string transactionId) =>` |
| whole case, named | `PaymentResult.Paid p =>` |
| properties | `{ status: 200, body: string body } =>` |
| list | `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>` |

- **A pattern creates a variable** only through a typed declaration, such as `string transactionId`. A bare name is a local's value when a local with that name is in scope, and a type otherwise.
- **`and`, `or` and `not`** combine patterns. They exist only inside patterns.
- **`when` adds a condition** to an arm. The condition is an ordinary expression.

```csharp
const limit = 10;
const label = match (value) {          // value is Any?
    limit => "at the limit",           // compares value with the local limit's value, 10
    Circle => "a circle",              // no local is named Circle, so this tests the type
    default => "other",
};
```

**A property pattern matches only the properties it lists,** as in C#. It can nest, and it can test the type at the same time:

```csharp
if (response is { status: 200 }) { … }                          // ignores headers, body and the rest
if (response is { status: 200, body: { type: "json" } }) { … }  // nests into a property that is itself an object
if (event is Paid { amount: > 1000 } big) { … }                 // tests the type and one property, and binds big
```

**`is` and `as`:**

```csharp
if (result is PaymentResult.Paid p) { … }
if (result is PaymentResult.Paid(string transactionId)) { … }
const paid = result as PaymentResult.Paid;                    // PaymentResult.Paid?, null if it is not one
const paid = result as PaymentResult.Paid ?? throw new NotPaid(result);
```

- **`is`** tests a value against any pattern, and creates the pattern's variables when it matches.
- **Narrowing:** after `is` without a name, a local variable or parameter counts as the tested type for the rest of the block. Assigning to it inside the block ends the narrowing. Properties are not narrowed, because they could change between the test and the use. To use a property's tested value, bind it to a name, as in `is int t`.

```csharp
if (entity is HasDesign) {
    render(entity.designKey());  // entity counts as HasDesign here
}
```

```csharp
if (order.total is int) { order.total + 1; }   // compile error: order.total may have changed since the test
if (order.total is int t) { t + 1; }           // compiles: t holds the value that was tested
```

- **A variable that `is` creates exists only where the test held:** inside the `if` for `is`, and after an `if` whose block always exits for `is not`, as in C#. A block always exits when every path through it ends in `return`, `throw`, `break` or `continue`.

```csharp
if (shape is Circle circle) {
    area = circle.radius;              // compiles: here shape is a Circle
}
circle.radius;                         // compile error: `circle` exists only where `shape is Circle circle` is true
```

```csharp
public Receipt checkout(Map<string, Any?> payload, string plan)
{
    if (payload["orderId"] is not int orderId) { throw new BadPayload("orderId"); }
    if (prices[plan] is not int price) { return Receipt.unknownPlan(plan); }
    return charge(orderId, price);                          // orderId and price are both known here
}
```

- **A negative test is written `is not`.** `is` binds with the comparisons, as in C# (section 19), so `!entity is HasDesign` reads as `(!entity) is HasDesign`. That is a compile error, because `!` takes a `bool`.

```csharp
if (entity is not HasDesign) { … }       // compiles
if (!entity is HasDesign) { … }          // compile error: write entity is not HasDesign
```

- **In a pattern, `not` beside `or` needs parentheses,** in `is` and in `match` arms alike. C# reads `not Paid or Refunded` as `(not Paid) or Refunded`, which already matches a `Refunded` result, so the `or Refunded` does nothing. `not` beside `and` reads the way it binds, so it needs none.

```csharp
if (result is not Paid or Refunded) { … }      // compile error: write not (Paid or Refunded), or (not Paid) or Refunded
if (result is not (Paid or Refunded)) { … }    // compiles: neither Paid nor Refunded
if (code is not null and not "") { … }         // compiles: (not null) and (not "")
```

- **`as`** converts a value to a type, or gives null.
- **`as` to a collection type checks every element,** wherever the value came from, and gives null if any element is wrong. So `as List<string> ?? throw …` throws on a wrong element.
- **`x is int?` is a compile error,** because `int?` also matches null. Write `x is int`, or `x == null`.
- **A pattern that can never match is a compile error.**

```csharp
if (x is int?) { … }                   // compile error: int? matches null too, so write x is int or x == null
Circle circle = this.next();
if (circle is Square) { … }            // compile error: This pattern never matches the value it tests.
```

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

**Methods of a PHP# class are closed unless the class opens them** with `virtual`, or by declaring them `abstract`. A subclass replaces a method only with a required `override`:

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

**Methods of a plain PHP class are open unless PHP marks them `final`.** PHP has no `virtual`, so PHP's own rule decides. Replacing one still needs `override`:

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

These are compile errors, whether the parent is PHP# or plain PHP:

- a missing `override`
- `override` when the parent has no such method
- overriding a PHP# method that is not `virtual` or `abstract`
- overriding a plain PHP method marked `final`
- an override that renames a parameter of the method it overrides. The error names both names.

```csharp
public class Image
{
    public virtual void resize(int width, int height) { … }
}

public class Thumbnail : Image
{
    public override void resize(int w, int h) { … }   // compile error: w renames width, h renames height
}
```

**Classes are open** unless marked `final`. `final override` stops an override chain at that class.

**`super`** calls the parent's version:

```csharp
public override string label() => super.label() + " (page)";
```

**An interface can give a method a default body.** PHP# declares no traits:

```csharp
public interface HasDesign
{
    List<string> claims { get; set; }                       // abstract: each class declares its storage
    string designColumn { get; }                            // abstract
    string designKey() => `design:${this.designColumn}`;    // compiles: a default body
    string designId() => `design:${this.id}`;               // compile error: a default body sees only HasDesign's members, and id is not one
}

public class Page : DatabaseEntity, HasDesign
{
    public List<string> claims { get; set; } = [];          // the storage lives in the class that owns it
    public string designColumn => "design";
}

page.designKey();                                           // "design:design", from the default body
```

- A default body sees only the interface's own members.
- Every field is declared in the class that owns it. An interface holds no fields.
- A PHP# interface with default bodies compiles to a PHP interface plus a PHP trait named `<Interface>\Defaults` that holds the default bodies, as Kotlin nests `DefaultImpls` in an interface. A plain PHP class that implements it must also `use` that trait to get the defaults. PHP# code never names the trait, so section 23's rule that a class cannot share its full name with a namespace does not apply to it.

```php
use App\Design\HasDesign;

class LegacyPage implements HasDesign
{
    use HasDesign\Defaults;                                 // gets designKey() from HasDesign's default body

    public array $claims = [];
    public string $designColumn = "design";
}
```

**A plain PHP trait** is listed in the header with the base class and interfaces, and it is also a type. It works everywhere an interface does:

```csharp
import Illuminate.Database.Eloquent.Model;
import Illuminate.Database.Eloquent.Factories.HasFactory;
import App.Legacy.Copies;

public class Order : Model, HasFactory { … }
public class Draft : Copies { … }               // compiles: a trait may stand alone in the header, as an interface may
if (entity is HasFactory f) { … }
public void seed(HasFactory owner) { … }
List<HasFactory> owners = [];
```

Reflection lists a class's traits, just as it lists the class's interfaces.

## 23. Namespaces and imports

Namespace parts are separated with `.`, and imports use `import`. Every part of a `namespace` line starts with a capital letter, as every type but the built-in ones does (section 24), so `namespace App.store;` is a compile error and `namespace App.Store;` compiles.

```csharp
namespace App.Tenant.Store;
import App.Shared.Schema.Entities.DatabaseEntity;
```

**Full names appear only in `namespace` and `import` lines.** Code uses the short imported name, so `.` in code is always member access. The last part of an import is always a class, or a plain PHP function that an `extern` declares (section 29). A full class name inside code is a compile error that names the import to add. A module header names a namespace, as the `namespace` line does, so `module : App.Shop` compiles (section 32).

**An import never carries `uses`,** because a library's effect lives in its one `extern` declaration (section 29).

**The standard library lives under one root, `Sharp`,** with the standard library namespaces `Sharp.Text`, `Sharp.Math`, `Sharp.Json`, `Sharp.IO`, `Sharp.Time`, `Sharp.Net` and `Sharp.Data`, as .NET has `System.*` and Rust has `std::*`.

**Only `Sharp` itself is imported by default,** together with the standard library's extensions on `string`, `int`, `float`, `List`, `Map` and `Set`, as Kotlin imports `kotlin.*`, `kotlin.text` and `kotlin.collections`. A bare `Int` is `Sharp.Int`, a bare `Key` is the standard attribute, and `name.trim()` needs no import. A class the file declares or imports under the same name shadows the default one.

**A static class in a standard library namespace needs one import line,** and the import's last part is the class, as for any import:

```csharp
import Sharp.Json.Json;

Json.encode(payload);     // compiles
Math.max(a, b);           // compile error: Math is not imported
```

**`import X.Y as Z;` renames an import in this file only.** It compiles to PHP's `use X\Y as Z;`. Here the rename keeps the standard `Key`, which `import Cache.Key;` would shadow:

```csharp
import Cache.Key as CacheKey;                                    // renamed in this file only

public struct Entry
{
    public Entry(
        [Key("cache_key")] public CacheKey key { get; },         // Key stays the standard attribute, CacheKey is the library class
    ) { }
}
```

- **An imported name is used once per file.** Two `import` lines with the same name are a compile error, and so is an import named like a class the file declares. Renaming one of them fixes it.
- **A rename changes the name, not what is imported.** Other files keep the original name, and an `extern` written with the new name declares the same function, so section 29's one-declaration rule still counts it once.

**A class cannot share its full name with a namespace.** `Store.sharp` beside a `Store/` folder is a compile error, because both would be `App.Tenant.Store`. Java's language specification has the same rule. `Store/Store.sharp` is allowed, because it is `App.Tenant.Store.Store`. The rule also covers plain PHP classes the checker sees.

## 24. Built-in types and `Any`

Built-in types are lowercase: `int`, `float`, `bool`, `string`, `void`, `null`. Every other type is capitalized: `List`, `Money`, `Any`.

**Names** follow TypeScript's casing:

- **PascalCase:** namespaces, types, type parameters, events and enum cases.
- **camelCase:** methods, properties, fields, parameters and locals.
- **UPPER_SNAKE_CASE:** constants.

```csharp
namespace App.Billing;                                         // namespace

public enum Status : string { case Paid = "paid"; }            // type and enum case

public class Invoice<TLine : Line>                             // type and type parameter
{
    const MAX_LINES = 100;                                     // constant
    List<TLine> pendingLines = [];                             // field
    public string dueDate { get; set; }                        // property
    public event Paid(int orderId, Money amount);              // event

    public Money totalIn(string currency)                      // method and parameter
    {
        const lineCount = this.pendingLines.count();           // local
        …
    }
}
```

Mago warns on a `.sharp` name that breaks the rule, through its existing naming lint rules and in their message shape, as in `method-name`'s "Method name `TotalIn` should be in camel case."

**A type holds null only when it is written with `?`,** for parameters, return types, properties and locals alike: `Customer?` may hold null, and `Customer` never does. Section 14.4 lists the compile errors for a `?` or a null check that cannot matter.

A `null` default needs the `?` too, for every type, on a field, a property or a parameter:

```csharp
private string name = null;              // compile error: write string? name = null
private string? nickname = null;         // compiles
public void tag(string label = null)     // compile error: write string? label = null
```

**A union type is written inline,** such as `int|string`, anywhere a type goes. It compiles to PHP's own union type.

```csharp
public User find(int|string id)
{
    if (id is int) { return User.byNumber(id); }
    return User.bySlug(id);   // id is a string here
}
```

- `is` narrows a union (section 21). After a branch that tested one type and returned, the code below it sees the types left.
- Alternatives that belong to the domain stay enums (section 20) and sealed interfaces (section 20.1).

**A union that holds null is written `(int|string)?`,** with the same `?` as any other type. `int|string|null` is a compile error that names `(int|string)?`.

```csharp
public (int|string)? find((int|string)? id)   // compiles: runs as PHP's int|string|null
public int|string|null find2()                 // compile error: write (int|string)?
```

**Integer overflow throws `ArithmeticError`** at the operation that overflows, as in Swift and C#'s `checked`. PHP's silent change to `float` does not happen in PHP# code. A shift is not an overflow: `<<` drops the bits it shifts out, and a shift by a negative count throws `ArithmeticError` (section 19).

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

**Converting values:**

```csharp
const qty = Int.parse(request.input("qty"));         // int, throws on "abc" and on "12abc"
const maybe = Int.tryParse(request.input("qty"));    // int?, null on "abc"
const cents = (int)(price * 100);                    // truncates toward zero
const admin = user as Admin;                         // Admin?, null if user is not one
const flag = request.input("flag") == "1";           // replaces (bool)
```

- `(int)` and `(float)` convert between `int` and `float`. `(string)` converts a number to a `string`.
- `(int)` truncates a `float` toward zero. A `float` too large for an `int`, or NaN, throws `ArithmeticError`.
- A string becomes a number only by parsing. `Int.parse(s)` and `Float.parse(s)` take `Any?`, and throw on anything that is not a string holding only a number, such as `"abc"`, `"12abc"` or `null`.
- `Int.tryParse(s)` and `Float.tryParse(s)` take `Any?` too, and give null where `parse` throws.
- A class or an interface narrows only with `as`, which gives null, or with `as … ?? throw` (section 21).
- PHP# has no user-defined conversion operators, such as C#'s `implicit operator`. A type converts only through a property or method it declares, such as `username.value`.
- `(bool)`, `(array)` and `(object)` do not exist. Conditions are `bool` (section 21), so a comparison such as `request.input("flag") == "1"` replaces `(bool)`. `(array)` and `(object)` are compile errors that name their replacements, shown below.
- PHP's cast aliases `(integer)`, `(double)`, `(boolean)` and `(binary)` do not exist.

**Replacing `(array)` and `(object)`:**

```csharp
OrderRow order = OrderRow.parse(row);             // replaces (array)row: a struct reads an object's public properties (section 10)
List<string> tags = List.wrap(value);             // replaces (array)value, where value is string|List<string>
List<List<int>> rows = List.wrap(numbers);        // compile error: T is List<int>, itself a list; write numbers is List<int> one ? [one] : numbers
Map<string, Any> payload = ["id": 1, "email": email];
string json = Json.encode(payload);               // {"id":1,"email":"…"}: replaces json_encode((object)[…])
const data = (array)row;                          // compile error: write OrderRow.parse(row) for an object, or List.wrap(row) for a value
const point = (object)["x": 1];                   // compile error: write a Map literal, or a struct
```

- **`List.wrap(T|List<T> value)`** gives a `List<T>`: the list itself, or a list holding the one value. The checker refuses `wrap` when `T` could itself be a list, and its error names the `is` form, `value is T one ? [one] : value`.
- **A JSON object** is a `Map` literal for a one-off payload, and a declared struct for a shape that repeats.
- **`Json.encode` encodes by the value's PHP# type, as far as the type is written,** so there a `Map` is a JSON object and a `List` is a JSON array, even when empty, and even when a `Map`'s keys run from 0 to n. PHP's own `json_encode` sees a plain array, so it gives `[]` for an empty `Map`.
- **Below an `Any`, a value encodes as PHP sees it,** because an array carries no mark that says `List` or `Map` (decision 26). Any list-shaped array there, such as an empty `Map` or a `Map<int, V>` with keys 0 to n, encodes as a JSON array. To keep a `Map` a JSON object, write its type or use a struct.

```csharp
Map<string, int> none = [:];
Json.encode(none);                                // {}
List<int> empty = [];
Json.encode(empty);                               // []
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                               // {"counts":[]}: counts sits below Any
Map<string, Map<string, int>> typed = ["counts": none];
Json.encode(typed);                               // {"counts":{}}: the type is written
```

**`Any` holds a value of any type except null. `Any?` also allows null.** A value of type `Any` must be checked with `is`, `as` or `match` before it can be used:

```csharp
Any payload = Json.decode(body);
payload.order;                              // compile error: check what payload is first
if (payload is WebhookPayload) {
    payload.order.total;                    // narrowed to WebhookPayload
}
```

**An unchecked `Any?` can be given a default or compared with a plain value:**

- `x ?? y` compiles and gives `Any`.
- `==` against a struct, enum, string, number or collection compiles, and compares by value (section 19).
- `==` against an object, or against another unchecked `Any?`, is a compile error.
- `${x}` in a template is a compile error until `x` is checked.

```csharp
Any? raw = Settings.raw("plan");
Any plan = raw ?? "free";          // compiles: ?? gives Any, a value that is never null
bool b = raw == "pro";             // compiles: compares with a string
bool c = raw == order;             // compile error: is raw the same object, or an equal value?
bool d = raw == other;             // compile error: other is an unchecked Any? too
string a = `plan: ${raw}`;         // compile error: check raw with is, as or match first
```

PHP's `mixed` is removed. Values coming from plain PHP that are typed `mixed` or untyped arrive as `Any?`. Decoding straight into a type, such as `Json.decode<WebhookPayload>(body)`, is the normal path. That decoding API is specified with the standard library.

**`Object` holds any object, and nothing else.** It compiles to PHP's `object`, and `Object?` adds null. Like `Any`, it has no members until `is` narrows it. An `Object` passes where `Any` is expected, and a plain PHP value typed `object` arrives as `Object`.

```csharp
public class Ids
{
    public static int identity(Object value) => spl_object_id(value);   // compiles
    public static int identityOf(Any value) => spl_object_id(value);    // compile error: Any may hold an int, and spl_object_id takes an object

    public static void sync(Object source)
    {
        source.id;                              // compile error: check what source is first
        if (source is Order order) { … }        // compiles
    }
}

Ids.identity(5);                                // compile error: 5 is not an object
```

## 25. Referring to classes

**`typeof(X)`** is typed `Class<X>`, and plain PHP receives the class-name string. It works on every type parameter, a method's included, because every type argument reaches the running program (section 11). PHP's `Order::class` is removed.

```csharp
Class<Order> type = typeof(Order);
type.attributes<Listen>();
typeof(TItem);
```

**`typeof(TItem)` works for any type argument, including `int`,** as C#'s `typeof(T)` does. It is a `Class<TItem>`, which can create objects, only when `TItem`'s bound is a class:

```csharp
public TItem make<TItem : Element>(string key)
{
    const type = typeof(TItem);
    return new (type)(key);                                      // compiles: TItem's bound is a class
}

public void log<TItem>(TItem value) { Log.info("type", ["class": typeof(TItem)]); }   // compiles, also when TItem is int

public TItem blank<TItem>()
{
    const type = typeof(TItem);
    return new (type)();                                         // compile error: TItem's bound is not a class
}
```

**`typeof(value)` gives an object's class.** For a value of type `T`, it is typed `Class<T>` and holds the object's runtime class, which may be a subclass of `T`. Plain PHP receives the class-name string. It replaces PHP's `$order::class` and `get_class($order)`.

```csharp
Class<Order> type = typeof(order);              // compiles: order's runtime class, which may be a subclass of Order
const fresh = new (type)(id);                   // compiles: a new object of that class
Log.info("saved", ["class": typeof(order)]);    // compiles; runs: plain PHP receives the class-name string
```

- **A bare name inside `typeof` is a local's value when a local with that name is in scope, and a type otherwise,** the same rule pattern matching uses (section 21).

**A class value works wherever a class name works.** `typeof` gives one, and a `Class<T>` field, parameter or local holds one:

```csharp
import Illuminate.Database.Eloquent.Model;

public abstract class Element
{
    public required Element(string key) { … }
    public static string defaultTag() => "div";
}

public class FormBuilder
{
    Map<string, Class<Element>> elements = ["form": typeof(FormElement), "input": typeof(InputElement)];

    public Element make(string kind, string key)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);   // Class<Element>, with no type written
        return new (type)(key);                                                // compiles: Element's constructor is required
    }

    public string tagFor(string kind)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);
        return type.defaultTag();                                              // calls defaultTag on the class type holds
    }

    public Model? load(Class<Model> type, int id) => type.find(id);           // calls find on the class type holds
}
```

- **`new (type)(…)`** creates an object from a class value. The parentheses around `type` mark it as an expression, not a class name. It needs a `required` constructor, as `new Self(…)` does. For a plain PHP class, such as `Class<Model>`, the checker checks the call against that class's own constructor.
- **A static call through a class value,** such as `type.defaultTag()` or `type.find(id)`, calls the static on the class the value holds.
- **`Class`'s own members win,** such as `attributes`. A class cannot declare a static named like one of them, as in TypeScript's error 2699.
- **A class value's type need not be written.** `const type = …` holds a `Class<Element>`, because the checker's types reach the running program (section 27).
- **A property named by a variable stays refused.** PHP's `$order->$column` has no PHP# form, and `order.getAttribute(column)` replaces it.

```csharp
type.attributes<Listen>();                       // Class's own method
public static string attributes() => "";         // compile error: attributes is a member of Class
new (kind)(key);                                 // compile error: kind is a string, not a Class
order.getAttribute(column);                      // compiles: Eloquent's API replaces $order->$column
```

**A class value of a generic class can carry its type arguments or leave them out,** as in C#. `typeof(PaginatedList<Order>)` is a `Class<PaginatedList<Order>>`, and `new (pages)()` creates a `PaginatedList<Order>`. A `Class<PaginatedList>` leaves them out, so `new` names them: `new (any)<Order>()`. `new (any)()` on an open generic class is a compile error.

```csharp
public class Pages
{
    public PaginatedList<Order> fresh()
    {
        Class<PaginatedList<Order>> pages = typeof(PaginatedList<Order>);
        return new (pages)();                                    // compiles: a PaginatedList<Order>
    }

    public PaginatedList<Order> ofOrders(Class<PaginatedList> any) => new (any)<Order>();   // compiles: a PaginatedList<Order>
    public Any open(Class<PaginatedList> any) => new (any)();                              // compile error: PaginatedList is generic, so new names its type arguments
}
```

**`Self`** means the class a static method was actually called on. It replaces PHP's `static`. PHP's `self` is removed: to mean the declaring class, write its name.

```csharp
public abstract class DatabaseEntity
{
    public required DatabaseEntity(Row row) { … }
    public static Self fromSchema(Any value, VerifiedUser caller) { … return new Self(row); }
}

const order = Order.fromSchema(value, caller);   // typed as Order
```

`Self` replaces the C# workaround of passing a class to itself, as in `class Order : Entity<Order>`.

`Self` is written only as a return type, and in bodies as `new Self(…)` and `Self.m()`. A `Self` parameter is a compile error, because a subclass would accept any parent:

```csharp
public bool same(Self other) { … }   // compile error: Self is a return type only
```

**`new Self(…)` compiles only when the class's constructor is marked `required`.** `Self` can be any subclass, so every class below it keeps a constructor that `new Self(…)` can call:

- its leading parameters match the parameters of the `required` constructor
- every parameter it adds has a default

```csharp
public class Order : DatabaseEntity
{
    public Order(Row row, Clock? clock = null) : super(row) { … }   // compiles: new Self(row) can call it
}

public class Invoice : DatabaseEntity
{
    public Invoice(Row row, Clock clock) : super(row) { … }         // compile error: new Self(row) cannot supply clock
}
```

`required` is optional. A class that never writes `new Self(…)` never writes it.

## 26. Extension methods

An extension adds methods and properties to an existing type from outside it. Extensions are declared in `extension(...)` blocks inside a static class, as in C# 14:

```csharp
public static class TextExtensions
{
    extension(string value)
    {
        public string slug() => Str.slug(value);
        public bool isBlank => value.trim() == "";
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
- **The receiver's type picks among imported extensions with one name,** as in C# and Kotlin:

```csharp
import App.Billing.LineTotals;      // extension(List<Line> lines) { public Money total() => …; }
import App.Billing.PriceTotals;     // extension(Map<string, Money> prices) { public Money total() => …; }

lines.total();                      // compiles: lines is a List<Line>, so LineTotals runs
prices.total();                     // compiles: prices is a Map<string, Money>, so PriceTotals runs
```

## 27. PHP# files

A file's extension marks it as PHP#. The engine, Composer's autoloader, editors and the checker all choose the grammar from the filename.

A PHP# file has no opening tag. Its first line is code:

```csharp
namespace App.Tenant.Store;

import App.Shared.Schema.Entities.DatabaseEntity;
```

The extension is **`.sharp`**, as in `StoreService.sharp`.

Plain PHP files keep `.php` and `<?php`, and the two call each other freely. Composer's autoloader tries `.sharp` after `.php`, the same way it already tries `.hh` for Hack.

**`Position` says where code sits in its source.** PHP's magic constants `__DIR__`, `__FILE__`, `__LINE__`, `__FUNCTION__`, `__METHOD__`, `__NAMESPACE__` and `__CLASS__` are removed, along with every other `__Something__` form. `Position` is a standard-library type, imported by default (section 23). It has `file`, `directory`, `line`, `column` and `function`. `function` is the fully qualified dotted name, as in `App.Reports.logSlow`, because a short name is ambiguous across namespaces.

```csharp
namespace App;

import Illuminate.Support.Facades.Log;
import App.Store.Order;

public class Reports
{
    public string stubsFolder()
    {
        const stubs = Position.current().directory + "/stubs";   // the folder that holds this .sharp file
        return stubs;
    }

    public static void logSlow(string message, Position caller = Position.current())
    {
        Log.warning(message, ["file": caller.file, "line": caller.line, "function": caller.function]);
    }

    public void run()
    {
        Reports.logSlow("slow query");                           // logs this call's own file and line, and "App.Reports.run"
    }
}

const here = __FILE__;                                           // compile error: write Position.current().file
Log.info("charged", ["class": typeof(Order)]);                   // compiles: replaces __CLASS__, and plain PHP receives "App\Store\Order"
```

- `Position.current()` in a body gives the position where it is written.
- As a parameter's default, `Position.current()` gives the caller's position, as C++20's `std::source_location::current()` and Swift's `#file` defaults do.
- A plain PHP caller gets the position where the parameter is declared, because PHP# does not compile plain PHP's calls.

**A `.sharp` file runs only after the checker accepts it.** A type error stops it from running, as in C# and Java. A pragma, `mago.toml`'s `ignore` or the baseline can hide a warning, never an error. The checker's types reach the running program, inferred ones too, so generic code (section 11), loops over enum-keyed maps (section 12) and class values (section 25) work without a written type.

**`vendor/bin/mago compile` compiles the project.** It checks every `.sharp` file, vendor packages included, and writes each accepted file as a `.sharpc` file into one `.sharp/` folder at the project root. The folder mirrors the source paths:

```text
project/
├── app/Orders/Order.sharp
├── app/Orders/LegacyExport.php             <- plain PHP: never compiled, runs as today
├── vendor/acme/money/src/Money.sharp       <- vendor is never written to
└── .sharp/                                 <- generated by mago compile; add it to .gitignore
    ├── app/Orders/Order.sharpc
    └── vendor/acme/money/src/Money.sharpc
```

- **`mago compile` writes `.sharp/`, and it also creates a missing `.lean` file beside a class that has a law** (section 28.1). It never rewrites an existing one.
- **The engine finds `.sharp/`** by walking up from the source file, as git finds `.git`. Nothing needs configuring.
- **The engine runs a `.sharp` file only from its current `.sharpc` file.** A missing, stale or mismatched one is refused, and the refusal names the fix:

```text
app/Orders/Order.sharp isn't compiled. Run vendor/bin/mago compile.
app/Orders/Order.sharp is out of date (app/Shared/Money.sharp changed). Run vendor/bin/mago compile.
app/Orders/Order.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.

app/Orders/Order.sharp has 1 error:
line 12: Invalid return type for method `Order.total`: expected `int`, but found `string`.

app/Orders/Order.sharp has 3 errors:
line 12: Invalid return type for method `Order.total`: expected `int`, but found `string`.
line 19: …
line 31: …
```

- **In development,** the `php.ini` setting `sharp.compile_command` lets the engine compile a stale file on demand before it runs, instead of refusing it. When the checker refuses the file it just compiled, the refusal lists every error in the file in line order, each on its own line with its line number.
- **A deploy** runs `vendor/bin/mago compile` and ships `.sharp/` with the code.
- **A type error, or a broken structure rule (section 28), stops the file from running.** So does a law without a proof, a gap, or a proof whose law was deleted, each a compile error that names it.
- **A rule that reads the whole project's structure can lag in development** until the next full compile. At deploy, `mago compile` checks every rule exactly.

## 28. Verification

The checker verifies two kinds of facts before code runs:

- **Structure:** what code may depend on and do. Visibility and effects belong to the language. Code-shape and style rules stay plugins in the checker.
- **Values:** facts the code guarantees, written as laws with proofs, as in Bend, Lean and Agda.

**A class states its laws in PHP#,** with `law`. A law's parameters range over every possible value. Its proofs go in a Lean file of the same name beside it, `Money.lean` beside `Money.sharp`, written in Lean 4 and checked by Lean. Bend 2 splits claims from proofs the same way, into `LAWS.bend` and `PROOF.bend`.

```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
}
```

**A module states laws that span its classes** in `Module.sharp` (section 32). Their proofs, and the namespace's structure rules, go in `Module.lean` in the same folder. Both files are optional.

```csharp
// app/Tenant/Store/Module.sharp
namespace App.Tenant.Store;

module
{
    law receiptMatchesRefunds(int paid, int refunded) => Receipts.refundable(paid, refunded) == Refunds.remaining(paid, refunded);
}
```

**Rules and laws gate running.** A broken structure rule stops the code that breaks it from running, as a type error does. A law without a proof, a gap, or a proof whose law was deleted is a compile error that names it (section 27).

**Laws hold only over pure code** (section 29). The checker translates pure PHP# code to Lean, and Lean's kernel checks the proofs. Bend's `--verdict` mode and Aeneas, which translates Rust to Lean, work the same way.

**A state machine gets its laws through a pure transition method on its status enum,** so a law covers every state and event:

```csharp
public enum Status : string
{
    case Open = "open";
    case Paid = "paid";
    case Refunded = "refunded";

    public Status after(Payment e) => match (this) { … };                          // pure: the next status for every status and event
    law refundedIsFinal(Payment e) => Status.Refunded.after(e) == Status.Refunded;
}
```

**Structure rules are checks that Lean runs.** The checker loads the code's structure, meaning its namespaces, imports and references, as data, and Lean runs each rule over it like a function. A broken rule reports every offending line. Laws, which cover every possible value, stay theorems proved by Lean's kernel. A structure rule only scans the facts that exist, where a kernel proof gives the same answer far more slowly. Structure rules replace architecture linters such as a Mago module-boundary rule, the way CodeQL queries and Mathlib's `#lint` checks do.

### 28.1 Lean files

A class's proofs sit in a Lean file of the same name beside it. A namespace's module laws, their proofs and its structure rules sit in `Module.sharp` and `Module.lean` in its folder.

```text
app/
├── Module.lean                  <- structure rules about the whole application
├── Shared/
│   ├── Module.lean              <- App.Shared's structure rules, such as "Shared never reaches Tenant"
│   ├── Money.sharp              <- states the law addKeepsCurrency
│   └── Money.lean               <- proves addKeepsCurrency
└── Tenant/Store/
    ├── Module.sharp             <- the module (section 32), and the law receiptMatchesRefunds
    ├── Module.lean              <- proves receiptMatchesRefunds, and holds App.Tenant.Store's structure rules
    ├── Receipts.sharp
    ├── Refunds.sharp
    └── StoreService.sharp
```

The checker created `Money.lean` and appended the proof `simp` found:

```lean
-- app/Shared/Money.lean
import Code.App.Shared.Money
open Sharp

theorem addKeepsCurrency : App.Shared.Money.addKeepsCurrency := by
  simp [App.Shared.Money.addKeepsCurrency, App.Shared.Money.add]
```

`app/Tenant/Store/Module.lean` proves the module law. It imports `Code.App.Tenant.Store.Module` because `Module.sharp` states a law. The same file also holds the namespace's structure rule: "`App.Tenant.Store` imports only `App.Tenant.Community` and `App.Shared.Schema`."

```lean
-- app/Tenant/Store/Module.lean
import Code.App.Tenant.Store.Module
open Sharp

theorem receiptMatchesRefunds : App.Tenant.Store.Module.receiptMatchesRefunds := by
  simp [App.Tenant.Store.Module.receiptMatchesRefunds, App.Tenant.Store.Receipts.refundable, App.Tenant.Store.Refunds.remaining]
```

- **The checker generates the Lean translation of pure code and the Lean statement of each law into `.sharp/`,** never into the source tree.
- **A law's generated Lean statement is named by its class's full name and the law's name,** as in `App.Shared.Money.addKeepsCurrency`. A module law's is named by its namespace, `Module` and the law's name, as in `App.Tenant.Store.Module.receiptMatchesRefunds`.
- **When a class or module has a law and no Lean file, the checker creates the file.** For each new law, it appends a proof found by Lean's automatic steps (`simp`, `omega`, `decide`), or a marked gap when none is found. It never rewrites a proof that exists.
- **A law without a proof, a gap, or a proof whose law was deleted is a compile error that names it.**
- **A rule lives with the namespace it constrains.** "Shared never reaches Tenant" goes in `app/Shared/Module.lean`. A rule about the whole application goes in `app/Module.lean`.
- **Names match exactly.** Translated code keeps its PHP# names in Lean, with no prefix.
- **A failed rule is reported on the code that breaks it,** such as the offending `import` line, not only as a failed theorem.
- **The editor shows,** above each method, the laws that mention it.

## 29. Effects

An effect is anything a method does beyond computing its result: database, network, files, clock, randomness, mail. PHP# tracks effects through the objects a class holds, a model called object capabilities, which Scala 3, Effekt and Pony also use. It also records every call into plain PHP, whose effect an `extern` declaration states, and every `emit`, which raises an event and has the effect `Events` (section 15).

**Any PHP# code may call plain PHP,** including Laravel's facades, helpers and model methods, and PHP's built-in functions the standard library does not wrap yet (section 8). Most libraries are plain PHP, so this is how PHP# code uses them.

```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

**The effect of plain PHP is declared once, with `extern`.** A `.sharp` declaration file names a plain PHP class, one of its methods or a function, and the effect after `uses`. A declaration holds no code.

```csharp
// app/Stubs/Stripe.sharp
namespace App.Stubs;

import Stripe.StripeClient;

extern StripeClient uses Http;
```

- **A declaration file is an ordinary `.sharp` file.** Its `namespace` line matches its path, as section 5 requires.
- **`extern` covers a whole class, one method or a function,** each after importing it: `extern StripeClient uses Http;`, or `extern Carbon.now uses Clock;` and `extern now uses Clock;` as PHP#'s package ships them.
- **An `extern` that names no effect declares the class, method or function pure,** as in `extern BigDecimal;`.
- **Each class, method or function has at most one `extern` declaration in the whole project.** A second one is a compile error, as declaring a class twice is.
- **A call to plain PHP with an `extern` declaration has that effect,** so it fits a `uses` that names it: `StripeClient.charges().create(…)` fits `uses Http`.
- **An object a declared plain PHP call returns carries that call's effect,** so `StripeClient.charges()` returns an object with `Http`, and calling it fits `uses Http`. An `extern` on the returned class itself wins over the inherited effect. Kotlin treats Java's return values the same way.
- **A call to plain PHP with no declaration has an unknown effect.** Code with a body may make it, and is then never pure and never takes part in laws (section 28). No `uses` accepts it, and the error names the missing declaration.
- **PHP#'s Composer package ships the declarations for PHP's built-in functions and for Laravel.** Among PHP's built-ins, PDO is `Database`, curl is `Http`, `printf` and `fwrite` to `STDOUT` or `STDERR` are `Console`, `file_put_contents` and every other file function are `Files`, `exit` is `Process`, `time()` is `Clock`, `random_int` is `Random`, and `getenv()` and PHP's other environment built-ins are `Environment`. Every other built-in function is declared with its own effect, or as pure. In Laravel, Eloquent and `DB` are `Database`, the `Http` facade is `Http`, `Cache` is `Cache`, `Mail` is `Mail`, and `now()` and Carbon's clock reads are `Clock`.
- **A project declares its own libraries,** conventionally in `app/Stubs`.

**Every built-in function has a declaration, so its effect is always known.** `Console` covers standard output and standard error, which includes `printf` and `fwrite(STDOUT, …)` or `fwrite(STDERR, …)`. `Files` covers every other file. `Process` covers `exit`.

```csharp
public interface Formatter
{
    string format(string name);                                     // implementations must be pure
}

public class Visitor
{
    public string greet(string name) => "Hello, " + name.trim();    // pure: trim is a standard-library method
    public void remember(string token) { file_put_contents("seen.txt", token); }   // has Files
}

public class FileFormatter : Formatter
{
    public string format(string name)
    {
        file_put_contents("seen.txt", name);                        // compile error: format must be pure, and file_put_contents has the effect Files
        return name;
    }
}
```

**`extern` means "implemented outside PHP#",** as C#'s `extern` does. An `extern` declaration names plain PHP, as above. An `extern` method in the standard library has a native body:

```csharp
// in the standard library's Sharp.Json
public static class Json
{
    public static extern Map<string, Any?> decode(string json);   // compiles: the engine holds the body
}
```

- **A native body is compiled into the PHP# engine,** as PHP's own built-in functions are. It is written in Rust, behind a C interface.
- **Only the standard library declares native bodies.** In a project, `public static extern string slug(string title);` is a compile error.
- **One generator writes the C header from the `.sharp` declarations,** so the declaration and the Rust code have one signature.
- **The engine refuses to start if a declared native body is missing.**

**A `foreign` class turns an effect into an object** that code holds and passes on, such as a fake in tests. It is optional. Its methods call plain PHP, like any other code. Code that holds a `foreign` object has that effect by name, which `uses` can declare.

```csharp
import Illuminate.Support.Facades.Redis;

public foreign class RedisStore
{
    public string? get(string key) => Redis.get(key);
}
```

The standard library ships `Database`, `Http`, `Files`, `Console`, `Process`, `Clock`, `Random`, `Cache`, `Mail` and `Environment`. A project declares its own `foreign` classes the same way.

**`Events` is the effect of `emit`** (section 15). It is the one standard effect that is not a `foreign` class: a method has it when its body emits, and no object carries it.

**PHP# has no superglobals.** `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_REQUEST`, `$_SESSION`, `$_ENV` and `$GLOBALS` are compile errors that read "PHP# has no superglobals; take a Request". Request data arrives as an object, such as a framework's `Request`. The process environment arrives as `Environment`, a standard `foreign` class like `Clock` and `Random`:

- `string? variable(string name)` reads an environment variable, or gives null when it is not set.
- `List<string> arguments { get; }` holds the command-line arguments, starting with the script's name.
- `string currentDirectory { get; }` is the directory the process runs in.
- `static string require(string name)` reads an environment variable, and stops the app at startup when it is not set. A module calls it as `Environment.require("NAME")` (section 32).

```csharp
import Illuminate.Http.Request;

public class Deploy
{
    public Deploy(private Environment environment) { }

    public string region() => this.environment.variable("AWS_REGION") ?? "us-east-1";
    public string target() => this.environment.arguments[1];   // throws OutOfRangeException when no argument was passed
}

public class CheckoutController
{
    public string host(Request request) => request.getHost();   // compiles: replaces $_SERVER["HTTP_HOST"]
    public string origin() => _SERVER["HTTP_HOST"];             // compile error: PHP# has no superglobals; take a Request
}
```

`Deploy` holds an `Environment`, so it has that effect (see the rule on a class's effects below).

**A `foreign` object reaches other code only through constructors:**

```csharp
public class TenantCache
{
    public TenantCache(private RedisStore store) { }
}
```

- **A class's effects** are the `foreign` classes it holds, directly or through its fields, the effects of the plain PHP its methods call, and the events its methods emit. The checker works them out from field and constructor types and from method bodies. Nothing is written down.
- **A `foreign` object** is created in a module (section 32), as often as its binding's lifetime decides, and handed down through constructors. Creating one anywhere else, or storing one in a static, is a compile error.

**Pure code** reaches no `foreign` object, emits no event, calls no plain PHP unless an `extern` declares it pure, and changes nothing it was given (section 13). Getters must be pure. Laws (section 28) reason only about pure code, and Lean cannot see inside `foreign` classes or plain PHP.

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

An implementation that calls plain PHP fits the declaration only through an `extern`:

```csharp
public interface AudienceSync
{
    void sync(Contact contact) uses Http;
}

public class StripeGateway : PaymentGateway
{
    public Money quote(Cart cart) { … }
    public Charge charge(Cart cart) => StripeClient.charges().create(…);   // fits: StripeClient is declared Http
}

public class MailchimpSync : AudienceSync
{
    public void sync(Contact contact) { Mailchimp.lists().addListMember(…); }   // compile error
}
```

```text
MailchimpSync.sync calls Mailchimp, which has no extern declaration, so `uses Http` cannot accept it.
Declare its effect in a .sharp file, such as `extern Mailchimp uses Http;`.
```

**`uses Events` lets an implementation emit:**

```csharp
public interface Checkout
{
    void complete(Order order) uses Events;                                            // implementations may emit, nothing else
}

public class StoreCheckout : Checkout
{
    public void complete(Order order) { emit new Order.Paid(order.id, order.total); }   // fits: emit has the effect Events
}
```

**A method that takes a function can have that function's effects.** Its declaration writes `uses f`, where `f` is one of its function-typed parameters, with or without a body. At each call, the method has its body's own effects plus the effects of the function passed as `f`. Code that calls it writes nothing.

```csharp
// in the standard library's List<T>
public List<TResult> map<TResult>(Function<TResult(T)> f) uses f;

carts.map(c => c.total);                             // pure
carts.map(c => this.gateway.charge(c));              // has Http

// a method with a body: Http from this.gateway, plus the effects of prepare
public Charge charge(Cart cart, Function<Cart(Cart)> prepare) uses prepare => this.gateway.charge(prepare(cart));
```

The standard library's collection methods (section 12) are declared this way, so list code with pure functions stays pure, and laws (section 28) can reason about it.

**Code with a body writes no `uses` except `uses f`.** Its effects enter through the constructor, through the plain PHP it calls, through the events it emits and through each function its `uses f` names, and its body and declaration show all four. The checker works out each method's effects, and the editor displays them.

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

Methods declare no thrown exceptions, and the checker never reports an undeclared one.

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

## 32. Modules

A module says how the app builds its objects. Each namespace folder may hold `Module.sharp`, a `module { … }` declaration with no name, because the folder names it. `Module` is a reserved file name. NestJS modules have the same per-folder structure and overrides, and Dagger has the same compile-time check.

**A module declares services** with `singleton`, `scoped` and `transient`:

```csharp
// app/Shop/Module.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Mail.QueueMailer;
import App.Pricing.PriceRule;
import App.Pricing.TaxRule;
import App.Pricing.DiscountRule;
import App.Cache.RedisStore;

module
{
    singleton Mailer = SmtpMailer;                                             // every class that takes a Mailer gets the SmtpMailer
    singleton Mailer for SendReceipt = QueueMailer;                            // SendReceipt gets a QueueMailer instead
    singleton List<PriceRule> = [TaxRule, DiscountRule];                       // a class that takes a List<PriceRule> gets both
    singleton RedisStore = new RedisStore(Environment.require("REDIS_URL"));   // a foreign object, created here as its singleton lifetime decides
}
```

- **A service is built only by a constructor call** whose arguments are constants, configuration values or other services. A module holds no statements, branches, loops or methods. A choice made while the app runs goes in a class that implements `Factory<T>`, which the module binds.
- **`Environment.require("NAME")` is a static method** (section 29). It returns a `string`, and stops the app at startup when the value is missing.
- **A module is where `foreign` objects are created** (section 29). The binding's lifetime decides how often one is created, and it reaches classes only through constructors.
- **A module also states the laws that span its classes** (section 28).

**A `scoped` lifetime is one HTTP request, one queued job or one console command run.** The host starts and ends each scope, and the `php-sharp/laravel` adapter does that for Laravel, as ASP.NET Core does for each request. Under PHP-FPM, `singleton` and `scoped` behave the same. They differ in long-running workers.

**`Factory<T>` is `public interface Factory<T> { T create(); }`.** Binding a class that implements `Factory<Mailer>` to `Mailer` makes the container call `create()` each time the binding's lifetime needs a new `Mailer`, as Spring's `FactoryBean<T>` does:

```csharp
// app/Shop/MailerFactory.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Mail.QueueMailer;
import App.Flags.FeatureFlags;

public class MailerFactory : Factory<Mailer>
{
    public MailerFactory(private FeatureFlags flags, private SmtpMailer smtp, private QueueMailer queue) { }
    public Mailer create() => this.flags.enabled("queue-mail") ? this.queue : this.smtp;   // the choice is made while the app runs
}
```

```csharp
module
{
    scoped Mailer = MailerFactory;                                             // create() runs once per request, job or command
}
```

```csharp
module
{
    singleton Mailer = Environment.require("MAILER") == "queue" ? new QueueMailer() : new SmtpMailer();   // compile error: a service is built only by a constructor call; a choice made while the app runs goes in a Factory<Mailer>
    singleton Mailer = TaxRule;                                                                           // compile error: TaxRule is not a Mailer
}
```

**A nested folder's module overrides its parent's services.** A test or environment module overrides with `module : App.Shop { … }`, and the compiler checks every environment's set of services.

- **An environment module is `Module.<Environment>.sharp`** beside the `Module.sharp` it overrides, such as `Module.Production.sharp`. `APP_ENV` picks it, as it picks ASP.NET Core's `appsettings.Production.json`.
- **A test module is declared in the test file that uses it,** with the same header, as NestJS's testing module is.

```csharp
// app/Shop/Module.Production.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.QueueMailer;

module : App.Shop
{
    singleton Mailer = QueueMailer;                                            // in production, replaces App.Shop's SmtpMailer
}
```

```csharp
// tests/Shop/CheckoutTest.sharp
namespace App.Shop;

import App.Mail.Mailer;
import Tests.Fakes.FakeMailer;

module : App.Shop
{
    singleton Mailer = FakeMailer;                                             // the tests in this file get a FakeMailer
}

public class CheckoutTest { … }
```

**A missing or mistyped service is a compile error.** The container builds every class through its main constructor (section 9.1), including `on` listeners (section 15).

```csharp
public class Checkout
{
    public Checkout(private Mailer mailer, private PaymentGateway gateway) { }   // compile error when no module binds PaymentGateway
}
```

**The container implements PSR-11.** The optional package `php-sharp/laravel` makes Laravel's container ask PHP#'s container for every PHP# class. The language never depends on Laravel.

## Undecided, in order

None. The standard library is specified with the library itself, by the team lead.

# PHP# language specification

PHP# is a language that runs on PHP's engine and calls plain PHP freely, as Kotlin runs on the JVM and calls Java. This file is the single source of truth for PHP# syntax and rules.

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

<!-- example attribute-arguments: members -->
```csharp
[Field(label: "Name", render: Render.Heading, searchable: true)]
public string name { get; set; } = "";
```

Named arguments use `label: "Name"`, the same "name: value" form used everywhere in the language (section 16). C#'s `Label = "Name"` is not allowed.

## 2. Variables

Variables have no `$`. A local is declared with `let`, which can be reassigned, or `const`, which cannot. The meanings match TypeScript.

<!-- example inferred-locals: statements
private Account account = new Account();
-->
```ts
const currency = this.account.currency;
let total = 0;
```

**A local can also be declared with its type written,** as in C#, when the right side cannot say it, such as `null`. A typed local can be reassigned like `let`, and `const` can carry a type too.

<!-- example typed-locals: statements alone
private Plan plan() => new Plan();
-->
```csharp
let total = 0;              // type inferred, can be reassigned
Money? total = null;        // type written, can be reassigned
const plan = this.plan();   // type inferred, cannot be reassigned
const Plan plan = …;        // type written, cannot be reassigned
```

**A PHP# keyword, such as `let`, `match` or `emit`, can't name a local. Every other word can,** such as `empty`, `list` and `print`:

<!-- example keyword-names: statements -->
```csharp
const list = [1, 2, 3];     // compiles: PHP# has no list keyword
let empty = true;           // compiles
const print = "draft";      // compiles
const match = 1;            // compile error: match is a PHP# keyword
```

**Any word, PHP# keywords included, names a method or a property with no mark,** as in JavaScript and TypeScript, because a member's name is written only after `.` or where it is declared. Locals, parameters, `catch` variables and pattern variables can't take a keyword's name:

<!-- example keyword-members -->
```csharp
// app/Rule.sharp
namespace App;

public class Rule
{
    public bool match(string text) => text != "";   // compiles: a keyword names a method
    public string event { get; } = "paid";          // compiles: and a property
}
```

<!-- example keyword-members: statements
Rule rule = new Rule();
-->
```csharp
rule.match("paid");                                 // true
rule.event;                                         // "paid"
const event = "paid";                               // compile error: a local can't take a keyword's name
```

**PHP# keywords are lowercase only,** as in C#, Java, Kotlin, Swift, TypeScript, Go and Rust. `Self` (section 25) is the one capitalized keyword, as it is in Rust and Swift. A capitalized keyword is an ordinary name, so `class Event` and `class Struct` are valid. Where no such name exists, as for `TRUE` or `Return`, the error suggests the lowercase keyword:

<!-- example keyword-case -->
```csharp
// app/Struct.sharp
namespace App;

public class Struct { … }       // compiles: struct is a keyword, and Struct is a name
```

<!-- example keyword-case: statements -->
```csharp
const paid = true;              // compiles
const shipped = TRUE;           // compile error: no name TRUE exists; did you mean true?
Return;                         // compile error: no name Return exists; did you mean the keyword return?
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

<!-- example dot-access: statements
User user = new User();
string name = "Spring Sale";
-->
```csharp
user.name
Str.slug(name)
```

A class cannot declare a static member and an instance member with the same name, so `Link.name` always has exactly one meaning. C# has the same rule.

**A member of the same object is always written with `this.`**, as in TypeScript, Python and Swift. That holds for fields, properties and methods alike. A bare name is always a local, a parameter, a class or a constant, so any line read on its own shows whether it touches the object. Static members are written with the class name.

<!-- example this-members: statements in Checkout
public static int maximum { get; set; } = 10;
private int count = 0;
private Planner planner = new Planner();
string id = "order-1";
-->
```csharp
this.count += 1;
const plan = this.planner.plan(id);
Checkout.maximum;
```

**`Class.y` without a call always reads a static value:** a static property, a constant, an enum case, or a static method as a function value (section 14.3). A bare `Order.total` is never a property reference, which is written `nameof(Order.total)` (section 6.3).

## 5. Access modifiers

| Modifier | Who can reach it |
|---|---|
| `private` | this class. This is the default for members outside an interface. |
| `protected` | this class and its subclasses, in any namespace |
| `internal` | this namespace and every namespace below it. This is the default for classes. |
| `public` | everyone |

- **A namespace's inside is its folder.** A file's namespace must match its path, so no file can declare its way into a namespace. Each folder below the project's namespace root names a part of the namespace, so a folder whose name a namespace can't take, such as `it's`, is a compile error that asks to rename it.
- **Tests:** test folders may declare the namespace they test, which gives them `internal` access.
- **A member is never more visible than its class.**
- **Visibility is checked both ways.**
  - Too narrow is a compile error at the caller.
  - Too wide is a warning, for example a `public` member that nothing outside its namespace uses. It runs in a project, a package whose `composer.json` says `"type": "project"`, because a library's members are used by the projects that install it, which the checker never sees. A constructor never warns: `new` calls it, so it reaches as far as its class.
- **There is no named reach,** such as `internal(App\Tenant)`. A helper shared by sibling namespaces moves to their parent namespace.

**An interface member is always public, so it takes no access modifier.** Writing one, `public` included, is a compile error, as in TypeScript, Swift and Go:

<!-- example interface-members -->
```csharp
// app/Notifier.sharp
namespace App;

public interface Notifier
{
    string channel { get; }                 // compiles: public, as every interface member is
    void send(string message);              // compiles
    public void flush();                    // compile error: an interface member is always public, so write it with no access modifier
}
```

- **An interface member is as visible as its interface,** so an `internal` interface's members reach only what the interface reaches, as C#'s effective accessibility does.
- **Interface members are exempt from the too-wide warning,** because they have no narrower form.

## 6. Fields and properties

Fields and properties are separate concepts.

- **A field is storage.** It can only be `private` or `protected`. A field with no modifier is `private`. There are no public fields.
- **A property is the API.** It has `get` and `set` accessors. `get` reads at the property's access level, and `set` may carry a narrower one of its own.

<!-- example field-and-property: members -->
```csharp
Map<string, Plan> cache = [:];                                  // field
public int views { get; private set; } = 0;                     // auto-property
public string name { get; internal set => field = value.trim(); } = "";
public string slug => Str.slug(this.name);                      // computed
```

**A field or an auto-property can have an initial value,** written after its declaration as in C#:

<!-- example initial-values: members -->
```csharp
Map<string, Plan> cache = [:];                                  // field
public int views { get; private set; } = 0;                     // auto-property
public List<Tag> tags { get; set; } = [];
```

Initial values run before the parent's constructor, in the order the members are declared, and can't read `this`, as in C#.

**No code that can read the object runs before the object is built.** An initial value can't read `this`, it fills the storage without running a `set` body, constant or not, and observers wait until the constructor returns (section 6.2). Only a behavior's `set` processes an initial value, because a behavior never sees the object (section 6.4).

<!-- example initial-value-storage: members -->
```csharp
public string code { get; set => field = value.trim(); } = "  a  ";            // compiles: code starts as "  a  ", since set doesn't run
public string label { get; set => field = value.trim(); } = global.strval(1);  // compiles: a computed initial value fills the storage too
```

**A member has no implicit starting value, `null` included.** A `T?` member writes `= null` like any other starting value, and PHP# has no default filler such as C#'s `default(T)`.

**A field or a property with storage and no initial value must be set on every path through the main constructor,** as Swift's and Kotlin's stored properties must be. This holds for every one, settable or get-only, nullable or not, as it does for a struct. Otherwise it is a compile error that names both fixes: an initial value, or setting it in the constructor.

<!-- example nullable-members -->
```csharp
// app/Profile.sharp
namespace App;

public class Profile
{
    private int? total = null;               // compiles: starts as null
    private int? count;                      // compile error: give count an initial value, or set it on every path through the constructor
    public string? nickname { get; set; }    // compile error: give nickname an initial value, or set it on every path through the constructor
    public string? note { get; }             // compiles: the constructor sets it once
    public string? memo { get; } = null;     // compiles: the initial value sets it once
    public string? handle { get; }           // compile error: give handle an initial value, or set it on every path through the constructor
    public int level { get; set; }           // compiles: the constructor sets it
    public int rank { get; set; }            // compile error: give rank an initial value, or set it on every path through the constructor

    public Profile(string? note, bool claimed)
    {
        this.note = note;
        this.level = 1;
        if (claimed) { this.handle = "taken"; this.rank = 1; this.count = 1; this.nickname = "new"; }
    }
}
```

**A static field or property with storage needs an initial value, whatever its type.** No constructor sets it, so the error names that one fix.

**A static's initial value runs once, when a request first uses its class's statics,** so it may be any expression, as C#'s static initializer runs before the first use of a static. A get-only static is set only by its initial value.

**Plain PHP reaches a static with an accessor body, a computed body or an observer only through a method,** because PHP has no hooks on a static property. Reading or writing it directly from plain PHP is PHP's access error.

<!-- example static-initial-values: members -->
```csharp
private static int hits = 0;                     // compiles
private static int misses;                       // compile error: give misses an initial value
public static string? region { get; set; } = null;   // compiles: starts as null
public static string? zone { get; set; }         // compile error: give zone an initial value
private static int started = global.time();      // compiles: runs once, when a request first uses the class's statics
public static int limit { get; } = 10;           // compiles: get-only, set only by its initial value
```

### 6.1 Property features

PHP# has all of C#'s property features:

- auto-properties
- a separate, narrower access level for `set` and `init`
- get-only properties, which are set in the constructor
- `init`, which allows a set only while the object is being created
- computed properties, written `=> expr`
- accessor bodies, which use `field` for the hidden storage and `value` for the incoming value
- `required`, which the checker enforces wherever the object is created
- properties in interfaces, abstract properties, and overrides
- Reflection that tells fields from properties

**Only `set` and `init` take an access level of their own,** narrower than the property's, as Swift's `private(set)` is. `get` reads at the property's level, so an access level on it is a compile error. A property read only inside its class is read through a method there.

<!-- example access-on-get: members -->
```csharp
public int views { get; private set; } = 0;     // compiles: set is narrower than the property
public int score { protected get; set; } = 0;   // compile error: only set and init take an access level; read score inside the class through a method
```

**A `get` body may have any effect but never writes `this`.** A read may query the database or read the clock, and it carries the body's effects (section 29). Writing a field of `this`, or calling a method on `this` that writes one, is a compile error that names the write. A `lazy` property still keeps its computed value (section 6.5), because that write is the language's, not the body's.

A getter promises less than a method marked `readonly` (section 13): it may call a plain PHP method, and a PHP# method not marked `readonly` that writes nothing of `this`.

<!-- example getter-writes: members
private int reads = 0;
public int count() { this.reads = this.reads + 1; return this.reads; }
-->
```csharp
public int next => this.reads + 1;                                              // compiles: it reads this
public int bumped { get { this.reads = this.reads + 1; return this.reads; } }  // compile error: a getter never writes this, and bumped writes reads
public int counted => this.count();                                             // compile error: count writes reads, and a getter never writes this
```

**A class overrides a plain PHP parent's property with a property** marked `override`, and it writes the type like every PHP# property. Eloquent's `$table`, `$fillable` and `$timestamps` are overridden this way:

<!-- example plain-php-property-override: alone -->
```csharp
namespace App.Store;

import Illuminate.Database.Eloquent.Model;

public class Order : Model
{
    protected override string? table { get; set; } = "orders";                    // compiles: fits Model's @var string|null
    protected override List<string> fillable { get; set; } = ["number", "total"]; // compiles
    protected override List<string> with { get; set; } = ["customer"];            // compiles
    public override bool timestamps { get; set; } = false;                        // compiles

    protected override table { get; set; } = "orders";                            // compile error: write the type
    public override List<string> fillable { get; set; } = ["number"];             // compile error: fillable is protected in Model
    protected override string? table { get; set; } = 5;                           // compile error: 5 is not string?
    protected override int timestamps { get; set; } = 0;                          // compile error: Model's timestamps is bool
    protected override List<string> fillable { get; set; } = this.columns();      // compile error: an initial value can't read this
    public override bool timestamps = false;                                      // compile error: PHP# has no public fields; write a property
    protected string? table { get; set; } = "orders";                             // compile error: Model declares table; write override
    protected override string? table { get; } = "orders";                         // compile error: Model's own code writes table, so its override has set
    public override bool timestamps { get; protected set; } = false;              // compile error: timestamps is set at public in Model
}
```

- **The written type must fit the parent's.** When PHP declares the parent's property with no type, the written type must be assignable to the parent's `@var` type, or to anything when there is no `@var`. The engine drops the written type when the class links, because PHP refuses a typed redeclaration there. When PHP declares the parent's property with a type, the written type must equal it, because PHP checks property types for invariance when the class links. The engine keeps it.
- **The access level is written and must match the parent's.** It is `public` when the parent's property is, as `timestamps` is in `Model`. The `set` keeps the parent's level too, so `timestamps { get; protected set; }` is an error.
- **The override has `set`.** The parent's own code writes the property at any time, as Eloquent's `setTable` writes `table`, so a get-only override or one with `init` is an error. The override may give `get` and `set` bodies.
- **The initial value runs before the parent's constructor and can't read `this`** (section 6), so a parent's constructor that reads the property, as Eloquent's does, sees it.
- **A PHP# parent's `protected` field is overridden the same way,** by a property measured against that field. A PHP# property is closed unless it is `virtual` or `abstract`, as a method is (section 22), and a field has no `virtual`, so a `protected` field is always open.

### 6.2 Change observers

`willSet` runs before a write, and `didSet` runs after it.

**Observers run only once the object is built,** on every write after its constructor and any object initializer have finished, and also when the value is unchanged. The initial value runs none, and neither does a write while a constructor, a named constructor or an object initializer runs, a method it calls included. `set` and `init` bodies still run for those writes. A `with` copy is a new object being built, so the values it is written with run none either. A static's initial value runs none, and every later write does. Both observers see `value`, the written value, and `field`, the stored one: `willSet` reads the value the write replaces, and `didSet` the value it stored. Beside a `set` body, the order is `willSet`, the `set` body, then `didSet`. An observer runs inside `set`, so a property with one needs `set`.

**`willSet` cannot change or stop the write.** Writing `value` or returning in it is a compile error, and so is returning in a `set` body before `didSet`:

<!-- example change-observers: members
private void touch() { }
-->
```csharp
public string name { get; set; didSet => this.touch(); } = "";    // compiles: didSet runs on each write once the object is built
public int level { get; set; willSet { if (field != value) { this.touch(); } } } = 0;   // compiles: field is the value the write replaces
public int total { get; set; willSet { value = 0; } } = 0;        // compile error: willSet cannot change value
public int count { get; didSet => this.touch(); } = 0;            // compile error: didSet needs set
```

### 6.3 Typed property references

`nameof(Order.total)` names a property. The reference is typed, and the checker rejects a misspelled or removed property wherever it is used. It sits beside `typeof(Order)` (section 25), as in C#'s `nameof`.

<!-- example property-references: statements
Query query = new Query();
-->
```csharp
query.orderBy(nameof(Order.total));   // compiles: a typed reference to Order's total property
query.orderBy(nameof(Order.totl));    // compile error: Order has no property totl
query.orderBy(Order.total);           // compile error: Order.total reads a static value, and total is an instance property
```

A bare `Order.total` is never a property reference, so `Class.y` always reads a static value (section 4).

### 6.4 Reusable property behaviors

A clause after the property names its behaviors. It reads as "get/set via Trimmed".

<!-- example property-behaviors: members -->
```csharp
public string name { get; set; } via Trimmed, Tracked = "";
```

A behavior implements `get` and `set` once, as a class that implements the standard library's `Behavior<T>` for the property's type:

<!-- example behavior-class -->
```csharp
namespace App.Text;

import Sharp.Behavior;

public class Trimmed : Behavior<string>
{
    public string get(string value) => value;          // a read returns what is stored
    public string set(string value) => value.trim();   // a write stores the trimmed text
}
```

- A behavior is a class that implements `Behavior<T>` for the property's type, and its constructor takes no arguments. A behavior for another type, an interface, an abstract class, or a constructor that needs an argument is a compile error.
- `via` names a type, so a generic behavior takes its type arguments as every type does (section 11): `via Logged<string>` creates each behavior as `new Logged<string>()`.
- A behavior's `get` and `set` are the property's accessors. Each may have any effect, and a read or write of the property carries it (section 29). `set` may write the behavior's own fields, and `get` writes none, as no `get` body writes `this` (section 6.1).
- `via` goes on an instance auto-property with `get; set;`. A property in an interface or an `abstract` one has no storage to run around, a static one has no object to keep behaviors in, and one with an accessor body already says how it reads and writes, so each is a compile error. A constructor parameter takes no `via`: declare the property in the class body.
- Each object creates its own behaviors with `new` when it is constructed, before any initial value, and keeps each one in a hidden slot. A copy made with `with`, or with plain PHP's `clone`, creates its own too, so two objects never share a behavior's state.
- A write runs each behavior's `set` in the order they are written, then stores the result. A read runs each behavior's `get` the other way, so the first behavior wraps the rest. The initial value is a write, so it runs through each `set`, which never sees the object (section 6).

### 6.5 `lazy`

`lazy` is a modifier, not a behavior. A lazy property computes its value on the first read, then keeps it and never recomputes it. `static lazy` computes once for the host's app lifetime, the lifetime of a `singleton` (section 32).

<!-- example lazy-property: members
private Planner planner = new Planner();
private int id = 1;
-->
```csharp
public lazy Plan plan => this.planner.plan(this.id);
```

A `lazy` body may have any effect, such as a database query, and reading the property carries the body's effects (section 29). Like every `get` body, it never writes `this` (section 6.1). It may read properties that can change later. The value is computed once and kept, even if those inputs change, as in Swift and Kotlin. A value that must follow its inputs is a plain computed property (`=> expr`).

- **A body that throws keeps nothing,** so the next read runs it again, as Kotlin's `lazy` does.
- **A copy made with `with` keeps no `lazy` value,** because it is a new value whose inputs may differ. Its first read computes each `lazy` property again, as Kotlin's `copy()` does.

## 7. Methods

The return type comes first, and there is no keyword. Every declaration has the same order: modifiers, then the type, then the name.

<!-- example method-declarations: members
private int a = 1;
private int b = 2;
-->
```csharp
public Plan plan(Order order) { … }
public int total() => this.a + this.b;
public T first<T>(List<T> items) { … }
```

**A method can take any number of arguments** with PHP's `...`. The parameter is written `int ...values`, and `values` arrives as a `List<int>`. A call spreads an existing list with `...list`.

<!-- example variadic-parameters: statements in Money
import Sharp.Math.Math;
List<int> prices = [900, 2900];
-->
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

**A class has one method per name, so there is no overloading by argument types.** A second method with the same name is a compile error, whatever it takes. Give each method a name that says what it takes, so a reader of the call knows which one runs:

<!-- example one-method-per-name: members -->
```csharp
public void grantUserAccess(User user) { … }          // compiles
public void grantPageAccess(Page page) { … }          // compiles
public void grant(User user) { … }
public void grant(Page page) { … }                    // compile error: grant is declared twice. Name each by what it takes, as in grantPageAccess
```

**A statement that can never run is a compile error,** as Java's "unreachable statement" and Zig's "unreachable code" are. A statement after `return`, `throw`, `global.exit(…)`, `break` or `continue` in the same block never runs, and neither does one after an `if` whose every branch ends the block, or after a loop that never ends, such as a `do` whose body ends the block. The error names the line that ends the block, and says to delete the statement or move it above that line. The block of an `if` or a `for … of` whose condition never finishes, as `if (global.exit(1))`, never runs either, and the error says to write the condition as its own statement in place of the `if` or the `for`.

<!-- example unreachable-statements: members -->
```csharp
public int total(List<int> prices)
{
    int sum = 0;
    for (const price of prices) {
        if (price < 0) {
            continue;
            sum -= price;                     // compile error: continue ends the block, so this never runs
        }
        sum += price;
    }
    return sum;
    sum = 0;                                  // compile error: return ends the block, so this never runs
}
```

## 8. Functions

There are no top-level functions. Shared code lives in static methods on a class.

**A PHP function is reached through the type it works on,** as a method the standard library declares with an extension (section 26), as in `name.trim()`. Otherwise it goes through a static class in its standard library namespace (section 23), as in `Math.max(a, b)` and `Json.encode(body)`.

<!-- example standard-library-calls -->
```csharp
import Sharp.Json.Json;
import Sharp.Math.Math;

public class Receipt
{
    public string label(string name) => name.trim();                        // compiles
    public int larger(int a, int b) => Math.max(a, b);                      // compiles
    public string body(Map<string, Any> payload) => Json.encode(payload);   // compiles
    public string raw(string name) => trim(name);                           // compile error: write name.trim()
}
```

- **Every PHP built-in function is a standard library method,** so calling one by its bare name from `.sharp` code is a compile error that names the method. The ten effect classes of section 29 replace the functions for their effects, so `printf`, `time()` and `file_put_contents` are compile errors too.
- **A built-in the standard library has no method for is called through PHP's root namespace,** as `global.levenshtein(a, b)` (section 23), and the standard library calls PHP the same way.
- **PHP's constants are read through its root namespace too,** as `global.PHP_EOL` (section 23). A bare `PHP_EOL` is a compile error, because a bare uppercase name is always the code's own constant.

**Output and exit go through the standard library.** `echo` and `print` are removed, and output goes through `Console` (section 29). `die` is removed, because `die("…")` prints its message and exits with status 0, which reports success. Exiting goes through `Process`.

<!-- example output-and-exit: statements -->
```csharp
public class Prune
{
    public Prune(private Console console, private Process process) { }

    public void run(int pruned, bool failed)
    {
        if (failed) {
            this.console.writeError("Prune failed");         // compiles: the message goes to standard error
            this.process.exit(1);                            // compiles: ends the process with status 1
        }
        this.console.writeLine(`Pruned ${pruned} carts`);    // compiles
    }
}

echo "Pruned";                                               // compile error: write console.write(text)
print "Pruned";                                              // compile error: write console.write(text)
printf("Pruned");                                            // compile error: write console.write(text)
public void stop() { exit(1); }                              // compile error: write process.exit(1)
public void fail() { die("Prune failed"); }                  // compile error: write the message with console.writeError, then process.exit(1)
```

- Exiting skips every `finally` block, as Java's `System.exit` and C#'s `Environment.Exit` do.
- Printing has the effect `Console`, and exiting has the effect `Process` (section 29), as Koka's `console` effect marks printing.

## 9. Constructors

A constructor is named after its class. A parameter with an access modifier declares a member, and the parameter spells out which kind:

- **`private Planner planner`** declares a field.
- **`public Account account { get; }`** declares a property. A `public` parameter without accessor braces is a compile error, just as a public field is.
- **A parameter with no modifier** is an ordinary parameter.

<!-- example parameter-members: members in StoreService -->
```csharp
public StoreService(
    private Planner planner,
    [Field(label: "Account")] public Account account { get; private set; },
) {
    if (!account.active) { throw new InactiveAccount(account); }
}
```

**A member declared on a parameter takes access levels and attributes only,** as TypeScript's and Kotlin's parameter properties do: an access modifier, an accessor list whose `set` or `init` may take a narrower access level, such as `{ get; private set; }`, and attributes. Accessor bodies, `willSet`, `didSet`, `via`, `lazy`, computed properties and `static` go on a member in the class body:

<!-- example parameter-member-limits: members in Ledger -->
```csharp
public Ledger(
    public Money balance { get; } via Tracked,                     // compile error: declare balance in the class body to give it via
    public string name { get; set => field = value.trim(); },      // compile error: declare name in the class body to give it an accessor body
) { }
```

**A constructor marked `required`** is one that `new Self(…)` can call on the class and on every subclass. Section 25 gives its rules.

### 9.1 Named constructors

A class has one unnamed main constructor. Any other constructor has a name and passes control to the main one with `: this(…)`. Every creation is written `new Class…`.

<!-- example named-constructors: statements in Artifact
string json = "{}";
-->
```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }

let artifact = new Artifact.fromJson(json);
```

- The dependency container always uses the main constructor (section 32).
- A class has one main constructor, as it has one method per name (section 7).
- A static method that looks up an existing object, such as `Journey.forUuid`, stays a static method.

**A subclass's constructor always calls its parent's with `: super(…)`,** as in `public Order(Row row) : super(row) { }`, even when the parent's takes no arguments, as Kotlin always writes `: Document()`. A constructor without the call is a compile error:

<!-- example super-call -->
```csharp
// app/Document.sharp
namespace App;

public class Document
{
    public Document() { }
}
```

<!-- example super-call -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice : Document
{
    public Invoice(public int number { get; }) : super() { }      // compiles: Document takes no arguments, and the call is still written
}
```

<!-- example super-call -->
```csharp
// app/CreditNote.sharp
namespace App;

public class CreditNote : Document
{
    public CreditNote(public int number { get; }) { }             // compile error: CreditNote extends Document, so write : super()
}
```

**`this` is refused in the arguments of `: super(…)` and `: this(…)`,** as in C#, Java, Kotlin, Swift and TypeScript, because the object doesn't exist until the constructor runs:

<!-- example constructor-call-this: members in Artifact -->
```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }   // compiles
public Artifact.copy() : this(this.manifest) { }                         // compile error: `this` is not available before the constructor runs: pass a parameter instead.
```

**A get-only property is set once.** A named constructor runs on the new object after the main constructor, so it can't set a get-only property again. A property that a named constructor sets again is declared `{ get; init; }` (section 6.1):

<!-- example set-once: members in Money -->
```csharp
public Money(public int amount { get; }, public string currency { get; init; }) { }
public Money.zero(string currency) : this(0, "USD") { this.currency = currency; }   // compiles: currency is init
public Money.free(string currency) : this(1, currency) { this.amount = 0; }         // compile error: amount is get-only, and the main constructor set it
```

## 10. Structs

A `struct` is a value type.

- **A struct never changes.** A method returns a changed copy, usually built with `with`, as Kotlin's data class `copy` does. `point.x = 5` is a compile error that names a method returning a changed copy.
- **`readonly struct` is a compile error,** because every struct is already read-only.
- **`==` compares values.** `===` works on classes only (section 19).
- **A struct cannot inherit or be inherited,** but it can implement interfaces.
- **A struct has no default value.** Every property or field without an initial value must be set when the struct is created, and `= null` makes a nullable one optional, as in Rust, Swift, Kotlin and TypeScript.
- **A struct can be generic,** as in `public struct Pair<TFirst, TSecond>`, with the type parameters of section 11.

<!-- example struct-changes -->
```csharp
// app/Point.sharp
namespace App;

public struct Point
{
    public int x { get; init; }
    public int y { get; init; }
    public Point moved(int dx, int dy) => this with { x: this.x + dx, y: this.y + dy };
}
```

<!-- example struct-changes -->
```csharp
// app/Size.sharp
namespace App;

public readonly struct Size { … }     // compile error: every struct is already read-only; write struct
```

<!-- example struct-changes: statements
Point p = new Point { x: 1, y: 2 };
-->
```csharp
Point q = p.moved(1, 0);              // compiles: q is a changed copy, and p is unchanged
p.x = 5;                              // compile error: a struct never changes; return a changed copy from a method, such as moved
Point origin = new Point { x: 0 };    // compile error: y has no initial value, so set it when the Point is created
```

`with` copies an object or a struct and sets the listed properties through their `init` or `set` accessors. The original is unchanged.

**A struct keys a `Map` when every field has `==`, by its whole value with nothing declared, or when it declares `==` with `hash()`, by that pair,** as Swift's `Hashable` structs, Rust's `Hash` derive, C#'s record structs, Java's records, Kotlin's data classes and Go's structs are. It is the general rule for keys (section 12): a struct whose every field has `==` has `==` and `hash()`, and a struct with a field that has none, such as a function, declares both (section 19). Two structs are one key when they are `==`. A struct gets JSON key text only from its `toString()`, so `Json.encode` of a `Map` keyed by a struct without `toString()` is a compile error, as Jackson writes a key through `toString` and serde and Go refuse a key that is not text.

**Every struct has a static `parse`,** which reads a `Map<string, Any?>` or an object's public properties and throws one error that lists every bad field, and a static `tryParse`, which gives null instead. The names follow `Int.parse` and `Int.tryParse` (section 24). Every class gets both the same way.

<!-- example struct-parse: statements
Map<string, Any?> payload = ["customerId": "abc"];
-->
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

- The keys are the parameter names of the main constructor (section 9.1), then every property an object initializer may set, as C#'s `System.Text.Json` reads a record.
- `[Key("plan_code")]` renames the key a parameter reads.
- A nested struct parses the same way.
- A `List` checks each element.
- An enum parses from its value.
- A missing key for a `T?` parameter reads as null.

An object's public properties parse the same way as a `Map`'s keys, so a row object from plain PHP becomes a checked struct:

<!-- example struct-parse-object: statements
Object row = Rows.withId("abc");
-->
```csharp
public struct OrderRow
{
    public OrderRow(public int id { get; }, public string number { get; }) { }
}

OrderRow order = OrderRow.parse(row);   // row is a plain PHP object from a database query; throws: "id: expected int, got string 'abc'"

public class OrderRecord
{
    public OrderRecord(public int id { get; }, public string number { get; }) { }
}

OrderRecord record = OrderRecord.parse(row);   // a class parses the same way: its main constructor's parameters, then every property an initializer may set
```

## 11. Generics

**Generics are reified.** The running program knows every type argument.

- `page is PaginatedList<Order>` is checked while the code runs.
- A value of the wrong type throws a `TypeError` where it enters PHP# from plain PHP. Section 12 lists where a value enters. An `int` where PHP# expects a `float` does not throw: it becomes a `float` (section 24).

**Each type argument of a generic class has its own static members,** as in C#. `Counter<Order>.count` is separate from `Counter<User>.count`, and a static member may use the class's type parameters:

<!-- example generic-statics: statements -->
```csharp
public class Counter<T>
{
    public static int count { get; set; } = 0;
    public static List<T> seen { get; set; } = [];      // compiles: a static member may use T
}

Counter<Order>.count += 1;
Counter<User>.count;                                     // 0: Counter<User> has its own count
Counter.count;                                           // compile error: name the type argument, as in Counter<Order>.count
```

A static member names its type arguments: `Counter<Order>.count`. A bare `Counter.count` names none and is a compile error.

**Writing type arguments:**

- **`new` always names them:** `new PaginatedList<Order>(…)`, or names a class value that carries them, as in `new (pages)()` (section 25). `new Self(…)` names none, because `Self` is the class with its own type parameters.
- **A type names them too:** `PaginatedList<Order> page`. `Class<PaginatedList>` is the one exception: a class type names the class itself.
- **A generic method call fixes them** from the arguments it receives, or from the declared type its result goes into. A type argument that neither fixes is a compile error, as in C#'s error CS0411 and in Swift.
- **A plain PHP generic class,** declared with PHPDoc's `@template`, takes type arguments in PHP# code, as in `Collection<Order>`. The checker checks them, but the object carries none while the code runs, as Java's erased generics do. So a type test against one, such as `x is Collection<Order>`, is a compile error that says the type argument is unknown while the code runs (section 21).

<!-- example generic-method-inference: statements alone
Cache cache = new Cache();
-->
```csharp
public class Cache
{
    public T get<T>(string key) { … }
}

List<Order> orders = cache.get("orders");   // compiles: the declared type fixes T as List<Order>
const orders = cache.get("orders");         // compile error: nothing fixes T; write cache.get<List<Order>>("orders")
```

**A generic object that plain PHP creates without type arguments** (`new PaginatedList($rows)`, or Laravel's container) has its bounds as its type arguments, as Java's raw types do, but checked where it enters PHP# instead of trusted. Under `PaginatedList<TItem : DatabaseEntity>`, a raw page is a `PaginatedList<DatabaseEntity>`. A parameter that needs `PaginatedList<Order>` throws `TypeError`. A method that takes any page is generic. PHP# code never creates a raw object, because a type argument it can't fix or infer is a compile error.

<!-- example raw-generic-objects: statements
use App\PaginatedList;
$rows = [new App\Order()];
$report = new App\Report();
-->
```php
$page = new PaginatedList($rows);                       // plain PHP: no type arguments, so the page is a PaginatedList<DatabaseEntity>
$report->show($page);                                   // throws TypeError: show needs a PaginatedList<Order>
$report->count($page);                                  // accepted: TItem is fixed as DatabaseEntity
```

<!-- example raw-generic-objects -->
```csharp
public class Report
{
    public void show(PaginatedList<Order> page) { … }
    public int count<TItem : DatabaseEntity>(PaginatedList<TItem> page) { … }
}
```

**Every type argument is carried at runtime, written or inferred,** so a generic method can use a type parameter that its call inferred:

<!-- example inferred-type-arguments: statements
Inbox inbox = new Inbox();
List<Order> orders = [new Order()];
Any message = new Order();
-->
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
- **`Object` bounds a type parameter to any object,** `<TValue : Object>`, as C#'s `where T : class` and TypeScript's `T extends object` do. It is a bound alone.
- **A type parameter with no bound may hold `null`,** so it is bounded by `Any?`.
- **A generic class can't implement PHP's `Serializable`,** and the error points to `__serialize` and `__unserialize`.
- **A class value with type arguments can't be a `Map` key,** because `Class<PaginatedList<Order>>` and `Class<PaginatedList<Invoice>>` share the plain class name.

<!-- example type-parameter-bounds: members -->
```csharp
public class PaginatedList<TItem : DatabaseEntity> { … }
public PaginatedList<TItem> list<TItem : DatabaseEntity>(Query<TItem> query) { … }
public TValue keep<TValue : Object>(TValue value) => value;
```

**A class that implements or extends a generic type takes its type argument's type,** as in C#, Java, Kotlin, TypeScript and Rust: `OrderHandler : Handler<Order>` writes `handle(Order item)`. A call from plain PHP still checks `item` against `Order` under the caller's `strict_types`, as every typed parameter is checked, and reflection reports `Order` as its type:

<!-- example generic-implementation -->
```csharp
namespace App.Handlers;

import App.Order;

public interface Handler<T>
{
    void handle(T item);
}

public class OrderHandler : Handler<Order>
{
    public void handle(Order item) { }   // compiles: takes the type argument's type
}
```

<!-- example generic-implementation: statements
$handler = new App\Handlers\OrderHandler();
-->
```php
$handler->handle("x");   // throws TypeError: handle takes an Order
```

**`Self` on a receiver typed by a type parameter is that type parameter** (section 25). In `TBox refill<TBox : Box<int>>(TBox box)`, `box.withValue(5)` returns a `TBox`, not `Box<int>`, as Rust's `t.clone()` on a `T: Clone` returns a `T`:

<!-- example self-on-type-parameters: members -->
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
A private member may break the marker, and is then reachable only through `this`.

When a missing marker blocks a substitution, the error names the marker to add:

```text
PaginatedList<Order> cannot be used as PaginatedList<DatabaseEntity>.
TItem is only returned by PaginatedList, so declare it `out TItem`.
```

There is no variance at the point of use, such as Java's `? extends T`.

## 12. Collections

PHP# has three collection types: `List<T>`, `Map<TKey, TValue>` and `Set<T>`.

- They are values, as structs (section 10) and Swift's `Array`, `Dictionary` and `Set` are. Assigning or passing a collection copies it only when one side later writes to it.
- A `Map`'s key is any type with `==` and `hash()` (see **Map keys** below).

<!-- example copy-on-write: statements
List<Line> a = [new Line()];
Line line = new Line();
-->
```csharp
let b = a;
b.add(line);   // b is copied here, and a is unchanged
```
<!-- run copy-on-write
a.count();   // 1
b.count();   // 2
-->

**A method that changes a collection it was given changes its own copy.** It returns the collection when the caller should see the change:

<!-- example changed-copy-returned: statements in Cart
List<Line> lines = [new Line()];
Line shipping = new Line();
-->
```csharp
public static List<Line> withShipping(List<Line> lines, Line shipping)
{
    lines.add(shipping);
    return lines;
}

lines = Cart.withShipping(lines, shipping);   // the caller keeps the change
```

**A collection held in a property changes through the property's `set`.** `this.lines.add(line)` reads `lines`, changes the copy and writes it back through `set`. Code that cannot reach the `set` cannot change the collection, and a copy in a local changes only the local:

<!-- example property-collections: statements
Order order = new Order();
Line line = new Line();
-->
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

At each crossing, the runtime checks every element once and throws a `TypeError` that names the element. An `int` where PHP# expects a `float` does not throw: it becomes a `float` (section 24). A call from PHP# to PHP# checks nothing at runtime, because the checker proved it.

`as` to a collection type checks every element too, wherever the value came from, and gives null if any element is wrong (section 21).

<!-- example element-checks: statements
LegacyCart legacy = new LegacyCart();
Line shipping = new Line();
-->
```csharp
List<Line> lines = legacy.lines();            // plain PHP returned it: every element is checked here
lines = Cart.withShipping(lines, shipping);   // PHP# to PHP#: nothing is checked
```

**Literals:**

<!-- example collection-literals: statements
Line lineA = new Line();
Line lineB = new Line();
Plan pro = new Plan();
Plan team = new Plan();
-->
```csharp
const lines = [lineA, lineB];                  // List<Line>
const plans = ["pro": pro, "team": team];      // Map<string, Plan>, the same name: value rule as section 16
Map<string, Plan> empty = [:];                 // an empty Map
Set<string> tags = ["vip"];                    // the declared type makes it a Set
```

PHP's `["key" => value]` is not used, because `=>` is the lambda arrow.

**An empty literal, `[]` or `[:]`, with no declared type is a compile error,** as in Swift, because nothing says what it holds:

<!-- example empty-literals: statements alone -->
```csharp
let messages = [];                             // compile error: an empty literal needs a type: write List<string> messages = []
List<string> messages = [];                    // compiles
```

**A `List` literal where a `Map` is declared, or a `Map` literal where a `List` is declared, is a compile error,** at a declaration, an assignment, a default, a return or an argument, because `[:]` and `[key: value]` write a `Map`, and `[]` and `[a, b]` write a `List` or a `Set`:

<!-- example literal-kinds: statements -->
```csharp
Map<string, int> counts = [];                  // compile error: `[]` is an empty List. An empty Map is written `[:]`.
List<string> tags = [:];                       // compile error: `[:]` is an empty Map. An empty List is written `[]`.
Map<int, string> names = ["a"];                // compile error: A Map literal is written `[key: value]`.
```

**A spread copies a collection into a literal,** and the collection's type decides what it means:

<!-- example spreads: statements
List<Line> open = [new Line()];
List<Line> closed = [new Line()];
string binary = "php";
string name = "migrate";
List<string> arguments = ["--force"];
Map<string, string> config = ["root": "/srv"];
Map<string, int> defaults = ["pro": 20];
Map<string, int> overrides = ["pro": 30];
Image image = new Image();
-->
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

**Indexing:**

- A `List` read, `x[i]`, throws `OutOfRangeException` when the index is missing, whatever follows it, as in Kotlin, Swift and C#. `x.getOrNull(i)` is the safe read, as in Kotlin, so `x[i] ?? fallback` is a compile error that names `getOrNull`.
- A `Map` read, `x[key]`, gives `TValue?`, null when the key is missing, as in Kotlin and Swift.
- `x[i] = v` on a `List` replaces the element at `i`, and throws `OutOfRangeException` past the end. Appending is `add`.
- `x[key] = v` on a `Map` inserts or replaces the key.
- A `List` index is an `int`. Any wider index, such as `int|string` or `Any`, is a compile error. A negative index is never in a `List`, so `items[-1]` throws `OutOfRangeException`, and the last item is `items.last()`, as in Kotlin.

<!-- example index-rules: statements
List<Line> lines = [];
Line line = new Line();
Map<string, Plan> plans = [:];
Plan pro = new Plan();
List<int> items = [1, 2, 3];
int|string id = 1;
-->
```csharp
const first = lines[0];       // throws OutOfRangeException when lines is empty
lines[0] = line;              // replaces the element at 0; throws OutOfRangeException when lines is empty
plans["pro"] = pro;           // inserts or replaces
items[-1];                    // throws OutOfRangeException: write items.last()
items.last();                 // the last item
items.getOrNull(5);           // null: index 5 is missing
items[5] ?? 0;                // compile error: a List read throws when the index is missing; write items.getOrNull(5) ?? 0
items[id];                    // compile error when id is int|string: a List index is an int
```

**A `List` index is a place,** as a Rust `Vec` index and a Go slice index are, so a changing method called through it changes the element the list stores: `grid[0].add(5)` adds to the list at index 0. A struct's fields never change (section 10), so `points[0].x = 5` stays a compile error. **A change through a `Map` index is a compile error,** as Go refuses `m[k].x = 5`, because a `Map` read is a copy:

<!-- example index-changes: statements
List<List<int>> grid = [[1, 2], [3]];
Map<string, List<int>> stock = ["sku-1": [1]];
-->
```csharp
grid[0].add(5);                               // changes the list stored at index 0
grid[0].count();                              // 3
stock["sku-1"]?.add(5);                       // compile error: a Map read is a copy: read it, change it, and store it back
List<int> counts = stock["sku-1"] ?? [];
counts.add(5);
stock["sku-1"] = counts;                      // stores the changed list back
```

**A `Map` read gives `TValue?`, so it is handled where it is read,** with `??`, `?.`, `is`, `as`, `match` or `get`. A `Map` read used as a `TValue` is a compile error that names `??` and `get`:

<!-- example map-reads: statements alone
string plan = "pro";
private void charge(int price) { }
-->
```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices["pro"];                                  // compile error: handle a missing "pro" with ??, or read it with get
int price = prices["pro"] ?? 0;                             // compiles: 0 when "pro" is missing
int price = prices[plan] ?? throw new UnknownPlan(plan);    // compiles: throws when plan is missing
if (prices[plan] is int price) { this.charge(price); }      // compiles: runs only when plan is present
int? maybe = prices.get(plan);                              // compiles: null when plan is missing
```

Data with fixed keys is a class, so a `Map` holds keys that come from outside, where a missing key is normal.

**A `Map` with nullable values reads as Kotlin's does:** a read from `Map<string, int?>` gives `int?`, so a missing key and a stored null read the same. `map.has(key)` tells them apart, as Kotlin's `containsKey` does.

<!-- example nullable-map-values: statements -->
```csharp
Map<string, int?> limits = ["pro": null];
limits["pro"] ?? 0;          // 0: the stored null
limits["team"] ?? 0;         // 0: the missing key
limits.has("pro");           // true
limits.has("team");          // false
```

**A key read back out of a `Map<string, TValue>` is a `string`,** an all-digit key such as `"5"` included. This covers the key of `for (const [key, value] of map)` and of `keys()`.

<!-- example map-string-keys: statements
Map<string, int> stock = ["5": 1, "sku-2": 2];
-->
```csharp
void reserve(string sku, int count) { … }

for (const [sku, count] of stock) {   // stock is Map<string, int>, so sku is a string, even for the key "5"
    this.reserve(sku, count);
}
```

**Map keys:** anything with `==` and `hash()` (section 19) keys a `Map` and joins a `Set`, as in Swift, Rust, Java and Kotlin.

- A loop over the `Map` gives each key as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type, as in `const [Status status, int n]`, stays allowed. An inferred `Map`, such as a `groupBy` result, gives its keys back the same way.
- Plain PHP receives the `Map` itself, or a PHP array where its parameter's declared type names `array`. A PHP array's keys are `int` or `string`, so passing a `Map` keyed by anything else to such a parameter is a compile error that names the parameter and the key type. `counts.count()` counts any `Map`, where PHP's `global.count(counts)` takes only one keyed by `int` or `string`.

<!-- example enum-map-keys -->
```csharp
// app/Status.sharp
namespace App;

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
```

<!-- example enum-map-keys -->
```csharp
// app/OrderStats.sharp
namespace App;

public class OrderStats
{
    public Map<Status, int> countByStatus(List<Order> orders)
    {
        Map<Status, int> counts = [:];
        for (const order of orders) {
            counts[order.status] = (counts[order.status] ?? 0) + 1;
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
```

<!-- example enum-map-keys: statements
OrderStats stats = new OrderStats();
List<Order> orders = [new Order()];
-->
```csharp
for (const [status, n] of stats.countByStatus(orders)) { … }   // compiles: status arrives as a Status, with no type written
for (const [status, group] of orders.groupBy(o => o.status)) { … }   // compiles: an inferred Map's keys arrive as Status too
Map<Username, Order> byUser = [:];                               // compiles: a struct whose every field has == keys a Map by its whole value
```

**Only a literal takes its collection type from where it lands.** A list literal passed where a `Set` or a tuple is expected becomes one. A `List` variable passed there is a compile error, and where a `Set` is expected the error names `toSet()`:

<!-- example set-arguments: statements in Tags -->
```csharp
public static void apply(Set<string> tags) { … }
Tags.apply(["vip", "new"]);                      // compiles: the literal becomes a Set
```

<!-- example set-variable-arguments: statements in Tags
List<string> picked = ["vip"];
-->
```csharp
public static void apply(Set<string> tags) { … }
Tags.apply(picked);                              // compile error: a List is not a Set; write picked.toSet()
```

**Methods** follow Kotlin's collection names:

<!-- example collection-read-methods: statements
List<Line> lines = [new Line()];
Line line = new Line();
-->
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
- A `sortedBy` key of type `T` or `T?` compiles exactly when `<` compiles on two non-null `T` values. The nullable part is allowed, and it sorts first.
- A method that takes a function has that function's effects (section 29), so `lines.map(l => l.name)` is pure.
- Methods that change a collection change it in place:

<!-- example collection-write-methods: statements
List<Line> lines = [new Line()];
Line line = new Line();
Map<string, Plan> plans = [:];
Plan pro = new Plan();
-->
```csharp
lines.add(line);
lines.insert(0, line);
lines.remove(line);           // by value: the first equal element, and true when one was removed
lines.clear();
plans["pro"] = pro;
plans.remove("pro");          // by key
```

**Each collection does the natural thing with a method:**

- `remove` removes a value from a `List` and a key from a `Map`.
- On a `List`, `remove(value)` removes the first element equal to `value` by `==` (section 19), renumbers the list, and returns `bool`: true when it removed one.
- `filter` renumbers what a `List` keeps, and keeps a `Map`'s keys.

<!-- example natural-remove: statements
List<Line> lines = [new Line()];
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
List<string> tags = ["a", "b", "a"];
tags.remove("a");                     // true, and tags is ["b", "a"]
tags.remove("z");                     // false, and tags is unchanged
lines.filter(l => l.free);            // List<Line>, renumbered
plans.filter(p => p.active);          // Map<string, Plan>, keys kept
```

`contains`, a `List`'s `remove`, `indexOf` and `Set<T>` need `==` on the element type (section 19). Without it, they are a compile error.

The complete method list is specified with the standard library.

**`Iterable<T>` is anything a loop can read,** and it compiles to PHP's `iterable`. `List<T>` and `Set<T>` are `Iterable<T>`. A `Map<TKey, TValue>` is an `Iterable<[TKey, TValue]>` of its entries, and `values()` gives its values (section 17):

<!-- example iterables: statements
OrderReport report = new OrderReport();
Cart cart = new Cart();
-->
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

<!-- example yield: statements
OrderImport importer = new OrderImport([], new Order());
-->
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

<!-- example readonly-positions: members
private Customer owner = new Customer();
-->
```csharp
public void render(readonly Order order)          // the parameter
public readonly Customer customer => this.owner;  // the returned value
public Money totalIn(string currency) readonly    // this, inside the method
public readonly Customer buyer() readonly         // both
```

**Through a `readonly` value, only reading is allowed:**

- property `get` accessors
- methods marked `readonly`

Setters and unmarked methods are compile errors. **`readonly` holds only types PHP# checks,** so a plain PHP class under `readonly` is a compile error:

<!-- example readonly-values: members -->
```csharp
import Illuminate.Database.Eloquent.Model;

public class Order
{
    public string number { get; set; } = "";
    public Money totalIn(string currency) readonly { … }
    public void markPaid() { … }
}

public void render(readonly Order order)
{
    order.number;              // compiles: a get accessor
    order.totalIn("USD");      // compiles: totalIn is marked readonly
    order.markPaid();          // compile error: markPaid is not marked readonly
    order.number = "A-1";      // compile error: order is readonly
}

public void show(readonly Model model) { … }   // compile error: Model is a plain PHP class, which PHP# does not check
```

**Readonly reaches everything read through it.** A property read through a `readonly` value is also `readonly`, so `order.lines` is readonly when `order` is.

- `order.lines.add(line)` writes `lines` back through its `set` (section 12), so through a `readonly` `order` it is a compile error.
- A copy in a local belongs to the local. After `let copy = order.lines`, `copy.add(line)` is allowed and leaves `order` unchanged.
- The objects inside that copy stay readonly.

**A readonly value never goes where a writable one is expected.** Passing, returning or storing it is a compile error that names the `readonly` to declare:

<!-- example readonly-stays-readonly: members -->
```csharp
public Order give(readonly Order order) => order;   // compile error: declare the return type `readonly Order`
```

**A `readonly` method is checked as if `this` were a `readonly` value.** Inside it, `this` cannot be written, and only other `readonly` methods can be called on it. An override or an implementation of a `readonly` method is `readonly` too, so a call through a readonly value never runs a body that changes `this`:

<!-- example readonly-overrides -->
```csharp
// app/Shape.sharp
namespace App;

public class Shape
{
    public virtual int area() readonly => 0;
}
```

<!-- example readonly-overrides -->
```csharp
// app/Square.sharp
namespace App;

public class Square : Shape
{
    public override int area() => 1;   // compile error: Square.area overrides the readonly Shape.area, so declare it `area() readonly`
}
```

**A `readonly` that can't matter is a compile error,** as a `?` that can't matter is: before a type whose value never changes, an `int`, a `string`, an enum, a struct, a function type or a collection of them, and in `is` or `as`:

<!-- example readonly-cannot-matter: members -->
```csharp
public int count(readonly List<int> numbers) => 0;   // compile error: a List<int> never changes, so write List<int>
```

**A method's `readonly` is its last clause,** after `on` and `uses`, because it talks about the `this` inside the body. Anywhere else it is a compile error that says to write it last:

<!-- example readonly-last-clause -->
```csharp
// app/Quotes.sharp
namespace App;

public interface Quotes
{
    Money quote(Cart cart) uses Http readonly;   // compiles: readonly is the last clause
    Money price(Cart cart) readonly uses Http;   // compile error: write readonly last
}
```

**Operators, `int hash()` and `string toString()` are readonly by definition.** The language runs them on readonly values with no marker, so a body that changes an operand or `this` is a compile error, and an operator that returns an operand declares its return type `readonly`:

<!-- example readonly-by-definition -->
```csharp
// app/Points.sharp
namespace App;

public class Points
{
    public int count { get; set; } = 0;

    public static Points operator +(Points a, Points b)
    {
        a.count += b.count;                       // compile error: an operator's operands are readonly
        return new Points();
    }

    public static readonly Points operator -(Points a, Points b) => a;   // compiles: it returns a readonly operand

    public int hash() => this.count++;            // compile error: hash() is readonly
    public string toString() => "points";         // compiles
}
```

**A missing marker is a compile error that names the fix:**

```text
render cannot call order.totalIn: order is readonly.
totalIn does not change Order, so declare it `totalIn(string currency) readonly`.
```

**Fields, properties and classes use `{ get; }`, not `readonly`.** A get-only property already cannot be reassigned. `readonly` on a field, a promoted constructor parameter or a class is a compile error that names `{ get; }`:

<!-- example readonly-fields -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice
{
    private readonly Money total;                       // compile error: declare a get-only property with { get; }
}
```

<!-- example readonly-fields -->
```csharp
// app/Bill.sharp
namespace App;

public class Bill
{
    public Bill(public readonly Money due) { }          // compile error: declare a get-only property with { get; }
}
```

<!-- example readonly-fields -->
```csharp
// app/Quote.sharp
namespace App;

public class Quote
{
    public Quote(public Money due { get; }) { }         // compiles
}
```

<!-- example readonly-fields -->
```csharp
// app/Receipt.sharp
namespace App;

public readonly class Receipt { … }                     // compile error: declare its properties with { get; }
```

`readonly struct` is a compile error too, because every struct is already read-only (section 10).

## 14. Functions as values

### 14.1 Function types

A function type is written as `Function<ReturnType(ParameterTypes)>`, in the same order as a method declaration. One name covers every case, including functions that return `void`. Its effects go inside the brackets, after the parameter types, as in `Function<Charge(Cart) uses Http>` (section 29).

<!-- example function-types: members -->
```csharp
Function<Money?(readonly Line, string)> priceOf = (line, currency) => null
Function<void(Order)>? onPaid = null
public Function<bool(Order)> eligibleFor(Account account)
```

### 14.2 Lambdas

A lambda is written as a bare arrow. Parameter types are optional when the checker can infer them.

<!-- example lambdas: statements
List<Line> lines = [new Line()];
int minimum = 500;
-->
```csharp
lines.filter(l => !l.refunded);
const check = (Order order) => order.total >= minimum;
const price = (readonly Line line, string currency) => {
    if (line.free) { return Money.zero(currency); }
    return line.product.priceIn(currency);
};
```

A lambda captures the variables it uses from around it, with no `use` list. It captures the variable itself, not a copy, as C# does:

<!-- example capture-by-reference: statements -->
```csharp
let count = 0;
const increment = () => { count += 1; };
increment();   // count is now 1
```
<!-- run capture-by-reference
count;   // 1
-->

A lambda that calls `add`, `set` or `remove` on a captured variable changes the variable itself:

<!-- example capture-and-change: statements
List<int> numbers = [1, 2, 3];
-->
```csharp
List<int> seen = [];
numbers.filter(n => { seen.add(n); return true; });   // seen now holds every number
```
<!-- run capture-and-change
seen.count();   // 3
-->

### 14.3 Methods as values

A method named without parentheses is a function value. It is found when the code runs, so it works when the class lives in another file. PHP writes this as `Str::slug(...)`.

<!-- example method-values: statements
import Illuminate.Support.Str;
List<string> names = ["Spring Sale"];
-->
```csharp
names.map(Str.slug);
```

In a plain PHP class with both a property and a method of that name, the property wins, as in PHP.

**A field or property holding a `Function` is called like a method** when the class has no method with that name. A `.sharp` class cannot declare a method and a field or property with the same name, so `this.discount(…)` always has one meaning.

<!-- example function-fields: statements
int price = 900;
-->
```csharp
private Function<int(int)> discount = (int amount) => amount - 100;
int paid = this.discount(price);       // calls the function in discount, since there is no discount method
```

The field or property is read before the arguments run, as in Java, C#, TypeScript and Rust. An argument that replaces `discount` doesn't change which function runs.

### 14.4 Null operators

- **`a ?? b`:** `b` when `a` is null.
- **`a ??= b`:** assigns `b` only when `a` is null.
- **`a?.b`:** reads `b`, or gives null when `a` is null. PHP writes this as `?->`.
- **`f?.(x)`:** calls `f` only when it is not null.
- **`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`:** leave when `a` is null. `return` takes a value when the method returns one.

<!-- example leave-on-null: members
private Map<string, int> prices = [:];
private Map<int, Customer> customers = [:];
private void charge(Customer customer, int price) { }
-->
```csharp
public void renewAll(List<int> customerIds, string plan)
{
    int price = this.prices[plan] ?? return;                // no such plan: nothing to renew
    for (const id of customerIds) {
        Customer customer = this.customers[id] ?? continue; // skip ids with no customer
        this.charge(customer, price);
    }
}
```

**A `?` or a null check that cannot matter is a compile error,** because it misstates the code. Section 24 gives the rule for `?`.

- a null check, `?.` or `??` on a value whose type has no `?`
- a nullable return type on a method that never returns null

<!-- example needless-null-checks: members
private Customer customer = new Customer();
-->
```csharp
public void renew(Customer customer, Plan plan)
{
    if (customer != null) { … }                     // compile error: customer is Customer, so it can never be null
    int price = plan.price ?? 0;                     // compile error: plan.price is int, so ?? never applies
}
public Customer? current() { return this.customer; } // compile error: current never returns null, so its type is Customer
```

## 15. Events

An event says that something happened, such as an order being paid. `emit` raises it, and every listener for it runs. Nothing is wired by hand.

<!-- example event-declarations -->
```csharp
// app/OrderEvent.sharp
namespace App;

public interface OrderEvent { int orderId { get; } }                  // the type both events share
```

<!-- example event-declarations -->
```csharp
// app/OrderPaid.sharp
namespace App;

public event OrderPaid(int orderId, Money amount) : OrderEvent;       // declares the event type OrderPaid
```

<!-- example event-declarations -->
```csharp
// app/OrderRefunded.sharp
namespace App;

public event OrderRefunded(int orderId, Money amount) : OrderEvent;   // declares the event type OrderRefunded
```

<!-- example event-declarations -->
```csharp
// app/Order.sharp
namespace App;

public class Order
{
    public Order(public int id { get; }, public Money total { get; }) { }

    public void markPaid()
    {
        …
        emit new OrderPaid(this.id, this.total);                      // runs every listener for OrderPaid
    }
}
```

<!-- example event-declarations -->
```csharp
// app/Receipts.sharp
namespace App;

public class Receipts
{
    public void deliver(OrderPaid e) on OrderPaid { … }               // listens to every OrderPaid
}
```

**Declaring:** `event` declares an event type. `public event OrderPaid(int orderId, Money amount);` declares `OrderPaid`, a class whose parameters become get-only properties, as C#'s positional records and Kotlin's data classes do.

**No type nests.** Every type, events and interfaces included, is declared at file level in its own file (section 27). An interface that several events share, such as `OrderEvent`, is declared the same way. An event lives in the namespace of the code it describes:

<!-- example standalone-event -->
```csharp
// app/Imports/RowImported.sharp
namespace App.Imports;

public event RowImported(int row);
```

**Emitting:** `emit` raises an event. Any code may raise any event type:

<!-- example emit-forms: statements
public event OrderPaid(int orderId, Money amount);
Order order = new Order();
Money total = new Money(500, "USD");
-->
```csharp
emit new OrderPaid(order.id, total);             // runs every listener for OrderPaid
```

**`new Order.Paid(…)` always calls `Order`'s named constructor `Paid`** (section 9.1). `.` in code is always member access, and a class can't share its full name with a namespace (section 23), so a type `App.Order.Paid` can't exist beside the class `App.Order`. The named constructor is the only reading.

**Plain PHP events:** `extern event` marks a plain PHP class, such as a Laravel event, as an event, so PHP# code can listen to it:

<!-- example extern-event -->
```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Queue.Events.JobFailed;

extern event JobFailed;
```

**`extern event` states the class's effects after `uses`,** as every `extern` does (section 29). `extern event JobFailed;` names no effect, so it declares an event that is pure. With `uses`, a call into the event's class has the effect it names:

<!-- example extern-event-effects -->
```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Queue.Events.JobFailed;

extern event JobFailed uses Database;            // an event, and a call into JobFailed has the effect Database
```

**Listening:** a trailing `on` clause after a method's parameters lists the events the method listens to, for as long as the app runs. The method takes zero parameters, or one parameter whose type every listed event shares. It takes no other parameter, and no parameter is implicit. PHP# creates a new object for every event and passes its constructor's services from the module of its namespace. A listener's class needs no binding.

<!-- example listeners
import Illuminate.Queue.Events.JobFailed;
public interface OrderEvent { int orderId { get; } }
public event OrderPaid(int orderId, Money amount) : OrderEvent;
public event OrderRefunded(int orderId, Money amount) : OrderEvent;
-->
```csharp
public class OrderNotices
{
    public void send(OrderEvent e) on OrderPaid, OrderRefunded { … }      // two events, read through the type they share
    public void refresh() on OrderPaid { … }                              // no parameter: the event's data is not needed
    public void audit(OrderEvent e) on OrderEvent { … }                   // every event that implements OrderEvent
    public void alert(JobFailed e) on JobFailed { … }                     // a plain PHP event marked with extern event
}
```

- **A listener on an interface hears every event that implements it.**
- **Every `on` is checked before the code runs.** A wrong event name, or a parameter type the listed events don't share, is a compile error:

<!-- example listener-errors: members
public event OrderPaid(int orderId, Money amount);
public event OrderRefunded(int orderId, Money amount);
-->
```csharp
public void send(OrderPaid e) on OrderPaid, OrderRefunded { … }         // compile error: OrderRefunded is not an OrderPaid
public void refresh() on OrderPayed { … }                               // compile error: there is no event OrderPayed
public void log(OrderPaid e, Logger logger) on OrderPaid { … }          // compile error: a listener takes only the event
```

**Listening for a while:** `Events.on<RowImported>(e => …)` starts a listener and returns a handle. The listener stops when the handle goes out of scope:

<!-- example scoped-listener: members
public class ProgressBar { public void advance() { } }
public class Importer { public void run(string path) { } }
-->
```csharp
import App.Imports.RowImported;

public class ImportScreen
{
    public ImportScreen(private ProgressBar progress, private Importer importer) { }

    public void import(string path)
    {
        const listening = Events.on<RowImported>(e => this.progress.advance());   // listens until import returns
        this.importer.run(path);
    }
}
```

**Effects:** `emit` has the effect `Events` (section 29), so a method that emits shows `Events` among its effects, and code without a body allows it with `uses Events`.

**Timing:** the default dispatcher runs listeners immediately, before `emit` returns. An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries, or not at all in tests.

The app's top module binds `Dispatcher`, its binding serves every `emit`, and a binding of it in any other module of the app is a compile error. A test module still replaces it:

<!-- example one-dispatcher
namespace App;

import Sharp.Dispatcher;
import Sharp.ImmediateDispatcher;
-->
```csharp
// app/Module.sharp
module
{
    singleton Dispatcher = ImmediateDispatcher;   // compiles: the app's dispatcher, for every emit
}
```

<!-- example one-dispatcher
namespace App.Shop;

import Sharp.Dispatcher;
import Sharp.ImmediateDispatcher;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton Dispatcher = ImmediateDispatcher;   // compile error: only app/Module.sharp, the top module, binds Dispatcher
}
```

**A plain PHP library's own events reach PHP# listeners through PSR-14.** PHP#'s `Sharp.Psr.ListenerProvider` offers every PHP# listener as a PSR-14 listener provider. One module binding gives it to a library whose dispatcher follows PSR-14, as any binding creates a service (section 32):

<!-- example psr-listeners -->
```csharp
// app/Module.sharp
namespace App;

import Psr.EventDispatcher.EventDispatcherInterface;
import Sharp.Psr.ListenerProvider;
import Vendor.Events.EventDispatcher;

module
{
    singleton EventDispatcherInterface = new EventDispatcher(new ListenerProvider());   // compiles: the library's dispatcher runs every PHP# listener
}
```

- **Every event the library dispatches runs each `on` method and each listener `Events.on` started for it,** with nothing else written by hand. Each listener runs on a new object, as for `emit`, and the library's dispatcher decides when.
- **A library with a dispatcher of its own kind needs one adapter package,** and PHP# ships none for any framework.

## 16. Naming a value

Every place that names a value uses `name: value`:

- named arguments
- attribute arguments
- object initializers
- `with`

<!-- example naming-values: ignore an attribute with no declaration to attach to -->
```csharp
new Money { amount: 500, currency: "USD" }
price with { amount: 400 }
new Artifact.fromJson(json: raw)
[Field(label: "Name")]
```

When a variable has the same name as the property, the value can be left out:

<!-- example initializer-shorthand -->
```csharp
// app/Amount.sharp
namespace App;

public struct Amount
{
    public int value { get; init; }
    public string currency { get; init; }
}
```

<!-- example initializer-shorthand: statements
Account account = new Account();
-->
```csharp
const currency = account.currency;
Amount price = new Amount { value: 500, currency };     // short for currency: currency
```

**Any parameter can be named at a call,** including a call into plain PHP. An override keeps every parameter name of the method it overrides (section 22), so a name means the same parameter on every class.

A named argument that matches no parameter is a compile error, also on a method that takes any number of arguments (section 7).

<!-- example named-arguments: statements
Image image = new Image();
string url = "https://example.com/orders";
Map<string, string> payload = ["id": "1"];
-->
```csharp
import Illuminate.Support.Facades.Http;

image.resize(width: 800, height: 600);
Http.post(url, data: payload);           // plain PHP: Laravel's post(string $url, $data = [])
```

An object initializer runs after the constructor. It sets properties through their `set` or `init` accessors, so validation in those accessors runs. The checker requires every `required` property to be set.

## 17. Loops

`for … of` loops over a collection or any `Iterable<T>` (section 12), as in TypeScript. The loop variable is declared with `const` or `let`, and each pass gets a fresh variable (section 3).

<!-- example for-of: statements
List<Line> lines = [new Line()];
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
for (const line of lines) { … }
for (const [key, plan] of plans) { … }           // each entry of a Map is a [key, value] tuple
for (const entry of plans) { … }                 // entry is a [string, Plan] tuple
for (const [i, line] of lines.entries()) { … }
```

<!-- example map-values: statements
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
for (const plan of plans.values()) { … }         // a Map's values
```

**A loop over a `Map` gives each entry as a `[key, value]` tuple, and `values()` gives the values,** as Swift's, Kotlin's, Rust's and C#'s loops over a dictionary do. The key arrives as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type stays allowed (section 12).

**A `const` loop variable can have its type written,** as in `for (const [Status status, int n] of counts)`. `let` with a written type is a parse error, because a typed local is written without `let` (section 2). A written `string` key over a `Map<string, TValue>` reads back as a `string`.

<!-- example typed-loop-variables: statements
Map<Status, int> counts = [Status.Paid: 1];
List<Line> lines = [new Line()];
-->
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

**`?? continue` in a loop's own condition skips to the loop's next check,** as `continue` does anywhere in the loop (section 14.4). The condition runs again, so the body runs only for a value that is not null, and `?? break` ends the loop at the first null instead. A source that stays null keeps the loop running, as `while (true) { continue; }` does.

<!-- example continue-in-condition: statements in Feed
private int calls = 0;
private bool? next() { this.calls += 1; return this.calls % 3 == 0 ? null : this.calls < 7; }
let passes = 0;
-->
```csharp
while (this.next() ?? continue) { passes += 1; }                // a null runs the condition again
do { passes += 1; } while (this.next() ?? continue);
for (let i = 0; this.next() ?? continue; i++) { passes += 1; }  // a null runs i++, then the condition
passes;                                                          // 5
```

**The body of every `if`, `else`, `while`, `do` and `for` is written in braces,** as in Go, Rust and Swift, and as PSR-12 requires of PHP. A body without braces is a compile error:

<!-- example braces: statements
bool done = false;
-->
```csharp
if (done) { return; }           // compiles
if (done) return;               // compile error: write the body in braces, as in { return; }
while (!done) done = true;      // compile error: write the body in braces, as in { done = true; }
```

## 18. Strings

**Joining:** `+` joins strings. Joining a string with a number is a compile error, so `"1" + 1` cannot produce `"11"`. `+` in plain PHP files keeps its PHP meaning.

What `+` does is chosen from the operand types the checker sees, as C# and Kotlin resolve operators. Two strings join, two numbers add, and an object with `operator +` (section 19) calls it.

<!-- example string-joining: statements
Order order = new Order();
-->
```csharp
const label = "Order " + order.number;
```

**Interpolation:** backtick templates with `${expr}` interpolate, as in TypeScript. They can span several lines, so they replace heredoc and nowdoc.

<!-- example templates: statements
Order order = new Order();
Contact contact = new Contact();
-->
```csharp
const label = `Order ${order.number} for ${contact.name}`;
const html = `
    <div class="order">${order.number}</div>
`;
```

A template's `${expr}` accepts `int`, `float`, `string`, `bool`, and any type that declares `string toString()`, as Rust's `format!` takes any `Display` type. A `bool` prints `true` or `false`, and any other type prints what its `toString()` returns. Every other type is a compile error, "A template shows `int`, `float`, `string`, `bool` or a type that declares `toString()`, and `Status` is none of them.", with the offending type's name in place of `Status`. A literal or narrowed type counts as its base type, so `1|2` is an `int`. A union of two of them, such as `int|string`, is none of them and is refused. A nullable value such as `int?` is refused until it is checked (section 24), and the same error names `int?`.

<!-- example template-types: statements
int count = 3;
bool isPaid = true;
Status status = Status.Paid;
List<int> items = [1, 2];
Order order = new Order();
-->
```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // bool: "Paid: true"
const c = `Status: ${status}`; // compile error: Status declares no toString()
const d = `Items: ${items}`;   // compile error: List<int> declares no toString()
const e = `Order: ${order}`;   // compile error: Order declares no toString()
```

<!-- example template-to-string -->
```csharp
// app/Sku.sharp
namespace App;

public class Sku
{
    public Sku(public string code { get; }) { }
    public string toString() => this.code;
}
```

<!-- example template-to-string: statements -->
```csharp
const sku = new Sku("A-12");
const label = `Sku: ${sku}`;   // Sku declares toString(): "Sku: A-12"
```

A plain PHP file keeps PHP's own interpolation.

**Plain strings:** `"…"` and `'…'` never interpolate, so braces and `$` inside them are literal.

PHP's backtick shell execution is removed. `shell_exec()` stays.

## 19. Equality, comparison and operators

**`==` exists only where the type has it:**

- **Strings, numbers, enums and class values** (section 25) compare by value with `==`, with no code.
- **A struct or a collection gets `==` with no code when every field or element has `==`,** and it compares each field or element with that one's own `==`, as Swift, Rust and Go do. A struct with a field that has no `==` has no `==` either.
- **Any type may declare `operator ==` with `hash()`,** a struct as well as a class, and the declared one replaces the one a struct gets with no code.
- **A class** compares with `==` only if it or a parent declares `operator ==`. Otherwise `==` on it is a compile error.
- **A function has no `==`,** so `==` between two function values is a compile error.
- **`Any?`** compared with `==` against a struct, enum, string, number or collection compares by value, and is false when the types differ.
- **`===`** is true only for the same object. It works on classes only, and cannot be overridden.
- **`Set`** hashes a struct, enum, string, number or collection by its value, and an object by its class's `hash()`. A struct that declares `hash()` is hashed by it.

**Operators** are declared once on a type and inherited by its subclasses:

<!-- example operators -->
```csharp
// app/DatabaseEntity.sharp
namespace App;

public abstract class DatabaseEntity
{
    public int id { get; }
    public DatabaseEntity(int id) { this.id = id; }
    public static bool operator ==(DatabaseEntity a, DatabaseEntity b) => a.id == b.id;
    public int hash() => this.id;
}
```

<!-- example operators -->
```csharp
// app/Money.sharp
namespace App;

public class Money
{
    public static bool operator ==(Money a, Money b) { … }   // a and b are never null
    public int hash() { … }
    public static int operator <=>(Money a, Money b) { … }
    public static Money operator +(Money a, Money b) { … }
}
```

<!-- example operators: statements
Order order = new Order(1);
Order sameOrderLoadedAgain = new Order(1);
Cart cart = new Cart();
Cart otherCart = new Cart();
-->
```csharp
order == sameOrderLoadedAgain;   // Order inherits DatabaseEntity's ==, so this compares ids
cart == otherCart;               // compile error: Cart declares no ==
cart === cart;                   // true: the same object
```

A struct declares `==` the same way, and replaces the one it gets with no code:

<!-- example struct-equality -->
```csharp
// app/Email.sharp
namespace App;

public struct Email
{
    public string address { get; init; }
    public static bool operator ==(Email a, Email b) => a.address.trim() == b.address.trim();
    public int hash() { … }
}
```

<!-- example struct-equality: statements -->
```csharp
Email typed = new Email { address: "ada@example.com " };
Email stored = new Email { address: "ada@example.com" };
typed == stored;                 // true: Email's own == ignores the trailing space
const twice = (int x) => x * 2;
twice == twice;                  // compile error: a function has no ==
```

- **Overloadable operators:** `+ - * / % **`, unary `-`, `==` and `<=>`. No other operator can be overloaded.
- **`!=`** is derived from `==`.
- **`< > <= >=` and sorting** are derived from `<=>`.
- **Strings compare byte by byte,** as in Go. `<`, `>` and `sort` put `"10"` before `"9"` and capitals before lowercase, so `"10" < "9"` is true and `"Zebra" < "apple"` is true. The order agrees with `==`. `compareTo` gives -1, 0 or 1 in the same order, so `"b".compareTo("a")` is 1.
- **`==` and `hash()` go together:** declaring `==` without `hash()` is a compile error, because `Set` needs both.

**`==` and `!=` on a nullable type are lifted,** as C#'s operators on nullable values and Kotlin's `==` are. Null equals only null. Two non-null values use the type's own `==`, including a declared `operator ==`, so an `operator ==` takes two non-null values and never sees a null. `x != null` is the normal null check, and it never runs user code. `x is null` and `x is not null` stay valid as ordinary patterns (section 21).

<!-- example lifted-equality: statements
Money total = new Money(500, "USD");
-->
```csharp
Money? price = null;
price == null;                   // true, without running Money's ==
price == Money.zero("USD");      // false, without running Money's ==
total == Money.zero("USD");      // false: total is a Money, so this runs Money's ==
if (price != null) { … }         // the normal null check
if (price is not null) { … }     // compiles: an ordinary pattern
```

**Bitwise operators** `|`, `&`, `^`, `~`, `<<` and `>>`, and their compound forms such as `|=`, take `int` only. Flags are ints joined with `|`:

<!-- example bitwise: statements
bool isAdmin = true;
bool isOwner = false;
int count = -1;
-->
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
| `? :` | right | **differs:** PHP refuses nesting without parentheses (section 21) |
| `=`, `+=`, `-=`, `*=`, `/=`, `%=`, `**=`, `??=`, `&=`, `\|=`, `^=`, `<<=`, `>>=` | right | same, without `.=` |
| lambda `=>`, `throw` | right | the lambda is PHP#'s. `throw` is as in PHP 8 |

PHP's `and`, `xor` and `or` do not exist (section 21).

**Interface names** have no `I` prefix and no `Interface` suffix: `Comparable`, `Linkable`.

## 20. Enums

An enum can give each case a raw value, as Swift's raw-value enums do:

<!-- example backed-enum -->
```csharp
public enum Retry : string
{
    case None = "none";
    case ExponentialDays = "days";
}
```

The header lists the backing type and the interfaces after `:`, as a class header does (section 22). A leading `int` or `string` is the backing type, and every name after it is an interface:

<!-- example enum-interfaces -->
```csharp
public enum Status : string, HasLabel
{
    case Active = "a";
}
```

An enum case can also carry data. This is a closed set, and every case is declared in one block:

<!-- example enum-data-cases
public class Example
{
    public PaymentResult decline()
    {
        …
    }
}
-->
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

- **Backed:** every case has a value, as in `case None = "none"`, and no case carries data.
- **Not backed:** any mix of plain cases and cases that carry data.

<!-- example enum-shapes -->
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
- **Two cases are `==`** when they are the same case and each field is `==`, as two structs are (section 19). A case with a field that has no `==` gives its enum no `==`.
- **At runtime,** each case is a real class, so Reflection sees it.
- **The checker** rejects a `match` over the enum that misses a case.

### 20.1 Sealed interfaces

A `sealed interface` is a closed set of classes. Its implementers must sit in the same namespace, and so in the same folder. The checker gathers the set from that namespace, so there is no list to maintain.

<!-- example sealed-interfaces -->
```csharp
// app/Interaction.sharp
namespace App;

public sealed interface Interaction { … }
```

<!-- example sealed-interfaces -->
```csharp
// app/PopupInteraction.sharp
namespace App;

public class PopupInteraction : Interaction { … }      // same namespace: allowed
```

- **Outside the namespace,** implementing it is a compile error.
- **A `match` over it** needs no `default`, as long as it handles every implementer.
- **Adding an implementer** makes every `match` that does not handle it a compile error.

Use an enum when the cases are small data. Use a sealed interface when each case is a full class with its own methods.

## 21. Pattern matching

`match` is the only branching construct on a value. `switch` is removed. `match` works both as an expression and, with block arms, as a statement. It compares strictly and never falls through.

<!-- example match-forms: statements
import Illuminate.Support.Facades.Log;
PaymentResult result = PaymentResult.Declined(reason: "card expired");
Order order = new Order();
private void deliver(Order order) { }
-->
```csharp
const message = match (result) {
    PaymentResult.Paid(string transactionId) when order.isTest => `Test payment ${transactionId}`,
    PaymentResult.Paid(string transactionId) => `Paid ${transactionId}`,
    PaymentResult.Declined d => `Declined: ${d.reason}`,
    PaymentResult.RequiresAction(string redirect) => `Continue at ${redirect}`,
};

match (result) {
    PaymentResult.Paid(string transactionId) => {
        this.deliver(order);
        Log.info(transactionId);
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

<!-- example match-coverage: statements
Status status = Status.Active;
int n = 3;
-->
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
| constant | `200 =>`, `Status.Paid =>` |
| comparison | `< 1000 =>`, `>= 1000 and < 10000 =>` |
| case unpacked by position | `PaymentResult.Paid(string transactionId) =>` |
| whole case, named | `PaymentResult.Paid p =>` |
| properties | `{ status: 200, body: string body } =>` |
| list | `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>`, `[...List<Line> before, Line last] =>`, `[Line first, ..., Line last] =>` |

- **A pattern takes types and constants only,** such as `Circle`, `200` or `Status.Paid`, as in C#. A local's value is compared in `when`.
- **A list pattern has at most one rest,** anywhere among its elements. `...List<Line> rest` takes the elements the other patterns leave, and a bare `...` skips them. A second rest is a compile error.
- **A pattern creates a variable** only through a typed declaration, such as `string transactionId`.
- **`and`, `or` and `not`** combine patterns. They exist only inside patterns.
- **`when` adds a condition** to an arm. The condition is an ordinary expression.

<!-- example bare-name-patterns: statements
Any? value = 10;
-->
```csharp
const limit = 10;
const label = match (value) {          // value is Any?
    int n when n == limit => "at the limit",   // compares value with the local limit's value, 10, in when
    Circle => "a circle",                      // a bare name in a pattern is a type
    default => "other",
};
```

<!-- example pattern-locals: statements
Any? value = 10;
-->
```csharp
const limit = 10;
const label = match (value) {
    limit => "at the limit",                   // compile error: limit is a local; compare its value in when
    default => "other",
};
```

**A property pattern matches only the properties it lists,** as in C#. It can nest, and it can test the type at the same time:

<!-- example property-patterns: statements
Response response = new Response();
Any? payment = new Paid();
-->
```csharp
if (response is { status: 200 }) { … }                          // ignores headers, body and the rest
if (response is { status: 200, body: { type: "json" } }) { … }  // nests into a property that is itself an object
if (payment is Paid { amount: > 1000 } big) { … }               // tests the type and one property, and binds big
```

**`is` and `as`:**

<!-- example is-and-as: statements alone 3-4
PaymentResult result = PaymentResult.Declined(reason: "card expired");
-->
```csharp
if (result is PaymentResult.Paid p) { … }
if (result is PaymentResult.Paid(string transactionId)) { … }
const paid = result as PaymentResult.Paid;                    // PaymentResult.Paid?, null if it is not one
const paid = result as PaymentResult.Paid ?? throw new NotPaid(result);
```

- **`is`** tests a value against any pattern, and creates the pattern's variables when it matches.
- **Narrowing:** after `is` without a name, a local variable or parameter counts as the tested type for the rest of the block. Assigning to it inside the block ends the narrowing. Properties are not narrowed, because they could change between the test and the use. To use a property's tested value, bind it to a name, as in `is int t`.

<!-- example narrowing: statements
Any? entity = new Page();
private void render(string key) { }
-->
```csharp
if (entity is HasDesign) {
    this.render(entity.designKey());  // entity counts as HasDesign here
}
```

<!-- example property-narrowing: statements
Tab order = new Tab();
-->
```csharp
if (order.total is int) { order.total + 1; }    // compile error: order.total may have changed since the test
if (order.total is int t) { let next = t + 1; } // compiles: t holds the value that was tested
```

- **A variable that `is` creates exists only where the test held:** inside the `if` for `is`, and after an `if` whose block always exits for `is not`, as in C#. A block always exits when every path through it ends in `return`, `throw`, `break` or `continue`.

<!-- example pattern-scope: statements
Any? shape = new Circle();
float area = 0.0;
-->
```csharp
if (shape is Circle circle) {
    area = circle.radius;              // compiles: here shape is a Circle
}
circle.radius;                         // compile error: `circle` exists only where `shape is Circle circle` is true
```

<!-- example is-not-scope: members
private Map<string, int> prices = [:];
private Receipt charge(int orderId, int price) => new Receipt();
-->
```csharp
public Receipt checkout(Map<string, Any?> payload, string plan)
{
    if (payload["orderId"] is not int orderId) { throw new BadPayload("orderId"); }
    if (this.prices[plan] is not int price) { return Receipt.unknownPlan(plan); }
    return this.charge(orderId, price);                     // orderId and price are both known here
}
```

- **A negative test is written `is not`.** `is` binds with the comparisons, as in C# (section 19), so `!entity is HasDesign` reads as `(!entity) is HasDesign`. That is a compile error, because `!` takes a `bool`.

<!-- example negative-tests: statements
Any? entity = new Page();
-->
```csharp
if (entity is not HasDesign) { … }       // compiles
if (!entity is HasDesign) { … }          // compile error: write entity is not HasDesign
```

- **In a pattern, `not` beside `or` needs parentheses,** in `is` and in `match` arms alike. C# reads `not Paid or Refunded` as `(not Paid) or Refunded`, which already matches a `Refunded` result, so the `or Refunded` does nothing. `not` beside `and` reads the way it binds, so it needs none.

<!-- example not-or-parentheses: statements
Payment result = new Paid();
string? code = "A-1";
-->
```csharp
if (result is not Paid or Refunded) { … }      // compile error: write not (Paid or Refunded), or (not Paid) or Refunded
if (result is not (Paid or Refunded)) { … }    // compiles: neither Paid nor Refunded
if (code is not null and not "") { … }         // compiles: (not null) and (not "")
```

- **`as`** converts a value to a type, or gives null.
- **`as` to a collection type checks every element,** wherever the value came from, and gives null if any element is wrong. So `as List<string> ?? throw …` throws on a wrong element.
- **`x is int?` is a compile error,** because `int?` also matches null. Write `x is int`, or `x == null`.
- **A pattern that can never match is a compile error.**

<!-- example impossible-patterns: statements
Any? x = 1;
private Circle next() => new Circle();
-->
```csharp
if (x is int?) { … }                   // compile error: int? matches null too, so write x is int or x == null
Circle circle = this.next();
if (circle is Square) { … }            // compile error: This pattern never matches the value it tests.
```

**Every type answers `is`, `as` and `match` the same way, function types included,** as in C#, Swift and Go. `value is Function<int(int)>` tests the function's parameter and result types while the code runs, as `value is List<int>` tests the list's elements. A test whose type the value's source never declares is a compile error that says the type is unknown, such as a function-type test on a `Closure` that a plain PHP library returns with no parameter or result types:

<!-- example type-tests -->
```php
class Legacy
{
    public static function doubler(): \Closure { return fn($x) => $x * 2; }
}
```

<!-- example type-tests: statements -->
```csharp
Any? doubled = (int x) => x * 2;
doubled is Function<int(int)>;               // true
doubled is Function<string(string)>;         // false: the parameter and result types differ
Legacy.doubler() is Function<int(int)>;      // compile error: the type of Legacy.doubler()'s function is unknown
```

**Boolean operators:** `&&`, `||` and `!` exist only in expressions. PHP's `and`, `or` and `xor` operators are removed, so `=` can no longer bind before `and`.

**Conditions are `bool`.** `if`, `while`, `do … while`, `for`, `? :`, `&&`, `||`, `!` and `match`'s `when` take a `bool`. Any other type is a compile error, so PHP's truthiness never applies:

<!-- example bool-conditions: statements
List<int> items = [1];
-->
```csharp
if (items.count()) { … }        // compile error: int is not bool
if (items.count() > 0) { … }
```

**The ternary** `c ? a : b` gives `a` when `c` is true, and `b` otherwise.

- PHP's two-operand `a ?: b` does not exist. `??` covers null (section 14.4).
- `a ? b : c ? d : e` means `a ? b : (c ? d : e)`, as in C#, Java, TypeScript, Swift and Dart.

## 22. Inheritance

The class header lists the base class and interfaces after `:`. The checker knows which name is the class.

<!-- example class-header -->
```csharp
public class Page : DatabaseEntity, Linkable, Shareable { … }
```

**Methods of a PHP# class are closed unless the class opens them** with `virtual`, or by declaring them `abstract`. A subclass replaces a method only with a required `override`:

<!-- example virtual-methods -->
```csharp
// app/DatabaseEntity.sharp
namespace App;

public abstract class DatabaseEntity
{
    public virtual string label() { … }      // subclasses may replace it
    public void save() { … }                 // nobody may replace it
}
```

<!-- example virtual-methods -->
```csharp
// app/Page.sharp
namespace App;

public class Page : DatabaseEntity
{
    public override string label() { … }
}
```

**Methods of a plain PHP class are open unless PHP marks them `final`.** PHP has no `virtual`, so PHP's own rule decides. Replacing one still needs `override`:

<!-- example plain-php-overrides -->
```php
abstract class Report
{
    abstract protected function render(): string;
    public function title(): string { return 'Report'; }
    final public function id(): string { … }
}
```

<!-- example plain-php-overrides: alone 4-5 -->
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

<!-- example override-parameter-names -->
```csharp
// app/Image.sharp
namespace App;

public class Image
{
    public virtual void resize(int width, int height) { … }
}
```

<!-- example override-parameter-names -->
```csharp
// app/Thumbnail.sharp
namespace App;

public class Thumbnail : Image
{
    public override void resize(int w, int h) { … }   // compile error: w renames width, h renames height
}
```

**Classes are open** unless marked `final`. `final override` stops an override chain at that class.

**`super`** calls the parent's version:

<!-- example super-calls
public class Page : DatabaseEntity
{
    …
}
-->
```csharp
public override string label() => super.label() + " (page)";
```

**An interface can give a method or a computed property a default body.** PHP# declares no traits:

<!-- example interface-defaults -->
```csharp
// app/HasDesign.sharp
namespace App;

public interface HasDesign
{
    List<string> claims { get; set; }                       // abstract: each class declares its storage
    string designColumn { get; }                            // abstract
    string designKey() => `design:${this.designColumn}`;    // compiles: a default body
    string designLabel => `${this.designColumn} design`;    // compiles: a computed property's default body
    string designId() => `design:${this.id}`;               // compile error: a default body sees only HasDesign's members, and id is not one
}
```

<!-- example interface-defaults -->
```csharp
// app/Page.sharp
namespace App;

public class Page : DatabaseEntity, HasDesign
{
    public List<string> claims { get; set; } = [];          // the storage lives in the class that owns it
    public string designColumn => "design";
}
```

<!-- example interface-defaults: statements
Page page = new Page();
-->
```csharp
page.designKey();                                           // "design:design", from the default body
page.designLabel;                                           // "design design", from the default body
```

- A default body sees only the interface's own members.
- Every field is declared in the class that owns it. An interface holds no fields.
- A computed property's default body runs on each read, so it takes no `static` and no `lazy`. An enum has no property, so an enum that implements an interface with one is a compile error.
- A PHP# interface with default bodies compiles to a PHP interface plus a PHP trait named `<Interface>\Defaults` that holds the default bodies, as Kotlin nests `DefaultImpls` in an interface. A plain PHP class that implements it must also `use` that trait to get the defaults. PHP# code never names the trait, so section 23's rule that a class cannot share its full name with a namespace does not apply to it.

**A class has one method per name, whatever it inherits** (section 7). When two places give a class a body for the same method, the class writes the method itself, as Kotlin requires. The places are two interfaces' default bodies, a default body and a plain PHP trait, two header traits, or a default body and the base class. Until the class writes it, the class is a compile error that names both places. Inside its own methods, the class runs one interface's default body with `super<Interface>`:

<!-- example one-body-per-method -->
```csharp
// app/Tickets.sharp
namespace App;

public interface ShowsLabel
{
    string label() => "label";
}

public interface ShowsTitle
{
    string label() => "title";
}

public class Ticket : ShowsLabel, ShowsTitle { }        // compile error: Ticket gets label from both ShowsLabel and ShowsTitle, so Ticket writes label

public class Sticker : ShowsLabel, ShowsTitle
{
    public override string label() => super<ShowsLabel>.label() + "/" + super<ShowsTitle>.label();
}

public class Stray : ShowsTitle
{
    public string name() => super<ShowsLabel>.label();   // compile error: Stray does not implement ShowsLabel
}
```
<!-- run one-body-per-method
new Sticker().label();   // "label/title"
-->

- `super<Interface>` names an interface the class implements, and is written only in the class's own methods.
- Replacing a default body takes `override`, as replacing a base class's method does. Implementing an interface method that has no default body takes none.
- A header trait replaces the base class's method, as PHP's `use` does, so the two count as one place.

A sub-interface replaces its parent's default body with `override`, and the most specific interface's body wins, as in Java, C# and Kotlin:

<!-- example sub-interface-defaults -->
```csharp
// app/Shows.sharp
namespace App;

public interface Shows
{
    string label() => "shows";
}

public interface ShowsMore : Shows
{
    override string label() => "more";             // compiles: replaces the default body of Shows
}

public interface ShowsLess : Shows
{
    string label() => "less";                     // compile error: replaces the default body of Shows, so it writes override
}

public class Fancy : ShowsMore { }                // gets label from ShowsMore
```
<!-- run sub-interface-defaults
new Fancy().label();   // "more"
-->

<!-- example php-trait-defaults -->
```php
use App\Design\HasDesign;

class LegacyPage implements HasDesign
{
    use HasDesign\Defaults;                                 // gets designKey() from HasDesign's default body

    public array $claims = [];
    public string $designColumn = "design";

    public string $designLabel {                            // runs designLabel's default body
        get => $this->{'designLabel@App\Design\HasDesign'}();
    }
}
```

**A plain PHP trait** is listed in the header with the base class and interfaces, and it is also a type. It works everywhere an interface does:

<!-- example plain-php-traits -->
```csharp
// app/Order.sharp
namespace App;

import Illuminate.Database.Eloquent.Model;
import Illuminate.Database.Eloquent.Factories.HasFactory;

public class Order : Model, HasFactory { … }
```

<!-- example plain-php-traits -->
```csharp
// app/Draft.sharp
namespace App;

import App.Legacy.Copies;

public class Draft : Copies { … }               // compiles: a trait may stand alone in the header, as an interface may
```

<!-- example plain-php-traits: statements
Any? entity = new LegacyEntity();
-->
```csharp
import Illuminate.Database.Eloquent.Factories.HasFactory;

if (entity is HasFactory f) { … }
public void seed(HasFactory owner) { … }
List<HasFactory> owners = [];
```

Reflection lists a class's traits, just as it lists the class's interfaces.

## 23. Namespaces and imports

Namespace parts are separated with `.`, and imports use `import`. Every part of a `namespace` line starts with a capital letter, as every type but the built-in ones does (section 24), so `namespace App.store;` is a compile error and `namespace App.Store;` compiles.

<!-- example namespace-and-import -->
```csharp
namespace App.Tenant.Store;
import App.Shared.Schema.Entities.DatabaseEntity;
```

**Full names appear only in `namespace` and `import` lines.** Code uses the short imported name, so `.` in code is always member access. The last part of an import is always a class, or the namespace of a plain PHP library's functions and constants. A full class name inside code is a compile error that names the import to add. A module header names a namespace, as the `namespace` line does, so `module : App.Shop` compiles (section 32).

**A plain PHP library's function is called through its namespace, as a member,** as Go calls a package's functions, as Python does after `import pdf`, as Java calls Kotlin's top-level functions and as C# calls F# modules. After `import Vendor.Pdf;`, the call is `Pdf.render(html)`. A function in PHP's root namespace is called as `global.now()`, as C#'s `global::` names the root. Importing a single function, or calling a function by its bare name, is a compile error, so every call outside the current class reads `Name.member(…)`:

<!-- example library-functions -->
```php
namespace Vendor\Pdf;

function render(string $html): string { return $html; }
```

<!-- example library-functions -->
```csharp
// app/Invoices/Printer.sharp
namespace App.Invoices;

import Vendor.Pdf;

public class Printer
{
    public string print(string html) => Pdf.render(html);     // compiles: the function is called through its namespace
    public string draft(string html) => render(html);         // compile error: call it through its namespace, as in Pdf.render(html)
}
```

<!-- example library-functions -->
```csharp
// app/Invoices/Draft.sharp
namespace App.Invoices;

import Vendor.Pdf.render;                                     // compile error: import the namespace Vendor.Pdf, and call Pdf.render

public class Draft { }
```

**A plain PHP constant is read through its namespace the same way.** `global.PHP_EOL` reads PHP's constant, and `Pdf.DEFAULT_DPI` reads the library's after `import Vendor.Pdf;`. A bare uppercase name is always the code's own constant, so a bare PHP constant such as `PHP_EOL` is a compile error that says to write `global.PHP_EOL`:

<!-- example library-constants -->
```php
namespace Vendor\Pdf;

const DEFAULT_DPI = 300;
```

<!-- example library-constants -->
```csharp
// app/Invoices/Layout.sharp
namespace App.Invoices;

import Vendor.Pdf;

public class Layout
{
    public string line(string text) => text + global.PHP_EOL;     // compiles: PHP's constant is read through global
    public int largest() => global.PHP_INT_MAX;                   // compiles
    public int dpi() => Pdf.DEFAULT_DPI;                          // compiles: the library's constant is read through its namespace
    public string bare(string text) => text + PHP_EOL;            // compile error: write global.PHP_EOL
}
```

**An import never carries `uses`,** because a library's effect lives in its one `extern` declaration (section 29).

**The standard library lives under one root, `Sharp`,** with the standard library namespaces `Sharp.Text`, `Sharp.Math`, `Sharp.Json`, `Sharp.IO`, `Sharp.Time`, `Sharp.Net` and `Sharp.Data`, as .NET has `System.*` and Rust has `std::*`.

**Only `Sharp` itself is imported by default,** together with the standard library's extensions on `string`, `int`, `float`, `List`, `Map` and `Set`, as Kotlin imports `kotlin.*`, `kotlin.text` and `kotlin.collections`. A bare `Int` is `Sharp.Int`, a bare `Key` is the standard attribute, and `name.trim()` needs no import. A class the file declares or imports, or a class in the file's own namespace, shadows the default one, as in Kotlin:

<!-- example own-namespace-shadows -->
```csharp
// app/Shop/Environment.sharp
namespace App.Shop;

public class Environment
{
    public static string stage() => "production";
}
```

<!-- example own-namespace-shadows -->
```csharp
// app/Shop/Release.sharp
namespace App.Shop;

public class Release
{
    public string stage() => Environment.stage();                   // compiles: Environment is App.Shop.Environment, with no import
    public string? region(Environment environment) => environment.variable("AWS_REGION");   // compile error: App.Shop.Environment declares no variable
}
```

**A static class in a standard library namespace needs one import line,** and the import's last part is the class, as for any class:

<!-- example standard-library-import: statements
Map<string, Any> payload = ["id": 1];
int a = 1;
int b = 2;
-->
```csharp
import Sharp.Json.Json;

Json.encode(payload);     // compiles
Math.max(a, b);           // compile error: Math is not imported
```

**`import X.Y as Z;` renames an import in this file only.** It compiles to PHP's `use X\Y as Z;`. Here the rename keeps the standard `Key`, which `import Cache.Key;` would shadow:

<!-- example import-rename -->
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

<!-- example naming-cases -->
```csharp
// app/Billing/Status.sharp
namespace App.Billing;                                         // namespace

public enum Status : string { case Paid = "paid"; }            // type and enum case
```

<!-- example naming-cases -->
```csharp
// app/Billing/Invoice.sharp
namespace App.Billing;

public class Invoice<TLine : Line>                             // type and type parameter
{
    const MAX_LINES = 100;                                     // constant
    List<TLine> pendingLines = [];                             // field
    public string dueDate { get; set; } = "";                   // property

    public Money totalIn(string currency)                      // method and parameter
    {
        const lineCount = this.pendingLines.count();           // local
        …
    }
}
```

<!-- example naming-cases -->
```csharp
// app/Billing/InvoicePaid.sharp
namespace App.Billing;

public event InvoicePaid(int invoiceId, Money amount);         // event
```

A name that breaks the rule is a warning, as in "Method name `TotalIn` should be in camel case."

**A type holds null only when it is written with `?`,** for parameters, return types, properties and locals alike: `Customer?` may hold null, and `Customer` never does. Section 14.4 lists the compile errors for a `?` or a null check that cannot matter.

A `null` default needs the `?` too, for every type, on a field, a property or a parameter:

<!-- example null-defaults: members -->
```csharp
private string name = null;              // compile error: write string? name = null
private string? nickname = null;         // compiles
public void tag(string label = null)     // compile error: write string? label = null
```

**A union type is written inline,** such as `int|string`, anywhere a type goes. It compiles to PHP's own union type.

<!-- example union-types: members -->
```csharp
public User find(int|string id)
{
    if (id is int) { return User.byNumber(id); }
    return User.bySlug(id);   // id is a string here
}
```

- `is` narrows a union (section 21). After a branch that tested one type and returned, the code below it sees the types left.
- `List`, `Map` and `Set` can't share a union, because they share PHP's one array kind and the literal rules need to know which one a place holds.
- Alternatives that belong to the domain stay enums (section 20) and sealed interfaces (section 20.1).

**A union that holds null is written `(int|string)?`,** with the same `?` as any other type. `int|string|null` is a compile error that names `(int|string)?`.

<!-- example nullable-unions: members -->
```csharp
public (int|string)? find((int|string)? id)   // compiles
public int|string|null find2()                 // compile error: write (int|string)?
```

**Integer overflow throws `ArithmeticError`** at the operation that overflows, as in Swift and C#'s `checked`. PHP's silent change to `float` does not happen in PHP# code. A shift is not an overflow: `<<` drops the bits it shifts out, and a shift by a negative count throws `ArithmeticError` (section 19).

- `int ** int` is an `int`. A negative exponent throws `ArithmeticError`, as in Rust, where `i64::pow` takes no negative exponent, and C#'s `BigInteger.Pow`. A `float` on either side gives a `float`: `2.0 ** -1` is `0.5`.

<!-- example int-power: statements
int side = 3;
int a = 2;
int b = 3;
-->
```csharp
let area = side ** 2;        // int
2 ** -1                      // compile error: `2 ** -1` is an `int` raised to a negative power, which throws: write `2.0 ** -1`
a ** b                       // int. Throws ArithmeticError at runtime when b < 0
2.0 ** -1                    // 0.5
```

**`/` on two integers truncates toward zero,** as in C#. `/` with a `float` operand stays float division.

<!-- example integer-division: statements -->
```csharp
7 / 2          // 3
-7 / 2         // -3
7 / 2.0        // 3.5
```

- Division by zero throws `DivisionByZeroError`.
- `global.PHP_INT_MIN / -1` throws `ArithmeticError`, because the result overflows.
- `/=` follows the same rules.
- Like `+` (section 18), what `/` does is chosen from the operand types the checker sees.

**An `int` where a `float` is expected is a compile error in PHP# code,** wherever a value lands, such as an assignment, an argument, a return, a property write, a list or map element, or a generic `T` that is `float`, as in `Cell<float>`.

<!-- example int-to-float: statements alone
int count = 3;
int n = 2;
-->
```csharp
float total = 0;                     // compile error: `int` is not `float`: write 0.0
float total = 0.0;                   // compiles
float price = count;                 // compile error: `int` is not `float`: convert with (float) count
float price = (float) count;         // compiles: the conversion is written out
Cell<float> cell = Cell.empty();
cell.put(2);                         // compile error: `int` is not `float`: write 2.0
cell.put(2.0);                       // compiles
cell.put((float) n);                 // compiles
bool same = cell.get() == 2.0;       // true
```

- A literal `0` is an `int`, as in Kotlin.
- Arithmetic that mixes an `int` and a `float`, such as `count * 1.5`, gives a `float` and compiles.
- An `int` from plain PHP becomes a `float` wherever PHP# expects a `float`, such as a parameter, a generic `T` that is `float`, the elements of a `List<float>` or a `Set<float>`, and the values of a `Map<K, float>`. PHP# code then sees what its type says, so `cell.get() == 2.0` is true.

<!-- example plain-php-float-arguments: statements
$price = new App\Price();
$cell = App\Cell::empty();
$report = new App\Report();
-->
```php
<?php declare(strict_types=1);

$price->set(5);              // PHP# set(float value): value is 5.0
$cell->put(2);               // PHP# Cell<float>: the cell holds 2.0
$report->total([1, 2, 3]);   // PHP# total(List<float> amounts): amounts is [1.0, 2.0, 3.0]
```

**Converting values:**

<!-- example conversions: statements
import Illuminate.Http.Request;
Request request = new Request();
float price = 9.99;
Any? user = new User();
-->
```csharp
string text = request.input("qty") as string ?? ""; // an Any? is checked as a string first
const qty = Int.parse(text);                         // int, throws on "abc" and on "12abc"
const maybe = Int.tryParse(text);                    // int?, null on "abc"
const cents = (int)(price * 100);                    // truncates toward zero
const admin = user as Admin;                         // Admin?, null if user is not one
const flag = request.input("flag") == "1";           // replaces (bool)
```

- `(int)` and `(float)` convert between `int` and `float`. `(string)` converts a number to a `string`.
- `(int)` truncates a `float` toward zero. A `float` too large for an `int`, or NaN, throws `ArithmeticError`.
- A string becomes a number only by parsing. `Int.parse(s)` and `Float.parse(s)` take a `string`, as C#'s `int.Parse(string)` and Kotlin's `String.toInt()` do, and throw on a string that holds anything but a number, such as `"abc"` or `"12abc"`. An `Any?` is checked with `as string` first.
- `Int.tryParse(s)` and `Float.tryParse(s)` take a `string` too, and give null where `parse` throws.
- A class or an interface narrows only with `as`, which gives null, or with `as … ?? throw` (section 21).
- PHP# has no user-defined conversion operators, such as C#'s `implicit operator`. A type converts only through a property or method it declares, such as `username.value`.
- `(bool)`, `(array)` and `(object)` do not exist. Conditions are `bool` (section 21), so a comparison such as `request.input("flag") == "1"` replaces `(bool)`. `(array)` and `(object)` are compile errors that name their replacements, shown below.
- PHP's cast aliases `(integer)`, `(double)`, `(boolean)` and `(binary)` do not exist.

**Replacing `(array)` and `(object)`:**

<!-- example array-object-replacements: statements
import Sharp.Json.Json;
Object row = Rows.withId(1);
string email = "ada@example.com";
-->
```csharp
OrderRow order = OrderRow.parse(row);             // replaces (array)row: a struct reads an object's public properties (section 10)
Map<string, Any> payload = ["id": 1, "email": email];
string json = Json.encode(payload);               // {"id":1,"email":"…"}: replaces json_encode((object)[…])
const data = (array)row;                          // compile error: write OrderRow.parse(row)
const point = (object)["x": 1];                   // compile error: write a Map literal, or a struct
```

- **A JSON object** is a `Map` literal for a one-off payload, and a declared struct for a shape that repeats.
- **`Json.encode` encodes by the value's PHP# type,** so a `Map` is a JSON object and a `List` is a JSON array wherever it is, below an `Any` too, even when empty, and even when a `Map`'s keys run from 0 to n. A `Map` is an object the engine tells apart from a `List` (section 12).

<!-- example json-encoding: statements
import Sharp.Json.Json;
-->
```csharp
Map<string, int> none = [:];
Json.encode(none);                                // {}
List<int> empty = [];
Json.encode(empty);                               // []
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                               // {"counts":{}}: an empty Map below Any is still an object
Map<string, Any> listed = ["counts": empty];
Json.encode(listed);                              // {"counts":[]}: an empty List below Any is still an array
```

**`Any` holds a value of any type except null. `Any?` also allows null.** A value of type `Any` must be checked with `is`, `as` or `match` before it can be used:

<!-- example any-checks: statements
import Sharp.Json.Json;
string body = "{}";
-->
```csharp
Any? payload = Json.decode(body);
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

<!-- example unchecked-any: statements
Order order = new Order();
Any? other = 1;
-->
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

<!-- example object-type: statements -->
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

**`typeof(X)`** is typed `Class<X>`, for any one type `X`, as C#'s `typeof` is. It works on every type parameter, a method's included, because every type argument reaches the running program (section 11). PHP's `Order::class` is removed.

<!-- example typeof-types
public class Example
{
    public void run<TItem>()
    {
        …
    }
}
-->
```csharp
Class<Order> type = typeof(Order);
type.attributes<Listen>();
typeof(TItem);
typeof(int);                    // the class value of int
typeof(int|string);             // the class value of int|string: a union is one type
typeof(List<Order>);            // the class value of a list of orders
typeof(Map<string, int>);
typeof(Self);                   // the class run was called on
```

**`typeof(TItem)` works for any type argument, including `int`,** as C#'s `typeof(T)` does. It is a `Class<TItem>`, which can create objects, only when `TItem`'s bound is a class:

<!-- example typeof-type-parameters: members
import Illuminate.Support.Facades.Log;
-->
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

**`typeof` takes a type only. `Class.of(value)` gives an object's class,** as Swift's `type(of:)` and Python's `type()` do. For a value of type `T`, it is typed `Class<T>` and holds the object's runtime class, which may be a subclass of `T`.

<!-- example typeof-values: statements
import Illuminate.Support.Facades.Log;
Order order = new Order();
int id = 1;
-->
```csharp
Class<Order> type = Class.of(order);            // compiles: order's runtime class, which may be a subclass of Order
const fresh = new (type)(id);                   // compiles: a new object of that class
Log.info("saved", ["class": Class.of(order)]);  // compiles
typeof(order);                                  // compile error: typeof takes a type; write Class.of(order)
```

**A class value works wherever a class name works.** `typeof` and `Class.of` give one, and a `Class<T>` field, parameter or local holds one:

<!-- example class-values -->
```csharp
// app/Element.sharp
namespace App;

public abstract class Element
{
    public required Element(string key) { … }
    public static string defaultTag() => "div";
}
```

<!-- example class-values -->
```csharp
// app/FormBuilder.sharp
namespace App;

import Illuminate.Database.Eloquent.Model;

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
- **`Class`'s own members win,** such as `attributes`. A class cannot declare a static named like one of them, as in TypeScript's error 2699. Only the standard library adds members to `Class`, so an extension of `Class<T>` elsewhere is a compile error.
- **`attributes<TAttribute : Object>()` reads attributes, which are objects,** so `attributes<int>()` is a compile error.
- **A class value's type need not be written.** `const type = …` holds a `Class<Element>`.
- **A property named by a variable has no form.**

<!-- example class-members: statements
Class<Element> type = typeof(FormElement);
string kind = "form";
string key = "signup";
-->
```csharp
type.attributes<Listen>();                       // Class's own method
type.attributes<int>();                          // compile error: int is not an Object
public static string attributes() => "";         // compile error: attributes is a member of Class
new (kind)(key);                                 // compile error: kind is a string, not a Class
```

**A class value of a generic class can carry its type arguments or leave them out,** as in C#. `typeof(PaginatedList<Order>)` is a `Class<PaginatedList<Order>>`, and `new (pages)()` creates a `PaginatedList<Order>`. A `Class<PaginatedList>` leaves them out, so `new` names them: `new (any)<Order>()`. `new (any)()` on an open generic class is a compile error.

<!-- example generic-class-values -->
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

**Two class values are equal when they name the same class with the same type arguments,** as in C#, so `typeof(Box<Order>) == typeof(Box<Invoice>)` is false:

<!-- example class-value-equality: statements
public class Box<T> { }
public class Invoice { }
-->
```csharp
typeof(Box<Order>) == typeof(Box<Order>);       // true
typeof(Box<Order>) == typeof(Box<Invoice>);     // false: the same class with other type arguments
```

**`Self`** means the class a static method was actually called on. It replaces PHP's `static`. PHP's `self` is removed: to mean the declaring class, write its name.

<!-- example self-return: statements
public class Order : DatabaseEntity { public Order(Row row) : super(row) { } }
Any value = 1;
VerifiedUser caller = new VerifiedUser();
-->
```csharp
public abstract class DatabaseEntity
{
    public required DatabaseEntity(Row row) { … }

    public static Self fromSchema(Any value, VerifiedUser caller)
    {
        Row row = …;
        return new Self(row);
    }
}

const order = Order.fromSchema(value, caller);   // typed as Order
```

`Self` replaces the C# workaround of passing a class to itself, as in `class Order : Entity<Order>`.

`Self` is written only as a return type, and in bodies as `new Self(…)`, `Self.m()` and `typeof(Self)`, the class value of the class the method was called on, which replaces PHP's `static::class`. A `Self` parameter is a compile error, because a subclass would accept any parent:

<!-- example self-parameter: members -->
```csharp
public bool same(Self other) { … }   // compile error: Self is a return type only
```

**`new Self(…)` compiles only when the class's constructor is marked `required`.** `Self` can be any subclass, so every class below it keeps a constructor that `new Self(…)` can call:

- its leading parameters match the parameters of the `required` constructor
- every parameter it adds has a default

<!-- example required-constructors
public abstract class DatabaseEntity { public required DatabaseEntity(Row row) { } }
…
-->
```csharp
// app/Order.sharp
namespace App;

public class Order : DatabaseEntity
{
    public Order(Row row, Clock? clock = null) : super(row) { … }   // compiles: new Self(row) can call it
}
```

<!-- example required-constructors -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice : DatabaseEntity
{
    public Invoice(Row row, Clock clock) : super(row) { … }         // compile error: new Self(row) cannot supply clock
}
```

`required` is optional. A class that never writes `new Self(…)` never writes it.

## 26. Extension methods

An extension adds methods and properties to an existing type from outside it. Extensions are declared in `extension(...)` blocks inside a static class, as in C# 14:

<!-- example extensions -->
```csharp
// app/Shared/Text/TextExtensions.sharp
namespace App.Shared.Text;

import Illuminate.Support.Str;

public static class TextExtensions
{
    extension(string value)
    {
        public string slug() => Str.slug(value);
        public bool isBlank => value.trim() == "";
    }
}
```

<!-- example extensions: statements
string title = "Spring Sale";
-->
```csharp
import App.Shared.Text.TextExtensions;

title.slug();
title.isBlank;
TextExtensions.slug(title);         // the same call, written through the class, as in C#
```

- **An extension applies only in files that import its class, and in the files of its own namespace,** as Kotlin's do in their package. An extension the file imports, or one its own namespace declares, shadows a default extension of the same name, as a class does (section 23).
- **An extension member named like a member its type, or a class above it, already declares is a compile error where the extension is written,** because it can never run, as Swift refuses an `invalid redeclaration`. On a union receiver, such as `Order|string`, it is an error only when every type of the union declares the member, because otherwise the extension runs on the rest:

<!-- example extension-hidden-member -->
```csharp
// app/Billing/OrderNames.sharp
namespace App.Billing;

import App.Order;

public static class OrderNames
{
    extension(Order order)
    {
        public string label() => "order";   // compile error: Order already declares label(), so this extension member never runs
    }
}
```

- **An extension is chosen from the type the line shows,** as in C# and Kotlin. A member a subclass declares never makes an extension a compile error:

<!-- example extension-static-choice: statements -->
```csharp
import App.Billing.OrderLabels;     // extension(Order order) { public string caption() => …; }

Order order = new RushOrder();      // RushOrder : Order declares its own caption()
order.caption();                    // compiles: the line shows an Order, so OrderLabels runs
```

- **Extensions can only reach the type's public members,** so access rules still hold.
- **The receiver's type picks among the extensions with one name that apply in a file,** as in C# and Kotlin. When several fit, the one whose receiver type is the most specific runs, so an extension on `RushOrder` wins over one on `Order` for a `RushOrder`. Two that fit where neither receiver type is inside the other are a compile error:

<!-- example extension-overloads: statements
List<Line> lines = [new Line()];
Map<string, Money> prices = ["pro": new Money(2900, "USD")];
-->
```csharp
import App.Billing.LineTotals;      // extension(List<Line> lines) { public Money total() => …; }
import App.Billing.PriceTotals;     // extension(Map<string, Money> prices) { public Money total() => …; }

lines.total();                      // compiles: lines is a List<Line>, so LineTotals runs
prices.total();                     // compiles: prices is a Map<string, Money>, so PriceTotals runs
```

- **An extension block can declare type parameters, as in C# 14,** so one block serves a receiver of any type argument, `extension<TItem>(List<TItem> items)`, within the type parameters' bounds (section 11). A call infers them from its receiver. A member's own type parameters come after the block's, and a call writes only those:

<!-- example generic-extensions -->
```csharp
// app/Shared/Lists/ListExtensions.sharp
namespace App.Shared.Lists;

import App.DatabaseEntity;

public static class ListExtensions
{
    extension<TItem>(List<TItem> items)
    {
        public bool holds(TItem wanted) => items.contains(wanted);
        public List<TOut> mapped<TOut>(Function<TOut(TItem)> map) => items.map(map);
    }

    extension<TEntity : DatabaseEntity>(List<TEntity> rows)
    {
        public int saved => rows.count();
    }
}
```

<!-- example generic-extensions: statements
List<Order> orders = [new Order()];
List<string> names = ["spring"];
-->
```csharp
import App.Shared.Lists.ListExtensions;

orders.holds(new Order());              // compiles: TItem is Order, from orders
orders.mapped<string>(o => "order");    // compiles: a List<string>, the call writes only TOut
orders.saved;                           // compiles: an Order is a DatabaseEntity
names.saved;                            // compile error: string is outside TEntity's bound, so List<string> has no saved
```

- **Each type parameter of an extension block appears in its receiver's type,** because the receiver is the only place a call infers it from, as C# requires:

<!-- example extension-unused-type-parameter -->
```csharp
// app/Shared/Lists/Pairs.sharp
namespace App.Shared.Lists;

public static class Pairs
{
    extension<TItem, TOther>(List<TItem> items)    // compile error: the receiver List<TItem> doesn't use TOther
    {
        public int size() => items.count();
    }
}
```

## 27. PHP# files

A file's extension marks it as PHP#. The engine, Composer's autoloader, editors and the checker all choose the grammar from the filename.

A PHP# file has no opening tag. Its first line is code:

<!-- example file-header -->
```csharp
namespace App.Tenant.Store;

import App.Shared.Schema.Entities.DatabaseEntity;
```

The extension is **`.sharp`**, as in `StoreService.sharp`.

**A `.sharp` file that declares a type declares one, named like the file,** as Java allows one public top-level class per file. Every type, events and interfaces included, sits at file level in the namespace its folder names (section 5), so a reader finds a type by its namespace and its file. A second type in a file is a compile error. An `extern` declaration file (section 29) and a `Module.sharp` file (section 32) hold no type, so the rule doesn't cover them:

<!-- example one-type-per-file -->
```csharp
// app/Tenant/Store/StoreService.sharp
namespace App.Tenant.Store;

public class StoreService { … }       // compiles: StoreService.sharp declares StoreService
public class StoreHelper { … }        // compile error: move StoreHelper to StoreHelper.sharp
```

Plain PHP files keep `.php` and `<?php`, and the two call each other freely. Composer's autoloader tries `.sharp` after `.php`, the same way it already tries `.hh` for Hack.

**`Position` says where code sits in its source.** PHP's magic constants `__DIR__`, `__FILE__`, `__LINE__`, `__FUNCTION__`, `__METHOD__`, `__NAMESPACE__` and `__CLASS__` are removed, along with every other `__Something__` form. `Position` is a standard-library type, imported by default (section 23). It has `file`, `directory`, `line`, `column` and `function`. `function` is the fully qualified dotted name, as in `App.Reports.logSlow`, because a short name is ambiguous across namespaces.

<!-- example position: statements -->
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
Log.info("charged", ["class": typeof(Order)]);                   // compiles: replaces __CLASS__
```

- `Position.current()` in a body gives the position where it is written.
- As a parameter's default, `Position.current()` gives the caller's position, as C++20's `std::source_location::current()` and Swift's `#file` defaults do.
**A `.sharp` file runs only after the checker accepts it.** A type error stops it from running, as in C# and Java. A pragma, `mago.toml`'s `ignore` or the baseline can hide a warning, never an error.

**`vendor/bin/mago compile` compiles the project.** It checks every `.sharp` file, vendor packages included. It also creates a missing `.lean` file beside a class that has a law (section 28.1), and never rewrites an existing one.

- **A `.sharp` file that isn't compiled, or is out of date, is refused,** and the refusal names the fix:

```text
app/Orders/Order.sharp isn't compiled. Run vendor/bin/mago compile.
app/Orders/Order.sharp is out of date (app/Shared/Money.sharp changed). Run vendor/bin/mago compile.
app/Orders/Order.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
```

- **A deploy** runs `vendor/bin/mago compile` before the code runs.
- **A type error, or a broken structure rule (section 28), stops the file from running.** So does a law without a proof, a gap, or a proof whose law was deleted, each a compile error that names it.
- **A rule that reads the whole project's structure can lag in development** until the next full compile. At deploy, `mago compile` checks every rule exactly.

## 28. Verification

The checker verifies two kinds of facts before code runs:

- **Structure:** what code may depend on and do. Visibility and effects belong to the language. Code-shape and style rules stay plugins in the checker.
- **Values:** facts the code guarantees, written as laws with proofs, as in Bend, Lean and Agda.

**A class states its laws in PHP#,** with `law`. A law's parameters range over every possible value. Its proofs go in a Lean file of the same name beside it, `Money.lean` beside `Money.sharp`, written in Lean 4 and checked by Lean. Bend 2 splits claims from proofs the same way, into `LAWS.bend` and `PROOF.bend`.

<!-- example class-law -->
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

<!-- example module-law -->
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

<!-- example state-machine-law -->
```csharp
public enum Status : string
{
    case Open = "open";
    case Paid = "paid";
    case Refunded = "refunded";

    public Status after(Payment e) => match (this) {                               // pure: the next status for every status and event
        Status.Open => Status.Paid,
        Status.Paid => Status.Refunded,
        Status.Refunded => Status.Refunded,
    };
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

**A structure rule is a Lean definition with the `@[rule]` attribute,** the way Lean marks a simp lemma with `@[simp]`. **It names a namespace by its full name, and a name that is no namespace in the project is a compile error,** as any unknown name in Lean is. That holds in the rules PHP# ships, such as `.neverImports`, and in a rule of your own, such as one built with `.imports` and `under`:

```lean
-- app/Tenant/Store/Module.lean
import Code.App.Tenant.Store.Module
open Sharp

theorem receiptMatchesRefunds : App.Tenant.Store.Module.receiptMatchesRefunds := by
  simp [App.Tenant.Store.Module.receiptMatchesRefunds, App.Tenant.Store.Receipts.refundable, App.Tenant.Store.Refunds.remaining]

@[rule] def importsOnly : Rule := .importsOnly [App.Tenant.Community, App.Shared.Schema]   -- compiles
@[rule] def neverLedger : Rule := .neverImports App.Shared.Ledgr                            -- compile error: unknown identifier `App.Shared.Ledgr`
@[rule] def ownLedger : Rule := .imports fun _ i => under i.name App.Shared.Ledgr           -- compile error: unknown identifier `App.Shared.Ledgr`
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

**Any PHP# code may call plain PHP,** such as a library's classes, facades and models. Most libraries are plain PHP, so this is how PHP# code uses them. PHP's own built-in functions are the exception: the standard library replaces them (section 8).

<!-- example plain-php-calls -->
```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

**The effect of plain PHP is declared once, with `extern`.** A `.sharp` declaration file names a plain PHP class, one of its methods or a function, and the effect after `uses`. A declaration holds no code.

<!-- example extern-declaration -->
```csharp
// app/Stubs/Stripe.sharp
namespace App.Stubs;

import Stripe.StripeClient;

extern StripeClient uses Http;
```

- **A declaration file is an ordinary `.sharp` file.** Its `namespace` line matches its path, as section 5 requires.
- **`extern` covers a whole class, one method or a function,** each after importing it: `extern StripeClient uses Http;`, `extern Carbon.now uses Clock;`, `extern Pdf.render uses Files;` for a function after `import Vendor.Pdf;`, or `extern global.now uses Clock;` for a function in PHP's root namespace (section 23).
- **An `extern` that names no effect declares the class, method or function pure,** as in `extern BigDecimal;`.

<!-- example extern-functions -->
```php
namespace Vendor\Pdf;

function render(string $html): string { return $html; }
```

<!-- example extern-functions -->
```csharp
// app/Stubs/Functions.sharp
namespace App.Stubs;

import Vendor.Pdf;

extern Pdf.render uses Files;        // compiles: Pdf.render(html) has the effect Files
extern global.now uses Clock;        // compiles: global.now() has the effect Clock
extern now uses Clock;               // compile error: name the function through its namespace, as in global.now
```

- **Each class, method or function has at most one `extern` declaration in the whole project.** A second one is a compile error, as declaring a class twice is.
- **A call to plain PHP with an `extern` declaration has that effect,** so it fits a `uses` that names it: `StripeClient.charges().create(…)` fits `uses Http`.
- **An object a declared plain PHP call returns carries that call's effect,** so `StripeClient.charges()` returns an object with `Http`, and calling it fits `uses Http`. An `extern` on the returned class itself wins over the inherited effect.
- **A call to plain PHP with no declaration has an unknown effect.** Code with a body may make it, and is then never pure and never takes part in laws (section 28). No `uses` accepts it, and the error names the missing declaration.
- **A project declares the effects of every library it uses,** conventionally in `app/Stubs`. PHP# ships declarations for no framework, so every library is declared the same way.
- **PHP's own built-in functions come declared.** PHP#'s Composer package ships an `extern global.…` declaration for each one, so `global.levenshtein(a, b)` is pure and `global.fopen(path, "r")` has `Files`.

**The standard library's ten effect classes replace PHP's built-ins for their effects:**

- `Database` replaces PDO.
- `Http` replaces curl.
- `Files` replaces every file function outside standard output and standard error.
- `Console` replaces printing to standard output and standard error.
- `Process` replaces `exit`.
- `Clock` replaces `time()`.
- `Random` replaces `random_int`.
- `Cache` replaces the APCu functions.
- `Mail` replaces `mail()`.
- `Environment` replaces `getenv()` and PHP's other environment functions.

Calling a replaced function directly is a compile error that names the method (section 8). **A PHP built-in class, such as `PDO` or `DateTime`, is refused the same way,** and the error names the standard library type that replaces it, such as `Database` or `Date`.

<!-- example pure-interface -->
```csharp
// app/Formatter.sharp
namespace App;

public interface Formatter
{
    string format(string name);                                     // implementations must be pure
}
```

<!-- example pure-interface -->
```csharp
// app/Visitor.sharp
namespace App;

public class Visitor
{
    public Visitor(private Files files) { }

    public string greet(string name) => "Hello, " + name.trim();    // pure: trim is a standard-library method
    public void remember(string token) { this.files.write("seen.txt", token); }   // has Files
}
```

<!-- example pure-interface -->
```csharp
// app/FileFormatter.sharp
namespace App;

public class FileFormatter : Formatter
{
    public FileFormatter(private Files files) { }

    public string format(string name)
    {
        this.files.write("seen.txt", name);                         // compile error: format must be pure, and files.write has the effect Files
        return name;
    }
}
```

**A `foreign` class turns an effect into an object** that code holds and passes on, such as a fake in tests. It is optional. Its methods call plain PHP, like any other code. Code that holds a `foreign` object has that effect by name, which `uses` can declare.

<!-- example foreign-class -->
```csharp
import Illuminate.Support.Facades.Redis;

public foreign class RedisStore
{
    public string? get(string key) => Redis.get(key);
}
```

The standard library's ten effect classes, listed above, are `foreign` classes. A project declares its own `foreign` classes the same way.

**`Events` is the effect of `emit`** (section 15). It is the one standard effect that is not a `foreign` class: a method has it when its body emits, and no object carries it.

**PHP# has no superglobals.** `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_REQUEST`, `$_SESSION`, `$_ENV` and `$GLOBALS` are compile errors. For the environment and the command-line arguments, the error names `Environment`. For request data, it names a parameter that takes it. The process environment arrives as `Environment`, a standard `foreign` class like `Clock` and `Random`:

- `string? variable(string name)` reads an environment variable, or gives null when it is not set.
- `List<string> arguments { get; }` holds the command-line arguments, starting with the script's name.
- `string currentDirectory()` reads the directory the process runs in on every call, as Go's `os.Getwd()` and Rust's `env::current_dir()` do.
- `void changeDirectory(string path)` changes the directory the process runs in.
- `string require(string name)` reads an environment variable, and throws `ValueError` when it is not set, with the message `Environment.require: the environment variable "NAME" is not set`. A module binding calls it on the `Environment` it takes as a parameter (section 32), as ASP.NET Core's `IConfiguration` is a service.

<!-- example environment -->
```csharp
// app/Deploy.sharp
namespace App;

public class Deploy
{
    public Deploy(private Environment environment) { }

    public string region() => this.environment.variable("AWS_REGION") ?? "us-east-1";
    public string target() => this.environment.arguments[1];   // throws OutOfRangeException when no argument was passed
}
```

<!-- example environment -->
```csharp
// app/CheckoutController.sharp
namespace App;

import Illuminate.Http.Request;

public class CheckoutController
{
    public string host(Request request) => request.getHost();   // compiles: the request arrives as a parameter
    public string origin() => _SERVER["HTTP_HOST"];             // compile error: take the request data as a parameter
}
```
<!-- run environment
new Deploy(new Environment()).target();   // throws OutOfRangeException
-->

`Deploy` holds an `Environment`, so it has that effect (see the rule on a class's effects below).

**A `foreign` object reaches other code only through constructors:**

<!-- example foreign-constructor -->
```csharp
public class TenantCache
{
    public TenantCache(private RedisStore store) { }
}
```

- **A class's effects** are the `foreign` classes it holds, directly or through its fields, the effects of the plain PHP its methods call, and the events its methods emit. The checker works them out from field and constructor types and from method bodies. Nothing is written down.
- **A `foreign` object** is created in a module binding (section 32), as often as its binding's lifetime decides, and handed down through constructors. Creating one anywhere else, or storing one in a static, is a compile error.

**Pure code** reaches no `foreign` object, emits no event, calls no plain PHP unless an `extern` declares it pure, and changes nothing it was given (section 13). A `get` body has whatever effects its body has, and a read of the property carries them (section 6.1). Laws (section 28) reason only about pure code, and Lean cannot see inside `foreign` classes or plain PHP.

**Code without a body is pure unless it says `uses`.** This covers interface methods, abstract methods and function types. Every implementation is held to what the declaration allows:

<!-- example effectful-declarations: members -->
```csharp
public interface PaymentGateway
{
    Money quote(Cart cart);                          // implementations must be pure
    Charge charge(Cart cart) uses Http;              // implementations may reach Http, nothing else
}

Function<Money(Offer)> priceOf = (offer) => Money.zero("USD");      // a pure function value
Function<Charge(Cart) uses Http> charge = (cart) => new Charge();   // may reach Http
```

**A function type lists its effects inside its brackets,** after the parameter types, wherever the type is written:

<!-- example function-type-effects: members -->
```csharp
Function<Charge(Cart) uses Http> charge = (cart) => new Charge();          // compiles
Function<Charge(Cart) uses Http>? fallback = null;                          // compiles
Map<string, Function<Charge(Cart) uses Http, Mail>> handlers = [:];         // compiles: Http and Mail are both effects
Function<int(Function<int(int) uses Http>)> apply = (step) => 0;            // compiles
```

An implementation that calls plain PHP fits the declaration only through an `extern`:

<!-- example extern-implementations -->
```csharp
// app/AudienceSync.sharp
namespace App;

public interface AudienceSync
{
    void sync(Contact contact) uses Http;
}
```

<!-- example extern-implementations -->
```csharp
// app/StripeGateway.sharp
namespace App;

import Stripe.StripeClient;

public class StripeGateway : PaymentGateway
{
    public Money quote(Cart cart) { … }
    public Charge charge(Cart cart) => StripeClient.charges().create(…);   // fits: StripeClient is declared Http
}
```

<!-- example extern-implementations -->
```csharp
// app/MailchimpSync.sharp
namespace App;

import Mailchimp.Mailchimp;

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

<!-- example emit-effect -->
```csharp
// app/OrderPaid.sharp
namespace App;

public event OrderPaid(int orderId, int total);
```

<!-- example emit-effect -->
```csharp
// app/Checkout.sharp
namespace App;

public interface Checkout
{
    void complete(Order order) uses Events;                                            // implementations may emit, nothing else
}
```

<!-- example emit-effect -->
```csharp
// app/StoreCheckout.sharp
namespace App;

public class StoreCheckout : Checkout
{
    public void complete(Order order) { emit new OrderPaid(order.id, order.total); }    // fits: emit has the effect Events
}
```

**A method that takes a function can have that function's effects.** Its declaration writes `uses f`, where `f` is one of its function-typed parameters, with or without a body. At each call, the method has its body's own effects plus the effects of the function passed as `f`. Code that calls it writes nothing.

<!-- example function-effects: statements in Carts<T>
List<Cart> carts = [new Cart()];
private PaymentGateway gateway = new StripeGateway();
-->
```csharp
// in the standard library's List<T>
public List<TResult> map<TResult>(Function<TResult(T)> f) uses f;

carts.map(c => c.total);                             // pure
carts.map(c => this.gateway.charge(c));              // has Http

// a method with a body: Http from this.gateway, plus the effects of prepare
public Charge charge(Cart cart, Function<Cart(Cart)> prepare) uses prepare => this.gateway.charge(prepare(cart));
```

The standard library's collection methods (section 12) are declared this way, so list code with pure functions stays pure, and laws (section 28) can reason about it.

**Plain PHP can run any function it is given,** as `global.array_map(f, list)` and `global.usort(list, f)` do. So a call of a plain PHP function or method has the effects its `extern` lists, plus the effects of each lambda, method value and function value passed to it. A getter that passes `() => global.time()` to `global.call_user_func` has the effect `Clock`.

**Code with a body writes no `uses` except `uses f`.** Its effects enter through the constructor, through the plain PHP it calls, through the events it emits and through each function its `uses f` names, and its body and declaration show all four. The checker works out each method's effects, and the editor displays them.

## 30. Tuples

A tuple groups a fixed number of typed values without declaring a class. It uses TypeScript's brackets. Each element is declared type first, as everywhere in PHP#.

<!-- example tuples: statements in Order
public class Date { }
private Money total = new Money(500, "USD");
private int count = 2;
private Date since = new Date();
Order order = new Order();
-->
```csharp
public [Money total, int count, Date since] summarize() { return [this.total, this.count, this.since]; }

const [total, count, since] = order.summarize();     // unpack
const summary = order.summarize();
summary.total;                                        // read by name, as in C#

public [Money, int] totals() { return [this.total, this.count]; }   // compiles: element names are optional
const [money, items] = order.totals();                              // unpack an unnamed tuple
```

- **A tuple can have any number of elements.** The checker suggests a struct past four.
- **Names are optional in a tuple type,** as in TypeScript, C# and Swift. `[Money, int]` compiles.
- **A list literal becomes a tuple** when the declared type is a tuple.
- **Map entries are tuples:** `for (const [key, plan] of plans)`.

## 31. Expected failures as values

An expected failure, such as a declined card, is returned as a value. An exception means a bug, and it fails loudly.

<!-- example result-enum: members -->
```csharp
public enum Result<T, TError : Error>
{
    case Ok(T value);
    case Err(TError error);                       // Err beside the Error interface, as Rust's Result::Err sits beside its Error trait
}

public Result<Order, PaymentError> charge(Cart cart) { … return Result.Err(PaymentError.Declined(reason)); }
```

`Result` is an ordinary enum with data from the standard library, so `match`, `is` and the exhaustive-case check work on it unchanged.

**`try` passes a failure up to the caller.** If the call returns `Err`, the enclosing method returns that same `Err` immediately. Otherwise `try` gives the `Ok` value. Zig and Swift use `try` the same way.

<!-- example try-propagation: members
private CheckoutGateway gateway = new CheckoutGateway();
-->
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

<!-- example error-interface -->
```csharp
// in the standard library's Sharp
public interface Error { string message { get; } }
```

<!-- example error-interface -->
```csharp
// app/PaymentError.sharp
namespace App;

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

<!-- example module-services -->
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
    singleton RedisStore(Environment environment) = new RedisStore(environment.require("REDIS_URL"));   // a foreign object, created here as its singleton lifetime decides
    transient SendReceipt = SendReceipt;                                       // a class binds to itself, so PHP# creates it
}
```

- **A binding's value is a class or one expression.** A class, such as `SmtpMailer`, is built through its main constructor (section 9.1). A class binds to itself to be created, as `SendReceipt` does.
- **A binding names the services it needs as parameters, and its value is one expression over them,** as Dagger's `@Provides` methods do. The container passes each parameter the service bound to its type, so `RedisStore` above gets the app's `Environment`, which the standard library's module binds for every namespace. An expression is for a value no binding gives, such as `RedisStore`'s URL.
- **A module stays declarative:** one expression per binding, and no statements, loops or methods.
- **A `foreign` object is created in a module binding** (section 29). The binding's lifetime decides how often one is created, and it reaches classes only through constructors.
- **A module also states the laws that span its classes** (section 28).

**The host defines each lifetime, as ASP.NET Core's host does.** A `singleton` lives as long as the host runs the app. A `scoped` binding lives for one scope, which the host starts and ends, such as one HTTP request, one queued job or one console command run. A `transient` binding is created each time a class needs one.

A binding's expression runs each time its lifetime needs a new service, so a choice made while the app runs is one expression:

<!-- example scoped-binding
namespace App.Shop;

import App.Flags.FeatureFlags;
import App.Mail.Mailer;
import App.Mail.QueueMailer;
import App.Mail.SmtpMailer;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton FeatureFlags = FeatureFlags;
    transient QueueMailer = QueueMailer;
    transient SmtpMailer = SmtpMailer;
    scoped Mailer(FeatureFlags flags, QueueMailer queue, SmtpMailer smtp) = flags.enabled("queue-mail") ? queue : smtp;   // runs once per request, job or command
}
```

<!-- example module-errors: alone
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Pricing.TaxRule;
-->
```csharp
// app/Shop/Module.sharp
module
{
    let fallback = new SmtpMailer();   // compile error: a module holds no statements; give a binding one expression
    singleton Mailer = TaxRule;        // compile error: TaxRule is not a Mailer
}
```

**A nested folder's module overrides its parent's services.** A test or environment module overrides with `module : App.Shop { … }`, and the compiler checks every environment's set of services: the base modules under all of that environment's modules at once.

- **An environment module is `Module.<Environment>.sharp`** beside the `Module.sharp` it overrides, such as `Module.Production.sharp`. `SHARP_ENV` picks it, as `ASPNETCORE_ENVIRONMENT` picks ASP.NET Core's `appsettings.Production.json`.
- **A test module is declared in the test file that uses it,** with the same header, as NestJS's testing module is. While a test of that file runs, its services are the test module's over the base modules, with no environment module, which is the set the compiler checks.

<!-- example environment-module -->
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

<!-- example test-module -->
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

**A missing or mistyped service is a compile error.** PHP# creates the classes a module binds and the classes that declare an `on` method (section 15), each through its main constructor (section 9.1). Every parameter of their constructors, and every binding parameter, needs a binding in the module of its namespace or of a namespace above it. The compiler checks every such class, whether or not another class takes it. A class no module binds is ordinary code, created with `new`, such as `Money`.

<!-- example missing-service -->
```csharp
// app/Shop/Checkout.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.PaymentGateway;

public class Checkout
{
    public Checkout(private Mailer mailer, private PaymentGateway gateway) { }
}
```

<!-- example missing-service
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton Mailer = SmtpMailer;
    transient Checkout = Checkout;   // compile error: Checkout needs a PaymentGateway, and no module binds one
}
```

**The container implements PSR-11,** so any framework's container can ask PHP#'s container for a PHP# class. The language never depends on a framework.

## Undecided, in order

None. The standard library is specified with the library itself.

# Slice 9: Constructors

Spec section 9 and 9.1, with the parts of sections 6, 14.2 and 25 that section 9 reaches. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Members declared on constructor parameters work on master. The rest of section 9 does not:

- **A subclass cannot feed its parent.** `public Order(Row row) : super(row) { }` is 4 generic parse errors. The only form that compiles, a subclass constructor without `: super`, is **silently accepted and wrong**: the parent's constructor never runs, so the parent's get-only properties are never set and the first read of one throws at runtime.
- **Named constructors are refused** with "A named constructor is not supported yet in PHP#.", at the declaration and at `new Artifact.fromJson(json)`.
- **The spec's own guard clause is refused.** `if (!tenant.active) throw new InactiveTenant(tenant);` gets "This statement without braces is not supported yet in PHP#."
- **The parameter refusals are generic.** `lazy` and a computed property on a parameter give 10 and 8 "Parse error encountered during parsing" lines, and a second unnamed constructor gets PHP's "class method `Overload.Overload` has already been defined".

The Slice builds all of it in 3 mago-sharp PRs and 1 php-sharp PR. It adds two CST nodes after the `Operator` precedent, lowers `: super(…)` to the `parent::__construct(…)` that `super.m()` already uses, and lowers a named constructor to a public static method, the PHP idiom for a named constructor. `ext/sharp/sharp.c` does not change: every node the Slice emits is a kind master already emits.

Section 9's headline example still does not compile when the Slice lands, because it writes `via Tracked`, and `via` waits on section 6.4. Everything else in that example compiles.

## Decisions team-lead approves

Each one is drawn in full further down.

1. **`: super(…)` and `: this(…)` are one CST node, `ConstructorInitializer`, after Roslyn's `ConstructorInitializerSyntax`.** The main constructor carries it on `Method` as `initializer`. The bridge lowers `: super(args)` to `parent::__construct(args)` after the class's initial values and before the body, the order C#, Dart and Swift use: a class sets its own fields before its base class runs.
2. **A constructor without `: super(…)` calls the parent's constructor with no arguments, as C# calls `base()`.** When the parent's constructor needs an argument, that is the compile error `missing-super-call`, as C#'s CS7036. This closes the silent accept above. The spec does not write it (open question 1).
3. **A named constructor is its own CST node, `NamedConstructor`, a `ClassLikeMember` beside `Operator`.** The codex registers it as the public static method it lowers to, flagged `NAMED_CONSTRUCTOR`, as `walk_in_operator` registers an operator as `op_Addition`. `new Artifact.fromJson(json)` carries the name on `Instantiation` as `constructor`, after Dart's `ConstructorName`. The parser can fill it because of section 23: full names appear only in `namespace` and `import` lines, `.` in code is always member access, and nothing nests, so `new X.y(…)` always calls class `X`'s named constructor `y`, and no type shares that name.
4. **A named constructor lowers to `public static function fromJson(string $json): \Demo\Artifact`.** Its body creates the object with the main constructor, runs its own statements on it, and returns it. Inside it, `this` is a hidden local, after the bridge's `receiver#1` and `operand#1` precedent. Plain PHP calls it as `Artifact::fromJson($json)`.
5. **Named constructors are not inherited**, as in C# and Dart. `new Sub.fromJson(json)` is an error when only `Artifact` declares `fromJson`, and the static method creates an `Artifact` even when plain PHP calls it through a subclass.
6. **A named constructor cannot overwrite a get-only property the main constructor already set.** PHP sets a `readonly` property once, and master lowers a get-only property to `readonly`. The analyzer reports it as the existing `invalid-property-write`. C# allows the overwrite, so this narrows the spec (open question 2).
7. **The guard clause is the only form without braces.** The spec decides braces nowhere. It writes exactly two statements without them, `if (…) throw …;` in section 9 and `if (…) return …;` in section 14.2, both an `if` without `else`. The Slice accepts exactly that shape and nothing wider (open question 3).
8. **`lazy` becomes a contextual modifier now,** after `required` and `extern` in `parse_optional_modifier`, so a `lazy` parameter is one named error. `lazy` anywhere else is "not supported yet" until section 6.5 builds it.

## Rulings

team-lead approved decisions 1, 3, 4, 5, 7 and 8, the public names tree, and the PR order. Decisions 2 and 6 and the four open questions wait for the Architect, because each decides what compiles. Until he answers, the Slice refuses rather than guesses:

1. **Implicit `super()` (open question 1).** PR 2 refuses a constructor without `: super(…)` in a class whose parent has a constructor, with one named error: "`Child`'s constructor must call `Base`'s with `: super(…)`." That closes the silent accept. If the Architect picks implicit `super()`, PR 2 relaxes it to decision 2.
2. **A named constructor overwriting a get-only property the main constructor set (open question 2).** PR 3 refuses it, as decision 6 says.
3. **Braces (open question 3).** PR 1 accepts exactly the two forms the spec writes, an `if` without `else` around one `return` or `throw`, as decision 7 says. `docs/spec.md` does not change until the Architect rules.
4. **`this` in `: super(…)` and `: this(…)` (open question 4).** Refused in both, as the `: super(…)` section says.

## How the states were observed

Every state below comes from the `mago` built from #131's branch, `/Users/jordan/Developer/sharp-worktrees/5/mago-sharp/target/debug/mago`, run on one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-constructors/ws`. Its `composer.json` maps `Demo\` to `app/`, and its `mago.toml` has `paths = ["app"]`. Every example file's code is shown where its state is given. Some are shown with one line per class, so a quoted line and column refer to the file on disk.

```sh
MAGO=/Users/jordan/Developer/sharp-worktrees/5/mago-sharp/target/debug/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-constructors
compile() { env -u FORCE_COLOR XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=emacs MAGO_LOG=error \
  $MAGO --no-version-check --colors never --threads 2 --workspace $P/ws compile; }
```

One `compile` run printed every error line quoted below, and ended with `Compiled 7 PHP# files into .sharp/. 16 PHP# files were refused`. The 7 compiled files are `Support`, `Members`, `Required`, `Journey`, `NoSuper`, `NoSuperUse` and `GetOnly` (`find $P/ws/.sharp -name "*.sharpc"`).

`app/Support.sharp`, which every example uses:

```csharp
namespace Demo;

import Attribute;
import Exception;

class Planner { public int plan() => 1; }
class Tenant { public bool active { get; set; } = true; }
class InactiveTenant : Exception { public InactiveTenant(public Tenant tenant { get; }) { } }
class Manifest { public static Manifest parse(string json) => new Manifest(); }
class Row { }
[Attribute]
class Field { public Field(public string label { get; }) { } }
```

**The binary matches master everywhere section 9 reaches.** It was built at 19:17 (`stat -f "%Sm" …/target/debug/mago`), the build the section 28 note records from #131's head `4df706a39`. Slot 5's mago-sharp is at master `9e99ae688`. `git diff --stat 4df706a39 HEAD -- crates/syntax/src/parser crates/semantics/src/internal/checker/sharp.rs crates/sharp-bridge/src/lower.rs crates/names/src crates/codex/src/signature_builder.rs` shows one file, `sharp.rs`, whose diff touches only `as` types, patterns and an empty `match`.

**The engine part of each state is read from code.** No PHP# engine binary is built: `ls …/sapi/cli/php` in slots 1 to 5 and in `/Users/jordan/Developer/php-sharp` finds none. A refused file never runs: `ext/sharp/sharp.c` `sharp_refuse` prints "`<file>` isn't compiled. Run vendor/bin/mago compile.", and `Zend/tests/sharp/compile_command.phpt` shows the checker's errors as "CompileError: `<file>` has N errors:". A compiled file runs the PHP the bridge lowers it to, read from `crates/sharp-bridge/src/lower.rs` and its tests in `crates/sharp-bridge/tests/lower.rs`.

# Every rule of section 9, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), and **wrong** (it compiles and runs differently from the spec).

## A constructor is named after its class

"A constructor is named after its class."

Works. `app/Members.sharp` compiled. The engine runs it: `Zend/tests/sharp/Account.sharp` declares `public Account(int opening)`.

- Mechanism: none new. The parser makes a name followed by `(` with no type before it a `Method` without a return type (`parse_sharp_member_with_attributes_and_modifiers` in `crates/syntax/src/parser/internal/class_like/method.rs`), and `php_method_name` in `crates/names/src/binding.rs` names it `__construct`.

## A parameter with an access modifier declares a member

"A parameter with an access modifier declares a member, and the parameter spells out which kind." `app/Members.sharp`:

```csharp
namespace Demo;

class FieldParameter
{
    public FieldParameter(private Planner planner) { }
    public int plan() => this.planner.plan();
}

class PropertyParameter
{
    public PropertyParameter(public Tenant tenant { get; }) { }
}

class PlainParameter
{
    private int total;
    public PlainParameter(int count) { this.total = count; }
}

class AttributedParameter
{
    public AttributedParameter([Field(label: "Tenant")] public Tenant tenant { get; }) { }
}

class TrailingComma
{
    public TrailingComma(
        private Planner planner,
        public Tenant tenant { get; },
    ) {
        this.planner.plan();
    }
}

class AccessorParameter
{
    public AccessorParameter(public string name { get; private set => field = trim(value); }) { }
}
```

- **`private Planner planner` declares a field.** Works. `compile` accepted `Members.sharp`. The engine runs it in `Zend/tests/sharp/Deploy.sharp` (`private Environment environment`).
  - Mechanism: none new. `Lowering::parameter_of_type` gives the `PARAM` node the member's flags, as php-src builds `private \Demo\Planner $planner`.
- **`public Tenant tenant { get; }` declares a property.** Works. The engine runs it in `Zend/tests/sharp/Badge.sharp` (`public int id { get; }`), lowered to `public readonly int $id`.
  - Mechanism: none new.
- **"A `public` parameter without accessor braces is a compile error, just as a public field is."** Works. `app/PublicBare.sharp`, `public PublicBare(public Tenant tenant) { }`, printed `app/PublicBare.sharp:5:23:error - semantics: A `public` constructor parameter needs accessors: a public member is a property, as in `public int id { get; }`.`
  - Mechanism: none new. `enter` in `crates/semantics/src/internal/checker/sharp.rs`.
- **"A parameter with no modifier is an ordinary parameter."** Works (`PlainParameter`). The engine runs it in `Zend/tests/sharp/Order.sharp` (`string code`).
  - Mechanism: none new.

## Section 9's example

```csharp
public StoreService(
    private Planner planner,
    [Field(label: "Tenant")] public Tenant tenant { get; } via Tracked,
) {
    if (!tenant.active) throw new InactiveTenant(tenant);
}
```

Refused, by `via` alone. `app/StoreService.sharp` holds the block inside `class StoreService` and printed only `app/StoreService.sharp:7:64:error - parse: `via` is not supported yet in PHP#.`, because a parse error stops `check_slice` for the file. Each other part, checked on its own:

- The field parameter, the attributed property parameter, and the trailing comma with `) {` work (`FieldParameter`, `AttributedParameter`, `TrailingComma` above).
- `via Tracked` is refused. It waits on section 6.4 (below).
- The guard clause is refused. `app/Braceless.sharp`:

  ```csharp
  namespace Demo;

  class Braceless
  {
      public Braceless(public Tenant tenant { get; })
      {
          if (!tenant.active) throw new InactiveTenant(tenant);
      }
  }
  ```

  printed `app/Braceless.sharp:7:9:error - semantics: This statement without braces is not supported yet in PHP#.`

After the Slice the example compiles once `via Tracked` is removed. With `via`, it stays refused with today's one message until section 6.4.

### Which statements go without braces

**The spec decides braces nowhere.** `trace grep -i "brace" docs/spec.md docs/decisions` finds only the accessor braces of section 9 and string braces of section 18. The rule "the body of `if`, `else` and each loop is a block in braces" lives only in `check_slice`'s doc comment and `has_braces` in `crates/semantics/src/internal/checker/sharp.rs`. It is the slice's narrowing, not the spec's.

**The spec writes two statements without braces,** found with `trace grep "if \(.*\) (return|throw|continue|break)" docs`:

- section 9: `if (!tenant.active) throw new InactiveTenant(tenant);`
- section 14.2: `if (line.free) return Money.zero(currency);`, inside a lambda. Refused today: `app/LambdaReturn.sharp` printed `app/LambdaReturn.sharp:8:13:error - semantics: This statement without braces is not supported yet in PHP#.`

Both are an `if` without `else` whose body is one statement that leaves. So the Slice accepts exactly this, and nothing wider:

- **Accepted:** an `if` without `else` whose body is one `return`, with or without a value, or one `throw`.
- **Still refused as "not supported yet":** an `else` or `else if` beside a body without braces, any other statement without braces (an assignment, a call, a local, `break`, `continue`), and every loop body without braces. The spec writes none of them.

- Mechanism: semantics only. `has_braces` accepts `Statement::Return` and an expression statement whose expression is `Expression::Throw` as the body of an `If` without an `else_clause`. The note gains one sentence: "An `if` without `else` may hold one `return` or `throw` without braces."
  - Names: none new. `always_exits` in `crates/names/src/internal/walker.rs` already counts a bare `return` and `throw`, so `if (x is not Paid p) return;` keeps `p` in scope after the `if`, as decision 020 says.
  - Bridge: none new. `Lowering::r#if` lowers `body.statement` through `Lowering::statement`, which already lowers a bare `return` or `throw`. php-src's grammar builds `if (c) return x;` as an `IF_ELEM` holding the `RETURN` itself, so the tree is PHP's own.

## A member declared on a parameter supports what the class body supports

"A member declared on a parameter supports everything the same declaration supports in the class body."

- **Attributes.** Works (`AttributedParameter`). The engine runs it in `Zend/tests/sharp/Product.sharp` (`[Field(label: null)] public int id { get; }`).
  - Mechanism: none new. `Lowering::attributes` on the parameter.
- **Accessor bodies and a separate access level for each accessor.** Works (`AccessorParameter`). The engine runs both: `Zend/tests/sharp/Accessors.sharp` (`public string city { get; set => field = ucfirst(trim(value)); }`) and `Zend/tests/sharp/Ticket.sharp` (`public int seats { get; protected set; }`).
  - Mechanism: none new. `check_accessors` and `Lowering::hooks` serve a parameter and a class-body property alike.
- **`willSet` and `didSet`.** Refused, the same on a parameter and in the class body, so the parity holds. `app/Observers.sharp`, `public Observers(public string name { get; set; didSet => this.touch(); }) { }`, printed `app/Observers.sharp:5:53:error - semantics: This accessor is not supported yet in PHP#.`, and `app/BodyParity.sharp`'s class-body `didSet` printed the same at `5:36`.
  - Mechanism: none in this Slice. Section 6.2 builds observers in `check_accessors` and `Lowering::hooks`, which both places share, so a parameter gets them with no extra work.
- **`via` behaviors.** Refused. `app/Via.sharp`, `public Via(public Tenant tenant { get; } via Tracked) { }`, printed `app/Via.sharp:5:46:error - parse: `via` is not supported yet in PHP#.`
  - Mechanism: none. See "What the Slice refuses".

## `lazy`, computed properties and `static` are not allowed on a parameter

"`lazy`, computed properties and `static` are not allowed on a parameter, because each of those has no incoming value to receive."

- **`lazy`.** Refused with generic errors. `app/NotOnParameter.sharp`, `public LazyParameter(public lazy Planner planner { get; }) { }`, printed 10 errors, the first `app/NotOnParameter.sharp:5:46:error - parse: Parse error encountered during parsing`, and one says "A PHP# field needs a type". `lazy` is no keyword yet: `app/LazyMember.sharp`, `public lazy Planner planner => new Planner();`, printed 6 generic parse errors.
  - Mechanism: parser and semantics. `parse_optional_modifier` in `crates/syntax/src/parser/internal/modifier.rs` reads `lazy` as `Modifier::Lazy`, a contextual word as `virtual`, `override`, `required` and `extern` are. `check_slice` reports it on any parameter: "A parameter cannot be `lazy`: a lazy property computes its own value, and a parameter receives one." `lazy` declares no member, so a parameter with only `lazy` is no promoted property. A word before `(`, `)`, `,` or `=` stays a name, so `(lazy) => lazy` keeps compiling. Anywhere else it reports "`lazy` is not supported yet in PHP#." until section 6.5, as the parser reports `required` off the constructor today.
- **A computed property.** Refused with generic errors. `app/ComputedParameter.sharp`, `public ComputedParameter(public string slug => "x") { }`, printed 8 lines of `Parse error encountered during parsing`, the first at `5:49`.
  - Mechanism: parser. After a parameter's name, a `.sharp` file's `=>` parses its expression whole and pushes one error at the `=>`: "A parameter cannot be a computed property: a computed property has no incoming value to receive." The parser reads every parameter the same way, so the message is true of a method's and a lambda's parameter too. The parameter stays in the list, so the class parses on, as `skip_via_clause` in `crates/syntax/src/parser/internal/class_like/property.rs` does for `via`.
- **`static`.** Works. `app/StaticParameter.sharp`, `public StaticParameter(public static int count { get; set; }) { }`, printed `app/StaticParameter.sharp:5:35:error - semantics: Parameter `count` cannot have the `static` modifier.`
  - Mechanism: none new. PHP's own parameter check in `crates/semantics`.

## A `required` constructor

"A constructor marked `required` is one that `new Self(…)` can call on the class and on every subclass. Section 25 gives its rules."

Works for the class. `app/Required.sharp` compiled:

```csharp
namespace Demo;

abstract class Element
{
    public required Element(public string key { get; }) { }
    public static Self make(string key) => new Self(key);
}
```

Refused for section 25's subclasses, because they write `: super(row)`. `app/RequiredChildren.sharp`:

```csharp
namespace Demo;

abstract class DatabaseEntity { public required DatabaseEntity(public Row row { get; }) { } }
class Clock { }
class Ledger : DatabaseEntity { public Ledger(Row row, Clock? clock = null) : super(row) { } }
class Invoice : DatabaseEntity { public Invoice(Row row, Clock clock) : super(row) { } }
```

printed 8 generic errors, 4 per constructor, starting `app/RequiredChildren.sharp:14:49:error - parse: Parse error encountered during parsing` and including `14:57: A PHP# parameter needs a type, as in `int extra`.` (`Ledger` and `Invoice` are section 25's `Order` and `Invoice`, renamed only because `Order` is used below.)

- Mechanism: `: super(…)` (below). The `required` rule itself needs nothing new: the analyzer's `validate_method_signature_compatibility` already refuses a descendant constructor that changes the `required` one's parameters or adds one without a default. After PR 2, `Ledger` compiles and `Invoice` is refused by that check.

## 9.1 One unnamed main constructor

"A class has one unnamed main constructor."

Refused with PHP's message. `app/Overload.sharp`, with `public Overload(int id) { }` and `public Overload(string name) { }`, printed `app/Overload.sharp:6:12:error - semantics: class method `Overload.Overload` has already been defined`. The message calls a PHP# constructor a method and names neither fix.

- Mechanism: semantics. `check_slice` reports a second constructor before PHP's duplicate check does: "A class has one main constructor. Give this one a name, as in `public Overload.fromName(string name) : this(…)`."

## 9.1 Named constructors

"Any other constructor has a name and passes control to the main one with `: this(…)`. Every creation is written `new Class…`."

```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }

let artifact = new Artifact.fromJson(json);
```

Refused, in both places. `app/Artifact.sharp` holds the two constructors in `class Artifact` and printed `app/Artifact.sharp:6:12:error - parse: A named constructor is not supported yet in PHP#.` `app/ArtifactUse.sharp` holds `let artifact = new Artifact.fromJson(json); return artifact;` in a method and printed `app/ArtifactUse.sharp:7:28:error - parse: A named constructor is not supported yet in PHP#.` Both refusals are deliberate: `parse_sharp_member_with_attributes_and_modifiers` and `parse_lhs_expression` in `crates/syntax/src/parser/internal/expression.rs` parse the form whole and push `ParseError::NotSupportedYetInSharp("A named constructor", …)`.

- Mechanism: parser, CST, names, codex, analyzer, semantics, bridge (decisions 3 and 4).
  - Parser: the two refusals become `parse_named_constructor`, which builds `NamedConstructor`, and `Instantiation.constructor`. Both paths already consume exactly the right tokens.
  - Names: a named constructor's parameters and `this` bind as a constructor's do (`crates/names/src/internal/walker.rs`).
  - Codex: `SignatureBuilder::walk_in_named_constructor` in `crates/codex/src/signature_builder.rs` registers the public static method `fromJson(string $json): Artifact` with `MetadataFlags::NAMED_CONSTRUCTOR`, as `walk_in_operator` registers an operator. So a plain PHP file that calls `Artifact::fromJson($json)` is checked against it with no extra code.
  - Analyzer: `analyze_class_instantiation` in `crates/analyzer/src/expression/instantiation.rs` resolves `new Artifact.fromJson(json)` to that method, declared on `Artifact` itself, checks the arguments as `analyze_invocation` does, and types the result `Artifact`. The `: this(…)` arguments are checked against the main constructor, as `new Artifact(…)` is. An abstract class keeps its existing refusal of `new`.
  - Semantics: `check_slice` requires `: this(…)` on a named constructor and refuses `: super(…)` there. A named constructor takes `public`, `protected` or `private` and nothing else. Its name follows a method's name rules: no `__` start, not its class's name, and not the name of another member, which PHP's duplicate-method check reports once the named constructor counts as a method.
  - Bridge: `Lowering::named_constructor` (below).
- **`: this(…)` on the main constructor** is an error: "The main constructor passes control to its base class with `: super(…)`. Only a named constructor calls `: this(…)`."
- **`Artifact.fromJson(json)` without `new`** is the new analyzer error `named-constructor-without-new`: "`Artifact.fromJson` is a constructor: write `new Artifact.fromJson(json)`." The analyzer decides it, because `Artifact` may be another file's.
- **`new Sub.fromJson(json)`**, where only `Artifact` declares `fromJson`, is an error: "`Sub` has no constructor `fromJson`: named constructors are not inherited." (decision 5).

## 9.1 `: super(…)`

"A constructor calls its base class's constructor with `: super(…)`, as in `public Order(Row row) : super(row) { }`."

Refused with generic errors. `app/Super.sharp`:

```csharp
namespace Demo;

class Entity { public Entity(public Row row { get; }) { } }
class Order : Entity { public Order(Row row) : super(row) { } }
```

printed three `app/Super.sharp:10:27:error - parse: Parse error encountered during parsing` lines and `app/Super.sharp:10:35:error - parse: A PHP# parameter needs a type, as in `int extra`.` The parser reads `: super` as a return type.

**Without `: super`, master compiles code whose parent never runs: silently accepted, and wrong.** `app/NoSuper.sharp` and `app/NoSuperUse.sharp` both compiled:

```csharp
namespace Demo;

class Base { public Base(public Row row { get; }) { } }
class Child : Base { public Child(Row row) { } }
class Inherits : Base { private string label = strtoupper("x"); }

class NoSuperUse
{
    public Row child() => new Child(new Row()).row;
    public Row inherits() => new Inherits(new Row()).row;
}
```

- `Child`'s constructor lowers to a `__construct(\Demo\Row $row)` with no `parent::__construct`, as PHP runs a constructor. `Base`'s promoted `readonly` `$row` is never set, so `child()` throws PHP's `Error` "must not be accessed before initialization". The checker accepted it.
- `Inherits` has no constructor and a non-constant initial value, so `Lowering::class` gives it `public function __construct()` holding `$this->label = strtoupper('x');` (the test `a_class_with_a_non_constant_initial_value_and_no_constructor_gets_one` in `crates/sharp-bridge/tests/lower.rs`). That constructor takes no arguments and never calls `Base`'s. The analyzer checks `new Inherits(new Row())` against `Base`'s inherited constructor and accepts it, while the engine drops the argument, so `inherits()` throws the same `Error`.

- Mechanism: parser, CST, names, semantics, analyzer, bridge.
  - Parser: `parse_optional_constructor_initializer` reads `:`, then `super` or `this`, then an argument list, after a constructor's parameters. Any other word after `:` is one error: "A constructor passes control with `: super(…)` or `: this(…)`."
  - CST: `ConstructorInitializer` on `Method.initializer` (decision 1).
  - Names: the arguments bind in the constructor's parameter scope.
  - Semantics: the arguments are a method body's expressions, except `this`, which is an error: "`this` is not available before the constructor runs: pass a parameter instead." That follows C#'s CS0027. It is needed for `: this(…)`, where no object exists yet, and the Slice applies it to both forms (open question 4).
  - Analyzer: the arguments are checked against the parent's constructor as `parent::__construct(…)` is checked in `crates/analyzer/src/expression/call/static_method_call.rs`, so argument count and type errors are the existing ones. `crates/analyzer/src/readonly.rs` and `crates/analyzer/src/statement/class_like/initialization.rs` count the initializer as the parent constructor call they already track. When no constructor exists above the class, `: super()` is accepted and does nothing, as C#'s `: base()` on `object`, and `: super(args)` is an error: "`Base` has no constructor to call: remove the arguments."
  - Analyzer, decision 2: a constructor without an initializer, in a class whose parent's constructor needs an argument, is `missing-super-call`: "`Child`'s constructor must call `Base`'s with `: super(…)`: `Base`'s constructor needs `row`."
  - Bridge: `Lowering::method` puts `parent::__construct(args)` after the `Set` conversions and the initial values and before the body, as `STATIC_CALL` of `parent`, the node `super.m()` already lowers to in `Lowering::method_call`. A constructor without an initializer gets `parent::__construct()` when `Types::parent_constructor` finds a constructor above the class. The constructor `Lowering::class` makes for initial values gets the same call.
  - Analyzer, the `Inherits` case: a class without a constructor, with a non-constant initial value, whose parent's constructor takes parameters, is `not-supported-yet` (below). That is the one shape the engine cannot run correctly yet.

## 9.1 A named constructor runs on the new object

"A named constructor runs on the new object, so it can set get-only properties and `init` properties."

Refused today, because named constructors are. `init` is refused everywhere: `app/BodyParity.sharp`'s `public string name { get; init; } = "";` printed `app/BodyParity.sharp:12:31:error - semantics: This accessor is not supported yet in PHP#.`

- Mechanism: bridge and analyzer (decisions 4 and 6).
  - The body runs on the object `: this(…)` created. `this` in it lowers to the hidden local `$this#1` instead of `$this`, `return;` lowers to `return $this#1;`, and a lambda in the body that reads `this` captures `$this#1`. The static method runs in the class's scope, so PHP lets it set a `readonly` property and reach private members.
  - Get-only properties: PHP sets a `readonly` property once, and the main constructor has already run. So the body can set a get-only property the main constructor leaves unset, and setting one it already set is `invalid-property-write`: "`manifest` is already set by the main constructor, and a get-only property is set once." The check reuses the re-initialization tracking `report_parent_constructor_reinitialization` already does in `crates/analyzer/src/readonly.rs`.
  - `init` properties: section 6.1's `init` is not built. When it is, the analyzer treats a named constructor's body as constructor code, the same predicate this Slice gives get-only writes, so `init` needs no change here.

## 9.1 The dependency container

"The dependency container always uses the main constructor (section 32)."

Nothing to observe: section 32's container is not built.

- Mechanism: none. A named constructor is a static method in PHP, so any container that reads `ReflectionClass::getConstructor()` sees only the main constructor.

## 9.1 No overloading

"There is no overloading by argument types."

Refused, with PHP's message (`app/Overload.sharp`, above). The Slice names the fix.

## 9.1 A lookup stays a static method

"A static method that looks up an existing object, such as `Journey.forUuid`, stays a static method."

Works. `app/Journey.sharp` compiled:

```csharp
namespace Demo;

class Journey
{
    public Journey(public string uuid { get; }) { }
    public static Journey forUuid(string uuid) => new Journey(uuid);
}
```

- Mechanism: none new.

# What a plain PHP caller sees

Every PHP# file compiles with `declare(strict_types=1)`, and class names are full names. The PHP below is the tree the bridge will emit, written as PHP.

**`new SalesReport(1)`**, for section 22's `SalesReport : Report`, given a plain PHP `Report` that declares `public function __construct(int $id)` (section 22 shows no constructor) and `public SalesReport(int id) : super(id) { }`:

```php
class SalesReport extends \App\Report
{
    public function __construct(int $id)
    {
        parent::__construct($id);
    }
}
```

A plain PHP caller writes `new SalesReport(1)`, and `Report`'s constructor runs first, as a hand-written PHP subclass runs it. With a non-constant initial value, `$this->label = …;` comes before `parent::__construct($id);`.

**`Artifact::fromJson($json)`**, for section 9.1's example:

```php
class Artifact
{
    public function __construct(public readonly \Demo\Manifest $manifest) {}

    public static function fromJson(string $json): \Demo\Artifact
    {
        ${'this#1'} = new \Demo\Artifact(\Demo\Manifest::parse($json));
        return ${'this#1'};
    }
}
```

`${'this#1'}` is how PHP source spells the local the bridge names `this#1`. No PHP# local can have that name. A plain PHP caller writes `Artifact::fromJson($json)`. PHP# writes `new Artifact.fromJson(json)`, which lowers to the same static call.

**Reflection:**

- `(new ReflectionClass(Artifact::class))->getConstructor()` is `__construct`, with one parameter `$manifest` whose `isPromoted()` is true, and a `readonly` property `manifest`.
- `getMethod('fromJson')` is a public static method, not a constructor (`isConstructor()` is false), with the parameter `string $json`, the return type `Demo\Artifact`, and the named constructor's attributes.
- The hidden local is invisible to reflection. Backtraces name the frame `Demo\Artifact::fromJson`, and `Position.current()` inside it names `Demo.Artifact.fromJson`, as `Lowering::enter` names any member.

**A plain PHP subclass:** `class LegacyArtifact extends Artifact {}`.

- `new LegacyArtifact($manifest)` runs `Artifact`'s constructor, which PHP inherits.
- `LegacyArtifact::fromJson($json)` returns an `Artifact`, not a `LegacyArtifact`, because the named constructor belongs to `Artifact`, as in C# and Dart (decision 5). `new static` would instead call `LegacyArtifact`'s constructor with `Artifact`'s arguments, which fails as soon as that constructor's parameters differ.
- A subclass that declares its own constructor calls `parent::__construct($manifest)` itself, as in any PHP class.
- A subclass that declares its own `fromJson` must keep a compatible signature, because PHP checks static methods for compatibility. A PHP# subclass meets the same check through `validate_method_signature_compatibility` (below).

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
mago-sharp
├── crates/syntax/src/
│   ├── cst/cst/class_like/constructor.rs      <- added:
│   │                                             ConstructorInitializer { colon, keyword (`super` | `this`), argument_list }
│   │                                             NamedConstructor { attribute_lists, modifiers, class, dot, name,
│   │                                                                parameter_list, initializer: Option<ConstructorInitializer>, body }
│   ├── cst/cst/class_like/method.rs           <- Method gains `initializer: Option<ConstructorInitializer>`; PHP never sets it
│   ├── cst/cst/class_like/member.rs           <- ClassLikeMember::NamedConstructor added
│   ├── cst/cst/instantiation.rs               <- Instantiation gains `constructor: Option<ConstructorName>`
│   │                                             ConstructorName { dot, name }: the `.fromJson` of `new Artifact.fromJson(json)`
│   ├── cst/cst/modifier.rs                    <- Modifier::Lazy added, contextual as Modifier::Required is
│   ├── cst/node.rs, walker/mod.rs             <- Node::ConstructorInitializer, Node::NamedConstructor, Node::ConstructorName
│   ├── error.rs                               <- ParseError::ComputedParameterInSharp(Span), at the `=>` (PR 1), and
│   │                                             ParseError::ConstructorInitializerInSharp(Span) for "A constructor passes
│   │                                             control with `: super(…)` or `: this(…)`." (PR 2)
│   └── parser/internal/class_like/method.rs   <- parse_named_constructor and parse_optional_constructor_initializer added;
│                                                 both NotSupportedYetInSharp("A named constructor") paths deleted
├── crates/hir/src/ir/item/modifier.rs         <- ModifierKind::Lazy, the mirror every Modifier variant has
├── crates/codex/src/
│   ├── metadata/flags.rs                      <- MetadataFlags::NAMED_CONSTRUCTOR added
│   └── signature_builder.rs                   <- walk_in_named_constructor: the static method a named constructor lowers to
├── crates/analyzer/src/code.rs                <- IssueCode::MissingSuperCall ("missing-super-call") and
│                                                 IssueCode::NamedConstructorWithoutNew ("named-constructor-without-new") added;
│                                                 reuses invalid-property-write and not-supported-yet
├── crates/semantics/src/internal/checker/sharp.rs
│                                              <- check_slice's doc gains the initializer, the named constructor, the guard clause
│                                                 and `lazy`; has_braces accepts the guard clause
├── crates/sharp-bridge/src/
│   ├── lower.rs                               <- Lowering::named_constructor added; Lowering::method emits parent::__construct
│   └── lower/types.rs                         <- Types::parent_constructor added: the constructor parent::__construct reaches
└── composer/src/Sdk/Syntax/NodeKind.php       <- regenerated with scripts/regen-sdk-node-kinds.php

the PHP the bridge emits
├── parent::__construct(args);                 <- `: super(args)`, after the initial values, before the body
├── parent::__construct();                     <- a constructor without `: super`, when a constructor exists above
├── public static function name(…): \Class     <- a named constructor, with its written access level
│     { ${'this#1'} = new \Class(…); …; return ${'this#1'}; }
└── \Class::name(args)                         <- `new Class.name(args)`

php-sharp
├── Zend/tests/sharp/Constructors.sharp        <- added: SalesReport, Artifact, implicit super, the guard clause
├── Zend/tests/sharp/Constructors.inc          <- added: a plain PHP caller, reflection, and LegacyArtifact
├── Zend/tests/sharp/constructors.phpt         <- added
└── ext/sharp/Cargo.toml, Cargo.lock           <- the pinned Mago commit moves
```

Not added: no new `sharp_kind`, no change to `ext/sharp/sharp.c`, no new crate. Two parse error variants are added, `ComputedParameterInSharp` and `ConstructorInitializerInSharp`, after the `UntypedFieldInSharp` precedent: every PHP# parse error with its own message has its own variant, and the generic one prints "Parse error encountered during parsing".

The names, each with the slate it was picked from:

- `ConstructorInitializer`: Roslyn's name for `: base(…)` and `: this(…)`. Also considered: `ConstructorChain`, `ConstructorDelegation`, `ConstructorCall`, `SuperOrThisCall`.
- `NamedConstructor`: the spec's own heading, and Dart's term. Also considered: `ConstructorDeclaration`, `SecondaryConstructor` (Kotlin), `ConvenienceConstructor` (Swift), `FactoryConstructor`.
- `ConstructorName`: Dart's analyzer node for `.name` after the class in `new A.name()`. Also considered: `NamedConstructorReference`, `ConstructorSelector`.
- `missing-super-call`: also considered `missing-constructor-initializer`, `missing-parent-constructor-call`, `implicit-super-needs-arguments`.
- `named-constructor-without-new`: also considered `named-constructor-call`, `constructor-called-as-method`, `missing-new`.

Every exhaustive match over `ClassLikeMember` gains the `NamedConstructor` arm, as each did for `Operator`: the analyzer's `analyze_class_like`, the codex scanner, `crates/fingerprint/src/class_like/mod.rs`, the formatter (which refuses `.sharp` files), `crates/hir/src/lower/item/member/mod.rs`, both semantics checkers, the bridge, and `crates/sharp-lean/src/translate/mod.rs`, where a law that reaches a named constructor is refused with the existing `CONSTRUCTOR_BODY` reason, "Mago does not translate constructor bodies to Lean."

# The PRs, in order

Each PR lands with tests on both sides where the engine is reached, and ends in something a developer runs. PR 1 is independent. PR 3 needs PR 2's `ConstructorInitializer`. PR 4 needs PRs 1 to 3.

1. **mago-sharp: the main constructor's refusals and the guard clause.** `has_braces` accepts an `if` without `else` around one `return` or `throw`. `Modifier::Lazy`, with its parameter error and its "not supported yet" elsewhere. The computed-parameter error. The second-constructor error. `check_slice`'s doc. Tests in `crates/syntax/tests/sharp.rs`, `crates/semantics/tests/sharp.rs` and `crates/sharp-bridge/tests/lower.rs` (the guard clause lowers to an `IF_ELEM` holding a `THROW`).
   - Run: `compile` on the scratch project compiles `Braceless.sharp` and `LambdaReturn.sharp`, and refuses `NotOnParameter.sharp`, `ComputedParameter.sharp` and `Overload.sharp` with one named error each.
2. **mago-sharp: `: super(…)`.** `ConstructorInitializer`, `Method.initializer`, `parse_optional_constructor_initializer`, the names scope, the `this` refusal, `: this(…)` refused on the main constructor, the analyzer's argument check and initialization tracking, `missing-super-call`, the `not-supported-yet` refusal for `Inherits`, `Types::parent_constructor`, the bridge's explicit and implicit `parent::__construct`, and `NodeKind.php` regenerated.
   - Run: `compile` compiles `Super.sharp` and `RequiredChildren.sharp`'s `Ledger`, refuses `Invoice` with section 25's rule, refuses `Child` with `missing-super-call`, and refuses `Inherits` as not supported yet.
3. **mago-sharp: named constructors.** `NamedConstructor`, `ConstructorName`, `parse_named_constructor`, `walk_in_named_constructor` with `NAMED_CONSTRUCTOR`, the analyzer's instantiation path, `named-constructor-without-new`, non-inheritance, `invalid-property-write` for an overwrite, the semantics rules, `Lowering::named_constructor`, the Lean refusal, and `NodeKind.php` regenerated.
   - Run: `compile` compiles `Artifact.sharp` and `ArtifactUse.sharp`. A file holding `Artifact.fromJson(json)` is refused with `named-constructor-without-new`.
4. **php-sharp: the engine runs section 9.** Bump the pinned Mago commit and add `Constructors.sharp`, `Constructors.inc` and `constructors.phpt` in all four opcache modes: `new SalesReport(1)` runs `Report`'s constructor, an implicit `super()` runs a parent's constructor, `new Artifact.fromJson(json)` and `Artifact::fromJson($json)` give the same object, reflection shows what "What a plain PHP caller sees" lists, `LegacyArtifact::fromJson($json)` returns an `Artifact`, and the guard clause throws. Waits for PRs 1 to 3.
   - Run: `sharp/bin/test Zend/tests/sharp/constructors.phpt` passes.

# What the Slice refuses as "not supported yet", and why

- **`via` on a constructor parameter, and on a property.** Unchanged: the parser's `skip_via_clause(true)` in `crates/syntax/src/parser/internal/function_like/parameter.rs` skips the clause and reports "`via` is not supported yet in PHP#." Section 6.4 defines what a behavior is, and nothing in this Slice can run one. Because it is a parse error, it is the file's only error until it is removed, so section 9's own example stays refused until section 6.4 lands.
- **`willSet`, `didSet` and `init`, on a parameter and in the class body.** Unchanged: "This accessor is not supported yet in PHP#." Sections 6.1 and 6.2 build them, in code parameters share.
- **`lazy` anywhere but a parameter.** "`lazy` is not supported yet in PHP#." Section 6.5 gives it a meaning. On a parameter it is a real error, because the spec forbids it there.
- **A class without a constructor, with a non-constant initial value, whose parent's constructor takes parameters** (`Inherits` above). "A class whose initial values run in its constructor declares one when its base class's constructor takes arguments: write `public Inherits(Row row) : super(row) { }`." Its initial values need a PHP constructor, and the bridge cannot copy the parent's parameter list, defaults included, from another file. Master accepts it and runs it wrong today.
- **Statements without braces other than the guard clause** keep today's "not supported yet", because the spec does not decide them.

The Slice also refuses, as errors, what PHP itself refuses once named constructors are static methods:

- **A subclass's named constructor that shares a parent's named constructor's name with incompatible parameters.** PHP refuses the class when it links, so `validate_method_signature_compatibility` reports it.
- **A named constructor named like another member of its class.** PHP refuses a second method of one name.

# Found outside the Slice

- **A get-only property that the constructor never sets compiles, and a write to it outside the constructor is only a warning.** `app/GetOnly.sharp`, with `public string label { get; }`, a constructor that sets only `count`, and `public void relabel(string label) { this.label = label; }`, compiled with `app/GetOnly.sharp:15:14: warning[possibly-invalid-property-write]: Readonly property `GetOnly.label` may already be initialized.` (from `mago analyze`). The second `relabel` call throws at runtime. Section 6.1 says a get-only property is set in the constructor. `check_property_initialization` in `crates/analyzer/src/statement/class_like/initialization.rs` exists and returns early unless `settings.check_property_initialization` is on. This belongs to section 6.1's Slice.

# Open questions for the Architect

1. **Implicit `super()`.** Section 9.1 says a constructor calls its base class's constructor with `: super(…)`, and does not say what a constructor without one does. Master runs PHP's rule, which never calls the parent, and that compiles code that throws (`Child` above). The Slice follows C#: no initializer means `super()`, and an error when the parent needs arguments. The other choice is to require `: super(…)` in every constructor whose class has a parent with a constructor, which makes every Eloquent subclass with a constructor write `: super()`. The spec would gain: "A constructor without `: super(…)` calls the base class's constructor with no arguments."
2. **A named constructor cannot overwrite what the main constructor set.** C# lets a constructor chained with `: this(…)` reassign a read-only member. PHP sets a `readonly` property once. The Slice refuses the overwrite. Allowing it would mean lowering get-only properties to `private(set)` instead of `readonly`, which changes every class's reflection and what plain PHP sees. The spec would gain, in 9.1: "It can set a get-only property the main constructor leaves unset."
3. **Braces.** The spec never states a rule. The Slice accepts only the two forms the spec writes. The spec would gain, in section 17 or beside section 9's example: "A block is in braces, except an `if` without `else` whose body is one `return` or `throw`."
4. **`this` in `: super(…)` and `: this(…)`.** The Slice refuses it in both, as C#'s CS0027 does. In `: this(…)` no object exists. In `: super(…)` the object exists in PHP, so allowing it there would work, but would make the two forms differ.

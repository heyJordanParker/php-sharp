# Slice 6: Fields and properties

Spec section 6 and 6.1 to 6.5, with the parts of sections 5, 9, 12, 16, 22, 29 and 32 that section 6 reaches. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Most of section 6's storage rules work on master `e19d4c27c8`. Fields, auto-properties, accessor levels on `set`, get-only properties, `init`, computed properties, accessor bodies, initial values and their order all compile and run. The nine failing examples fail for five reasons:

- **Four features are refused whole:** change observers (6.2), `nameof` (6.3), `via` (6.4) and `lazy` (6.5).
- **Overriding is refused, or silently wrong.** A property marked `override` is "Overriding a property is not supported yet". The field form the spec now forbids, `public override bool timestamps = false;`, compiles. A property that redeclares a PHP# parent's property without `override` compiles.
- **Two rules are silently accepted.** A get-only property that the constructor sets on one path only compiles, and its first read throws at runtime. A static `int misses;` with no initial value compiles.
- **A get-only `T?` set by the constructor is refused** as "not supported yet".
- **Three example lines need something outside section 6:** `internal set` (section 5), `new List<Tag>()` (sections 11 and 12), and a getter that calls the harness's plain PHP `Str.slug`, which has no `extern` declaration (section 29).

The Slice builds all of it in one php-sharp PR, `feat/properties`, across `sharp/mago`, the engine tests and the harness. Two open PRs already build part of section 6, and this PR lands after them:

- **#123 (structs)** builds `required`, object initializers, and the "set on every path through the main constructor" check for a non-nullable get-only property (its ruling 14, `MethodMetadata::assigned_properties`).
- **#102 (generics)** builds `new C<T>(…)`, which `via` needs if a behavior is generic (question 10).

13 questions about what users write go to Jordan. Each names its spec line. The parts they decide are designed below to the recommended answer and are built last.

## Questions for Jordan

Each question is about syntax or meaning. The recommendation is mine, and the build waits for the answer.

1. **Line 197, `internal set`.** `internal` is section 5's, and master refuses it everywhere with "This modifier is not supported yet in PHP#." (`internal_is_not_supported_yet` in `crates/semantics/tests/sharp.rs`). Options:
   - A: section 5's `internal` lands whole, in its own PR, before this one.
   - B: this PR builds `internal` on `set` and `init` only.
   - C: the example writes `protected set`.
   - Recommended: A. `internal set` checks a write against namespace reach, which is the same check every `internal` member needs. Building it for one position leaves two checks to merge later.
2. **Line 207, `new List<Tag>()`.** It is a parse error, and #102 does not add it: #102 parses type arguments after `new`, but `List` has no constructor. Section 12 creates a collection with a literal. Is `new List<Tag>()` PHP#? Recommended: no, and the example writes `= [];`. Example errata are team-lead's (slice 10, ruling 13).
3. **Lines 261 and 289, is every override a property?** Master overrides with a field, `protected override string table = "orders";` (decision 028, `Zend/tests/sharp/Store.sharp`). Line 281 refuses the `public` field form. Is `protected override string? table = "orders";` also an error, and is a PHP# parent's field overridden by a property? Recommended: yes to both. One form, a property, with the error "An override is a property: write `protected override string? table { get; set; } = "orders";`." Decision 028 is restated to the property form.
4. **Lines 286 to 288, which accessors an override of a plain PHP property takes.** PHP refuses a `readonly` redeclaration, and the parent's own code writes these properties. Recommended: `{ get; set; }` with no bodies, `set` at the property's own level. `get;` alone, `init`, accessor bodies, observers and `via` on it are errors that name the parent.
5. **Line 258, "overrides" of a PHP# property.** Section 22 closes a PHP# method unless it is `virtual` or `abstract`. Line 289 lets a PHP# parent's field be overridden. Recommended: a PHP# property follows section 22, so it is closed unless `virtual` or `abstract`, and section 22's five errors apply to it. A protected field stays open, as line 289 says, because a field has no `virtual`.
6. **Line 259, "Reflection that tells fields from properties".** A `protected` field and a `protected` auto-property both run as PHP's `protected` property today, so nothing tells them apart. What does code call? Options:
   - A: `ReflectionProperty::isField(): bool`, from a flag the bridge sets on each field, beside PHP's `isPromoted()` and `isVirtual()`.
   - B: an attribute the bridge writes on each field, read with `getAttributes()`.
   - C: C#'s two lists, `getFields()` and `getProperties()` on `ReflectionClass`.
   - Recommended: A. It is one flag and one method, and PHP's `getProperties()` keeps listing both, as plain PHP tools expect.
7. **Lines 191 and 252, an access level on `get`.** C# lets either accessor be narrower. PHP cannot run a `get` narrower than `set`. Master refuses `{ protected get; set; }` as "not supported yet". Recommended: only `set` and `init` take a level, as in Swift's `private(set)`, and a level on `get` is the error "Write the read level on the property, and a narrower one on `set`."
8. **Line 293, what an observer sees and when it runs.** Recommended, after Swift:
   - Both observers see `value`, the written value, and `field`, the storage, as accessor bodies do (line 256). In `didSet`, `field` is the new value. In `willSet` it is "not supported yet": before the first write there is no old value, and Jordan rules between `T?`, null before the first write, and a compile error.
   - `willSet` cannot change or stop the write.
   - Jordan's ruling: they run on every write, the constructor's and the initial value's included.
   - They run when the value is unchanged.
   - Beside a `set` body, the order is `willSet`, the `set` body, `didSet`.
   - They need `set`. On `init`, a get-only or a computed property, they are an error.
9. **Line 304, the value of `nameof(Order.total)`.** The harness's `Query.orderBy(string column)` takes it. Recommended: the string `"total"`, typed `string`, as C#'s `nameof`. It names an instance or static property visible at the call, never a field or a method. `nameof` becomes a keyword, as `typeof` is, so it can't name a local.
10. **Lines 319 to 328, what a behavior is.** The spec gives the clause and the wrapping order, not the class. Recommended:
    - A behavior implements the standard library interface `Behavior<T>`, with `T get(T value)` and `T set(T value)`. A generic interface needs #102.
    - `set` runs outermost first: `via Trimmed, Tracked` passes the written value to `Trimmed.set`, its result to `Tracked.set`, and that result to storage. `get` runs the other way, from storage through `Tracked.get` and then `Trimmed.get`.
    - The property keeps its own storage. A behavior sees neither the object nor the property's name.
    - PHP# creates each behavior with `new Trimmed()` once per object, while the object is created. Ruling 3 says PHP# creates only classes a module binds or that declare an `on` listener. Is a behavior a third kind? Recommended: yes, and a behavior takes no constructor arguments in v1.
    - `via` goes on an auto-property with `get` and `set` and no bodies. It is an error with observers, `lazy`, `static` or in an interface.
    - A behavior's `get` is a getter, so it never writes `this` (section 6.1).
11. **Lines 332 to 342, `lazy`.**
    - Jordan's ruling: a `lazy` body may have any effect, and a read carries it. "Getters must be pure." is gone from section 29: a `get` body may have any effect but never writes `this` (`getters_never_write_this`, `getter-writes-this`), and unlike a method marked `readonly` it may call plain PHP and unmarked PHP# methods. The kept value is the language's write.
    - Recommended form: only `lazy T name => expr;`. `lazy` with accessors or an initial value is an error.
    - If the body throws, nothing is kept and the next read runs it again, as Kotlin's `lazy` does.
    - `static lazy` lives "the lifetime of a `singleton` (section 32)". #121 makes a singleton live for the request. Recommended: a `static lazy` value is kept where #121 keeps singletons, so the two lifetimes are equal by construction. This needs #121. The other option is a PHP static, equal under PHP-FPM and different in a worker that serves many requests.
12. **Lines 201 and 237, a static initial value that is not constant.** `private static Plan plan = new Plan();` is refused today as "not supported yet". When does it run? Recommended: once, when the class is first used, as C#'s static initializer runs. The bridge runs it right after the class is declared, since a compiled file declares its class when the autoloader first needs it.
13. **Lines 196 and 299, a non-nullable settable property with no initial value.** `public int views { get; private set; }` and `public string name { get; set; didSet => … }` must compile, and the example classes have no constructor. Today the first read before a write throws PHP's "must not be accessed before initialization". Options: keep the throw, or start at the type's default (`0`, `""`, `false`) as C# does. Recommended: keep the throw, and name it in a sentence of section 6, since a default `""` hides a missing write.

## Decisions team-lead approves

1. **Order: this PR lands after #123 and #102,** and after #121 if question 11's answer keeps `static lazy` with singletons, and after section 5 if question 1's answer is A.
   - #123 builds `required` (line 257), object initializers, and the every-path check for a non-nullable get-only property, as `uninitialized-property` "`count` is get-only and never set: set it in the main constructor, or give it an initial value." This PR extends that check to `T?` and to the spec's wording. It adds no second definite-assignment mechanism.
   - If team-lead orders this PR first, it builds the every-path check on master's `artifacts.method_initialized_properties` and #123 adapts. That is the second mechanism the order above avoids.
2. **The harness gains three files,** listed in the PR body for review:
   - `sharp/harness/app/Stubs/Str.sharp`: `extern Str;`. `Str.slug` is pure plain PHP, and line 198's getter needs it declared. A probe with this file compiles `public string slug => Str.slug(this.name);` with no error.
   - `sharp/harness/app/Trimmed.sharp` and `sharp/harness/app/Tracked.sharp`, the behaviors line 323 names, in the shape question 10 settles.
3. **Static accessor bodies, static computed properties and get-only statics join this PR.** PHP has no hooks on a static property, so master refuses all three as "not supported yet". `static lazy` needs the bridge to lower every PHP# read of a static member into a hidden static method. That lowering also serves these three, which line 255's and line 256's bullets cover with no `static` exception.
4. **Section 9's parameter limits for `via`, `willSet`, `didSet` and `lazy` move with the features.** While a feature is refused, a parameter refuses it with the same "not supported yet". Once built, a parameter gives section 9's error, "declare balance in the class body to give it via", so line 446 passes. Line 447, an accessor body on a parameter, compiles today against section 9's rule. Recommended: this PR refuses it too, with the same message, so section 9's last entry leaves.
5. **The engine fixtures that override with a field move to the property form:** `Zend/tests/sharp/Store.sharp`, `RushOrders.sharp` and `TypedOrders.sharp`. An auto-property with no bodies lowers to the same PHP property a field does, so the expected output of `property_override*.phpt` does not change.
6. **The names below,** each with the slate it was picked from.

## How the states were observed

Every state below comes from the `mago` built from `feat/properties` at `e19d4c27c8` into `sharp/build/darwin-arm64/mago/release/mago`, with `CARGO_PROFILE_RELEASE_LTO=off`, run on one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-properties/ws`. It is a copy of the project `sharp/bin/spec` writes for an example: the harness, the standard library and `composer.json` mapping `App\` to `app/`. Each probe is one class in `app/Probe/`, named in the tables.

```sh
MAGO=sharp/build/darwin-arm64/mago/release/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-properties
env -u FORCE_COLOR XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=emacs MAGO_LOG=error \
  $MAGO --no-version-check --colors never --threads 2 --workspace $P/ws compile
```

`TEST_MAGO_EXECUTABLE=$MAGO php sharp/bin/spec` printed each failing line quoted in the tables. No engine is built in this worktree, so the engine part of each state is read from the bridge in `crates/sharp-bridge/src/lower.rs` and from the `Zend/tests/sharp/` test named beside it.

The states are **works**, **refused** (with the message), **silently accepted** (code the spec refuses compiles) and **wrong** (it compiles and runs differently from the spec).

# Every rule of section 6, its state and its mechanism

Paths under `crates/` are in `sharp/mago/crates/`. "Semantics" is `crates/semantics/src/internal/checker/sharp.rs`. "The bridge" is `crates/sharp-bridge/src/lower.rs`.

## Section 6: fields

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 190 | A field is `private` or `protected` | Works. `PublicField`: "A PHP# field cannot be `public`". Engine: `fields.phpt` | None new | `is_slice_property` |
| 190 | A field with no modifier is `private` | Works. `FieldReader`: "Cannot read private property `Fields.c`" | None new | The bridge's `ZEND_ACC_PRIVATE` in `Lowering::property` |
| 190 | There are no public fields | Silently accepted for `public override bool timestamps = false;` (`FieldOverride`), because `is_override` skips the check | Semantics refuses `override` on any field (question 3) | `is_slice_property` |
| 195 | `Map<string, Plan> cache = [:];` | Works | None new | |

## Section 6: properties

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 191 | A property has `get` and `set`, each with its own level | Works for `private set` and `protected set`. Refused on `get`: `GetAccess`, "This accessor is not supported yet" | Jordan's ruling on question 7: only `set` and `init` take a level, and one on `get` is the error "Only `set` and `init` take an access level", whose help says to read the property through a method inside the class. Lines 191, 252 and 483 say so, with the `access-on-get` example | `check_accessors` |
| 196 | `public int views { get; private set; }` | Works (`Views`). Reading before a write throws at runtime (question 13) | None new | |
| 197 | `{ get; internal set => field = value.trim(); }` | Refused: `InternalSet`, "This accessor is not supported yet" at `internal` | Question 1: built with section 5's `internal`, so the `field-and-property` example passes | |
| 198 | `public string slug => Str.slug(this.name);` | Refused: `PureGetter`, "Getter `slug` calls `Str.slug`, which has no `extern` declaration. Getters must be pure." | Harness `app/Stubs/Str.sharp` (decision 2). Since Jordan's ruling on question 11 a getter may call it without one | Section 29's `extern` |

## Section 6: initial values

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 201 | A field or auto-property has an initial value after its declaration | Works (`Initial`) | None new | |
| 207 | `= new List<Tag>();` | Refused: `NewList`, 4 generic parse errors at `<` | Question 2 | |
| 210 | Initial values run before the parent's constructor, in declaration order | Works. A constant is PHP's default. Any other runs at the start of the constructor, before `parent::__construct` (`Lowering::class`). Engine: `initial_values.phpt` (order), `constructors.phpt` (before `: super`) | None new | |
| 210 | An initial value can't read `this` | Works: `InitialThis`, "An initial value cannot use `this`" | None new | |
| 212 | A `T?` field or settable auto-property with no initial value starts as null | Works (`Profile`'s `total`, `nickname`). The bridge gives the default `null` (`is_null_by_default`), tested in `crates/sharp-bridge/tests/lower.rs`. No engine test | An engine row | |
| 212 | A get-only `T?` is set once, by its initial value or the constructor | `= null` works (`Profile`'s `memo`). Constructor-set refused: `Profile`, "A get-only nullable property without an initial value is not supported yet in PHP#." | Built: semantics no longer refuses it, and `check_get_only_initialization` holds it to every path. The bridge lowers it to `readonly` with no default, so the constructor's write is PHP's one `readonly` write. Engine: `overrides.phpt` (`Member`'s `note`) | `Lowering::property`, `accessor_flags` |
| 214 | A property with storage and no initial value, get-only or a settable one that isn't `T?`, is set on every path through the main constructor, or it is an error naming both fixes (Jordan's ruling on question 13) | Silently accepted, wrong: `Coupon` (`string code`, set on one path) and `NoConstructor` compile, and the first read of an unset one throws. `Claim` (`string?`) is refused as not supported | Built: `check_get_only_initialization` takes the class's members and adds each settable property that isn't `T?`, `init` or `required`, with the note "A property that isn't `T?` holds a value from the start". The spec's settable examples gain initial values. Engine: `overrides.phpt` (`Member`'s `level`) | #123's `MethodMetadata::assigned_properties` (decision 1) |
| 237 | A static that isn't `T?` needs an initial value, and the error names both fixes | Silently accepted: `Statics`' `misses` and `label` compile | Semantics, in `is_slice_property`'s static branch: "give `misses` an initial value, or make it `int?`" | |
| 237 | `public static string? region { get; set; }` starts as null | Works | None new | |
| 201, 237 | A static's initial value that is not constant | Refused: `StaticObject`, "A static member's initial value that is not constant is not supported yet" | Question 12 | |

## Section 6.1: property features

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 251 | Auto-properties | Works. Engine: `properties.phpt` | None new | |
| 252 | A separate access level per accessor | As line 191 | As line 191 | |
| 253 | Get-only properties, set in the constructor | Works, lowered to `readonly`. Engine: `properties.phpt`, `Badge.sharp` | None new | |
| 254 | `init` | Works (#110, mago-sharp #169). Engine: `constructors.phpt` | None new | |
| 255 | Computed properties | Works for instance members. Refused for `static`: "A static computed property is not supported yet" | Decision 3 | |
| 256 | Accessor bodies with `field` and `value` | Works for instance members. Engine: `Accessors.sharp`. Refused for `static`: "A static property with an accessor body is not supported yet" (`is_slice_property`) | Decision 3 | |
| 257 | `required`, enforced wherever the object is created | Refused: `Label`, "`required` is not supported yet". Object initializers are parse errors (`LabelUse`) | #123 builds both, with `missing-required-property` | #123 |
| 258 | Properties in interfaces | Works for `{ get; }` in the checker (`Named`, `Person`). The bridge lowers them (`Lowering::interface_property`). `{ get; set; }` is refused as "This class member is not supported yet", because `is_interface_property` takes only `get`. No engine test | `is_interface_property` takes `{ get; set; }`, both bare. The analyzer's `report_unfit_interface_property` already refuses a class that gives it get-only, naming `set`. Engine: `Sized` and `Box` in `Overrides.sharp` | |
| 258 | Abstract properties | Refused: `AbstractShape`, "This modifier is not supported yet" at `abstract` | Semantics accepts `abstract` on a property with bodiless accessors in an abstract class. The bridge writes PHP's `abstract public string $kind { get; }` | The analyzer's `unimplemented-abstract-property-hook` for a concrete subclass |
| 258 | Overrides | Refused: `Square`, "Overriding a property is not supported yet". Silently accepted: `Hexagon` redeclares `Shape.title` with no `override` | Questions 3 and 5. See the override section below | `check_class_like_properties`, `check_override_modifier_on_fields` |
| 259 | Reflection tells fields from properties | No: both run as PHP properties | Question 6: `ReflectionProperty::isField()` reads `ZEND_ACC_FIELD`, which the bridge sets on each field | |

### Section 6.1: overriding a plain PHP parent's property

`app/Over/P277.sharp` to `P280.sharp` hold one spec line each, and `F277.sharp` to `F279.sharp` the same line in master's field form. `Model` is the harness's `Illuminate\Database\Eloquent\Model`, with untyped properties typed by `@var`.

| Line | Code | Today | Mechanism |
|---|---|---|---|
| 271 to 274 | `protected override string? table { get; set; } = "orders";` | Refused: "Overriding a property is not supported yet" | Semantics accepts `override` on an auto-property. The analyzer's override checks move from fields to properties |
| 276 | `protected override table { get; set; }` | Generic parse error "Expected one of `Variable`, found `LeftBrace`", and the rest of the class fails to parse | A named parse error, `UntypedPropertyInSharp`: "write the type", after `UntypedFieldInSharp` |
| 277 | `public override` over a `protected` parent | Refused as not supported (`P277`) | None new. The field form gets `incompatible-property-access`, "The override `F277.fillable` is `public`, but `Model.fillable` is `protected`." (`F277`) |
| 278 | `= 5` on `string?` | Refused as not supported (`P278`) | None new. The field form gets `invalid-property-default-value` (`F278`) |
| 279 | `int` over `@var bool` | Refused as not supported (`P279`) | None new. The field form gets `incompatible-property-type`, "The override `F279.timestamps` has type `int`, which does not fit `bool`" (`F279`) |
| 280 | `= this.columns()` | Refused as not supported, and by "An initial value cannot use `this`" (`P280`) | None new |
| 281 | `public override bool timestamps = false;` | Silently accepted (`FieldOverride`) | Semantics: question 3's error, "PHP# has no public fields; write a property" |
| 282 | `protected string? table { get; set; }` with no `override` | Refused by the analyzer: "A property that replaces the untyped PHP property `Model.table` is not supported yet." | `check_override_modifier_on_fields` becomes `check_override_modifier_on_properties`, and reports `missing-override-attribute` for every property. The untyped-parent refusal in `check_class_like_properties` goes, because an override's written type is decision 028's case |

- **What the PHP side gets.** `Lowering::property` already writes `#[\Override]` from the `override` modifier and drops the written type over an untyped parent. An auto-property with no bodies lowers to the same PHP property a field does, `protected $table = 'orders'`. Engine: `property_override.phpt` and its chain and JIT variants, with decision 5's fixtures.
- **The initial value of an override** is no longer held to a constant. Line 288 says it runs before the parent's constructor, which `Lowering::class` already does. A class with a non-constant initial value whose parent has a constructor gets `missing-super-call` until it declares one (`Derived`), so the parent's arguments are never dropped.
- **A PHP# parent's property with accessor bodies** is overridden as a property with hooks. PHP 8.4 lets a child property add or replace hooks. The analyzer's `Overriding the PHP# property … is not supported yet` in `check_override_modifier_on_fields` goes.
- **Question 4's ruling, as built.** An override of a plain PHP property takes every accessor form PHP runs, `get` and `set` bodies included, typed parent or untyped. Two forms are errors that name the parent, from `report_plain_php_override_set` in `check_class_like_properties`, replacing the generic `readonly` and write-level messages over a plain PHP parent:
  - a get-only or `init` override: "`Model.table` is settable, and `Model`'s own code writes it, so its override `Order.table` has `set`." An `init` adds ", not `init`". `init` promises no write after creation, which the parent's own code breaks, so it falls under the same reason as get-only.
  - a `set` at another level than the parent's, while the read level matches: "`Model.timestamps` is set at `public`, so its override `Order.timestamps` is set at `public` too." Line 287 says the level matches, so a wider `set` is refused too.
- **Question 5, as built.** The codex marks a PHP# property that is neither `virtual`, `abstract`, `override`, `private` nor in an interface `FINAL`, as `scan_method` does for methods, and the analyzer's `override-final-property` says "Cannot override `Base.closed`: a PHP# property is closed unless it is `virtual` or `abstract`." A field stays open. An abstract property lowers as an interface property does, with hooks that have no body and no `readonly`.

## Section 6.2: change observers

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 293 | `willSet` runs before a write, `didSet` after it | Refused: `Observed`, `WillSet`, "This accessor is not supported yet" | Built, as below. Jordan's ruling on question 8: observers run on every write, the initial value's and the constructor's included. `field` in `willSet` is "not supported yet" until his ruling on its first-write value | `check_accessors`, `Lowering::hooks` |
| 299 | `public string name { get; set; didSet => this.touch(); }` | Passes: `change-observers` is out of the baseline | As above | |

- **Syntax:** none new. The parser already reads `willSet` and `didSet` as `PropertyHook`s by name. `PropertyHook::is_observer` names them, beside `is_setter`.
- **Semantics:** `is_slice_accessor` accepts each once, with a body, no modifier, no parameter list, on a property with `set` (question 8). On a parameter, decision 4's error.
- **Codex:** `scan_property_hook` counts the observers' bodies into the `set` hook, so `value` and `field` resolve as they do for a `set` body. Each observer keeps its own effect summary, `Body::Accessor` named `willSet` or `didSet`, which every write calls beside `set`.
- **The bridge:** `Lowering::hooks` writes one `set` hook: the `willSet` body, then the `set` body or the storage write `$this->name = $value`, then the `didSet` body. A `get` with no body stays PHP's backed read. An observed property's initial value, constant or not, is a write at the start of the constructor (`Property::initial_value_runs_in_constructor`).
- **Engine:** `Observers.sharp` and `observers.phpt`, with each order of question 8 as a row.

## Section 6.3: typed property references

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 304, 310 | `nameof(Order.total)` names a property and is typed | Refused: `NameOf`, "Function `nameof` could not be found", then "Cannot access instance property `Order.total` statically" | See below | `TypeOf` |
| 311 | `nameof(Order.totl)` is an error | Passes for the wrong reason: the error is "Function `nameof` could not be found" | The analyzer's `non-existent-property`: "Order has no property `totl`" | |
| 312, 315 | `Order.total` reads a static value, so it is an error for an instance property | Works: `References`, `invalid-static-property-access`, and a second error on the same line, `no-value` | None new | |

- **Syntax:** `NameOf { nameof, left_parenthesis, class, dot, property, right_parenthesis }`, after `TypeOf` in `crates/syntax/src/cst/cst/type_of.rs` and `parser/internal/type_of.rs`. `nameof` joins `SHARP_KEYWORDS` (question 9).
- **Names:** the class binds as `typeof`'s does.
- **Analyzer:** `expression/name_of.rs` resolves the property on the class, refuses a missing or invisible one, and types the expression `string`.
- **The bridge:** a string literal of the property's name.
- **Engine:** a row in `name_of.phpt` passing it to a PHP# method that takes `string`.
- **As built.** `nameof` names a field too, because team-lead's ruling makes it C#'s `nameof`, and the codex cannot tell a field from an auto-property without a new flag. A method is refused as "Property `Order.sum` does not exist.", and a property not readable at the call as "`Order.secret` is `private`, so `nameof` can't name it here." The expression is the literal string of the name, so `sharp-lean` translates it as that string, and a class law over it is proven.

## Section 6.4: reusable property behaviors

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 319, 323 | `public string name { get; set; } via Trimmed, Tracked;` | Refused at parse: `Via`, "`via` is not supported yet in PHP#." from `skip_via_clause` | See below, to question 10's recommendation | |
| 326 | A behavior implements `get` and `set` once | No | Question 10 | |
| 327 | One behavior instance per object, in a hidden slot | No | The bridge | Named constructors' hidden local `this#1` |
| 328 | Behaviors run in written order, each wrapping the next | No | The bridge | |

- **Syntax:** `BehaviorList { via, behaviors }` on `HookedProperty` and on a promoted parameter, parsed where `skip_via_clause` skips it today. `skip_via_clause` and its `NotSupportedYetInSharp("`via`")` go.
- **Names:** each behavior binds as a class name.
- **Semantics:** `via` only where question 10 allows it. On a parameter, decision 4's error.
- **Analyzer:** each behavior implements `Behavior<T>` for the property's type, as `invalid-property-behavior`: "`Trimmed` is a `Behavior<string>`, and `count` is an `int`."
- **The bridge:** one private property per behavior, named `name#via0` and `name#via1`, which PHP source can't spell, as the bridge already names `this#1`. The constructor fills them with `new \App\Trimmed()` before the initial values. The property gets two hooks: `get => $this->{'name#via0'}->get($this->{'name#via1'}->get($this->name))` and `set { $this->name = $this->{'name#via1'}->set($this->{'name#via0'}->set($value)); }`.
- **Standard library:** `sharp/composer/library/Sharp/Behavior.sharp` declares `Behavior<T>`, if question 10 says so.
- **Engine:** `Behaviors.sharp` and `behaviors.phpt`: the order of two behaviors, one instance per object, and two objects that keep separate behavior state.

## Section 6.5: `lazy`

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 332, 339 | `public lazy Plan plan => this.planner.plan(this.id);` computes on first read and keeps it | Refused: `Lazy`, "`lazy` is not supported yet in PHP#." (from `enter` in semantics, after the constructors Slice made `lazy` a modifier) | See below | `Modifier::Lazy`, `Lowering::property` |
| 332 | `static lazy` computes once for a singleton's lifetime | Refused: `StaticLazy`, "A static computed property is not supported yet" and "`lazy` is not supported yet" | Question 11 and decision 3 | #121 |
| 342 | Computed once, even if its inputs change | No | The bridge keeps the first value | |

- **Semantics:** `lazy` only on a computed property (question 11). It is no longer "not supported yet" outside a parameter. The parameter's error from the constructors Slice stays.
- **Analyzer:** the body is a `get` body, so `getters_never_write_this` covers it, and a read carries its effects.
- **The bridge, instance:** `public private(set) \App\Plan $plan { get => $this->plan ??= <body>; }`, a backed property whose `get` hook fills its own storage once. A `lazy T?` keeps a hidden `plan#lazy` flag beside it, because `??=` would run the body again after `null`.
- **The bridge, static:** PHP has no hooks on a static property. The value is held in a hidden static, or in #121's singleton store (question 11), behind a hidden static method `plan#lazy`. Every PHP# read of `StaticLazy.plan` lowers to a call of that method. Decision 3's static computed properties and accessor bodies use the same read lowering, without the keeping.
- **Engine:** `Lazy.sharp` and `lazy.phpt`: the body runs once, a changed input does not recompute, a throw keeps nothing, and a `static lazy` runs once per request.

# Queue 1: team-lead's answers, and what they needed checked

team-lead accepted this note and answered questions 1 to 6, 9, 10, the parts of 11 that are not purity, and 12. Questions 7, 8, 11's purity and 13 went to Jordan, and their parts wait. Decisions 1 to 6 are approved, line 447 included. Found item 1 is fixed in this PR.

## Question 2: `new List<Tag>()`

Section 12 makes a collection with a literal, under "**Literals:**" on line 816, and names no constructor for `List`, `Map` or `Set` anywhere. It does not refuse `new List<…>()` in words either. The checker's `List` (`crates/prelude/assets/extensions/sharp.php`) declares no constructor, and #102 adds none. A sweep of `docs/spec.md` and `README.md` for `new List<`, `new Map<` and `new Set<` finds line 207 and nothing else. So line 207 becomes `public List<Tag> tags { get; set; } = [];`, the form line 788 already writes for `Order.lines`.

## Question 4: what PHP runs on a redeclared plain PHP property

Stock PHP 8.5.1 ran each form a PHP# override can lower to, over a parent with a constructor that reads the property and a method that writes it. The scripts are `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-properties/php-hooks/forms.php` and `set-levels.php`.

| Form | Lowers from | Typed parent | Untyped parent |
|---|---|---|---|
| `protected $table = "orders";` | `{ get; set; }` | Runs | Runs, with the type dropped (decision 028) |
| `protected ?string $table` over an untyped parent | a written type kept | | Fatal: "Type of C::$table must be omitted to match the parent definition" |
| `{ get => …; }` and `{ set => …; }` hooks | accessor bodies, observers, `via` | Runs: the parent's constructor and its own writes go through the hooks | Runs |
| `protected readonly ?string $table;` | `{ get; }` | Fatal: "Cannot redeclare non-readonly property TypedParent::$table as readonly" | Fatal, the same |
| `protected protected(set)` on a protected parent | `init`, `protected set` | Runs: PHP drops a set level equal to the property's | Fatal: "Property with asymmetric visibility … must have type" |
| `public protected(set)` and `protected private(set)` | `init` on a public parent, any narrower `set` | Fatal: "Set access level of C::$timestamps must be omitted (as in class PublicTyped)" | Fatal: "must have type" |

So an override of a plain PHP property supports, as forms PHP runs:

- `{ get; set; }`, with or without accessor bodies, observers or `via`, typed or untyped.
- `{ get; init; }` and `{ get; protected set; }` only where the level equals the property's, which the bridge already drops (`accessor_flags`).

Two forms can't run as they lower today, and they are reported here before any refusal:

- **`{ get; }`**: PHP refuses `readonly` over a non-`readonly` parent, typed or untyped. It could run as a backed property with a `get` hook and no `readonly` (`typed-public-get-hook-only` runs). But the plain PHP parent's own code then writes it after the constructor, as Eloquent's `Model` does, so "set once" would not hold.
- **A `set` or `init` narrower than the property**: PHP refuses a set level the parent's property does not have, typed or untyped.

Proposed: both are the analyzer's error at the override, naming the parent: "`Model.table` is settable, so its override has `set`." and "`Model.timestamps` is set at `public`, so its override's `set` is too."

# Section 5: `internal`

Question 1's answer: section 5's `internal` is built whole in this PR, once team-lead approves this part. Every line of section 5 about `internal`, observed with the probes in `app/Tenant/` of the scratch project:

| Line | Rule | Today | Mechanism |
|---|---|---|---|
| 157 | `internal` reaches its namespace and every namespace below it | Refused everywhere: "This modifier is not supported yet in PHP#." on a member, a parameter, a declaration and an accessor (`internal_is_not_supported_yet`, and `InternalSet` at line 197) | Semantics lifts the refusal. The analyzer's member visibility check (`crates/analyzer/src/visibility/mod.rs`) gains a reach: the caller's namespace is the member's namespace or one below it. The bridge lowers `internal` to PHP's `public`, and `internal set` to no set level, because PHP has no namespace visibility. The rule is the checker's alone |
| 157 | `internal` is the default for classes | Silently accepted: `Hidden`, `class Hidden` with no modifier in `App.Tenant.Store`, is used from `App.Tenant.Other` (`SiblingUse`) with no error | The analyzer refuses a reference to a class, interface, enum or struct from outside its reach, wherever a type name is resolved: `new`, a type, `import`, a static access, `is`, `as`, `typeof` |
| 160 | A file's namespace matches its path | Silently accepted: `app/Tenant/Other/Mismatch.sharp` declares `namespace App.Tenant.Store;` and compiles | Semantics compares the file's `namespace` line with the PSR-4 folder Composer maps it to, which the compiler already reads for a law's Lean module name |
| 161 | A test folder may declare the namespace it tests, which gives it `internal` access | Not checked: `Tests/Tenant/HiddenTest.sharp` declares `App.Tenant.Store` and compiles, because nothing is checked | Line 160's check accepts a file in a test folder declaring any namespace. Proposed: a test folder is one Composer's `autoload-dev` maps, which is how a Composer project marks its tests. The spec does not define it, and #121 defines only a test file (one holding a test module). This is for team-lead to approve |
| 162 | A member is never more visible than its class | Not checked, because no class is narrower than `public` today | The analyzer's reach of a member is the narrower of its own and its class's, as C#'s effective accessibility is |
| 164 | Too narrow is a compile error at the caller | Works for `private` and `protected`. None for `internal` | Line 157's check, with the caller's namespace in the message: "`Hidden` is internal to `App.Tenant.Store`, and `App.Tenant.Other` is outside it." |
| 165 | Too wide is a warning, for example a `public` member nothing outside its namespace uses | None: `Wide.unusedOutside` is `public` and used only in its class, and `mago analyze` reports nothing | `too_wide_members` in `statement/class_like/unused_members.rs` warns on a `public` member that nothing outside its namespace and the ones below it uses, its own class's uses and a member nothing uses included. It reads every file's uses, so it runs once after `Effects::solve`, in `IncrementalAnalysisService::run_analyzer_selective` and `AnalysisService::oneshot`. It runs in a project only, a package whose `composer.json` says `"type": "project"` (Jordan's ruling): the loader sets `File::is_project`, and the codex marks the file's PHP# class-likes `MetadataFlags::PROJECT` |
| 166 | There is no named reach, such as `internal(App\Tenant)` | Refused with 8 generic "Parse error encountered during parsing" (`NamedReach`) | A named parse error at the `(`: "`internal` takes no namespace: move the shared helper to the parent namespace." |
| 183 | An interface member is as visible as its interface | Not checked (`InternalInterface` with no modifier is unreachable by no rule) | Line 162's effective reach, for interface members too |
| 184 | Interface members are exempt from the too-wide warning | Holds trivially today | Line 165's warning skips interface members |

What `internal` changes for code that compiles today:

- **Every class with no modifier becomes `internal`.** A `.sharp` class written `class X` and used from a sibling namespace stops compiling. That is the spec's rule, and it is the risk of this part. Every engine fixture, spec example, harness file and standard library file runs through it, and it refuses one: the bridge test fixture `Lib.Stamped` in `crates/sharp-bridge/tests/lower.rs`, which `App.Tenant` extends, so it is now `public`.
- **Plain PHP is unchanged.** A `.php` file's references are never checked against `internal`, because PHP has no namespace visibility and plain PHP is not checked.

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
sharp/mago/crates/
├── syntax/src/
│   ├── cst/cst/name_of.rs                   <- added: NameOf { nameof, left_parenthesis, class, dot, property,
│   │                                           right_parenthesis }
│   ├── cst/cst/expression.rs                <- Expression::NameOf
│   ├── cst/cst/class_like/property.rs       <- added: BehaviorList { via, behaviors }; HookedProperty gains
│   │                                           `behaviors: Option<BehaviorList>`; PropertyHook::is_observer
│   ├── cst/cst/function_like/parameter.rs   <- FunctionLikeParameter gains `behaviors: Option<BehaviorList>`, so
│   │                                           section 9's error points at it
│   ├── cst/node.rs, walker/mod.rs           <- Node::NameOf, Node::BehaviorList
│   ├── cst/cst/modifier.rs                  <- ModifierSequenceExt::contains_internal
│   ├── token/mod.rs                         <- "nameof" joins SHARP_KEYWORDS (question 9)
│   ├── error.rs                             <- ParseError::UntypedPropertyInSharp(Span), "write the type";
│   │                                           ParseError::NamedReachInSharp(Span), "`internal` takes no namespace"
│   └── parser/internal/
│       ├── name_of.rs                       <- added: is_at_name_of, parse_name_of
│       └── class_like/property.rs           <- parse_optional_behavior_list replaces skip_via_clause
├── semantics/src/internal/checker/sharp.rs  <- is_slice_property: the get-only T? refusal, the override refusal and
│                                               the static computed and accessor-body refusals go; the static
│                                               initial value rule and the field override error are added.
│                                               is_slice_accessor: willSet and didSet. enter: lazy, abstract,
│                                               virtual and section 9's parameter errors
├── database/src/file.rs                     <- added: enum FolderNamespace { Namespace(Box<str>), Test }; File gains
│                                               `folder_namespace: Option<FolderNamespace>`, which the loader sets
│                                               from the nearest composer.json's PSR-4 folders, and `is_project:
│                                               bool`, from its `"type": "project"`
├── codex/src/
│   ├── metadata/flags.rs                    <- MetadataFlags::LAZY: the bridge lowers a read of a static one;
│   │                                           MetadataFlags::INTERNAL_SET for `internal set` and `internal init`.
│   │                                           A PHP# class-like without `public` takes INTERNAL, and one of a
│   │                                           project takes MetadataFlags::PROJECT
│   ├── metadata/property.rs                 <- PropertyMetadata::behaviors: the classes `via` names
│   └── scanner/property.rs                  <- observers count into the `set` hook
├── analyzer/src/
│   ├── code.rs                              <- IssueCode::InvalidPropertyBehavior ("invalid-property-behavior"),
│   │                                           IssueCode::InternalAccess ("internal-access", an error),
│   │                                           IssueCode::TooWideMember ("too-wide-member", a warning)
│   ├── lib.rs                               <- pub use of too_wide_members, the crate's first re-export
│   ├── visibility/mod.rs                    <- the namespace reach of `internal`, checked at an import and at
│   │                                           each member access
│   ├── expression/name_of.rs                <- added: resolves `nameof`, types it `string`
│   └── statement/class_like/
│       ├── unused_members.rs                <- added: too_wide_members(codebase, references) -> IssueCollection
│       ├── override_attribute.rs            <- check_override_modifier_on_fields becomes
│       │                                       check_override_modifier_on_properties
│       ├── mod.rs                           <- the untyped-parent "not supported yet" goes
│       └── property.rs                      <- each `via` behavior fits the property's type
├── orchestrator/src/service/                <- analysis.rs and incremental_analysis.rs call too_wide_members after
│                                               Effects::solve
├── sharp-bridge/src/lower.rs                <- Lowering::hooks (observers, behaviors), Lowering::property (lazy,
│                                               abstract, behavior slots), Lowering::name_of, the static member
│                                               read of a lazy, computed or hooked static
└── (every exhaustive match over Expression)  <- the NameOf arm: formatter, fingerprint, hir, sharp-lean, as each
                                                did for TypeOf
sharp/mago/composer/src/Sdk/Syntax/NodeKind.php   <- regenerated with scripts/regen-sdk-node-kinds.php
sharp/composer/library/Sharp/Behavior.sharp       <- added if question 10 says so: interface Behavior<T>

the PHP the bridge emits
├── public string $name { set { <willSet>; $this->name = $value; <didSet>; } }
├── public private(set) \App\Plan $plan { get => $this->plan ??= <body>; }
├── private \App\Trimmed ${'name#via0'};     <- one per behavior, filled in the constructor
├── abstract public string $kind { get; }
├── 'total'                                  <- nameof(Order.total)
└── public int $views = 0;                   <- `internal` runs as public, `internal set` takes no set level, and
                                                `internal init` takes protected(set), as every `init` does

php-sharp
├── docs/architecture/slices/06-properties.md   <- this note
├── docs/decisions/028-plain-php-property-override.md   <- the property form (question 3)
├── docs/spec.md                                <- errata team-lead rules on (questions 1, 2, 13)
├── ext/reflection/php_reflection.c, .stub.php  <- ReflectionProperty::isField(): bool, beside isVirtual
├── Zend/zend_compile.h                         <- ZEND_ACC_FIELD (1 << 15), a property flag the bridge sets on
│                                                  each field, a promoted one included
├── Zend/zend_compile.c                         <- a promoted parameter keeps ZEND_ACC_FIELD
├── Zend/tests/sharp/
│   ├── Store.sharp, RushOrders.sharp, TypedOrders.sharp   <- the property form (decision 5)
│   ├── Observers.sharp, observers.phpt         <- added
│   ├── NameOf.sharp, name_of.phpt              <- added
│   ├── Behaviors.sharp, behaviors.phpt         <- added
│   ├── Lazy.sharp, lazy.phpt                   <- added
│   ├── Reach.sharp, reach.phpt                 <- added: `internal` runs as public
│   └── Overrides.sharp, overrides.phpt         <- added: a get-only T? set by the constructor, a T? that starts as
│                                                  null, an interface property, an abstract property, a PHP#
│                                                  property override, static computed and hooked properties, and
│                                                  reflection. Named so because master's Members.sharp, which
│                                                  class_members.inc and static_members.phpt load, holds the name
├── sharp/harness/app/Stubs/Str.sharp           <- added: extern Str;
├── sharp/harness/app/Trimmed.sharp             <- added
├── sharp/harness/app/Tracked.sharp             <- added
└── sharp/baseline/spec/§6*/                    <- the nine section 6 entries leave, and §9/parameter-member-limits
                                                   if decision 4 includes line 447
```

Not added: no new `sharp_kind`, no change to `ext/sharp/sharp.c` and no new crate. Every node the bridge emits is a kind master already emits. An added fixture whose PHP has a hand-written twin joins `opcodes.phpt`, as `Store.sharp` and `Store.inc` do.

The names, each with the slate it was picked from:

- `NameOf`: the keyword and C#'s `NameOfExpression`, beside `TypeOf`. Also considered: `PropertyReference`, `MemberName`.
- `BehaviorList`: what the clause holds, beside `PropertyHookList`. Also considered: `ViaClause`, `BehaviorClause`, `Delegation` (Kotlin).
- `PropertyHook::is_observer`: the spec's heading word. Also considered: `is_change_observer`, `is_watcher`.
- `UntypedPropertyInSharp`: beside `UntypedFieldInSharp`. Also considered: `MissingPropertyType`.
- `invalid-property-behavior`: what is wrong, at the `via`. Also considered: `behavior-type-mismatch`, `invalid-via`.
- `MetadataFlags::LAZY`: the modifier's own word.
- `check_override_modifier_on_properties`: the function it was, for every property.
- `name#via0` and `plan#lazy`: hidden names, after `this#1`. Also considered: `name#behavior0`, `plan#value`.
- `ZEND_ACC_FIELD` and `isField`: the spec's word, beside `ZEND_ACC_VIRTUAL` and `isVirtual`. Also considered:
  `ZEND_ACC_SHARP_FIELD`, `isStorage`.
- `FolderNamespace`: what the folder says about the namespace. Also considered: `PathNamespace`, `Psr4Namespace`.
- `NamedReachInSharp`: the spec's "named reach", beside `UntypedPropertyInSharp`. Also considered: `InternalWithNamespace`.
- `internal-access`: the modifier and the act, as `private` access is reported. Also considered: `internal-reach`.
- `too-wide-member` and `too_wide_members`: the spec's "too wide". Also considered: `overly-public-member`.
- `File::is_project` and `MetadataFlags::PROJECT`: Composer's own word, `"type": "project"`. Also considered:
  `is_application`, `IN_PROJECT`.

# The PR

One php-sharp PR, `feat/properties`, whose body says `Completes: §6, §6.1, §6.2, §6.3, §6.4, §6.5` and lists the harness files.

Commits land in this order, each with its tests seen failing first:

1. **Overrides and the field rules:** question 3's field error, override on properties, `UntypedPropertyInSharp`, the moved analyzer checks, abstract and `virtual` properties, the fixtures of decision 5. `plain-php-property-override` passes.
2. **Statics and nullable get-only properties:** the static initial value rule, the get-only `T?` lift and its every-path check on #123's facts. `static-initial-values` and `nullable-members` pass.
3. **Observers.** `change-observers` passes.
4. **`nameof`.** `property-references` passes.
5. **`lazy` and the static read lowering of decision 3.** `lazy-property` passes.
6. **`via`.** `property-behaviors` passes.
7. **The example lines outside section 6:** `extern Str;` and the answers to questions 1 and 2. `field-and-property` and `initial-values` pass.
8. **Reflection,** to question 6's answer.

Run, before the PR opens:

- `sharp/bin/spec` shows every section 6 example passing, and `BODY=… sharp/bin/spec --completes` passes.
- `sharp/bin/test --opcache --repeat --passes` on every `Zend/tests/sharp/` test this PR adds or changes.
- `cargo test` on `mago-syntax`, `mago-semantics`, `mago-codex`, `mago-analyzer` and `mago-sharp-bridge`.
- `mago analyze` on a plain PHP project with properties, overrides and hooks reports exactly what master reports.

# The tests

One test per rule, with each form as a row, in the crate that owns the rule:

- `crates/syntax/tests/sharp.rs`: `nameof` and `via` parse, `UntypedPropertyInSharp`, and the spec syntax test that lists `via` as not supported yet loses that row.
- `crates/semantics/tests/sharp.rs`: a field can't override; a static that isn't `T?` needs an initial value; observers' placement; `lazy`'s placement; section 9's parameter errors. The tests named `*_is_not_supported_yet` for overrides, get-only `T?`, `lazy` and observers become the tests of the built rule.
- `crates/analyzer/tests/sharp.rs`: every-path for `T?`; each override line 271 to 282; `nameof`'s missing property; a behavior's type; `lazy`'s purity.
- `crates/sharp-bridge/tests/lower.rs`: the PHP each feature lowers to, read back as PHP source.
- `Zend/tests/sharp/`: each runtime behavior above, in all four opcache modes.

# Found outside the Slice

1. **A property named like its class is refused.** A probe `class Code` with `public string code { get; }` and a constructor printed "The class `Code` declares a method and a property named `Code`." `report_methods_named_as_properties` in semantics compares names ignoring case and counts the constructor, whose PHP name is `__construct`, as a method named `Code`.
2. **Section 9 line 447 is silently accepted:** an accessor body on a constructor parameter compiles against section 9's rule. Decision 4 proposes the fix.
3. **Line 311 of section 6.3 passes for the wrong reason today,** on "Function `nameof` could not be found". The check counts any error that is not "not supported yet" or a missing class.
4. **Decision 028 states the field form,** which section 6.1 now refuses on line 281.

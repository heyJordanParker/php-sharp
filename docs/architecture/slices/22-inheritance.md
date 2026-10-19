# Slice 22: Inheritance

Spec section 22, with the parts of sections 6.1, 11, 15, 20 and 26 that section 22 reaches. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Class headers, `virtual`, `override`, `super` and plain PHP overrides work on master. Interface default bodies and plain PHP traits do not:

- **Default bodies are refused.** `string greeting() => …;` in an interface gets "Interface method `Named.greeting` is implicitly abstract and cannot have a concrete body." and "This construct is not supported yet in PHP#.". Nothing builds the trait `<Interface>\Defaults`, so a plain PHP class's `use HasDesign\Defaults;` names a trait that does not exist. `php-trait-defaults` passes only because `sharp/bin/spec` runs `php -l` on a block that is plain PHP alone.
- **A plain PHP trait in a header is refused.** `public class Draft : Copies { }` gets "Class `Draft` cannot implement non-interface type `Copies`".
- **A plain PHP trait as a type is silently accepted and runs wrong.** `entity is HasFactory` gives `false` for a `LegacyEntity`, which uses `HasFactory`. `seed(HasFactory owner)` throws `TypeError` when PHP# passes it a `LegacyEntity`.
- **`final override` is refused** with "This modifier is not supported yet in PHP#.".
- **`super.draw()` on an abstract parent method compiles** and throws `Error: Cannot call abstract method` at runtime.
- **The `interface-defaults` example also needs `{ get; set; }` interface properties**, which section 6.1 owns and master refuses.

The Slice builds all of section 22 in one PR. Zend/ does not change:

- The bridge lowers a header trait into the `use` php-src builds for `use HasFactory;`, beside the `extends` and `implements` that `Lowering::class_header` already writes from the checker's kinds.
- The bridge lowers an interface's default bodies into the trait `<Interface>\Defaults`, in the interface's own compiled file. Every PHP# class, enum and event that implements the interface uses that trait.
- The one engine change is a trait-aware match in #102's value check, so a trait type tests and checks like an interface. The PR merges after #102.

## Decisions team-lead approves

1. **A header trait lowers as `use`, decided by the bridge from the codebase.** `Types::class_declaration` gains `DeclarationKind::Trait`, and `class_header` leaves a trait out of `implements`. The class's `use` list is the codex's `used_traits` for it, which holds its header traits and its interfaces' `Defaults` traits. This replaces the engine route of php-sharp `feat/slice-traits` (`8a8c4298ce`), which sorted traits out of the interface list in `zend_inheritance.c` and opcache. That route predates `class_header`, and master's engine no longer sorts headers.
2. **`<Interface>\Defaults` is one trait per interface with a default body, in the interface's compiled file.** The bridge declares it as `HasDesign\Defaults` in the file's namespace, so every name in a body resolves as it does in the interface. This is read from code and not run yet: `zend_compile_class_decl` checks only the part after the last `\` against PHP's reserved class names, so the engine declares `App\HasDesign\Defaults`. Each default body is a `public` method of the trait, and the interface's own methods are all abstract, as PHP requires.
3. **The codex registers the same trait, and the interface's methods stay abstract in the codex.** A PHP# class, enum or event that implements the interface, and whose parent class does not, gets the trait in `used_traits` from the populator. A plain PHP class that implements it without `use HasDesign\Defaults;` keeps mago's `unimplemented-abstract-method`, because PHP fatals on it.
4. **A default body is analyzed as a method of the interface,** so `this` is `HasDesign`. Line 2380's rule, "a default body sees only the interface's own members", is mago's own `non-existent-property` and `non-existent-method` on `this`.
5. **`X\Defaults` autoloads with `X`.** `sharp/composer/autoload.php` gets one rule: a class whose name ends in `\Defaults` is in the file of the name before it, through the class map and through PSR-4. Section 23 forbids a `HasDesign/` folder beside `HasDesign.sharp`, so no other file can claim the name.
6. **A trait type takes #102's path for a type PHP cannot test.** A parameter, result or property typed `HasFactory` lowers to PHP's `object`, and its PHP# type text carries `HasFactory`, as #102 lowers `List<int>` to `array`. `is`, `as` and a `match` arm on a trait lower to #102's engine test. `sharp_object_is` in `ext/sharp/sharp.c` matches a trait when the object's class, a parent, or a trait one of them uses, uses it.
7. **Five errors become rules instead of refusals or PHP's words.** Each is gated to `.sharp` files, so plain PHP analysis does not change:
   - an interface field: "An interface holds no fields: declare `count` in the class that implements it."
   - replacing a closed PHP# method: "`Base.save` is not `virtual`, so no subclass replaces it." This replaces "Cannot override final method", which names a `final` the code never wrote.
   - two classes in a header: "`TwoClasses` names two base classes, `DatabaseEntity` and `Report`." This replaces "cannot implement non-interface type `Report`".
   - `super.m()` when the parent's `m` is abstract: "`Shape.draw` is abstract, so `super.draw()` has no body to run."
   - two header traits, or two `Defaults` traits, that give the same method, which PHP refuses when it links the class. Question 2 decides the wording.
8. **A default body in a generic interface reads the type arguments each class gives the interface.** The codex gives the trait the interface's type parameters, as `@template` on a trait does, and each class the type arguments it gives the interface, as `@use` does. The bridge gives the trait the header `App.Keyed<$0>`, and the engine maps a trait's code, and a method a class took from a trait, to the interface the header names, so `is`, `new` and the checks of a call from plain PHP read the class's type arguments.

## Questions for Jordan

Each one decides what users write. The note recommends, and decides none.

1. **Lines 2253, 2345 and 2397: does `override` mark a method that an interface or a trait gives?** On master, `override` is optional on a method that implements an abstract interface method (`WithOverride` and `WithoutOverride` both compile). Three cases are open:
   - implementing an abstract interface method
   - replacing a default body
   - replacing a method a header trait gives
   Recommended: refuse `override` on the first, as C# does (CS0115), and require it on the second and third, because each replaces a body, as line 2253 requires for a base class's method. Kotlin requires it on all three.
2. **Lines 2345 and 2397: what happens when two places give a class the same method?** The cases are a default body against a base class's method, two interfaces' default bodies, two header traits' methods, and a sub-interface that gives a body to a method of the interface it extends. PHP runs a trait's method over an inherited one, and refuses two traits' methods with the same name. Recommended: a compile error at the class unless the class declares the method itself, as Kotlin requires. A sub-interface's body replaces its parent's, as in Java, and writes `override`.
   Decided: one body per member, so two places give a class a member only as a compile error the class fixes by writing it, and `super<Interface>` reuses one body. A header trait replaces the base class's method, as PHP's `use` does, and counts against a default body only. A sub-interface replaces its parent's default body with `override`, and the most specific interface's body wins, as in Java, C# and Kotlin: the class's `use` takes it `insteadof` the parent's.
3. **Line 2331: is `final` allowed on a method without `override`?** A PHP# method is already closed unless it is `virtual`. Recommended: a compile error, as C# refuses `sealed` without `override` (CS0238).
4. **Line 2397: can an enum's header name a plain PHP trait?** "It works everywhere an interface does", an enum header takes interfaces, and PHP enums can `use` traits that have no properties. Recommended: yes, with PHP's rule that the trait has no properties as the error.
5. **Line 2431: which traits does Reflection list?** Lowered to `use`, `ReflectionClass::getTraitNames()` and `class_uses()` list a class's own header traits and its `Defaults` traits, and a subclass lists neither, as PHP lists traits. `getInterfaceNames()` does list inherited interfaces. Recommended: keep PHP's lists as they are, `Defaults` traits included, because Laravel's `class_uses_recursive` already covers the inherited case.
6. **Line 2253: is a closed PHP# method closed to a plain PHP subclass at runtime?** `mago analyze` reports "Cannot override final method `App\DatabaseEntity::hash()`" for the plain PHP subclass, and the engine runs its replacement, which returned `99`. Recommended: keep it open at runtime, as master does, so PHPUnit and Mockery can still mock a PHP# class.
   Decided in the review of #181: closed at runtime too. The bridge lowers a closed method `final`, as Kotlin emits a member that is not `open` as a final JVM method, so the engine refuses a plain PHP subclass that replaces it, and `mago compile` refuses that subclass first. A test double replaces only `virtual` methods, as Mockito replaces only `open` Kotlin members without the `all-open` plugin.
7. **Not in the spec: method modifier pairs that C# refuses.** Master compiles each of these with no error: `private virtual` (CS0621), `virtual override` (CS0113), `abstract virtual` (CS0503), `static virtual` and `static override` (CS0112), and a `virtual` method in a `final` class (CS0549). Recommended: refuse each one with C#'s rule. `virtual` on an interface method is a generic parse error today, and becomes "An interface method takes no modifier".

## How the states were observed

- **mago:** release, built from master `660ad5a094` with `CARGO_PROFILE_RELEASE_LTO=off`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 12.
- **Engine:** `sharp/bin/build` at `660ad5a094`, PHP 8.5.11 NTS DEBUG.
- **Spec run:** `sharp/bin/spec` with that pair. Section 22 has 8 examples. `class-header`, `virtual-methods`, `plain-php-overrides`, `override-parameter-names`, `super-calls` and `php-trait-defaults` pass. `interface-defaults` and `plain-php-traits` fail. The run printed:

```text
FAIL §22 interface-defaults (docs/spec.md:2348)
  docs/spec.md:2356 expects no error, and the checker reports: Interface method `HasDesign.designKey` is implicitly abstract and cannot have a concrete body.
  docs/spec.md:2354 expects no error, and the checker reports: This class member is not supported yet in PHP#.
  docs/spec.md:2357 expects a compile error, and the checker reports: This construct is not supported yet in PHP#.
FAIL §22 plain-php-traits (docs/spec.md:2400)
  docs/spec.md:2417 expects no error, and the checker reports: Class `Draft` cannot implement non-interface type `Copies`
  docs/spec.md:2407 expects no error, and the checker reports: Class `App.Order` cannot implement non-interface type `HasFactory`
```

- **Probes:** the checker probes are in `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-inheritance/ws/app/Probe/`, a copy of `sharp/bin/spec`'s harness project. `compile.sh ws` runs `mago --workspace ws compile` with `MAGO_REPORTING_FORMAT=emacs`, and every message below is its output. The engine probes are in `run/app/Probe/`, compiled the same way and run by `run.php` on the engine. Their output:

```text
superCall: 'entity (page)'
isTrait: false
seedLegacy: TypeError: App\Probe\Runs::seed(): Argument #1 ($owner) must be of type Illuminate\Database\Eloquent\Factories\HasFactory, App\LegacyEntity given
listOfTrait: 1
plain PHP subclass replaces a closed method: 99
super of an abstract method: Error: Cannot call abstract method App\Probe\Shape::draw()
```

# Every rule of section 22, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), and **wrong** (it compiles and runs differently from the spec).

## The class header

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2246 | The header lists the base class and interfaces after `:`, and the checker knows which name is the class | Works in any order: `ClassFirst : DatabaseEntity, Linkable, Shareable` and `ClassLast : Linkable, Shareable, DatabaseEntity` compile | None new. The populator makes the one class the parent (`populator/hierarchy.rs`), and `Lowering::class_header` writes `extends` and `implements` from `Types::class_declaration` |
| 2246 | Two classes in one header | An error, in PHP's words: "Class `TwoClasses` cannot implement non-interface type `Report`" | The analyzer's PHP# message (decision 7) |
| 2246 | An interface header names only interfaces | Works: `ExtendsClass : Report` gets `invalid-extend`, and so does `ExtendsTrait : Copies` | None new |

## Methods of a PHP# class are closed unless opened

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2253 | A method is closed unless `virtual` or `abstract` | Works for PHP# subclasses: `override` of `Base.save` gets `override-final-method`. A plain PHP subclass replaces it at runtime (question 6) | `scan_method` marks the method final to the analyzer, and the bridge lowers it `final`, so the engine refuses a plain PHP subclass that replaces it |
| 2253 | A subclass replaces a method only with `override` | Works: `MissingOverride.label` gets `missing-override-attribute`, and so does `AbstractWithoutOverride.render` | None new. `check_override_attribute` |
| 2262, 2274 | `virtual` opens a method, `override` replaces it | Works: `GoodChild` compiles, and the engine runs `Article.label()` | None new |
| 2253 | Modifier pairs the spec does not state | Silently accepted (question 7) | Semantics, after question 7 |

## Methods of a plain PHP class are open unless PHP marks them `final`

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2294 | `override` of an abstract PHP method | Works (`PlainGood.render`) | None new |
| 2295 | `override` of a method PHP does not mark `final` | Works (`PlainGood.title`) | None new |
| 2296 | Replacing without `override` | Works: `PlainAbstractNoOverride.render` gets `missing-override-attribute` | None new |
| 2297 | `override` of a PHP `final` method | Works: the spec's own line gets the error | None new |

## The compile errors

| Line | Error | State on master | Mechanism |
|---|---|---|---|
| 2303 | A missing `override` | Works, for a PHP# and a plain PHP parent | None new |
| 2304 | `override` when the parent has no such method | Works: "Invalid `override` modifier on `OverrideNothing.nothing`", and `PlainNothing.absent` | None new |
| 2305 | Overriding a PHP# method that is not `virtual` or `abstract` | Works, in PHP's words: "Cannot override final method `Base.save`". `ReplaceClosed.save`, with no `override`, gets that and "Missing `override`", whose advice cannot fix it | The analyzer's PHP# message, and no missing-`override` error on a closed method (decision 7) |
| 2306 | Overriding a plain PHP `final` method | Works | None new |
| 2307 | An override renames a parameter, and the error names both names | Works: "Parameter #1 of `Renamed.pair` is named `w` but parent `Renames.pair` names it `width`". A plain PHP parent gets one error per renamed parameter | None new. `incompatible-parameter-name` |

## Classes are open unless `final`, and `final override`

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2331 | A class is open unless `final` | Works: `FromSharpFinal : Closed` and `FromPhpFinal : LegacyEntity` get `extend-final-class`. The bridge lowers `final` to `ZEND_ACC_FINAL`, so the engine refuses a plain PHP subclass too | None new |
| 2331 | `final override` stops an override chain | Refused: "This modifier is not supported yet in PHP#." at `final`. The analyzer already reads it as final: `PastFinal.kind` gets `override-final-method` | Semantics accepts `final` beside `override` on a method (question 3 decides `final` alone). `modifier_flags` lowers it to `ZEND_ACC_FINAL` |

## `super`

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2342 | `super` calls the parent's version | Works: the engine gave `'entity (page)'`. A plain PHP parent's method works too (`Tile.title`) | None new. `super.m()` lowers to `parent::m()` |
| 2342 | The parent's version of an abstract method | Wrong: `super.draw()` compiles and throws "Cannot call abstract method" | The analyzer's error at the call, in `resolver/static_method.rs`, for `.sharp` files (decision 7) |

## An interface can give a method a default body

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2354 | `List<string> claims { get; set; }`, an abstract property with `set` | Refused: "This class member is not supported yet in PHP#." `{ get; }` alone works | Section 6.1, line 258. See the boundary with properties |
| 2355 | `string designColumn { get; }` | Works | None new. `Lowering::interface_property` |
| 2356 | `string designKey() => …;` has a default body | Refused, as above | Semantics: a PHP# interface method may have a block or an expression body, checked as a class method's body, and PHP's two "interface method body" errors skip `.sharp` files. The names walker adds an interface's members, so a bare `designColumn` gets "Write `this.designColumn`". The codex registers the trait (decisions 2 and 3), and the bridge lowers it |
| 2357, 2380 | A default body sees only the interface's own members | Refused, as above, so the rule cannot run yet | The analyzer analyzes the body with `this` as the interface (decision 4). `this.id` gets `non-existent-property` |
| 2368, 2369 | A class gives the storage | Works: `Note`'s `{ get; set; } = []` and `=> "design"` implement the interface's properties | None new |
| 2377 | `page.designKey()` gives `"design:design"`, from the default body | Not reachable: the interface does not compile | The class's `use App\HasDesign\Defaults;`. The engine runs PHP's trait method |
| 2381 | An interface holds no fields | An error, in PHP's words and as a refusal: "Interface `HoldsField` cannot have non-hooked properties." and "This class member is not supported yet in PHP#." | One PHP# error (decision 7) |
| 2382 | The interface compiles to a PHP interface plus the trait `<Interface>\Defaults` | Not built | The bridge's `Lowering::defaults`, after mago-sharp `feat/slice-traits` |
| 2382, 2390 | A plain PHP class that implements it `use`s the trait to get the defaults | Silently accepted by the spec run, which only lints `php-trait-defaults`. The trait does not exist | The codex's trait for `mago analyze`, and the autoload rule (decision 5) for the engine. An engine test runs `LegacyPage.designKey()` |
| 2382 | Section 23's class-and-namespace rule does not apply to the trait | Holds by construction: the trait is never a file or a folder | None |

A default body in a generic interface reads the class's type arguments (decision 8). An interface's computed property, `string label => …;`, is a default body too: the interface states it as `public mixed $label { get; }`, the trait holds its body as the protected method `label@App\HasLabel`, and each class that gets the body declares `public mixed $label { get => $this->{'label@App\HasLabel'}(); }`, which a plain PHP class writes with its own type. An enum has no property, so an enum that implements the interface is an error that names the property.

## A plain PHP trait

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2397, 2407 | A trait is listed in the header with the base class | Refused: "Class `App.Order` cannot implement non-interface type `HasFactory`" | The populator moves a header trait from `direct_parent_interfaces` to `used_traits`, and the analyzer checks it as `use` checks it, after mago-sharp `feat/slice-traits`. The bridge lowers it as `use` (decision 1) |
| 2417 | A trait may stand alone in the header | Refused: "Class `Draft` cannot implement non-interface type `Copies`" | As above |
| 2397 | Two header traits with the same method | Not reachable. mago reports nothing for the PHP twin, and PHP refuses it when it links the class | The analyzer's error (decision 7, question 2) |
| 2397 | A trait in an enum header | An error: "Enum `Shade` cannot implement non-interface type `Copies`" | Question 4 |
| 2426 | `entity is HasFactory f` | Wrong: compiles, and gives `false` for a `LegacyEntity` | #102's engine test, with a trait-aware `sharp_object_is` (decision 6) |
| 2427 | `seed(HasFactory owner)` | Wrong: compiles, and PHP throws `TypeError` for a `LegacyEntity` | PHP type `object`, and the PHP# type text checked by #102 (decision 6) |
| 2428 | `List<HasFactory> owners = [];` | Works on master, which checks no element. Under #102's element checks it fails as `is` does | #102's `sharp_type_accepts`, through the same `sharp_object_is` |
| 2431 | Reflection lists a class's traits, as it lists its interfaces | Not reachable: no PHP# class compiles with a trait | The `use` lowering gives `getTraitNames()` and `class_uses()` their PHP answers (question 5) |

# Existing work, and what the Slice takes from it

- **php-sharp #34, mago-sharp #5, `feat/slice-classes` (merged).** The header, `virtual`, `override`, `super` and the plain PHP override rules. Everything that works today comes from it, and the Slice keeps all of it.
- **php-sharp #22, `docs/interface-defaults` (merged).** Line 2345 to 2382 and decision 036, which chose default bodies over PHP# traits. No code.
- **mago-sharp `feat/slice-traits`, commit `03bd7c17d9` (unmerged).** One commit on mago-sharp's master from before 816 later commits. It holds:
  - the populator moving header traits to `used_traits`, with a codex test
  - the analyzer running its trait-use, trait-conflict and readonly-trait checks over header traits, with PHP-twin tests
  - semantics accepting default bodies and `{ get; set; }` interface properties, with tests
  - the bridge's `Lowering::defaults`, `signature` and `abstract_property`, with 213 lines of lowering tests
  - `DEFAULTS_TRAIT_SHORT_NAME` in a new `crates/syntax/src/utils/interface.rs`

  The Slice ports the populator, analyzer, semantics and `defaults` code onto master's files, and its tests. It leaves out the `{ get; set; }` part (properties owns it) and the computed-property default bodies (not in the spec). It puts the trait's name in the codex, beside the trait the codex registers, so it adds no file to `syntax`. That branch never made a class use the trait, never registered it for `mago analyze`, and never autoloaded it.
- **php-sharp `feat/slice-traits`, commit `8a8c4298ce` (unmerged).** Engine linking of header traits in `zend_inheritance.c`, `zend_opcode.c` and opcache's persist code. Master's bridge now writes `extends` and `implements` itself, so the Slice takes none of that code. It takes the tests: `class_header_trait.phpt` and the preload and inheritance-cache rows, rewritten for `use`. Its `harness/Model.inc` becomes `harness/Traits.inc`, because master's `Zend/tests/sharp/harness/Model.inc` is the property override fixture.
- **#102, `feat/slice-generics` (open, about to merge).** `type_test_of`, the engine's type test, `sharp_type_accepts` and `sharp_object_is` are the path decision 6 extends.
- **mago-sharp #168, `fix/fingerprint-headers` (merged), and #124 (merged).** A compiled file lists the file of every symbol it reads as an input, so an edit that turns a header trait into an interface makes the PHP# file stale. The Slice adds a test of that, and no mechanism.
- **Sealed:** `sealed interface` is section 20.1, and section 22 has no `sealed` rule.

# Boundaries

- **properties (`feat/properties`, open, slot 1).** Section 6.1 line 258 lists "properties in interfaces". Line 2354 needs `{ get; set; }` on one, which master refuses and `feat/properties` does not build. Its `is_interface_property` still takes `get` alone, and its note marks row 258 "Works". Recommended: properties adds `set`. That is one condition in `is_interface_property`, and `Lowering::interface_property` already lowers each accessor. This PR merges after it. Properties also owns `virtual` and `override` on properties, and this Slice builds neither. Both branches change `override_attribute.rs`, `scanner/function_like.rs`, `semantics/.../sharp.rs` and `lower.rs`, so the second to merge resolves those files.
- **generics (#102).** It owns generic interfaces, so a default body in one is refused here (decision 8). The trait type rides on #102's type test and value check (decision 6), so this PR merges after #102.
- **extensions (#115, merged).** Section 26 refuses an extension member that its type, "or a class above it", already declares, and picks the most specific receiver. A `Defaults` method and a header trait's methods become members of the class in the codex, so #115's checks see them with no change. One analyzer row proves that an extension `designKey` on `Page` is refused.
- **events (section 15) and enums (section 20).** An event or an enum that implements an interface with default bodies uses its `Defaults` trait, as a class does. Question 4 decides whether an enum header can name a plain PHP trait.

# Public names this Slice adds or changes

| Name | Where | What it is |
|---|---|---|
| `DeclarationKind::Trait` | `sharp-bridge/src/lower/types.rs` | A trait, beside `Class` and `Interface`. Today `class_declaration` calls a trait a `Class` |
| `Types::used_traits` | `sharp-bridge/src/lower/types.rs` | The traits the codex says a class-like uses: its header traits and its `Defaults` traits |
| `defaults_trait_name` | `codex` | `App\HasDesign\Defaults` for `App\HasDesign` |
| `Lowering::defaults` | `sharp-bridge/src/lower.rs` | The trait of an interface's default bodies |
| autoload rule | `sharp/composer/autoload.php` | `X\Defaults` loads with `X` |

`defaults_trait_name` candidates, read at the callsite `let name = defaults_trait_name(interface);`:

- `defaults_trait_name` reads "the defaults trait's name". It uses the spec's word `Defaults`.
- `default_bodies_trait` reads "the trait of the default bodies". It returns a name, so the reader adds "name".
- `trait_for_defaults` reads "the trait for (the interface's) defaults". It is close to the first.
- `interface_defaults` repeats "interface", the argument's own name.
- `nested_defaults_name` says how the name is built, not what it is.

Recommended: `defaults_trait_name`, the spec's word plus what the function returns. Runner-up: `default_bodies_trait`.

`Types::used_traits` follows the codex field it reads, `ClassLikeMetadata::used_traits`, as `parent_constructor` follows `get_parent_constructor`.

# Files

```text
sharp/mago/crates/
├── semantics/src/internal/checker/
│   ├── sharp.rs                      <- default bodies in an interface; interface field error; final with override;
│   │                                    question 3 and 7 errors
│   └── class_like/{method,mod}.rs    <- PHP's two interface-body errors skip .sharp files
├── names/src/internal/walker.rs      <- an interface's member names, for bare names in a default body
├── codex/src/
│   ├── scanner/class_like.rs         <- the Defaults trait of a PHP# interface with a default body
│   └── populator/hierarchy.rs        <- header traits to used_traits; Defaults traits for PHP# class-likes
├── analyzer/src/
│   ├── statement/class_like/mod.rs   <- header traits checked as use; two traits with one method
│   ├── statement/class_like/override_attribute.rs  <- the closed-method message; question 1
│   └── resolver/static_method.rs     <- super of an abstract method
└── sharp-bridge/src/
    ├── lower.rs                      <- defaults(); a use list on a class, an enum and an event;
    │                                    final on a method; a trait type lowers to object
    └── lower/types.rs                <- DeclarationKind::Trait, used_traits
ext/sharp/sharp.c                     <- #102's sharp_object_is matches a trait by use
sharp/composer/autoload.php           <- X\Defaults loads with X
sharp/baseline/spec/§22/              <- both entries removed
Zend/tests/sharp/                     <- added: Inheritance.sharp and its .inc twin, inheritance_*.phpt;
                                         class_header_trait.phpt and harness/Traits.inc after feat/slice-traits;
                                         a row in opcodes.phpt
```

`docs/spec.md` changes only where Jordan's answers change a line.

# The tests

Each rule gets one test, with each form as a row, watched failing before its code.

- **`crates/semantics/tests/sharp.rs`:** a default body as a block and as an expression; an interface field; `final override`; question 3 and 7 forms.
- **`crates/codex` populator test:** a PHP# class and its subclass use a header trait, and a PHP class that `implements` a trait does not. A PHP# class, enum and event get `HasDesign\Defaults`, and a plain PHP class gets it only with `use`.
- **`crates/analyzer/tests/sharp.rs`:**
  - a header trait reports what its PHP twin's `use` reports
  - `this.id` in a default body
  - a trait type as a parameter, a `List` element and `is`
  - `super` of an abstract method
  - the closed-method and two-class messages
  - question 1 and 2 forms
  - #115's extension row
- **`crates/sharp-bridge/tests/lower.rs`:** the interface and its trait; a class's, an enum's and an event's `use` list; `final override`; a trait-typed parameter.
- **`Zend/tests/sharp/`, each in every opcache mode:**
  - `page.designKey()` from a PHP# class and from a plain PHP class that `use`s the trait, autoloaded
  - a header trait's field, method and constant, with Reflection
  - `is`, `as`, `match`, a parameter from plain PHP and a `List` element on a trait type
  - `final override` against a plain PHP subclass
  - preload and the inheritance cache
- **Inputs:** an edit that turns a header trait into an interface makes the PHP# file stale.
- **Plain PHP:** `mago analyze` on `sharp/phpunit/vendor` prints the same issues before and after.
- **Spec:** `sharp/bin/spec` passes every section 22 example, and `sharp/bin/spec --completes` passes on `Completes: §22`.

# The PR

One php-sharp PR on `feat/inheritance`, after #102 and the properties PR merge. It merges `origin/master` once both are in. Its body says `Completes: §22`. It changes no file in `sharp/harness/`: the spec's `Model`, `HasFactory`, `Copies`, `LegacyEntity` and `DatabaseEntity` are already there.

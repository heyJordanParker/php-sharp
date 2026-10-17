# Slice: singles

The last v1 feature PR. It builds every change team-lead approved that has no section PR of its own (`tmp/slice-singles/approved.md`), and the three spec examples that still fail in sections 9, 18 and 19. Every state below was observed on master `bda1b92df2`, with a release `mago` and the debug engine built from that commit.

## The answer first

Of the approved changes, two already hold on master, parts of a third hold, and the rest need building. Three spec examples fail in this PR's sections.

**Built by this PR:**

1. **A plain PHP `array<int, T>` arrives as a `Map<int, T>`.** `for (const person of users.all())` gives `[int, Person]` entries, and `List<Person> people = users.all();` is refused (`invalid-local-assignment-value`). A `list<T>` already arrives as a `List<T>`.
2. **`@event` is a parse error.** A bare keyword as a local, parameter or `catch` variable is refused with "`event` is a PHP# keyword, so it can't name a parameter." and the help "Rename it, as in `eventValue`." An override of a plain PHP method whose parameter is named `event` can't be written at all: the keyword is refused, and any other name is `incompatible-parameter-name`.
3. **`never` as a method's return type is "This type is not supported yet in PHP#."**
4. **A constructor parameter property can't take a default.** `public string rawBody { get; } = "",` is a parse error. Of the features a body property has, accessor bodies, `willSet`, `didSet`, `via` and `required` are refused on a parameter.
5. **§18 `template-to-string`:** a template refuses a type that declares `toString()`.
6. **§9 `parameter-member-limits`:** the parse error of `via` on line 553 stops the semantic check of the file, so line 554's accessor body gets no error. Item 4 rewrites this example.
7. **§19 `operators`:** the example's `DatabaseEntity` takes `int id`, and the harness `Order` that extends it has no `: super(…)`. The language rule holds: with `: super(id)` in `Order`, the example's only errors are on its `compile error` line.
8. **`catch (OutOfRangeError e)`** gives two PHP-worded errors and no suggestion.
9. **The leftovers:** calls into a default body name `Defaults.label`, an enum-case arm with a bound field gets a false "Redundant `&&`" hint, and `continue` outside a loop keeps PHP's message.

**Already holds on master:**

- **Every bare `Sharp` name resolves.** `InvalidFields`, `Behavior<string>`, `Dispatcher`, `ImmediateDispatcher`, `ClassMembers`, `Environment`, `Scope` and `Position` compile with no import. The list behind them is generated from the library, and a test fails when it is stale. #151 rewrites that list, so deleting it waits on #151 (question 8).
- **`x is Map<K, V> row` binds the converted value** in all 16 forms probed, the effects branch's ternary form included. The raw-array binding the effects builder saw does not reproduce on master.
- **The `never` rows other than the return type itself:** code after a `never` call is reported unreachable, an `int` method ending in a `never` call needs no `return`, `input ?? Errors.fail(…)` types as `string`, an override returning `void` is refused, a plain PHP method declared `never` reads as `never`, and a `never` parameter, property or type argument is refused.
- **§2 `keyword-case`** passes.

**Waits on open PRs:** `Process.exit` as a `never` method (#151), §7 `variadic-parameters` (`Math.max`, `feat/output-calls`), deleting the default-name list (#151 rewrites it), and the effects builder's raw-array binding (only #151's `Database.sharp` writes that form).

When the PR lands, `sharp/baseline/spec/§9/parameter-member-limits`, `§18/template-to-string` and `§19/operators` leave the list, and the body writes `Completes: §9, §18, §19`.

## Questions team-lead answered

Each decides what a user writes. Each has its proposal and its answer. Where they differ, the answer is what the PR builds.

1. **`@` before a name, and the standard library's `@` error control.** Approved 16:40: "`@` before any keyword makes it a name", `@` works for every keyword, `global` included. The standard library writes PHP's error control 11 times on master and 23 times on #151, as `@global.unpack(format, data)`. Under the approved rule, that line reads a local named `global`.
   - **Proposed:** `@` directly before a letter is always a name. Error control is written `@(global.unpack(format, data))`, and the 11 library lines (23 after #151) move to that form. User code keeps its refusal of error control.
   - `@global` stays error control, so a local can't be named `global`. That breaks "every keyword".
   - **Answered: `@` before a name is always the keyword escape, as in C#, and PHP# has no `@` error control,** because it exists only for PHP's warnings. Each library line that used it reads its failure as a PHP# value instead: an `extern` declared to return `false` or `null` on failure, read with a type test. #151's lines move the same way after it merges.
2. **`@this`.** The checker refuses a local named `this` because "PHP forbids `$this`" (`check_local_name`). `@this` is a keyword's name like any other.
   - **Proposed:** `@this` names a local, a `catch` variable or a pattern variable, and the bridge lowers it to a hidden PHP name. A parameter named `@this` is refused, because a named argument `this:` must reach a PHP parameter of that name, and PHP has none. No plain PHP method has a `$this` parameter, so no override needs one.
   - Refuse `@this` everywhere, with the reason "`this` is the object the method runs on."
   - **Answered: `@this` works in every name position, parameters included.** The bridge picks the PHP variable name, so the user never meets PHP's `$this` rule.
3. **Where an int-keyed plain PHP array becomes a `List`.** Approved 15:35 names what plain PHP returns. Spec lines 955 to 957 list three crossings.
   - **Proposed:** all three. A plain PHP caller's `[5 => 1, 9 => 2]` passed to a PHP# `List<int>` parameter arrives as `[1, 2]`, as a return does. Today it throws "`App\Probe\ListToMap::firstOf(): Argument #1 ($items) must be of type List<int>, array given`", and a plain PHP return declared `list<Person>` with gaps throws "`App\Probe\Users::gappy(): Return value must be of type List<App.Probe.Person>, array returned`".
   - Only what plain PHP returns, as approved, and a plain caller's argument keeps today's `TypeError`.
   - With either, `x is List<T>` on a value with gaps in its keys stays false (`p1`, `listTest` on `[5 => 1]` gave `-1`), since a type test has no declaration to read. A `Map<int, T>` test on the same value stays true.
   - **Answered: all three crossings renumber.**
4. **A `List` value where a `Map` is declared.** Spec line 997 refuses a `List` literal in a `Map` place, and says nothing of a `List` value. Observed: `List<int> items = [4, 5]; Map<int, int> m = items;` compiles, and `m.get(1)` throws `Error: get() runs on a Map or a Set`.
   - **Proposed:** a compile error, "A `List` is not a `Map`: its keys are only its positions." A plain PHP value is the exception: it still holds its keys, so `Map<int, User> byId = users.keyBy("id").all();` compiles, as approved.
   - Leave it for after v1, under "found, not fixed".
   - **Answered: a compile error,** with plain PHP values exempt.
5. **Which body-property features a parameter property gets.** Approved 18:10 lists accessors, `init`, `required`, attributes, defaults, computed bodies, access levels and `readonly`, and asks for every feature a body property has. Spec line 548 and line 395 refuse more.
   - **Proposed:**
     - **Default after the accessors:** builds. Its value is any expression a method parameter's default may be. Today `global.time()` and `new Money(0, "USD")` as a method parameter default are "This expression is not supported yet in PHP#."
     - **Accessor bodies:** build. The argument is a write during the constructor, so a `set` body runs on it, as section 6.2 runs `set` for every constructor write. The default is the argument when the call leaves it out, so `set` runs on the default too. A body property's initial value runs no `set` (spec line 213), and this is the one place the two differ.
     - **`willSet` and `didSet`:** build, and run on every write after the constructor, as on a body property.
     - **`via`:** builds. The behaviors are created before the argument is stored, and the argument runs through each `set`.
     - **`required`:** a compile error, "A parameter is required unless it has a default: remove `required`.", on a constructor's parameter and on a method's, and the one error there. A parameter without a default is already required by every call.
     - **Computed bodies, `lazy` and `static`:** stay compile errors, each with the reason the checker gives today. A computed body and `lazy` compute their own value, and a parameter receives one. A parameter belongs to one object.
     - **`readonly`:** stays refused, as it is on a body property (spec line 1463). The two already match.
   - Only the approved list: `via` and the observers stay refused on a parameter, with spec line 395's reason.
   - **Answered: as proposed,** with the behaviors field on `FunctionLikeParameter`. A method parameter's default may also call a method or `new` an object, as in C# and Kotlin, and the bridge computes it when the call leaves the argument out.
6. **How §19's example gets an `Order` that calls `: super(id)`.** The harness `DatabaseEntity` has no constructor, the harness `Page` extends it with none either, and examples in sections 21 and 22 call `new Page()`.
   - **Proposed:** the `operators` statements block declares its own `Order` in its hidden setup, `public class Order : DatabaseEntity { public Order(int id) : super(id) { } }`. The example then states every class it assumes, and the harness stays as it is.
   - The harness `DatabaseEntity` gains `public DatabaseEntity(int id = 1) { this.id = id; }`, and the harness `Order` writes `: super(id)`. Every example that extends the harness `DatabaseEntity` with a constructor then needs `: super(…)`.
   - **Answered: as proposed.**
7. **Where a class that declares `toString()` becomes text.** Spec line 1995 covers a template. Plain PHP converts an object to a string through `__toString()`, as Blade's `{{ $sku }}` and `(string) $sku` do. The engine already calls a PHP# `toString()` for a JSON key (`sharp_table_json_key`, `sharp.c:7476`).
   - **Proposed:** a template calls `toString()` directly. Plain PHP's conversion is left for after v1, under "found, not fixed".
   - The compiled class also gets PHP's `__toString()`, calling `toString()`, so plain PHP converts it as a PHP# template does.
   - **Answered: plain PHP's string conversion calls `toString()` too,** so `(string) $sku` and `{{ $sku }}` give the template's text, as Java's and Kotlin's string conversion does.
8. **Deleting the default-name list.** Approved: "Bind every public `Sharp` class from the library itself, and delete the list." On master the list is `sharp/mago/crates/names/src/sharp_classes.rs`, generated by `just regen-sharp-classes` from the library and the prelude, and `the_default_class_likes_are_generated_from_the_standard_library_and_the_prelude` fails when it is stale. #151 changes the same code: it deletes `NAMED_EFFECTS`, regenerates the list with the ten effect classes, and adds `STANDARD_EXCEPTIONS`, 47 PHP exception classes a bare name reaches.
   - **Proposed:** after #151 lands, delete `sharp_classes.rs`, `STANDARD_EXCEPTIONS`, `scripts/regen-sharp-classes.php`, the `regen-sharp-classes` recipe and its test. `NamespaceClasses`, which the orchestrator already builds from every class-like the project scan finds and passes to `NameResolver::resolve_in`, also keeps every class-like directly in `Sharp` and every exception class `Builtins.sharp` declares, and `default_class` reads them from it. That changes what `NamespaceClasses` holds, a public type of `mago_names`. The prelude's engine classes, such as `Sharp\List` and `Sharp\Position`, need the same: the codex prelude already holds them.
   - Keep the generated list: the Capability holds, and its test already fails when the library gains a class.
   - **Answered: nothing to delete.** #145 made the list generated from the library, and its staleness test holds the rule. The PR checks that the bare names still resolve after #151 lands.

The spec sweep below was answered too: every Problem is fixed in this PR but line 3023 and line 459, which `feat/output-calls` changes. Each PHP name a sweep line reaches is replaced by the standard library member that replaces it. Found-not-fixed 1, the `while` panic, is fixed here unless #151 fixes it, and found-not-fixed 3, a parameter default that calls or `new`s, is built (question 5).

## Edge cases the spec leaves open

1. **`obj.@event`** reads the member `event`, since a member takes a keyword with no mark. It compiles and means the same as `obj.event`, as C#'s `obj.@event` does.
2. **The `incompatible-parameter-name` help names `@event`** when the parent's name is a keyword: "Name it `@event`, as the parent does."
3. **A `never` method that can return** is reported at its closing brace: "`Errors.stop` is `never`, and it reaches its closing brace when no `throw` runs before it." The spec's example puts the annotation on the `}` line.
4. **A `never` abstract method and an interface method** compile, and every implementation must be `never`, as the covariance check already says.
5. **A plain PHP `array<int|string, T>` stays a `Map<int|string, T>`.** Only an `int` key type alone becomes a `List`, and only where PHP declares no shape with known keys.
6. **An override of a plain PHP parent's property keeps its check against the parent's declared type.** The received type of item 1 applies where PHP# code reads a plain PHP value, so `protected override List<string> fillable { get; set; }` over Eloquent's `@var array<int, string>` (spec line 313) is checked as today, and `this.fillable` reads a `List<string>`.

## How the states were observed

```sh
CARGO_BUILD_JOBS=4 timeout 540 sharp/bin/build
CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --locked --release -j 2 \
  --manifest-path sharp/mago/Cargo.toml --target-dir sharp/build/darwin-arm64/mago --bin mago
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

`sharp/bin/spec` checked 204 examples, ignored 1, and 7 failed: §7 `variadic-parameters`, §8 `standard-library-calls`, §8 `output-and-exit`, §9 `parameter-member-limits`, §18 `template-to-string`, §19 `operators` and §29 `pure-interface`. That matches `sharp/baseline/spec`.

Each probe is a copy of the spec run's harness project, `sharp/build/darwin-arm64/spec/harness`, with probe classes under `app/Probe/`, compiled with `mago compile` and run on the engine through a `run.php` that autoloads the compiled files. They sit in `/private/tmp/claude-501/-Users-jordan-Developer-php-sharp/345d8519-d589-441b-947d-8f4fe33a0768/scratchpad/singles/`:

- `p1`: plain PHP arrays arriving, type tests on plain arrays, a `List` in a `Map` place
- `p2`: keyword names, overrides of a plain PHP method with a keyword parameter, named keyword arguments
- `p3`: `never`
- `p4`: constructor parameter properties, and method parameter defaults
- `p5`: bare `Sharp` names and the `catch` message
- `p6`: `Defaults.label`, the `Redundant &&` hint, a list rest, `continue`
- `p7`: two-field coverage of an enum case
- `p8-while-panic`: the `mago` panic under "Found, not fixed"
- `p19`: §19's project with `: super(id)` in `Order`

# Each change: its state, its mechanism, and the code it reuses

## 1. An int-keyed plain PHP array arrives as a `List` (approved 10-16 ~15:35)

**State.** `p1`, with a plain PHP `Users` whose methods declare `@return array<int, Person>`, `list<Person>` and `array<string, Person>`:

```text
for (const person of users.all())      error[non-existent-property]: `[int, Person]` has no element named `name`
List<Person> people = users.all();     error[invalid-local-assignment-value]: it is declared as `List<Person>`
for (const person of users.listed())   runs: "adabo"
Map<int, Person> byId = users.keyed(); runs: a Sharp\Map with keys 3 and 7
Map<string, Person> m = users.byName(); runs: a Sharp\Map
```

**Mechanism.** PHP# types are PHP types inside the checker: `List<T>` is `list<T>` and `Map<K, V>` is `array<K, V>`. A plain PHP `array<int, T>` therefore is a `Map<int, T>` to PHP# code. The reverse direction already has a function, `get_php_received_type` in `codex/src/ttype/mod.rs`, the type plain PHP receives where PHP# declares one. This change adds its mirror, the type PHP# receives where plain PHP declares one: each `array<int, T>` without known keys, at any depth, becomes `list<T>`. It applies where `get_php_received_type` applies, in the other direction:

```diff
 // analyzer/src/invocation/return_type_fetcher.rs, fetch_declared_invocation_return_type
-    if function_like_metadata.is_some_and(|metadata| metadata.flags.is_sharp()) {
-        resulting_type = known_type_or_error(resulting_type);
-        if !context.dialect.is_sharp() {
-            resulting_type = get_php_received_type(&resulting_type, context.codebase);
+    match function_like_metadata {
+        Some(metadata) if metadata.flags.is_sharp() => {
+            resulting_type = known_type_or_error(resulting_type);
+            if !context.dialect.is_sharp() {
+                resulting_type = get_php_received_type(&resulting_type, context.codebase);
+            }
+        }
+        Some(_) if context.dialect.is_sharp() && !matches!(…, Some(FunctionLikeIdentifier::Method(class, _)) if is_prelude_stub(*class)) => {
+            resulting_type = get_sharp_received_type(&resulting_type);
         }
+        _ => {}
     }
```

Only a target with plain PHP metadata gets the received type. A PHP# function value has no declaration, so a lambda passed for a `Function<Map<int, V>()>` returns the `Map` its type writes, and one whose body returns a `List` is refused as question 4 refuses a `List` in a `Map` place.

The same two lines go beside `get_php_received_type`'s calls in `resolver/property.rs` (`find_property_in_class`) and `resolver/static_property.rs` (`find_static_property_in_class`), for a property plain PHP declares.

At run time a `List` is a PHP array and a `Map` is a `Sharp\Map` (`get_collection_object`). The engine checks a plain value entering a `List<T>` place, and refuses one with gaps in its keys. The bridge renumbers a plain value the checker reads as a `List`, where it lowers it, with the check `Lowering::plain_value` already makes:

```php
\is_array($crossing#1 = \App\Probe\Users::all()) ? \array_values($crossing#1) : $crossing#1
```

A plain PHP property is a `List` for writes too: a write through it, as `users.names.add("cy")`, `users.names[0] = "zed"` or `Users.tags.add("z")`, sets the property to its renumbered value first and then changes it in place, `[$users->names = renumbered($users->names), $users->names->add("cy")][1]` (`Lowering::renumbered_first`), and an assignment target is never renumbered. `array_values` returns a packed array with no gaps as it is, by reference count (`ext/standard/array.c:4639`), so a real list pays nothing. In a `Map<int, T>` place, `Lowering::crossed_value` already builds `\Sharp\Map::from($crossing#1)` from the raw value, before any renumbering, so the keys stay. Question 3 adds the same renumbering where a plain PHP caller passes an argument or writes a property: `sharp_type_accepts` in `ext/sharp/` already turns an `int` into a `float` in place at those crossings, and renumbers there the same way.

**Reuses:** `get_php_received_type`'s shape and call sites, `Lowering::plain_value`, `Lowering::crossed_value`, `sharp_type_accepts`.

## 2. PHP# code never caters to the PHP underneath (approved 10-16 ~15:45)

The spec's opening paragraph, line 3, gains the rule. The sweep it asks for has its own section below, "The spec sweep".

```diff
 PHP# is a language that runs on PHP's engine and calls plain PHP freely, as Kotlin runs on the JVM and calls Java. This file is the single source of truth for PHP# syntax and rules.
+
+**No PHP# rule makes code write around how PHP# runs on PHP's engine.** A PHP# developer never needs to know what a PHP# construct compiles to. Plain PHP libraries are called as libraries, as Kotlin calls Java libraries.
```

## 3. `@` before any keyword makes it a name (approved 10-16 ~16:40)

**State.** `p2`, with a plain PHP `Behavior` declaring `submit(Form $form, Event $event)` and `run(bool $match)`:

```text
public override void submit(Form form, Event @event)   42 parse errors from 5:50 on
public override void submit(Form form, Event event)    `event` is a PHP# keyword, so it can't name a parameter.
public override void submit(Form form, Event e)        incompatible-parameter-name: … parent … names it `event`
const event = "paid";                                  `event` is a PHP# keyword, so it can't name a local.
catch (Error class)                                    `class` is a PHP# keyword, so it can't name a catch variable.
behavior.submit(form: form, event: e);                 compiles
```

**Mechanism.**

- **The lexer.** In a `.sharp` file, `@` directly before a letter is one identifier token whose span starts at `@` and whose name is the word after it (question 1). `@(` and `@ ` stay error control.
- **The parser** already reads a keyword where a local, parameter, `catch` variable or pattern variable is named (`parse_local_identifier`, `is_identifier_maybe_reserved`). It also reads one as a lone lambda parameter, as `match => 1` (`is_at_lambda`, a literal `true`, `false` or `null` excepted), so the checker refuses it with its message. A named argument `this:` names the parameter `@this` (`parse_argument_name`), since a name before `:` is never the object.
- **The checker.** `check_local_name` in `semantics/src/internal/checker/sharp.rs` refuses a keyword unless the byte before the name is `@`. Its message becomes the approved one:

```diff
-            Issue::error(format!("`{name}` is a PHP# keyword, so it can't name a {kind}."))
+            Issue::error(format!("`{name}` is a keyword; write `@{name}`."))
                 .with_annotation(Annotation::primary(span).with_message("Declared here."))
-                .with_help(format!("Rename it, as in `{renamed}`.")),
```

- **The bridge** lowers the name without `@`, so `@event` is PHP's `$event`, the parent's parameter name matches, and a named argument `event:` reaches it. `@this` gets a hidden name (question 2).

**Reuses:** the parser's keyword-as-name path, `check_local_name`, the bridge's hidden names (`Lowering::hidden`).

## 4. `never` is the return type of a method that never returns (approved 10-16 ~17:55)

**State.** `p3`:

```text
public static never fail(string message) => throw …   This type is not supported yet in PHP#.
string name = input ?? Errors.fail("…");               compiles
Errors.fail("after"); return 0;                        unevaluated-code: This statement never runs, because line 26 ends the block.
int ends() { Errors.fail("ends"); }                    compiles
Legacy.halt(); return 0;   (plain PHP `: never`)       unevaluated-code
int plain() { Legacy.halt(); }                         compiles
public override void stop()  (parent `never`)          incompatible-return-type
void parameter(never n)                                two errors: PHP's "bottom type `never` cannot be used as a parameter type" and "PHP# has no `never` type outside a return type."
public never value { get; set; }                       two errors: "Property `Holder.value` cannot have type `never`." and the PHP# one
List<never>                                            PHP# has no `never` type outside a return type.
```

**Mechanism.**

- **The checker.** `enter` in `semantics/src/internal/checker/sharp.rs` takes `Hint::Never` where a method's return type sits (`Place::Method` and `Place::Signature`), beside the arm that calls `is_slice_type` (line 1454). `type_written_another_way` already refuses it everywhere else. A parameter and a property also get Mago's PHP error beside it, which a `.sharp` file leaves out, so each gets the one PHP# error.
- **The analyzer.** `analyze_function_like` already calls `report_missing_return` when a `never` method can reach its end. In a `.sharp` file it reports at the closing brace, with the message of edge case 3.
- **The bridge.** `Lowering::erase` gives `Hint::Never` PHP's `never`, beside `Hint::Void`. PHP then throws `TypeError` if a `never` method returns anyway.
- **`Process.exit`** becomes `public never exit(int code);` in #151's `Sharp/Process.sharp`. The analyzer's handling of `never` calls then reports `process.exit(1); return 0;` and lets an `int` method end in `process.exit(1)`, as the `p3` rows show for `Errors.fail`.

**Reuses:** `type_written_another_way`, `report_missing_return`, the analyzer's `never` control flow, `Lowering::erase`.

## 5. A constructor parameter property takes a default, and matches a body property (approved 10-16 ~18:10)

**State.** `p4`, one parameter per feature:

```text
public string rawBody { get; } = "",                    14 parse errors from 7:40 on
public string a { get; set; }                           compiles
public string b { get; private set; }                   compiles
public string c { get; init; }                          compiles
public string d { get; private init; }                  compiles
[Key("f")] public string f { get; }                     compiles
protected string p { get; }, private string q { get; }  compiles
public required string e { get; }                       This modifier is not supported yet in PHP#.
public readonly List<int> g { get; }                    A constructor parameter that declares a member is never `readonly`: …
public string h { get; set => field = value.trim(); }   Declare `h` in the class body to give it an accessor body: …
public string i { get => field.trim(); }                Declare `i` in the class body to give it an accessor body: …
public string j { get; set; didSet => this.touch(); }   Declare `j` in the class body to give it `didSet`: …
public string k { get; set; willSet { } }               Declare `k` in the class body to give it `willSet`: …
public string l { get; set; } via Trimmed               parse error: Declare `l` in the class body to give it `via`: …
public lazy string m { get; }                           A parameter cannot be `lazy`: a lazy property computes its own value, and a parameter receives one.
public static string n { get; }                         Parameter `n` cannot have the `static` modifier.
public string o => "computed"                           parse error: A parameter cannot be a computed property: …
```

**Mechanism.**

- **The parser.** `parse_parameter` in `syntax/src/parser/internal/function_like/parameter.rs` reads the default before the accessors, as PHP writes `public string $rawBody = "" { get; }`. In a `.sharp` file it reads it after the accessors and after `via`. `skip_parameter_behaviors`, which today skips a parameter's `via` list with a parse error, reads it into the parameter instead. That gives `FunctionLikeParameter` a field for its behaviors, as a property has: a change to the CST's public API.
- **The checker.** The refusals of accessor bodies, `willSet`, `didSet` and `via` on a parameter leave `semantics/src/internal/checker/sharp.rs`. `required` gets question 5's error in place of "not supported yet". The refusals of `lazy`, `static` and a computed body stay.
- **The analyzer** already reads a parameter with a default as optional, so `new GatewayEvent("charge.succeeded")` checks with no change.
- **The bridge** lowers a parameter property to PHP's promoted property, and PHP 8.4 takes hooks and a default on one. The default goes into the parameter's default, the accessor bodies into its hooks, and the behaviors and observers lower as `Lowering::property` lowers them on a body property.
- **The spec.** Line 548 and the `parameter-member-limits` example change with question 5, and line 395 loses its last sentence:

```diff
-**A member declared on a parameter takes access levels and attributes only,** as TypeScript's and Kotlin's parameter properties do: an access modifier, an accessor list whose `set` or `init` may take a narrower access level, such as `{ get; private set; }`, and attributes. Accessor bodies, `willSet`, `didSet`, `via`, `lazy`, computed properties and `static` go on a member in the class body:
+**A member declared on a parameter is a property like one declared in the class body,** with its access levels, accessors, accessor bodies, observers, behaviors and attributes, and a default after its accessors, which the call may leave out. The argument is a write while the constructor runs, so a `set` body runs on it. A computed property and `lazy` compute their own value, and `static` belongs to no one object, so each goes on a member in the class body:

 <!-- example parameter-member-limits: members in Ledger -->
 ```csharp
 public Ledger(
-    public Money balance { get; } via Tracked,                     // compile error: declare balance in the class body to give it via
-    public string name { get; set => field = value.trim(); },      // compile error: declare name in the class body to give it an accessor body
+    public string name { get; set => field = value.trim(); } = "",  // compiles: the argument runs through set
+    public string slug => this.name.trim(),                          // compile error: declare slug in the class body to give it a computed body
 ) { }
 ```
```

The approved `GatewayEvent` example joins section 9 as a new example, with a run line for `paid.rawBody;   // ""`.

**Reuses:** the property hook list a parameter already parses, `Lowering::property`'s hooks, behaviors and observers, PHP 8.4's promoted hooks.

## 6. All of `Sharp` is imported by default (effects builder)

**State.** `p5`: a parameter of each of `InvalidFields`, `Behavior<string>`, `Dispatcher`, `ImmediateDispatcher`, `ClassMembers`, `Environment`, `Scope` and `Position` compiles in namespace `App.Probe` with no import. It holds.

**Mechanism.** Question 8.

## 7. No message on a `.sharp` file names a PHP class form (effects builder)

**State.** `p5`, in namespace `App.Probe`:

```text
catch (OutOfRangeError e)       error[non-existent-catch-type]: Attempting to catch an undefined class or interface: `OutOfRangeError`.
                                 = Types used in `catch` blocks must be existing and autoloadable classes or interfaces.
                                error[no-valid-catch-type-found]: None of the types specified in the `catch` declaration are valid catchable exceptions.
                                 = … (e.g., `\Exception`, `\MyCustomError`) …
catch (OutOfRangeException e)   `OutOfRangeException` is not imported.   (with #151, a default name)
```

The same holds beyond `catch`. `p3`'s `throw new LogicException(message)` in namespace `App.Probe` gives "Class `LogicException` not found." with the help "Ensure the name is correct, including its namespace, and that it's properly defined and autoloadable."

**Mechanism.** `report_unknown_sharp_class` in `analyzer/src/resolver/class_name.rs` reports a miscased keyword, a full name, and the one class of that short name the file doesn't import, and returns `false` for any other name. Eight paths then fall back to Mago's PHP message:

- `expression/instantiation.rs:444`, `resolver/class_constant.rs:449`, `statement/try.rs:644`, `statement/attributes/mod.rs:142`, `statement/class_like/property.rs:308` (a behavior)
- `statement/class_like/mod.rs:1775`, `:2138` and `:2183` (a header's base class, interface and trait)

In a `.sharp` file each fallback, `report_undefined_type` and `report_non_existent_class_like` (`nameof`, `typeof`, a static read) call `report_missing_class`, which runs `report_unknown_sharp_class` and otherwise reports "Cannot find type `App.Probe.OutOfRangeError`." with the help "Did you mean `OutOfRangeException`?" when the short name with `Error` and `Exception` swapped names a class the file reaches by its bare name. A `catch` clause whose type was reported reads as `Throwable`, as it already does for a reported name, so `no-valid-catch-type-found` is not reported a second time, and a `new` of a missing class has the refused type, so its use adds no second error.

**Reuses:** `report_unknown_sharp_class`, the refused type `TAtomic::Error`.

## 8. `x is Map<K, V> row` binds the converted value (effects builder)

**State.** `p1`, 16 forms: an `if`, a `match` arm, `is not` with an early return, a ternary in an expression body, on a typed plain PHP return, a `mixed` return, an untyped return, a plain PHP argument to `Any?`, `Json.decode(…)`, and `global.json_decode(…)` directly, with `Map<int, …>`, `Map<string, …>` and `List<…>`. Each bound the converted value, a `Sharp\Map` for a `Map`, and `row.get(5)` and `row.count()` ran. The effects builder's form, `statement.fetch(PDO.FETCH_ASSOC) is Map<string, Any?> row ? row : null`, lives only in #151's `Sharp/Database.sharp`. Its `while (Database.next(statement) is Map<string, Any?> row)` form panics master's `mago` (found, not fixed 1).

**Mechanism.** None on master. After #151 lands, the PR runs #151's `Database` with a SQLite statement. If it binds the raw array there, the cause is a `PDOStatement` method that the prelude declares, and the fix goes into `analyze_php_shape`'s binding with item 1's received type.

## 9. The failing spec examples

- **§2 `keyword-case`** passes on master.
- **§7 `variadic-parameters`** fails on `Math.max`, which `feat/output-calls` adds.
- **§9 `parameter-member-limits`:** item 5 and question 5.
- **§18 `template-to-string`:** "A template shows `int`, `float`, `string` or `bool`, and `Sku` is none of them." `is_shown` in `analyzer/src/expression/composite_string.rs` also accepts a type each of whose classes declares a public `string toString()` taking no argument, and `report_unshown` takes spec line 1995's message: "A template shows `int`, `float`, `string`, `bool` or a type that declares `toString()`, and `Status` is none of them." `Lowering::template_value` in the bridge lowers such a value to `$value->toString()`, beside its `bool` arm. A nullable one stays refused until it is checked. Decision 070 still says "PHP# has no such method" and is updated to section 18 (question 7).
- **§19 `operators`:** question 6. `p19` is the example's generated project with `public required Order(int id = 1) : super(id) { }` in `Order`. It compiles with errors on line 11 only, the line annotated `compile error`: "This statement has no effect: use the result of `cart == otherCart`, …" as the spec writes the line, and "`==` cannot compare `Cart` with `Cart`: `Cart` declares no `operator ==`." when the line keeps the value in a `const`.

## 10. The leftovers

- **A default body's call messages name the `Defaults` trait.** `p6`: `super<HasLabel>.label(5)` gives "Too many arguments provided for method `Defaults.label`.", and `tag.size("y")` "… argument #1 of `Defaults.size` …". `sharp_class_like_name` in `analyzer/src/utils/names.rs` names the trait `X\Defaults` of an interface `X` (codex's `DEFAULTS_TRAIT_SUFFIX`) by the interface, `HasLabel.label`.
- **The false "Redundant `&&`" hint.** `p6`, `mago analyze`: `ChargeError.Declined(string reason) => …` gives `help[redundant-logical-operation]` at the arm, twice. `analyze_php_shape` in `analyzer/src/expression/mod.rs` already drops `RedundantLogicalOperation` for an `is` or `as` without fields. It drops it for a type pattern with fields too, whose field tests `handled_test` already knows are always true (decision 14 of `21-pattern-matching.md`).
- **#131's four.** The descriptions are in no note or PR body, so these are the readings the code gives, for team-lead to confirm:
  - **A list rest's trailing element message:** `[...List<int> before, string tail]` on a `List<int>` gives "Impossible type assertion: `tail` of type `int` can never be `string`." It will read "`tail` is an `int` in a `List<int>`, so it is never a `string`."
  - **Coverage combining only simple arms:** `p7`'s two-field `Delivery.Noted(string?, bool)` match over all four combinations compiles with no `default`. No form that fails was found.
  - **HIR lowering of PHP# bodies:** `hir/src/lower/expression/mod.rs` reaches `unreachable_unchecked` on `Leave`, `Propagate`, `Is`, `As`, `PatternMatch`, `TypeOf`, `NameOf`, `With`, `GenericClass` and `SuperOf`. No crate but `mago-hir`'s own tests depends on `mago-hir`, so no user reaches it. Proposed: lower the ten kinds, or record that HIR reads plain PHP alone.
  - **`continue.rs` against `break.rs`:** `continue;` outside a loop gives "Continue statement used outside of loop.", where `break;` gives the PHP# "A `break` stands only inside a loop.". `continue.rs` takes `break.rs`'s `.sharp` messages, and both drop "or switch" in a `.sharp` file.

# The spec sweep: rules that make PHP# code work around PHP

Each is a Problem for team-lead. None is changed here unless an approved item above already changes it.

1. **Line 3023: "`List`, `Map` and `Set` can't share a union, because they share PHP's one array kind."** The reason is PHP's array. At run time a `Map` and a `Set` are objects and a `List` is an array, so the engine can tell them apart. orchestration-notes 10-17 ~03:00 already plans a proposal for it.
2. **Line 827: "A generic class can't implement PHP's `Serializable`, and the error points to `__serialize` and `__unserialize`."** Section 27 (line 3582) removes every `__Something__` form, and the checker refuses a member name that starts with `__`. So the error points to methods a `.sharp` file can't declare.
3. **Line 459 names `global.exit(…)`** among the statements that end a block, and `if (global.exit(1))`. Line 502 says exiting goes through `Process`. Item 4 rewrites line 459 as "a call of a `never` method, such as `process.exit(…)`", once #151 lands.
4. **Line 2034: "`shell_exec()` stays."** Line 498 makes every PHP built-in function a standard library method, and #151's `Process.run` maps `shell_exec`.
5. **Lines 500, 2884, 2885 and 3062 read PHP's constants for a newline and the `int` bounds:** `global.PHP_EOL`, `global.PHP_INT_MAX`, `global.PHP_INT_MIN`. A user has to know PHP's constant names for values the standard library could give. Built for the bounds: a read of `PHP_INT_MAX` or `PHP_INT_MIN`, bare or through `global`, is a compile error that names `Int.MAX` or `Int.MIN`, through the same `REPLACEMENTS` table as a replaced function. `global.PHP_EOL` stays.
6. **Line 329: an override of a typed plain PHP parent property "must equal it, because PHP checks property types for invariance when the class links."** An untyped parent's property takes any assignable type, because the engine drops the written type. The typed case could do the same.
7. **Line 1119: a `Map` keyed by an enum or a struct, passed to a plain PHP `array` parameter, is a compile error,** because a PHP array's keys are `int` or `string`. A backed enum key could pass its backing value, as the crossing back already takes a backing value as its case.
8. **Line 1575: "In a plain PHP class with both a property and a method of that name, the property wins, as in PHP."** PHP# code then can't take that method as a value.
9. **Line 2895: "Only `Sharp` itself is imported by default."** Every standard library failure is a PHP class, `ValueError`, `ArithmeticError`, `RuntimeException`, and on master each needs `import ValueError;`. #151's `STANDARD_EXCEPTIONS` makes 47 of them default names, so line 2895 needs to say so.
10. **The checker, not the spec:** `check_local_name` refuses `this` because "PHP forbids `$this`", and `GLOBALS`, `_SERVER` and the other superglobal names because the local "runs as a PHP variable of the same name". Both messages explain PHP's variables to a PHP# developer. Question 2 covers `@this`. The bridge could give a superglobal's name a hidden PHP name as it would `@this`.
11. **Line 63 and line 548 / line 395** are changed by items 3 and 5.

Not Problems, read and kept: line 254 (plain PHP reaches a static with hooks through a method: a plain PHP limit, not a PHP# rule), lines 2659 and 2661 (the `Defaults` trait plain PHP `use`s, which PHP# code never names), line 1326 (no two-way generators), line 3828 (PHP's built-in classes are refused in favour of the library's), and line 2158's precedence table, which compares PHP# with PHP for the reader.

# What waits on open PRs

- **#151 (effects):** `Process.exit` as `never` (item 4), the default-name list (question 8), `STANDARD_EXCEPTIONS` in the `catch` suggestion (item 7), item 8's reproduction, sweep 3 and 9, and the 12 `@global.` lines #151 adds (question 1). #151 also changes `names/src/lib.rs`, `names/src/sharp_classes.rs`, `analyzer/src/statement/try.rs` and `semantics/src/internal/checker/sharp.rs`, so whichever lands second merges those.
- **`feat/output-calls`:** §7 `variadic-parameters` (`Math.max`). It also changes `semantics/src/internal/checker/sharp.rs` and `sharp-bridge/src/lower.rs`.
- **`feat/functions-as-values`:** `@` on a lambda parameter goes through `parse_lambda_parameter_list`, which this PR's lexer change reaches on master. That branch changes `statement/function_like/mod.rs`, where item 4 changes `report_missing_return`'s call, and `semantics/src/internal/checker/sharp.rs` and `sharp-bridge/src/lower.rs`, so whichever lands second merges those.

# What changes

```text
docs/
├── spec.md                                   opening paragraph (2), §2 lines 53-84 (3), §7 never and line 459 (4),
│                                             §9 line 548 and parameter-member-limits, GatewayEvent (5), §6.4 line 395 (5),
│                                             §12 the int-keyed arrival example (1), §19 operators' setup (Q6)
├── decisions/070-template-values.md          toString, as section 18 now says
└── architecture/slices/singles.md            this note
sharp/
├── baseline/spec/                            drops §9/parameter-member-limits, §18/template-to-string, §19/operators
├── composer/library/Sharp/
│   ├── Data/{Binary,Compression,Hex,PhpSerializer}.sharp, Environment.sharp, Text/Regex.sharp
│   │                                         @global.x(…) is @(global.x(…)) (Q1)
│   └── Process.sharp                         (after #151) exit is never (4)
└── mago/crates/
    ├── syntax/src/lexer/mod.rs               @ before a letter is a name (3)
    ├── syntax/src/cst/cst/function_like/parameter.rs   FunctionLikeParameter's behaviors (5)
    ├── syntax/src/parser/internal/function_like/parameter.rs   the default after the accessors and via (5)
    ├── semantics/src/internal/checker/sharp.rs   check_local_name's @ and message (3), never as a return type (4),
    │                                         the parameter-property refusals (5)
    ├── codex/src/ttype/mod.rs                get_sharp_received_type, the mirror of get_php_received_type (1)
    ├── names/src/{lib.rs, sharp_classes.rs}, resolver.rs, scripts/regen-sharp-classes.php, justfile
    │                                         (after #151, Q8) the list leaves, NamespaceClasses holds the defaults
    ├── analyzer/src/invocation/return_type_fetcher.rs, resolver/property.rs, resolver/static_property.rs
    │                                         plain PHP's array<int, T> received as List<T> (1)
    ├── analyzer/src/expression/composite_string.rs   toString shown (§18)
    ├── analyzer/src/statement/function_like/mod.rs   a never method reported at its closing brace (4)
    ├── analyzer/src/resolver/class_name.rs   the suggestion of a default name (7)
    ├── analyzer/src/{expression/instantiation.rs, resolver/class_constant.rs, statement/try.rs,
    │   statement/attributes/mod.rs, statement/class_like/property.rs, statement/class_like/mod.rs}
    │                                         a missing type's PHP# message in a .sharp file (7)
    ├── analyzer/src/utils/names.rs           the Defaults trait named by its interface (10)
    ├── analyzer/src/expression/mod.rs        no Redundant && from a case pattern's fields (10)
    ├── analyzer/src/statement/loop/continue.rs   break.rs's .sharp messages (10)
    └── sharp-bridge/src/lower.rs             @ names (3), never (4), a parameter's default, hooks and behaviors (5),
                                              toString in a template (§18), a plain List renumbered (1)
ext/sharp/
└── sharp.c                                   sharp_type_accepts renumbers a plain array entering a List (Q3)
Zend/tests/sharp/
├── plain_array_arrival.phpt                  new (1)
├── keyword_names.phpt                        new (3)
├── never_methods.phpt                        new (4)
├── parameter_properties.phpt                 new (5)
└── template_to_string.phpt                   new (§18)
```

No `MetadataFlags` bit: `never` is a return type, a default is the parameter's own, and the received type is a type.

# Public names this PR adds or changes

- **`get_sharp_received_type`**, in `mago_codex::ttype`. The word `received` is fixed by its sibling `get_php_received_type`:
  - `resulting_type = get_sharp_received_type(&resulting_type, context.codebase);` — the mirror angle: the type PHP# code receives where plain PHP declares it. Its sentence needs no added word.
  - `get_arrival_type` — the spec's word, "arrives as a `List<T>`". Readers add "from plain PHP".
  - `get_plain_php_value_type` — the value's angle. Readers add "as PHP# sees it".
  - `as_sharp_collection_type` — the object that changes. Readers add "when it came from plain PHP", and it says nothing of the non-collection parts it walks.
  - `get_crossing_type` — the crossing event. The bridge already has `crossing_type` for another job, so it is removed.
  - Recommended: `get_sharp_received_type`, because it pairs with `get_php_received_type` and reads the same way.
  - Runner-up: `get_arrival_type`, because it is the spec's own word.
- **`FunctionLikeParameter`** gains its behaviors (item 5).
- **`NamespaceClasses`** holds the default class-likes too (question 8, after #151).

# The tests

One test per rule, with each form a row, each row seen failing first:

- **Item 1:** analyzer `a_plain_php_int_keyed_array_arrives_as_a_list`: a loop over it, a `List` place, a `Map<int, T>` place, `array<string, T>`, `array<int|string, T>`, a shape, a nested `array<int, array<int, T>>`, a property. Bridge `a_plain_php_list_is_renumbered_where_it_arrives`. `plain_array_arrival.phpt`: index 0 after gaps, a `Map` keeps 3 and 7, a real list stays the same array.
- **Item 3:** syntax `an_at_before_a_letter_is_a_name` with `@(…)` and `@ x` as error control. Semantics `every_keyword_names_a_local_with_at`: local, parameter, `catch`, pattern, `for … of`, each keyword, a bare keyword's message. Analyzer `an_override_keeps_a_keyword_parameter_name_with_at`. `keyword_names.phpt`: `event:` reaches `@event`.
- **Item 4:** analyzer `never_is_a_method_return_type` with the approved rows: `=> throw`, `?? Errors.fail(…)`, the closing-brace error, a parameter, a property and a type argument refused once, unreachable code after the call, an override returning `void`, a plain PHP `never`. `never_methods.phpt`: the throw reaches the caller.
- **Item 5:** semantics `a_parameter_property_takes_every_body_property_feature` with a row per feature of question 5. Syntax `a_parameter_default_follows_its_accessors`. `parameter_properties.phpt`: the default, `set` on the argument and on the default, an observer after the constructor and not during it, `via`.
- **Item 7:** analyzer `a_catch_of_a_missing_type_names_it_as_php_sharp_does`.
- **§18:** analyzer `a_template_shows_a_type_that_declares_to_string` (the rows of `a_template_refuses_every_type_but_int_float_string_and_bool` take the new message). `template_to_string.phpt`.
- **Item 10:** a row each in the tests that hold those messages today.

New engine tests run under opcache preloading and without it, and those touching shared functions run under `USE_ZEND_ALLOC=0 MallocScribble=1`. Each `.sharp` fixture declares one type and loads through `fixture('Name')`.

A plain PHP project's analysis stays the same: every change is under `context.dialect.is_sharp()`, a `.sharp` file's lexer mode, or the bridge.

# Found, not fixed

1. **`mago compile` panics on `while (value is List<int> items) { return items.count(); }`:** "panicked at crates/names/src/lib.rs:184:72: resolved name not found at position". Probe `p8-while-panic`. #151's `Database.sharp` writes this form, so #151 may carry a fix.
2. **The engine's `TypeError` for a plain return names PHP's form:** "`App\Probe\Users::gappy()`: Return value must be of type List<App.Probe.Person>, array returned". Probe `p1`, `gappyFirst`.
3. **A host error handler that ignores silencing turns a built-in's failure warning into an exception.** A failure warning is silenced, so the handlers of Laravel, Symfony and PHPUnit skip it. Suppressing it in the engine, for a handler that ignores silencing, waits until after v1.
4. **An enum's `toString()` doesn't reach plain PHP's string conversion.** PHP refuses `__toString()` on an enum, so a plain PHP `(string) Status::Paid` stays an error while a PHP# template shows the text.
5. **Laravel's typed `keyBy` cases.** `Map<int, User> byId = users.keyBy("id").all();` and `User.all()` need a generic `Illuminate\Support\Collection` and an Eloquent `Model` stub in `sharp/harness`, which this PR does not add.
6. **Messages that teach PHP,** already on master, as `check_local_name`'s "PHP forbids `$this`" (sweep 10).
7. **`global.shell_exec(…)` gets follow-on errors** after its "wrapped by the standard library" one: `falsable-return-statement` and `invalid-return-statement`, since the refused call keeps PHP's return type.
8. **`super<HasLabel>.label()` as a whole expression body panics `mago compile`,** on master too: `takes_receiver` in `sharp-bridge/src/lower/inline.rs` asks the type of `super<HasLabel>`, which the analysis gives none, and leaves out only `super` and `Self`.

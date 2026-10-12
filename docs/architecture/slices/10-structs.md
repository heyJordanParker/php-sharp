# Slice 6: Structs

Spec section 10, with the parts of the sections it leans on: section 16 (object initializers and `with`), section 6.1 (`init` and `required`), section 9 and 9.1 (main and named constructors), section 12 (`Map` keys), section 19 (`==`, `===` and `Set`), section 21 (a struct in a `match`), section 23 (the standard `Key` attribute) and section 24 (`Int.parse`, `Any` and `Object`). v1 plan Slice 6, "Structs, `with`, `init`, `required`, object initializers". team-lead approved it at `fdda638880` with the rulings below.

## The answer first

**No part of section 10 exists, and none of it compiles silently.** On mago-sharp `master` at `c3a2b5af8`, `struct`, `readonly struct`, an object initializer and `with` are parse errors, `required` on a property is "not supported yet", `[Key(…)]` is an unknown attribute, and `Note.parse(…)` on a class is "Method `Note.parse` does not exist." Section 10's 4 entries in `sharp/baseline/spec` fail on those parse errors.

**The Slice builds it in one mago-sharp PR and one php-sharp PR.** The mago-sharp PR opens once #166 merges, for a struct key in `has`, `keys()` and `remove`. The php-sharp PR follows the collection Slice's engine PR and pins past generics R3c's php-sharp merge, since `parse` checks types through R3c's engine walk. Generic structs are refused whole until generics R3c (Jordan's answer to question 4).

**Each construct lowers to PHP the engine already runs, plus one engine change:**

- A struct is a `final` PHP class that implements the engine interface `\Sharp\Struct`. Its properties lower exactly as a class's do today: a get-only property is `public readonly`, and an `init` property is `public protected(set)` (#169).
- `==` on a struct calls a generated `op_Equality`, the method a declared `operator ==` already lowers to, so every `==` path the bridge has, a `List`'s `contains` included, compares structs by value with no new branch.
- `x with { a: 1 }` is PHP 8.5's `clone($x, ['a' => 1])`. An object initializer sets the properties after `new`. Outside the class's own scope, both run inside a closure bound to the object, as named constructors already run (`Lowering::named_constructor`).
- `parse` and `tryParse` are static methods the bridge generates from the main constructor's parameter types, which typed compilation gives it.
- Any struct keys a `Map` and joins a `Set` by its whole value. The engine stores it under a key text it writes, `\Sharp\Map::key($struct)`, and reads the text back as the struct, `\Sharp\Map::readKey($text)`.
- The engine changes: php-sharp adds the interface `Sharp\Struct`, the class `Sharp\Map` with `key` and `readKey`, and a struct arm in `sharp_collection_key_slot` and `Sharp\Set::from`. All four write a key text through one C function.

**Plain PHP cannot change a struct with PHP's own syntax.** A write throws `Error`, `clone` with new values throws `Error`, and the class is `final` (run on the slot 7 engine, "What plain PHP can do with a struct"). Reflection can still write an `init` property, as .NET's `FieldInfo.SetValue` writes a `readonly` field.

**Guard 1.** Of section 10's 4 entries, the php-sharp PR removes `struct-changes`, `struct-parse` and the backing-value example as team-lead's spec PR rewrites it. `struct-parse-object` leaves once #454's `Object` type lands, and the PR that removes it writes `Completes: §10`. Outside section 10, the php-sharp PR removes section 23's `import-rename`, and section 16's `initializer-shorthand` and section 12's `enum-map-keys` once team-lead's spec PR (questions 5 and 6) has landed. Section 24's `array-object-replacements` leaves after #454, the harness's `OrderRow` and Slice 4's `Json.encode`.

## Rulings

team-lead's Queue 2, on the note at `fdda638880`:

1. **Decisions 1 to 7 are approved as written.** The closure cost of decision 4 stands. A scoped write opcode is a later Speed item, not this Slice.
2. **Standard-library choices 1 to 9 are approved as written.**
3. **Language rules 1 to 11 are approved. Rule 12 is reversed: a struct states laws as a class does.** Section 28 gives laws to a class (`Money`) and an enum (`Status`), and a struct is where value laws such as `addKeepsCurrency` belong. `sharp-lean` reads a struct through the codex as the `final` class it lowers to.
4. **Collection `==` joins the mago-sharp PR,** through `\array_all` with each element's `==`, a `Map` compared without regard to order ("Found outside the Slice", item 1). Task #463 closes through the required-property check, for classes too.
5. **Cross-slice order: `parse` lands first,** and generics R3c reuses its per-type generator.
6. **Question 6 is team-lead's, approved.** The harness gains `app/Username.sharp` and `app/OrderRow.sharp` in the spec PR team-lead opens with Jordan's answers, not in this Slice's php-sharp PR.
7. **Questions 1 to 5 are with Jordan.** The parts his answers decide are built last, to this note's proposals: the backed shape (question 1), the struct required rule (question 2), `parse` without a main constructor (question 3) and the generic struct refusal (question 4). A different answer replaces that part before the PR opens.
8. **One mago-sharp PR and one php-sharp PR** ("The PRs, in order").

Jordan's answers, through team-lead's Queue 3 and Queue 4:

9. **Question 1, "this one": any struct is a `Map` key by its whole value, with no declaration.** The `: string` header is gone, and a struct header lists interfaces only. Two keys are one key when the struct's `==` says they are equal. `keys()`, the loop and `has` give back the struct itself, never its text. Decision 6 is replaced. Precedent: Swift `Hashable`, Rust `derive(Hash, Eq)`, C# `record struct`, Java `record`, Kotlin `data class`, Go.
10. **Question 2, "this":** in a struct, every property without an initial value must be set at creation, written `required` or not, nullable too. `= null` makes it optional. The message names every missing property: "`new Point` must set `y` and `label`."
11. **Question 3, "this one":** `parse` reads the main constructor's parameters, then every property an object initializer may set.
12. **Question 4, "yes, obviously":** generic structs are allowed and land with generics R3c, so `struct Box<T>` is refused whole until then.
13. **Question 5, "sure":** `initializer-shorthand` declares its own struct. Example errata are team-lead's from now on.

team-lead's Queue 5 to Queue 9:

14. **`MetadataFlags::REQUIRED` means only "must be set at creation":** a property written `required`, or, in a struct, one with no initial value that is not a main-constructor parameter. Each constructor records the properties it definitely assigns on every path (`MethodMetadata::assigned_properties`), as C#'s definite assignment and Swift's definite initialization do, and a helper call doesn't count. At each `new`, the required set minus what that constructor assigns must be named by the initializer. A named constructor's set is its own plus the main constructor's it chains to.
15. **`parse` checks types through generics R3c's engine walk, `sharp_type_accepts`,** one call per parameter with a problem collector. Its own work stays keys, `[Key]` renames, defaults, missing keys and nested structs through their own `parse`. This replaces decision 5's per-type checks and ruling 5's cross-slice order.
16. **The key text, approved:** the class name, then the properties as JSON in declaration order, a `Map` and a `Set` in key order, the shortest round-trip float with `-0.0` as `0.0`, read back without running the constructor.
17. **A struct that holds a class instance, at any depth, can't key a `Map`:** "`Order` can't key a `Map`: `plan` is a `Plan`, a class, and a key holds only values." Refused where `Map<Order, …>` is written, and where a key type is inferred. Precedent: Rust's `HashMap` needs `Hash` on every field, and Swift's `Dictionary` needs `Hashable`. Section 19's `Set` of objects decides later whether a class with `hash()` can join.
18. **`Sharp\Struct`, `Sharp\Map::key` and `Sharp\Map::readKey` are approved,** with three conditions: only a lowered struct implements `Sharp\Struct`, as only an enum implements `UnitEnum`; `readKey` builds only a struct, checks each property's type and limits nesting as `json_decode` does; and one encoder serves every key position.
19. **The php-sharp PR adds the harness's `Username` and `OrderRow`,** one type per file, because only a Mago that parses `struct` compiles them. This replaces ruling 6's spec PR.

## Decisions

1. **A struct is its own CST node, `Struct`,** as `Event` and `Enum` are: attributes, modifiers, the contextual keyword `struct`, its name, an optional `Inheritance` of interfaces, and class members. `struct` is a keyword only where `struct`, a name and `:` or `{` follow, so a local named `struct` stays a name, as `is_at_event` does for `event`.
   - The codex records a struct as a `final` class-like with `MetadataFlags::STRUCT`, so every analyzer check reads it as the class it lowers to, as decision 2 of the events note does for an event.
   - Rejected: `Class` with a `struct` keyword. `Class` is PHP's node, and a struct refuses `extends`. A node of its own gives every consumer an explicit arm, so the compiler finds each place that must handle it.
2. **A struct property lowers as a class property does.** A get-only property is `public readonly`, set once. An `init` property is `public protected(set)` with no `readonly`, so a named constructor can set it again after the main constructor (#169). The class is `final`, so `protected(set)` means only the struct's own code writes it, and the checker allows that write only while the object is created.
   - Rejected: `readonly` on every struct property. PHP refuses a hooked `readonly` property ("Hooked properties cannot be readonly", run on the slot 7 engine), so an `init` body could not run, and a named constructor that sets an `init` property again would throw where the checker accepts it.
3. **`==` on a struct runs a generated `op_Equality(mixed $a, mixed $b): bool`.** It returns `$a === $b` unless both are instances of the struct, so null equals only null and a value of another type is unequal, as section 19 wants for `Any?`. Otherwise it compares each property with that property's own `==`, through `Lowering::equality`. The codex registers the operator, so the analyzer's `==` rules and `declared_equality` find it as they find `Money`'s.
4. **An object initializer and `with` share one write path,** a closure bound to the object, inside the class being created or copied and outside it alike:

   ```php
   (function ($x, $y) { $this->x = $x; $this->y = $y; return $this; })->call(new \App\Point(), 1, 2)   // new Point { x: 1, y: 2 }
   (function ($y) { return clone($this, ['y' => $y]); })->call($p, 9)                                  // p with { y: 9 }
   ```

   The values are evaluated in the caller, as the arguments, so `this` in a value is the caller's. A write runs the `init` or `set` hook (run on the slot 7 engine).
   - Built as one path. The note first proposed plain writes and `clone($x, [...])` inside the class, but an initializer's several writes after `new` are statements, and an initializer is an expression, so it needs the closure or a hidden local in every position. `this with { … }` through `clone` alone cost 625 and 548 ns, against 601 and 659 ns through the closure, so the second path would buy nothing measurable.
   - Measured on Homebrew PHP 8.5.1 with opcache, 1,000,000 creations, two runs: a main constructor took 221 and 240 ns, an initializer through the closure 755 and 631 ns, `this with { … }` inside the class 625 and 548 ns, and `p with { … }` through the closure 601 and 659 ns. The closure costs about what `clone` with an array costs. A scoped write opcode would remove it later, as a Speed item.
   - Rejected: a generated public method that writes the properties. Plain PHP could call it on an existing struct and change it.
5. **`parse` and `tryParse` are generated per struct by the bridge** (ruling 15 moves its type checks to R3c's `sharp_type_accepts`), from the parameter types typed compilation hands it, as serde's derive and kotlinx.serialization's compiler plugin generate a reader per type. `parse` collects every bad field, then throws one `Sharp\InvalidFields`. `tryParse` catches it and returns null. A nested struct is read through its own `parse`, and its fields are re-keyed under the outer path. The generated code needs no runtime type registry and no hidden member. The run below shows the generated shape giving the spec's exact message.
   - Generics R3c builds an element walk for values entering PHP# from plain PHP. It and `parse` check the same thing. Whichever lands second reuses the first, so PHP# keeps one check: if R3c lands first, `parse` reads through R3c's walk with errors collected; if this Slice lands first, R3c's checks reuse `parse`'s per-type generator. team-lead rules which, as a cross-slice order.
   - Rejected: a library reader that interprets a type descriptor at runtime, as Valinor reads types by reflection. It needs each parameter's PHP# type at runtime, which only R3c gives, and a descriptor each struct publishes for nested structs.
6. **Any struct keys a `Map` by its whole value, stored as a key text the engine writes** (ruling 9, replacing the backed key). `counts[point]` stores `\Sharp\Map::key($point)`, as `stored_key` stores a backed enum's `->value`, and a loop reads `\Sharp\Map::readKey($key)`, as `key_read_back` reads `Status::from($key)`. A `Set` of structs holds each struct under the same text, through `\Sharp\Set::from`.
   - The codex's `get_backing_key_type` gives a struct key the type `string`, its text, so every rule that reads a key type (comparators, template inference, array access) takes a struct with no further arm.
   - Two structs are one key exactly when their `==` calls them equal, because the text holds every property with its own value rules: a nested struct by its text, a `Map` and a `Set` in key order, and `-0.0` as `0.0`.
   - The bridge gives each struct `\Sharp\Struct`, so the engine writes a text only for an object PHP# created as a struct.
   - Rejected: a generated `hash()` per struct, as Swift synthesizes `Hashable`. A PHP array keys by `int` or `string` only, so a hash still needs a text, and a hash collision needs a second lookup by `==`. The text is the hash and the equality in one.
7. **A value pattern compares with section 19's `==`,** so `match (point) { origin => … }` matches an equal struct. `php_shape` writes `==` where it writes `===` today (`crates/syntax/src/utils/pattern.rs`), and the bridge lowers that `==` from the checker's types, which give `===` for every scalar and enum.

## Standard-library choices the spec leaves open

`parse` belongs to the standard library, whose API is team-lead's (decision 066). Where the spec sets it, the note follows the spec: the method names, the keys, `[Key]`, nested structs, `List`, enums, `T?` and the message shape `customerId: expected int, got string 'abc'; plan_code: missing`, which `sharp/bin/spec` compares exactly. Each item below is a proposal with its precedent, and team-lead rules.

1. **The error is `Sharp.InvalidFields`, a subclass of `ValueError`,** with `public Map<string, string> fields { get; }`, path to problem, in the order the fields were read. Its message joins them with `; `. `Int.parse` throws `ValueError`, so `catch (ValueError e)` catches both. A caller that answers an HTTP request renders `fields` per field, as Laravel's `ValidationException::errors()` and Pydantic's `ValidationError.errors()` give per-field lists.
   - Names considered: `InvalidFields` (what the caller learns), `ParseError` (refused: PHP's own `\ParseError` is the class `eval` and `include` throw), `MappingError` (Valinor's), `ValidationError` (Pydantic's; PHP# validates nothing beyond types), `PayloadError` (`parse` also reads row objects).
2. **The problem text is `missing` or `expected <type>, got <value>`.** The type is printed as PHP# writes it: `int`, `string?`, `List<Address>`, `Plan`. The value is `null`, `bool true`, `int 5`, `float 1.5`, `string 'abc'`, `List` or `Map` for an array, and `object App.Order`. A string longer than 40 bytes is cut with `…`, so a pasted document does not flood a log. PHP's `TypeError` names the given type the same way ("must be of type string, int given"). One library method builds it, `InvalidFields.expected(string type, Any? value)`, which every generated `parse` calls.
3. **Every bad field is reported at once, depth first, in parameter order.** Valinor collects all errors into one `MappingError` (its docs: "containing information about all errors encountered during the mapping"). C#'s `System.Text.Json`, serde and kotlinx.serialization stop at the first error, except that kotlinx's `MissingFieldException` lists every missing field of one class. The spec chose "one error that lists every bad field".
4. **A path is the input's keys, joined like a PHP# expression:** `address.city`, `addresses[2].city`, `prices["vip"]`. A key is the name the input uses, after `[Key]`, as the spec's `plan_code` shows. System.Text.Json's `JsonException.Path` and kotlinx write `$.addresses[2].city`, serde_path_to_error writes `addresses[2].city`, and Valinor's docs show dotted paths (`foo.bar`) with no list example. The library forms above come from their documentation, not from a run here.
5. **No value is converted.** An `int` parameter takes only an `int`, so `"5"` is `expected int, got string '5'`, and `Int.parse` stays the one way to read a number from text. The one exception is section 24's: an `int` where a `float` is expected becomes a `float`, as at any border with plain PHP. System.Text.Json (`JsonNumberHandling.Strict`), serde, kotlinx (without `isLenient`) and Valinor (without `allowScalarValueCasting()`) are strict by default. Pydantic converts by default, and is the rejected precedent. A database row passes, because PDO returns native numbers since PHP 8.1: on Homebrew PHP 8.5.1, an SQLite `select 7 as id, 2.5 as total` row object held `int(7)` and `float(2.5)`.
6. **Keys the struct does not read are ignored,** so a row object with more columns than the struct parses. System.Text.Json, serde and Pydantic ignore them by default. kotlinx and Valinor refuse them by default, and both add a flag to allow them.
7. **A missing key takes the parameter's default value when it has one,** and is `null` for a `T?` parameter without one, as the spec says. System.Text.Json and kotlinx use the constructor's default.
8. **A value that is already the expected type passes as it is.** A `Plan` parameter takes a `Plan` instance, a nested struct parameter takes that struct, and `Any?` takes anything. A class is not built from a `Map`, since "Classes do not get them". Pydantic's `arbitrary_types_allowed` checks an instance the same way. The spec's `RenewRequest` reads `Plan`, which is a class in `sharp/harness/`, so this rule is what lets its `plan_code` be checked at all.
9. **An object is read through `get_object_vars` from outside its class:** its public properties that are set. An Eloquent model's columns are magic, not properties, so `OrderRow.parse(model)` reports each one missing, and the caller writes `OrderRow.parse(model.toArray())`.

## Language rules the spec leaves open

The checker enforces each one with the message shown. team-lead rules, and brings any it rejects to Jordan.

1. **A struct property has `get`, or `get` and `init`. A `set` accessor is refused:** "A struct never changes, so `x` takes `init`, not `set`." A struct field is set only by its initial value and the main constructor, and lowers to `readonly`. A collection property can't change through `add` on `this`, because that writes the property back (decision 14).
2. **A write to a struct's property after it is created names a method that returns a changed copy,** as the spec's example shows: "A struct never changes; return a changed copy from a method, such as `moved`." The method named is the struct's first method that returns the struct. With none, the help shows `with`: "return a changed copy built with `with`." Both helps get a row that compiles the fix.
3. **A struct header lists interfaces only** (ruling 9). "A struct can't inherit: `Order` is a class." and, on a class, "`Point` is a struct, which can't be inherited." `abstract`, `sealed`, `static` and `readonly` are refused on a struct, and `protected`, `virtual`, `abstract` and `override` on its members: "A struct has no subclasses, so write `private`."
4. **`==` on a struct needs `==` on every property.** "`RenewRequest` can't compare by value: `plan` is a `Plan`, which declares no `operator ==`." Swift synthesizes `Equatable` and Rust derives `PartialEq` under the same condition. Comparing such a property by identity, as C# records and Kotlin data classes do, would make `==` mean identity in part of a value. A struct with a collection property is refused with today's collection refusal, "`==` on a collection is not supported yet", until collection `==` lands ("Found outside the Slice", item 1).
5. **A struct declares no `operator ==`:** "A struct compares by value: remove `operator ==`." So `==` on a struct always means the same thing. Kotlin and C# let a data class or record replace it, and that is the rejected precedent.
6. **`===` with a struct operand is refused:** "`===` tests for the same object, and `Point` is a struct: write `==`." This is the spec's "`===` works on classes only".
7. **`with` sets only properties that have `init` or `set` the caller may use:** "`value` is get-only, so `with` can't set it." with the help "Declare it `{ get; init; }`." A property named twice is refused. On a plain PHP object, `with` sets a public property that is not `readonly`, as PHP's own `clone` with values does. An enum or an event is refused, since neither has a property it could set.
8. **An object initializer and `with` may set an `init` property,** at the `init` accessor's access level. #169 left exactly this to this Slice. A lambda inside either is refused as #169 refuses it in a constructor.
9. **`required` needs `init` or `set`:** "`text` is required, so it is set when its object is created: give it `init` or `set`." Every creation sets each required property the constructor that runs does not set (ruling 14): "`new Label` must set `text`." C# reports the same with CS9035, and refuses a required property without a setter with CS9034.
10. **`[Key("…")]` goes only on a struct's main constructor parameter,** and two keys may not read the same name: "`Key` renames the key `parse` reads, and only a struct's main constructor parameters are read." and "`plan_code` is read twice, by `plan` and `code`."
11. **`X.parse` is refused when a parameter's type can't be read from a value:** a not-backed enum or a function type, also nested. "`Order.parse` can't read `state`: `CheckoutState` has no backing value." The struct itself compiles, so a struct used only inside the program may hold such a type. The error is at the call, where a `parse` that cannot work would be used.
12. **A struct states laws as a class does** (ruling 3, which reversed this note's first proposal). A struct's law and its proof follow section 28 unchanged, and `sharp-lean` reads the struct through the codex as the `final` class it is, as it reads any class.

## Spec questions for Jordan

Jordan answered all six (rulings 9 to 13, and ruling 6 for question 6). The questions stay as he read them.

1. **What is a struct's backing value?** Section 10 line 483: "**A struct can declare a backing value** with the header an enum uses (section 20). `public struct Username : string` has a `string` backing value, so it can be a `Map` key (section 12)." Line 489: `public Username(public string value { get; }) { }`. Section 12 line 947: `Map<Username, Order> byUser = [:];   // keyed by username.value`. Nothing says which member is the backing value, or how a key becomes a `Username` again.
   - Proposal: a backed struct's main constructor takes exactly one parameter, a get-only property named `value` of the backing type, and any other shape is a compile error: "A struct backed by `string` holds its backing value in `public Username(public string value { get; })`." Then `->value` stores the key and `new Username(key)` reads it back, as an enum's `->value` and `from` do, and Swift's `rawValue` and `init(rawValue:)` do (decision 027's precedent).
2. **Is every property of a struct that has no initial value required?** Line 450: "**A struct has no default value.** Every `required` property must be set when one is created." Lines 459 and 460 declare `public int x { get; init; }` and `public int y { get; init; }` without `required`, and line 474 creates `new Point { x: 1, y: 2 }`. If only `required` properties must be set, `new Point { x: 1 }` compiles and leaves `y` without a value, which PHP reports as "must not be accessed before initialization" when `y` is read.
   - Proposal: in a struct, a property with no initial value that the constructor does not set is required, written or not. That is what "no default value" means, and it is Swift's memberwise rule and Kotlin's data class constructor.
3. **What does `parse` read for a struct with no main constructor?** Line 512: "The keys are the parameter names of the main constructor (section 9.1)." The section's own `Point` has no main constructor and is created by an initializer.
   - Proposal: `parse` reads the main constructor's parameters, then each property an object initializer may set that the constructor does not, by its name. That is creation from a `Map`: the constructor call, then the initializer. System.Text.Json fills a type the same way, constructor parameters first, then `init` properties.
4. **May a struct take type parameters?** Section 10 shows none, and section 11 shows classes. C#, Swift, Kotlin and Rust allow generic value types.
   - Proposal: yes, with section 11's rules. `Box<int>.parse(…)` reads `T` while the code runs, so generic structs follow generics R3c, and until then `struct Box<T>` is refused whole: "A generic struct is not supported yet in PHP#." That refuses one whole rule, not part of one.
5. **Errata for `initializer-shorthand` (section 16, lines 1509 to 1515).** It writes `new Money { amount: 500, currency }`. The harness `Money` declares `public Money(public int amount { get; }, public string currency { get; })`, so the example both omits two constructor arguments and sets two get-only properties. Proposal: the example's setup declares the struct it creates, `public struct Amount { public int value { get; init; } public string currency { get; init; } }`, and the line reads `new Amount { value: 500, currency }`. `naming-values` (lines 1499 to 1505) is ignored and needs no change.
6. **Errata for two examples that name a struct no file declares.** Section 12 line 947 names `Username` (task #498), and section 24 line 2471 names `OrderRow`. Proposal: the harness gains `app/Username.sharp` with line 487's struct and `app/OrderRow.sharp` with line 525's, each in its own file (Jordan's ruling 3).

## How the states were observed

Every state below comes from the `mago` built from mago-sharp `master` at `c3a2b5af8` (#169 merged) in slot 9, `/Users/jordan/Developer/sharp-worktrees/9/mago-sharp/target/debug/mago`, built at 17:33 local time on 2026-10-12 with `git status --short` empty.

**The spec's examples ran through Guard 1's own checker.** `git archive` exported `README.md`, `docs/spec.md`, `sharp/bin/spec`, `sharp/baseline/spec`, `sharp/harness/` and `sharp/composer/` from php-sharp `master` at `7fdcaf1685` into `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-structs/spec-tree`, and `sharp/bin/spec` ran there on the slot 7 engine with `TEST_MAGO_EXECUTABLE` set to the `mago` above. It ended "Checked 167 examples, ignored 2, and 91 fail."

**Single rules ran on one probe project,** `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-structs/ws`, a copy of one example's generated project with the example removed: the harness, the standard library under `vendor/heyjordanparker/php-sharp-composer/`, and the probe files under `app/Probe/`.

```sh
MAGO=/Users/jordan/Developer/sharp-worktrees/9/mago-sharp/target/debug/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-structs
env -u FORCE_COLOR XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=json MAGO_LOG=error MAGO_THREADS=1 \
  $MAGO --no-version-check --colors never --workspace $P/ws compile > $P/probe.json
php $P/issues.php $P/probe.json
```

`issues.php` prints each issue with the message of its first span. Each probe below is quoted with what it printed. A parse error prints one line per token the parser skipped, and each section quotes the first.

**The lowering ran on the slot 7 engine,** `/Users/jordan/Developer/sharp-worktrees/7/php-sharp/sharp/build/darwin-arm64/sapi/cli/php` (PHP 8.5.11, at `efaccd222e`), through `$P/lowered.php`: the PHP this note says each form lowers to, written by hand, with `declare(strict_types=1)`. PHP's own behavior ran through `$P/php-precedent.php` on the same engine, and the cost through `$P/initializer-cost.php` on Homebrew PHP 8.5.1.

# Every rule of section 10, its state and its mechanism

The states are **parse error**, **refused** (a named "not supported yet" or another error), **missing** (it parses and the name does not exist) and **works**.

## A struct is a value type

"A `struct` is a value type."

Parse error. `app/Probe/Point.sharp`:

```csharp
namespace App.Probe;

public struct Point
{
    public int x { get; init; }
    public int y { get; init; }
}
```

printed

```text
app/Probe/Point.sharp:3 parse: Parse error encountered during parsing [Expected one of `Class`, found `Identifier`]
```

and 8 more for the lines after it. `struct-changes`, `struct-backing-value`, `struct-parse` and `struct-parse-object` each fail on the same error first, as `docs/spec.md:457 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier``.

- PHP it lowers to, run on the slot 7 engine:

  ```php
  final class Point
  {
      public protected(set) int $x;
      public protected(set) int $y;
  }
  ```

- Mechanism:
  - Syntax: the `Struct` node and `Statement::Struct` (decision 1). `parse_modified_class_like_with_attributes` tries `is_at_struct` beside `is_at_event`. `composer/src/Sdk/Syntax/NodeKind.php` regenerates.
  - Names: the walker binds a struct's members as a class's.
  - Codex: a `final` class-like with `MetadataFlags::STRUCT`.
  - Semantics: `check_slice` allows `Struct` at file level, and refuses the modifiers and members of language rules 1, 3, 5 and 12.
  - Bridge: `Lowering::r#struct` emits the `final` class, its members through the class path, and `op_Equality` (decision 3).

## A struct never changes, and a method returns a changed copy

"**A struct never changes.** A method returns a changed copy, usually built with `with`, as Kotlin's data class `copy` does. `point.x = 5` is a compile error that names a method returning a changed copy."

Parse error for a struct. The rule it builds on works for a class: #169 refuses a write to an `init` property after creation. `app/Probe/Writes.sharp`, `public void change(Note note) { note.text = "c"; }` on `public string text { get; init; } = "";`, printed

```text
app/Probe/Writes.sharp:5 invalid-property-write: `text` is set only while the object is created. [Written here.]
```

`struct-changes`'s `p.x = 5;` line printed `docs/spec.md:478 expects a compile error, and the checker accepts it`, only because the struct above it failed to parse.

- PHP's behavior, run: `$p->x = 5` from outside threw `Error: Cannot modify protected(set) property App\Point::$x from global scope`, and `p.moved(1, 0)` gave `[2,2]` while `p` stayed `[1,2]`.
- Mechanism: `crates/analyzer/src/resolver/property.rs`, #169's write rule, gives a struct's property language rule 2's message. Semantics refuses `set` on a struct property (language rule 1).

## `readonly struct` is a compile error

"**`readonly struct` is a compile error,** because every struct is already read-only."

Parse error. `app/Probe/Size.sharp`, `public readonly struct Size { public int width { get; init; } }`, printed

```text
app/Probe/Size.sharp:3 parse: Parse error encountered during parsing [Expected one of `Class`, found `Identifier`]
```

- PHP it lowers to: nothing, since it is refused.
- Mechanism: the parser reads `readonly` as a modifier of the struct, and `check_slice` refuses it: "Every struct is already read-only: write `struct`." A test row compiles `public struct Size { … }`. Section 13's Slice refuses `readonly class`, and this Slice refuses `readonly struct`.

## `==` compares values, and `===` works on classes only

"**`==` compares values.** `===` works on classes only (section 19)."

Missing for structs. `app/Probe/Compares.sharp` shows the paths a struct joins: `a == b` on two `Money` compiled, `a === b` on two `Note` compiled, and `a == b` on two `List<Money>` printed

```text
app/Probe/Compares.sharp:9 not-supported-yet: `==` on a collection is not supported yet, so it cannot compare `List<Money>` with `List<Money>`. [This is `List<Money>`.]
```

`app/Probe/Hashes.sharp`'s `prices.contains(price)` on a `List<Money>` compiled.

- PHP it lowers to, run on the slot 7 engine:

  ```php
  public static function op_Equality(mixed $a, mixed $b): bool
  {
      if (!$a instanceof \App\Point || !$b instanceof \App\Point) {
          return $a === $b;
      }

      return $a->x === $b->x && $a->y === $b->y;
  }
  ```

  `p == p.moved(0, 0)` gave `true`, `p == p.moved(1, 0)` gave `false`, an `Any?` holding `5` against `p` gave `false`, `null == null` gave `true`, and the `\array_find_key` search #160 lowers `contains` to found an equal `Point` in a list of two.
- PHP's own `==` on two equal objects gave `true` and `===` gave `false` (`php-precedent.php`), which is why PHP# cannot reuse either.
- Mechanism:
  - Codex: the scanner registers `operator ==` on every struct, so `operator_class` returns the struct and `declared_equality` calls it.
  - Analyzer: language rules 4, 5 and 6 in `crates/analyzer/src/expression/binary/comparison.rs`, beside `report_sharp_refusal`.
  - Bridge: `Lowering::r#struct` writes the method, comparing each property through `Lowering::equality` with two `Side::Hidden` reads.
  - Patterns: decision 7.

## A struct cannot inherit or be inherited, and implements interfaces

"**A struct cannot inherit or be inherited,** but it can implement interfaces."

Parse error, as every struct is.

- PHP it lowers to: `final class Username implements \App\HasLabel`. The engine refuses a subclass of a `final` class: `Class Sub cannot extend final class InitPoint` (`php-precedent.php`).
- Mechanism: every name in the header is an interface. The analyzer refuses a class in a struct's header and a struct in a class's header (language rule 3). The bridge adds `\Sharp\Struct` to the header's interfaces.

## A struct has no default value, and every `required` property is set

"**A struct has no default value.** Every `required` property must be set when one is created."

Refused. `app/Probe/Label.sharp`, `public required string text { get; init; }` in a class, printed

```text
app/Probe/Label.sharp:5 parse: `required` is not supported yet in PHP#. [Not supported yet.]
```

- PHP it lowers to: the property without `required`. The rule is the checker's alone.
- Mechanism: semantics accepts `required` on a property with `init` or `set` (language rule 9). The analyzer checks each `new`, with or without an initializer and through a named constructor, against the required properties the running constructor does not set. In a struct, question 2's answer decides whether a property without an initial value counts as required.

## `with` copies an object or a struct

"`with` copies an object or a struct and sets the listed properties through their `init` or `set` accessors. The original is unchanged."

Parse error, on a class too. `app/Probe/Copies.sharp`, `public Note changed(Note note) => note with { text: "b" };`, printed

```text
app/Probe/Copies.sharp:5 parse: Parse error encountered during parsing [Expected one of `Semicolon`, found `Identifier`]
```

- PHP it lowers to (decision 4), run on the slot 7 engine: `this with { x: this.x + dx, y: this.y + dy }` inside `Point` is `clone($this, ['x' => $this->x + $dx, 'y' => $this->y + $dy])`, and `p with { y: 9 }` outside `Point` is the bound closure, which gave `y` `9` and left `p.y` `2`. A `set` hook ran on the copy and left the original's value (`php-precedent.php`: `["B","A"]`).
- PHP's behavior: `__clone` runs before the new values are written, so a plain PHP parent's `__clone` sees the old ones (`php-precedent.php`: `[2,1]`).
- Mechanism:
  - Syntax: `Expression::With`, the object, `with`, and `{ name: value, … }`, at section 19's precedence, between the unary operators and `!`. Each item is a `NamedValue`, `name: value` or the shorthand `name`, which the object initializer shares.
  - Analyzer: language rules 7 and 8, each value checked as a write to its property.
  - Bridge: `Lowering::with`.

## Any struct is a `Map` key by its whole value

The spec's line, "**A struct can declare a backing value** … so it can be a `Map` key (section 12)", becomes Jordan's answer to question 1 (ruling 9) in team-lead's spec PR: any struct keys a `Map` by its whole value.

Parse error before this Slice. `app/Probe/Username.sharp`, line 487's `Username`, printed

```text
app/Probe/Username.sharp:3 parse: Parse error encountered during parsing [Expected one of `Class`, found `Identifier`]
```

**The key text** (ruling 16) is what plain PHP sees when it reads the array. It is the struct's class name, then its properties as a JSON object in declaration order. A nested struct writes its own text, an enum case writes `Class::Case`, a `List` writes a JSON array, and a `Map` or `Set` writes a JSON object in key order, so two equal `Map`s give one text. A string escapes `"`, `\` and each byte below `0x20` as JSON does. A float writes its shortest round-trip form, with `-0.0` as `0.0`, and `INF`, `-INF` and `NAN` as words.

**`readKey` is the exact inverse of `key`.** It creates the object without running the constructor, as `unserialize` does, sets each property in declaration order through PHP's own property type check, and refuses any other text with `ValueError`. Text from plain PHP can name any class, so `readKey` checks the class is a struct before it creates one, and stops past 512 levels of nesting, `json_decode`'s default depth.

**The `NaN` gap.** `NAN == NAN` is false, as in every language with IEEE floats, but two structs holding `NAN` write one text, so they are one key. Swift's `Hashable` and Rust's `Hash` on a float wrapper have the same gap. A key holding `NAN` is rare, and the alternative, a key that never finds itself again, is worse.

**JSON keys use `toString()`** (Queue 4). `Json.encode` doesn't exist on `master` yet, so this rule waits for the JSON Slice: `Json.encode` writes a struct key through the struct's `string toString()`, and encoding a `Map` whose key struct declares no `toString` is a compile error, "A JSON object key is text, and `Point` has no `toString`." Precedent: Jackson writes keys with `toString`, and serde_json and Go refuse keys that aren't text.

**A struct holding a class instance can't key a `Map`** (ruling 17). `app/S/Keys.sharp`, `Map<Order, int> counts` with `App.S.Order` holding `Plan plan`, printed

```text
app/S/Keys.sharp:5 template-constraint-violation: `App.S.Order` can't key a `Map`: `plan` is an `App.S.Plan`, a class, and a key holds only values. [`App.S.Order` keys this `Map`.]
```

The names are full because the harness also declares `App.Order` and `App.Plan`, so the short names would be ambiguous.

with the note "A struct key is stored as text built from its values, and a class instance compares through its own `==`, which no text matches." A `Set<Order>` gives "`Order` can't be in a `Set`: …". The walk follows nested structs, `List` and `Map` elements and `T?`, and names an interface, a function and `Any` as well.

- PHP it lowers to, run on the slot 8 engine through `Zend/tests/sharp/struct_keys.phpt`, with `Spot`, `Route` and `StructKeys` from `Zend/tests/sharp/`:

  ```text
  counts[new Spot(1, 2)] = 1; counts[new Spot(1, 2)] = 2; counts[new Spot(2, 1)] = 3;
  ["Demo\Spot{"x":1,"y":2}"] => 2, ["Demo\Spot{"x":2,"y":1}"] => 3

  two Routes, one with length 0.0 and tolls ["b": 2, "a": 1, "7": 3], one with -0.0 and the same tolls in another order:
  Demo\Route{"stops":[Demo\Spot{"x":1,"y":2}],"standing":Lib\Standing::Active,"tone":Demo\Tone::Warm,"length":0.0,"tolls":{"7":3,"a":1,"b":2},"note":"a \"quoted\"\u000a","open":true}
  ```

  The loop read each key back as the `Route` it was. `readKey` refused `stdClass{}` with "names stdClass, which is not a struct", a missing class with "names Demo\Missing, which does not exist", `{"x":"one"}` with PHP's "Cannot assign string to property Demo\Spot::$x of type int", 600 nested lists with "nests deeper than 512", and a missing property, a reordered one, an unknown enum case and trailing text with "is not a key text". A plain PHP class implementing `Sharp\Struct` stopped with "Non-struct class Fake cannot implement interface Sharp\Struct". PHP itself refuses an object key: `TypeError: Cannot access offset of type ReadonlyPoint on array` (`php-precedent.php`).
- Mechanism:
  - Codex: `get_backing_key_type` gives a struct key the type `string`.
  - Analyzer: `report_class_held_by_key` in `crates/analyzer/src/statement/class_like/struct.rs`, called where a `Map` or `Set` type is written and where a literal's key infers one. A loop over a `Map` whose keys mix a struct with other types is refused, as a mixed enum key is.
  - Bridge: decision 6. Every key position #166 adds (`has`, `keys()`, `remove`) reads through `stored_key` and `key_read_back`, so they take a struct with no further arm.
  - Engine (the php-sharp PR): `Sharp\Struct`, whose `interface_gets_implemented` accepts only a `final` class compiled from a `.sharp` file, as `UnitEnum` accepts only an enum. `Sharp\Map::key`, `Sharp\Map::readKey`, `Sharp\Set::from` and `sharp_collection_key_slot` all write through `sharp_struct_key`.
  - `groupBy` and `associateBy` with a struct key stay refused by their template bound, `G of array-key`, as an enum key is, until the collection Slice widens it. The engine's `sharp_collection_key_slot` arm is ready for it, and the class-instance refusal joins the inferred key type then.

## Every struct has a static `parse` and `tryParse`

"**Every struct has a static `parse`,** which reads a `Map<string, Any?>` or an object's public properties and throws one error that lists every bad field, and a static `tryParse`, which gives null instead. The names follow `Int.parse` and `Int.tryParse` (section 24). Classes do not get them." With its list: the keys are the main constructor's parameter names, `[Key("plan_code")]` renames one, a nested struct parses the same way, a `List` checks each element, an enum parses from its value, and a missing key for a `T?` parameter reads as null.

Missing. `struct-parse` fails on the struct's parse error. On a class, `app/Probe/Parses.sharp`, `Note.parse(payload)`, printed

```text
app/Probe/Parses.sharp:5 non-existent-method: Method `Note.parse` does not exist. [This method selection is invalid]
```

which is already the spec's "Classes do not get them", with a second `mixed-return-statement` for the unknown result. `app/Probe/Request.sharp`, `[Key("plan_code")]` on a class's constructor parameter, printed

```text
app/Probe/Request.sharp:5 non-existent-attribute-class: Attribute class `Key` not found or could not be autoloaded. [Unknown attribute class `Key`]
```

- PHP it lowers to, run on the slot 7 engine (`lowered.php`'s `RenewRequest` and `Address`, the spec's struct with an enum and a `List<Address>` added): each parameter is read in order into `$fields`, and the constructor runs only when none failed.

  ```php
  if (!array_key_exists('customerId', $input)) {
      $fields['customerId'] = 'missing';
  } elseif (!is_int($input['customerId'])) {
      $fields['customerId'] = \Sharp\InvalidFields::expected('int', $input['customerId']);
  }
  ```

  The run printed:

  ```text
  RenewRequest.parse(["customerId": "abc"]): Sharp\InvalidFields: customerId: expected int, got string 'abc'; plan_code: missing
  every bad field, nested paths: Sharp\InvalidFields: coupon: expected string?, got int 5; status: expected Status, got string 'gone'; addresses[1].city: expected string, got int 4; addresses[2]: expected Address, got string 'x'
  tryParse on a bad payload: null
  parse of a good payload: "Oslo"
  ```

  The first line is the spec's message to the byte.
- Mechanism:
  - Codex: the scanner registers `public static Self parse(Map<string, Any?>|Object value)` and `tryParse`, returning `Self?`, on every struct. `Object` is the codex's own object type, so `parse` takes any object before #454 lets PHP# code write `Object`.
  - Analyzer: language rules 10 and 11.
  - Bridge: `Lowering::parse` writes both methods (decision 5), reading `[Key]` from the parameter's attributes in the CST.
  - Standard library (the php-sharp PR): `Sharp.Key` and `Sharp.InvalidFields`.

## `parse` reads an object's public properties

"An object's public properties parse the same way as a `Map`'s keys, so a row object from plain PHP becomes a checked struct."

Missing, as `parse` is. `struct-parse-object`'s setup line `Object row = Rows.withId("abc");` also needs #454: section 24's `object-type` printed `docs/spec.md:2541 expects no error, and the checker reports: This type is not supported yet in PHP#.`

- PHP it lowers to, run: `\get_object_vars($value)` read inside the struct's class, which gives another class's public properties only. `Address.parse` of a row object with an extra `id` gave `"Oslo"`, and of `city: 7` threw `city: expected string, got int 7`.
- Mechanism: part of `Lowering::parse`. Standard-library choice 9 covers magic properties.

# Other spec examples that use a struct

- **Section 12, `enum-map-keys` line 947,** `Map<Username, Order> byUser = [:];`. Needs the harness's `Username` (ruling 19), #166, and this Slice's struct `Map` keys. Its line 946, `orders.groupBy(o => o.status)`, needs #166 and php-sharp's collection PR 4.
- **Section 12 line 663,** "They are values, as structs (section 10) and PHP's own arrays are." Prose, no example.
- **Section 16, `initializer-shorthand`,** `new Money { amount: 500, currency }`. Needs the object initializer and question 5's errata. The object initializer itself is section 16 line 1533: "An object initializer runs after the constructor. It sets properties through their `set` or `init` accessors, so validation in those accessors runs. The checker requires every `required` property to be set." `app/Probe/Initializes.sharp`, `new Note { text: "a" }`, printed `app/Probe/Initializes.sharp:5 parse: Parse error encountered during parsing [Expected one of `Semicolon`, found `LeftBrace`]`. It lowers as decision 4 shows.
- **Section 19,** "`Any?` compared with `==` against a struct … compares by value, and is false when the types differ" (decision 3, run above), and "`Set` hashes a struct … by its value". A `Set` of structs holds each one under its key text, as a `Map` key does, so `Set<Point>` works with this Slice. A `Set` of class instances stays refused: `app/Probe/Hashes.sharp`, `Set<Money> prices(…)`, printed `app/Probe/Hashes.sharp:7 not-supported-yet: This type is not supported yet in PHP#.` ("Found outside the Slice", item 2).
- **Section 21,** "`match` … compares strictly": a struct subject against a local's value (decision 7).
- **Section 23, `import-rename`,** `public struct Entry { public Entry([Key("cache_key")] public CacheKey key { get; }) { } }`. Needs `struct`, `Sharp.Key` (the php-sharp PR) and the `[Key]` rule. It printed `docs/spec.md:2278 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier``.
- **Section 24, `array-object-replacements` line 2471,** `OrderRow order = OrderRow.parse(row);`. Printed `docs/spec.md:2471 expects no error, and the checker reports: Method `OrderRow.parse` does not exist.` Needs `parse`, #454's `Object`, the harness's `OrderRow` (ruling 19), and Slice 4's `Json.encode` for line 2475.
- **Section 24 line 2477,** `(object)["x": 1]` is refused with "write a `Map` literal, or a struct". **Line 2481 and 2483:** "a declared struct for a shape that repeats" and "To keep a `Map` a JSON object, write its type or use a struct." PHP's `json_encode` already writes a struct's public properties: `{"x":1,"y":2}` (run), so Slice 4's `Json.encode` needs no struct arm.
- **Section 30 line 3312,** "The checker suggests a struct past four." Section 30's Slice.

# What plain PHP can do with a struct

Each line ran on the slot 7 engine through `lowered.php`.

- **Create one** through its main constructor, with PHP's own type checks: `new App\Username(5)` threw `TypeError: App\Username::__construct(): Argument #1 ($value) must be of type string, int given`. A struct without a main constructor, such as `Point`, can't be filled from plain PHP: `$q = new App\Point(); $q->x = 1;` threw `Cannot modify protected(set) property App\Point::$x from global scope`. Plain PHP creates it with `App\Point::parse(['x' => 1, 'y' => 2])`, which checks every field.
- **Change one: no.** `$p->x = 5` threw `Cannot modify protected(set) property App\Point::$x from global scope`, `clone($p, ['x' => 5])` threw the same, and `$u->value = "eve"` threw `Cannot modify readonly property App\Username::$value`. A subclass can't exist, because the class is `final`. `ReflectionProperty::setValue` changed an `init` property (`[5,1]`, on a copy) and was refused on a get-only one (`Cannot modify readonly property App\Username::$value`). Reflection is outside PHP#'s guarantees, as it is outside .NET's for a `readonly` field.
- **Compare two:** with `App\Point::op_Equality($a, $b)`. PHP's own `$a == $b` compares property by property with PHP's loose rules, and gave `true` for two equal `Username`s, but it would also equal `"10"` and `"1e1"` held in a `string` property.
- **Pass a bad value** into a PHP# method: PHP's parameter type checks the class, as for any class. A struct reaching PHP# from plain PHP was created by its constructor or `parse`, so its fields were checked there.
- **Encode one:** `json_encode` writes its public properties.
- **Read a `Map` keyed by structs:** each key is its key text, a string. `Sharp\Map::readKey($key)` gives the struct back, and `Sharp\Map::key($struct)` gives the text for a lookup. `Sharp\Map::key(new stdClass())` throws `TypeError: … must be of type Sharp\Struct, stdClass given`.
- **Implement `Sharp\Struct`: no.** A plain PHP class that names it stops with "Non-struct class Fake cannot implement interface Sharp\Struct", so the encoder never meets a class it could recurse through forever.

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
mago-sharp
├── crates/syntax/src/
│   ├── cst/cst/
│   │   ├── struct.rs                     <- new: Struct, `struct Name : Interfaces { members }`
│   │   ├── with.rs                       <- new: With, `object with { name: value, … }`; NamedValue, `name: value` or `name`
│   │   ├── instantiation.rs              <- Instantiation gains initializer: Option<ObjectInitializer>, `new X(…) { name: value, … }`
│   │   ├── expression.rs                 <- Expression gains With
│   │   └── statement.rs                  <- Statement gains Struct
│   ├── parser/internal/struct.rs         <- new: is_at_struct, parse_struct_with_attributes_and_modifiers
│   └── utils/pattern.rs                  <- a value pattern compares with == (decision 7)
├── composer/src/Sdk/Syntax/NodeKind.php  <- regenerated
├── crates/codex/src/
│   ├── metadata/flags.rs                 <- MetadataFlags::STRUCT, MetadataFlags::REQUIRED
│   ├── metadata/function_like.rs         <- MethodMetadata::assigned_properties, what a constructor sets on every path
│   ├── metadata/mod.rs                   <- CodebaseMetadata::struct_parse_fields, ParsedField
│   ├── ttype/mod.rs                      <- get_backing_key_type: a struct key is a string, its key text
│   └── scanner/                          <- a struct as a final class-like; operator ==, parse and tryParse
│                                            registered on it
├── crates/semantics/src/internal/checker/sharp.rs
│                                         <- Struct allowed; readonly struct, set and modifiers refused; required allowed
├── crates/analyzer/src/
│   ├── statement/class_like/struct.rs    <- new: check_struct, language rules 3 and 4; report_class_held_by_key
│   ├── expression/with.rs                <- new: analyze With, language rules 7 and 8
│   ├── expression/instantiation.rs       <- the object initializer, and required properties at every new
│   ├── resolver/property.rs              <- a struct's write names a method returning a changed copy
│   ├── expression/binary/comparison.rs   <- === on a struct refused
│   └── code.rs                           <- IssueCode::InvalidStruct, `invalid-struct`;
│                                            IssueCode::MissingRequiredProperty, `missing-required-property`
└── crates/sharp-bridge/src/lower.rs      <- Lowering::r#struct, with, object_initializer, parse; a struct key
                                             through \Sharp\Map::key and \Sharp\Map::readKey

php-sharp
├── sharp/composer/library/Sharp/
│   ├── Key.sharp                         <- new: the attribute, `[Key("plan_code")]`
│   └── InvalidFields.sharp               <- new: InvalidFields : ValueError, fields and expected(type, value)
├── ext/sharp/sharp.c                     <- interface Sharp\Struct; final class Sharp\Map with
│                                            key(Sharp\Struct $struct): string and readKey(string $text): Sharp\Struct;
│                                            the struct arm of sharp_collection_key_slot and Sharp\Set::from
├── Zend/tests/sharp/Spot.sharp, Tone.sharp, Route.sharp, StructKeys.sharp, struct_keys.inc,
│   struct_keys.phpt, struct_marker.phpt, Structs.sharp, structs.inc, structs.phpt, struct_parse.phpt
│                                         <- new, the engine's tests
├── sharp/harness/app/Username.sharp, OrderRow.sharp
│                                         <- new (ruling 19)
└── ext/sharp/Cargo.toml, Cargo.lock      <- the pinned Mago commit moves
```

Not added: no `sharp_kind`, no opcode, no crate, no `.sharpc` format change, no `ce_flags` bit, and no engine change to the class compiler.

The names, each with the slate it was picked from:

- `Struct`: the keyword. Also considered: `ValueType` (C#'s category, not its keyword), `Record` (C#'s and Java's word for a different feature).
- `With` and `NamedValue`: `with` is the keyword, and section 16 calls `name: value` "naming a value". Also considered: `WithExpression`, `CopyWith` (Dart's method), `PropertyAssignment`, `InitializerItem` (fits the initializer, not `with`).
- `ObjectInitializer`: section 16's word, and C#'s. Also considered: `Initializer`, `PropertyInitializer`, `MemberInitList`.
- `Sharp.InvalidFields`: standard-library choice 1.
- `Sharp.Key`: the spec's name.
- `invalid-struct`: every declaration rule it reports is about a struct, as `invalid-event` is for events. Also considered: `struct-mutation` (misses the header rules), one code per rule.
- `missing-required-property`: what is wrong at the `new`. Also considered: `required-property-not-set`, `missing-required-member` (C#'s CS9035 says "member"; PHP# says property).
- `check_struct`: as `check_listener`. Also considered: `check_struct_declaration`, `check_value_type`.
- `MetadataFlags::STRUCT`: beside `INIT` and `NAMED_CONSTRUCTOR`.
- `Sharp\Struct`: the marker, as `UnitEnum` marks an enum. Also considered: `Sharp\Value`, `Sharp\StructMarker`.
- `Sharp\Map::key` and `Sharp\Map::readKey`: beside the bridge's `\Sharp\Set::from`. Also considered: `Sharp\Key::of` (`Sharp.Key` is the attribute), `encode` and `decode` (JSON's words).
- `MetadataFlags::REQUIRED` and `MethodMetadata::assigned_properties` (ruling 14). Also considered: `MUST_SET`, `definitely_assigned`.

# The PRs, in order

**One mago-sharp PR and one php-sharp PR build all of section 10** (Jordan's direction, team-lead's Queue 2: every PR is another full round of suites, review and merge). The harness's `Username` and `OrderRow` go in the php-sharp PR (ruling 19).

1. **mago-sharp: structs**, on `feat/slice-structs` in slot 9. Every rule of section 10, with section 16's object initializer, section 6.1's `required` and section 19's collection `==`:
   - `Struct`, `readonly struct`, a struct never changes (language rules 1 and 2), the header with interfaces and no inheritance (rule 3), and laws on a struct (rule 12).
   - `==` by value, `===` refused (decision 3, rules 4 to 6), decision 7's value pattern, and collection `==` through `\array_all` with each element's `==`, a `Map` compared without regard to order.
   - The object initializer and its shorthand, `with` on structs and classes, `required` (rule 9), and #169's `init` write rule widened to both (rule 8). The required-property check closes task #463 for classes too.
   - Any struct as a `Map` key and a `Set` element by its whole value, the refusal of a struct holding a class instance, and the read-back (decision 6), in every key position #166 adds.
   - `parse` and `tryParse` (decision 5), `[Key]` (rules 10 and 11), and question 3's answer. Its type checks call R3c's `sharp_type_accepts` once its signature is fixed (ruling 15).
   - A generic struct refused whole (question 4).
   - Its tests stub `Sharp\Key` and `Sharp\InvalidFields` as fixtures, so the PR needs nothing from php-sharp.
   - It needs #166 merged, for a struct key in `has`, `keys()` and `remove`. The branch merges `origin/master` once #166 lands, then the PR opens with `ci-passed` and `patch-coverage` green. One fresh reviewer follows.
   - Run: `compile` compiles `struct-changes`, `struct-parse` and `import-rename`, refuses `Size.sharp` with "Every struct is already read-only: write `struct`.", refuses `p.x = 5` naming `moved`, refuses `new Label { }` with "`new Label` must set `text`.", refuses `Map<Order, int>` for an `Order` holding a `Plan`, and refuses `Order.parse` on a struct holding a not-backed enum.
2. **php-sharp: the engine runs structs**, on `feat/slice-structs` in slot 8. It follows the collection Slice's engine PR, which brings `sharp_collection_key_slot`'s backed enum arm, and is built while the mago-sharp PR is reviewed. It holds `sharp/composer/library/Sharp/Key.sharp` and `InvalidFields.sharp`, `Sharp\Struct` and `Sharp\Map` with the struct arms of `sharp_collection_key_slot` and `Sharp\Set::from`, the harness's `Username` and `OrderRow`, `struct_keys.phpt`, `struct_marker.phpt`, `structs.phpt` and `struct_parse.phpt` with their fixtures in all four opcache modes, and the pin past both the mago-sharp PR's merge and R3c's php-sharp merge. It removes every Guard 1 entry it fixes, through the `cp` command a run prints.
   - Run: `sharp/bin/test --opcache --passes` passes, and `sharp/bin/test --spec` passes with the shorter list.

The work in flight:

- **#166 (Map methods):** the mago-sharp PR opens after it merges.
- **The collection Slice's engine PR:** the php-sharp PR follows it.
- **#454 (`Object`, `typeof`):** `parse` itself does not need it: the codex types `parse`'s parameter with its own object type. The examples `struct-parse-object` and `array-object-replacements` write `Object` in PHP#. `Completes: §10` goes on the PR that removes `struct-parse-object`, which needs #454.
- **#152, php-sharp #102 and generics R3c:** a generic struct is refused whole until them (question 4). `parse` checks types through R3c's `sharp_type_accepts` (ruling 15), so the php-sharp PR pins past R3c's php-sharp merge.
- **The collection Slice:** widens `groupBy` and `associateBy`'s `G of array-key` for enum and struct keys. Until then a struct key there is refused as an enum key is.
- **#169 (`init`) and #161 (named constructors): merged** at `c3a2b5af8`. The PR builds on #169's write rule and its `protected(set)` lowering, and a struct's named constructor runs through #161 unchanged.

**Guard 1.** The php-sharp PR removes `struct-changes`, `struct-parse`, `import-rename` and the rewritten backing-value example, and `enum-map-keys` and `initializer-shorthand` when team-lead's spec PR has landed before it. `struct-parse-object` leaves with #454, and `array-object-replacements` also needs the harness's `OrderRow` and Slice 4's `Json.encode`.

Each PR body states the Problem, the Solution and an annotated file tree.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. No `.php` twin is written: no rule here runs code that `.php` also runs. Each help that quotes code has a row that compiles the fix. mago-sharp's `patch-coverage` needs every changed line run, so each refusal arm has a row.

**mago-sharp, `crates/syntax/tests/sharp.rs`:**

- `a_struct_header_lists_interfaces_only`: interfaces parse, and `string` or `int` in the header is one error.
- `a_generic_struct_is_one_not_supported_error_and_the_struct_parses_on`: question 4.
- `with_copies_the_object_before_it_with_the_values_it_names`: `p with { x: 1, y }` with the shorthand, and its precedence against `+`, `!`, unary `-` and a `new` with its own initializer.
- `an_object_initializer_follows_new`: with and without arguments, a named constructor, empty, and none.

**mago-sharp, `crates/semantics/tests/sharp.rs`:**

- `a_struct_takes_only_public`: `readonly`, `abstract` and `final` refused; the fix, `public struct`, compiles.
- `a_struct_property_takes_init_not_set`: `set` refused on a property and a main constructor parameter; `init` compiles.
- `a_struct_member_is_private_where_a_class_would_be_protected`: `protected`, `virtual` and `override` refused on a property, a method and a parameter; `private` compiles.
- `a_struct_declares_no_operator_equality`: `operator ==` refused; `operator +` compiles.
- `a_required_property_has_init_or_set`: a get-only `required` property refused; `init` and `set` compile.

**mago-sharp, `crates/analyzer/tests/sharp.rs`:**

- `a_struct_never_changes`: a write from outside and in its own method, each refused; the help names `moved`, and names `with` when no method returns the struct; both fixes compile.
- `a_struct_inherits_nothing_and_implements_interfaces`: a class in its header and a class extending it, each one error; an interface compiles.
- `a_struct_compares_by_value`: `===` refused with its fix compiled, a property without `==` refused naming it, `!=` against `Any?` compiles.
- `collections_compare_by_value`: two `List<int>`, two `List<Money>` through `Money`'s `==`, two `List<Point>`, a `Map` in another order, and a `List` of a class without `==` refused naming the class.
- `with_and_an_object_initializer_set_init_and_set_properties`: a get-only property refused with its help, a name given twice refused, a class with `set` compiles, a plain PHP `readonly` property refused without the help.
- `every_required_property_is_set_where_its_object_is_created` and `a_get_only_property_no_constructor_sets_is_an_error`: ruling 14's rows.
- `a_struct_keys_a_map_unless_it_holds_a_class_instance`: `Map<Username, int>` and `Set<Username>` compile, and a struct holding a class, an interface, a function or `Any`, directly, nested in a struct, in a `List`, in a `Map` or as `T?`, is refused where a `Map` or `Set` type is written and at a literal's key.
- `a_sharp_class_cannot_implement_the_struct_marker`: `class Fake : Struct` after `import Sharp.Struct` is refused.
- `every_struct_parses_and_classes_do_not`: `parse` and `tryParse` typed `Self` and `Self?`, a class's `parse` missing, `[Key]` off a struct's main constructor refused, two keys reading one name refused, a not-backed enum refused at the call.

**mago-sharp, `tests/compile.rs`:**

- `a_struct_states_a_law`: a struct law is proven, and refused once a changed method breaks it.

**mago-sharp, `crates/sharp-bridge/tests/lower.rs`:**

- `a_struct_is_a_final_class_whose_op_equality_compares_each_property`: the `final` class implementing `\Sharp\Struct`, its `readonly` properties and `op_Equality`.
- `with_and_an_initializer_run_in_a_closure_bound_to_the_object`: the initializer's writes and `with`'s `clone`, with the shorthand.
- `a_struct_key_goes_in_as_its_key_text_and_a_loop_reads_it_back`: `\Sharp\Map::key` in an index, a literal and a `Set` literal, and `\Sharp\Map::readKey` in a loop.
- `equality_of_two_collections_compares_each_element_with_its_own_equality`: collection `==` through `\array_all` for a `List` and a `Map`.
- `a_struct_parses_each_field_from_its_key_and_creates_itself_through_its_main_constructor` and `parse_reads_each_field_as_its_type`: `parse` and `tryParse` for each parameter kind (scalar, `T?`, a default value, enum, nested struct, `List`, `Map`, class instance, `Any?`).
- `a_value_pattern_on_a_struct_runs_its_op_equality`: decision 7.

**php-sharp, on the engine in all four opcache modes and both JITs:**

- `struct_keys.phpt`, with `Spot.sharp`, `Tone.sharp`, `Route.sharp`, `Boxed.sharp`, `StructKeys.sharp` and `struct_keys.inc`: two equal structs are one key in an index write, a literal and a `Set`; a loop reads each key back; `-0.0` and `0.0` are one key, and so are two equal `Map`s in any order; a round trip of `key` and `readKey` for a nested struct, a backed and a pure enum case, a float in each form (`0.1`, `1.0`, `1e25`, `-0.0`, `INF`, `-INF`, `NAN`, `123456789.125`), a `Map`, a nullable string with escapes and a `bool`; `readKey` refusing a class that is no struct, a missing class, a property of the wrong type, a missing property, a reordered one, an unknown enum case, trailing text, too deep a text, a case of a class that is no enum, an `int` past `PHP_INT_MAX`, an unknown escape and a text with no class name; `key` and `Set::from` refusing a plain object; and `key` throwing for a `Boxed` that plain PHP filled with an object or with lists nested past 512, and for a `Spot` created without its constructor.
- `struct_marker.phpt`: a plain PHP class implementing `Sharp\Struct` stops.
- `structs.phpt`: a changed copy leaves the original; `==` by value, with `null` and with another type; `contains` finds an equal struct; two `List<Point>` and two `Map`s in different orders compare equal; `with` runs an `init` body; an initializer from another class; plain PHP's write, `clone` with values and subclass each throw.
- `struct_parse.phpt`: the spec's message to the byte; every bad field with nested paths; `tryParse` null; a row object; an `int` into a `float` parameter; an extra key ignored; a default value taken.

# What the Slice refuses as "not supported yet", and why

- **A generic struct, whole,** until generics R3c, if Jordan allows generic structs (question 4).
- **A `Set` of class instances,** with today's refusal, until section 19's `Set` of objects lands. A `Set` of structs works.
- **A struct key in `groupBy` and `associateBy`,** refused by their template bound as an enum key is, until the collection Slice widens it.

# Found outside the Slice

1. **Collection `==` is refused,** "`==` on a collection is not supported yet" (`report_sharp_refusal`, `Refusal::Collection`). Section 19 says collections compare by value. A struct holding a `List` can't compare until it lands. Ruling 4: collection `==` joins this Slice's mago-sharp PR, where `Lowering::equality` gains the struct arm, and lowers through `\array_all` with each element's `==`, as #160 lowers `contains` through `\array_find_key`. A `Map` compares without regard to order, as Kotlin's and Swift's maps do.
2. **A `Set` of class instances is refused** ("This type is not supported yet in PHP#." for `Set<Money>`). Section 19 hashes an object by its class's `hash()` and a struct by its value. A struct already joins a `Set` by its key text, so section 19's work is the class arm only, and it decides whether a struct holding a class with `hash()` can join (ruling 17).
3. **Section 6.1 task #463, a get-only property no constructor sets, compiles.** The required-property check reads the same "what the running constructor sets" facts, and closes it for classes too.
4. **Struct `parse` and section 24's `Json.decode<WebhookPayload>(body)`** decode into a type the same way. The library's decoder can reuse `parse` for a struct when Slice 4 specifies it.

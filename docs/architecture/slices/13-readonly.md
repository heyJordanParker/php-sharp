# Slice 13: `readonly`

Spec section 13, `readonly`: a value only read, and a method that only reads `this`. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Every rule of section 13 is missing on master `c6a673cdde`. `readonly` before a type is either a parse error or refused as "This modifier is not supported yet in PHP#.", and `readonly` after a method's parameters is a parse error. No code in `sharp/mago/crates/analyzer/` reads a `readonly` marker on a value, so nothing would check the rules once the refusals go.

The Slice builds section 13 on two marks and the checks that read them:

- **`readonly` before a type is part of the type.** The parser reads `readonly Order` as a type hint, as it reads `Order?` and `Function<R(P)>`. The checker gives the object type a readonly flag, as it gives `this` its `is_this` flag today. A read through a readonly value gives a readonly value, so "readonly reaches everything read through it" is one rule in the type, not one rule per expression.
- **`readonly` after a method's parameters marks the method,** as `on` and `uses` mark it today. Inside it, `this` has the readonly type.

Readonly is a checker rule. The bridge erases both marks, so the engine runs the PHP it runs today, and `ext/sharp/` does not change. Plain PHP sees an ordinary method: `render(readonly Order order)` runs as `public function render(\App\Order $order)`.

When the Slice lands, `sharp/baseline/spec/§13/` loses `readonly-fields`, `readonly-positions` and `readonly-values`, and the PR writes `Completes: §13`. The same marks also let `lambdas` (§14.2) and possibly `function-types` (§14.1) pass, because their only refusal today is `readonly` on a lambda parameter and in a function type.

Seven questions below were rules users write that section 13 did not state. Each is ruled, and the build follows the rulings.

## Rulings

team-lead ruled questions 1, 3, 4, 6 and 7 as proposed. Jordan ruled questions 2 and 5:

- **Question 2:** operators, `hash()` and `toString()` are readonly by definition. They run on readonly values with no marker, and a body that changes an operand or `this` is a compile error. Section 13 states the rule with an example.
- **Question 5:** the method's `readonly` is always its last clause, after `uses` and after `on`, as in `Money quote(Cart cart) uses Http readonly;` and `public void refresh() on OrderPaid readonly { … }`. Any other position is a compile error that says to write `readonly` last. Section 13 states the order with an example.
- **A `get` body never writes `this`** (the properties Slice builds it), so a read through a readonly value is always allowed.

The questions as asked follow, each with its spec line, the proposal and the other choice.

1. **A readonly value where the type has no `readonly`.** Line 1175: "Before a type, it makes that value readonly." The spec never says whether `readonly Order` fits `Order`. Proposal: a compile error that names the fix, for an argument, a return, an assignment to a property, a field or a typed local, an element given to a collection, and a plain PHP parameter. C++ refuses a `const T&` where `T&` is expected ("discards qualifiers"), D's `const(T)` does not convert to `T`, TypeScript's `ReadonlyArray<T>` is not assignable to `T[]`, and Rust's `&T` is not a `&mut T`. Line 1185 already assumes it: `public readonly Customer buyer() readonly` returns `this.owner`, which is readonly inside a readonly method, so its return type needs `readonly`. The other choice: readonly is one step deep, and `Order copy = order; copy.markPaid();` compiles.
2. **The members the language calls for the user.** Lines 1188 to 1191: "Through a `readonly` value, only reading is allowed: property `get` accessors, methods marked `readonly`." `lines.contains(line)` on a `List<readonly Line>` runs `operator ==`, a `Set` or a `Map` key runs `hash()`, and a template runs `toString()`. Proposal: `operator ==`, `<=>`, the arithmetic operators, `hash()` and `toString()` are readonly by definition, and their bodies are checked so. Rust's `PartialEq::eq`, `Hash::hash` and `Display::fmt` all take `&self`. The other choice: the user marks them, so the harness writes `operator ==(readonly Line a, readonly Line b)` and `hash() readonly`.
3. **Overrides and implementations.** Line 1223: "A `readonly` method is checked as if `this` were a `readonly` value." A subclass could override `totalIn` with a body that changes `this`, and a call through `readonly Order` would reach it. Proposal: an override or an implementation of a readonly method is readonly, and an unmarked method may be overridden by a readonly one. An interface method may be marked, as in `Money quote(Cart cart) readonly;`. C++ makes `const` part of a member function's signature. The other choice: the marker is not inherited, and a readonly call is only as safe as the subclasses.
4. **Positions the spec does not show.** Line 1175 says "before a type", and section 13 shows a parameter and a returned value only. Each position below is refused as "not supported yet" until team-lead rules on it. Proposals:
   - A typed local, `readonly Order o = order;`: allowed. It is "before a type".
   - A type argument, `List<readonly Line>`: allowed. It is how the type of `let copy = order.lines` is written (line 1221).
   - A collection parameter, `readonly List<Line> lines`: `lines.add(line)` is allowed and changes the method's own copy, and its elements are readonly. Line 1220: "A copy in a local belongs to the local", and a parameter is a local.
   - `readonly` on `int`, `float`, `bool`, `string`, an enum or a struct: a compile error, "a `string` is a value and never changes: write `string`", as line 1275 refuses `readonly struct`. TypeScript refuses `readonly` on a type that is not an array or a tuple.
   - `readonly Any` and a union that holds a plain PHP class, `readonly Order|Model`: a compile error at the type that can be plain PHP, by line 1193.
   - `readonly T` on a type parameter (meets #102): allowed. A member of `T` through it follows `T`'s bound, and `readonly T` with no bound fits only a `T` that PHP# checks.
   - An extension's receiver, `extension(readonly Order order)` (meets section 26): allowed. An extension member is callable through a readonly value only when its receiver is `readonly`.
   - A type pattern or a cast, `x is readonly Order`, `x as readonly Order`: a compile error, "`is` keeps `readonly`: write `is Order`". A pattern on a readonly value already narrows to a readonly type.
5. **Where the marker goes among the clauses after the parameters.** Line 1176: "After a method's parameters, it makes `this` readonly inside that method." A method can also have `uses` (section 29) and `on` (section 15). Proposal: `readonly` comes first, right after `)`, as in `public Money quote(Cart cart) readonly uses Http;`. C++ writes `const` before `noexcept` and `override`. The other choice: any order.
6. **The message when the marker cannot be the fix.** Lines 1225 to 1230 show the message for `totalIn`, which does not change `Order`. `markPaid` does. Proposal, for a method that changes `this`: "render cannot call order.markPaid: order is readonly. markPaid changes Order: it writes `this.paid`." For a plain PHP method a PHP# class inherits: "render cannot call order.save: order is readonly. save is a plain PHP method of Model, which PHP# does not check." Rust's E0596 also names the fix only when there is one. The mechanism is under "Messages".
7. **Decision 051 contradicts line 1193.** `docs/decisions/051-readonly.md` says "Any plain PHP method can be called through a readonly value" and shows `order.save(); // compiles`. Commit `cc3258a319` ("the plain PHP boundary under readonly", Jordan-approved on 2026-10-13) changed the spec to "`readonly` holds only types PHP# checks, so a plain PHP class under `readonly` is a compile error". Proposal: this PR rewrites decision 051 to the spec's rule, with the Kotlin-platform-types option moved to its rejected options. A plain PHP method that a PHP# class inherits is then unmarked, and calling it through a readonly value is a compile error.

## How the states were observed

Every state below comes from a release `mago` built from master `c6a673cdde` in slot 3, `/Users/jordan/Developer/sharp-worktrees/3/php-sharp`, with `git status` clean:

```sh
CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off CARGO_TARGET_DIR=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-readonly/target \
  rustup run 1.97.0 cargo build --release -j 2 --bin mago --manifest-path sharp/mago/Cargo.toml
```

`sharp/bin/spec` ran with that `mago` in `TEST_MAGO_EXECUTABLE` on Homebrew PHP 8.5.1, because no engine is built in slot 3. Section 13 has no runtime lines, so the engine changes none of its results. The examples that claim a runtime value failed on Homebrew PHP and are not this note's. Its section 13 output:

```text
FAIL §13 readonly-positions (docs/spec.md:1181)
  docs/spec.md:1184 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `LeftBrace`, found `Readonly`
  docs/spec.md:1185 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
  line 9 of the generated app/Example.sharp, from the marker's setup or wrapper: Parse error encountered during parsing Unexpected token `RightBrace`
FAIL §13 readonly-values (docs/spec.md:1196)
  docs/spec.md:1202 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `LeftBrace`, found `Readonly`
  docs/spec.md:1203 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
  docs/spec.md:1204 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `RightBrace`
  line 11 of the generated app/Order.sharp, from the marker's setup or wrapper: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `Public`
  the harness file app/RushOrder.sharp has an error on line 3: Class `RushOrder` does not implement the abstract method `__construct`.
  docs/spec.md:1210 expects a compile error, and the checker accepts it
  docs/spec.md:1211 expects a compile error, and the checker accepts it
  docs/spec.md:1214 expects a compile error, and the checker accepts it
FAIL §13 readonly-fields (docs/spec.md:1235)
  docs/spec.md:1252 expects a compile error, and the checker reports: This modifier is not supported yet in PHP#.
  docs/spec.md:1241 expects a compile error, and the checker reports: This modifier is not supported yet in PHP#.
  docs/spec.md:1272 expects a compile error, and the checker reports: This modifier is not supported yet in PHP#.
```

A file with a parse error skips the PHP# checks, so lines 1182, 1183, 1210, 1211 and 1214 show nothing in that run. The `RushOrder` error is the parser's recovery from line 1202: the example's `Order` replaces the harness's, and `RushOrder` extends it. Each form was then compiled alone, one file each, in the scratch project `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-readonly/ws`, a copy of the generated `readonly-fields` project without its four example files:

```text
Parameter.sharp:7:24   public void render(readonly Order order)        Promoted properties are not allowed outside of constructors.
                                                                        This modifier is not supported yet in PHP#.
PlainPhp.sharp:7:22    public void show(readonly Model model) { }       the same two errors
Shop.sharp:7:17        public Shop(readonly Order order) { }            This modifier is not supported yet in PHP#.
Returned.sharp:9:12    public readonly Customer customer => this.owner; This modifier is not supported yet in PHP#.
Returned.sharp:11:12   public readonly Customer buyer() { … }           `readonly` modifier is not allowed on methods
                                                                        This modifier is not supported yet in PHP#.
Trailing.sharp:7:24    public int total() readonly { … }                Parse error encountered during parsing
Lambda.sharp:9:23      (readonly Line line) => line.free                This modifier is not supported yet in PHP#.
FunctionType.sharp:8:37 Function<Money?(readonly Line, string)>         Parse error encountered during parsing
Local.sharp:9:18       readonly Order o = order;                        Parse error encountered during parsing
Argument.sharp:7:27    List<readonly Line> lines                        Parse error encountered during parsing
Size.sharp:3:17        public readonly struct Size { … }                Parse error encountered during parsing
```

`Values.sharp` holds `readonly-values` with both markers taken out: `Order`'s members, `render` with `order.number;`, `order.totalIn("USD");`, `order.markPaid();` and `order.number = "A-1";`, and `show(Model model)`. That run printed `Compiled 92 PHP# files into .sharp/.` with no error. So once the parser reads the markers, every error left in the three examples is a section 13 rule.

## Existing work, and what the Slice reuses

No pull request or branch implements a readonly value or a readonly method:

- `gh pr list --state all --search readonly` finds 7 php-sharp and 11 mago-sharp pull requests. Each mentions PHP's `readonly` property, which a get-only PHP# property runs as, or `readonly struct`.
- `--search "immutable OR mutating OR \"read-only\" OR \"section 13\""` finds 10 and 17. None holds a section 13 rule.
- The branches named `*readonly*`, `*immutable*` or `*mutating*` are `feat/readonly`, this Slice's, and `origin/immutable-date`, php-src's 2012 `DateTimeImmutable` work.

What the Slice reuses:

- **Decision 014 and section 12 on master.** `check_changed_collection` in `crates/analyzer/src/expression/call/method_call.rs` sends `order.lines.add(line)` to the property write check of `order.lines`, which runs `set`. The readonly check sits in that property write check, so line 1219 needs no code of its own.
- **The effects solve, spec section 29.** `crates/analyzer/src/effects/` records which state each PHP# body changes, `Changed::This` among them, and `Effects::solve` combines the bodies across calls once every file is analyzed. The message of line 1229, "totalIn does not change Order", comes from that solve (question 6). `Recorder::stored_roots` in `effects/summary.rs` already states line 1220 for change roots: "A collection, string or scalar is copied into the local, which owns the copy (spec section 13), so it is fresh."
- **`TNamedObject`'s `is_this` and `is_static` flags** in `crates/codex/src/ttype/atomic/object/named.rs`. They mark an object type the same way the readonly flag will, and the combiner already keeps `is_static` per class name in `TypeCombination::object_static`.
- **`MetadataFlags::READONLY`** in `crates/codex/src/metadata/flags.rs`. Every reader of it today reads a class or a property, PHP's write-once `readonly`. No scanner sets it on a function-like, so it can mark a readonly method with no new flag.
- **`get_this_type`** in `crates/analyzer/src/statement/function_like/mod.rs` builds the type of `this` from the method's metadata, so a readonly method's `this` comes from one place.
- **The `FunctionHint` precedent.** PHP#'s function type, section 14.1, is a `Hint` variant with its own `Node` and `NodeKind`, and `just regen-sdk-node-kinds` writes it into `sharp/mago/composer/src/Sdk/Syntax/NodeKind.php`. `ReadonlyHint` follows it.
- **`Method::on` and `Method::uses`**, the clauses after a method's parameters. `Method::readonly` follows them.
- **#123, structs (open).** `check_struct_declaration` on `feat/slice-structs` refuses `readonly struct` with "Every struct is already read-only: write `struct`.", which is line 1275. This Slice builds nothing for it.

What the Slice does not reuse:

- **`MetadataFlags::MUTATION_FREE`,** Psalm's `@psalm-mutation-free`. It means a method changes nothing at all, and `clear_object_property_narrowings` in `crates/analyzer/src/invocation/post_process.rs` keeps every narrowing across a call of one. A readonly method may change other objects, so the flag would make that wrong.
- **`crates/analyzer/src/readonly.rs`.** It checks PHP's write-once properties, which PHP# get-only properties run as. Section 13's `readonly` is a different rule, so its checks do not go there.

# Every line of section 13, its state and its mechanism

The states are **missing** (no code holds the rule), **refused** (an error says "not supported yet"), **parse error**, **works**, and **owned elsewhere**. Line numbers are `docs/spec.md` at `c6a673cdde`.

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 1173–1176 | One rule: before a type, the value is readonly; after the parameters, `this` is | Parse error after `)`; refused or parse error before a type, by position (above) | `ReadonlyHint` before any type in a `.sharp` file; `Method::readonly` after `)`. The PHP parser is unchanged | `FunctionHint`, `Method::on` and `Method::uses` |
| 1182 | `render(readonly Order order)`, the parameter | Refused: "Promoted properties are not allowed outside of constructors." and "This modifier is not supported yet in PHP#." | The parameter's type is `readonly Order`. In a `.sharp` file `readonly` before a type is no longer a modifier, so `is_promoted_property()` stays false and a constructor's `Shop(readonly Order order)` declares no property | `parse_parameter`; `union_from_hint` |
| 1183 | `public readonly Customer customer => this.owner;`, the returned value | Refused: "This modifier is not supported yet in PHP#." | A computed property's type is `readonly Customer`, so each read gives a readonly value | Computed properties, section 6 |
| 1184 | `totalIn(string currency) readonly`, `this` inside the method | Parse error: "Expected one of `LeftBrace`, found `Readonly`" | The scanner sets `MetadataFlags::READONLY` on the method. `get_this_type` gives `this` the readonly flag in its body | `get_this_type`; `MetadataFlags::READONLY` |
| 1185 | `public readonly Customer buyer() readonly`, both | Parse error; alone, "`readonly` modifier is not allowed on methods" | The two marks above, independent | |
| 1188–1191 | Through a readonly value, only `get` accessors and readonly methods | Missing | Property resolution and method resolution read the receiver's flag. A `get` accessor is pure already ("Getters must be pure"), so a read is always allowed | `resolve_instance_properties`; `resolve_method_targets` |
| 1193 | Setters and unmarked methods are compile errors | Missing | A write through a readonly receiver is an `invalid-property-write`. A call of an unmarked method is the new `missing-readonly-marker`, reported after the solve (Messages) | `invalid-property-write`; the effects solve |
| 1193, 1214 | `readonly` holds only types PHP# checks: `show(readonly Model model)` is a compile error | Missing; the file is refused for the modifier | Semantics cannot tell a plain PHP class, so the analyzer refuses `readonly` on a type whose class is not PHP# (`ClassLikeMetadata::flags.is_sharp()`), at the type | `is_sharp()`, which the effects use |
| 1208 | `order.number;` compiles: a `get` accessor | Blocked by the parse error on 1202. Without the markers it compiles | The read gives `readonly string`, which is `string` (question 4) | |
| 1209 | `order.totalIn("USD");` compiles | Blocked | `totalIn` has `MetadataFlags::READONLY` | |
| 1210 | `order.markPaid();` is a compile error | Accepted, because semantics skips the file | `missing-readonly-marker` at the call | |
| 1211 | `order.number = "A-1";` is a compile error | Accepted, the same | `invalid-property-write`: "render cannot set order.number: order is readonly." | |
| 1217 | Readonly reaches everything read through it | Missing | A property read through a readonly receiver gives its declared type made readonly: every object in it, inside collections and type arguments too | `resolve_instance_properties` |
| 1219 | `order.lines.add(line)` writes back through `set`, so it is a compile error | Missing | `check_changed_collection` already sends it to the property write check of `order.lines`, which refuses it there | Decision 014; `check_changed_collection` |
| 1220 | `let copy = order.lines; copy.add(line);` is allowed and leaves `order` unchanged | Compiles (`Copy.sharp`, `Compiled 93 PHP# files`); decision 014 states it runs on the copy, not run here | The local's type is `List<readonly Line>`: the collection has no flag, only the objects in it | Section 12's copy semantics |
| 1221 | The objects inside that copy stay readonly | Missing | The element type keeps the flag, so `copy[0].amount = 1` and `for (l of copy) { l.amount = 1; }` are refused | The flag in the type |
| 1223 | A readonly method: `this` cannot be written, and only readonly methods are called on it | Missing | `this` has the readonly type, so every check above applies with no code of its own | `get_this_type` |
| 1225–1230 | A missing marker is a compile error that names the fix | Missing | Messages, below | `Effects::solve`; `Changed::This` |
| 1232, 1241 | `private readonly Money total;` is a compile error that names `{ get; }` | Refused: "This modifier is not supported yet in PHP#." | Semantics: a field whose type is a `ReadonlyHint` gets "A field keeps its value with a get-only property: write `public Money total { get; }`." So does a stored property, `{ get; set; }` included, because line 1232 says "properties" | `is_slice_property` |
| 1252 | `Bill(public readonly Money due)` is a compile error that names `{ get; }` | Refused, beside "A `public` constructor parameter needs accessors" | A parameter that declares a member, with a `ReadonlyHint`, gets the same message, and replaces the accessors error | Parameter members, section 9 |
| 1263 | `Quote(public Money due { get; })` compiles | Works | None | |
| 1272 | `public readonly class Receipt` is a compile error that names `{ get; }` | Refused: "This modifier is not supported yet in PHP#." | Semantics: `Modifier::Readonly` on a class gets "A class has no `readonly`: declare its properties with `{ get; }`." `readonly` stays a modifier before `class` | `enter`'s modifier arms |
| 1275 | `readonly struct` is a compile error | Parse error | Owned by #123 | `check_struct_declaration` on #123 |

## The parser

`readonly` is a PHP keyword, so the lexer needs nothing. In a `.sharp` file:

- `parse_type_hint` reads `readonly` followed by a type as `Hint::Readonly(ReadonlyHint { readonly, hint })`, as it reads `?` into `NullableHint`. `readonly readonly Order` is a parse error.
- The modifier parser stops at `readonly` when a type follows it, so `private readonly Money total;`, `public readonly Money due` and `render(readonly Order order)` all reach `parse_type_hint`. Before `class`, `struct`, `abstract`, `final` or another modifier, `readonly` stays a modifier, so `public readonly class Receipt` parses as today.
- `parse_sharp_member_with_attributes_and_modifiers` reads `readonly` after the parameter list into `Method::readonly: Option<Keyword>`, as the last clause, after `on` and `uses` (question 5). One written before `on` or `uses` is the parse error `ReadonlyNotLastInSharp`, which names the last place. An interface method and an abstract method take it too.
- Every `match` on `Hint` gains an arm: `Hint::Readonly` is its inner hint. These are the formatter (which prints `readonly ` and the hint), the fingerprint, the guard, three linter rules, `hir`, `sharp-lean`, `missing_type_hints`, the walker, `Node::visit_children` and `HasSpan`.

## The checker: semantics

`crates/semantics/src/internal/checker/sharp.rs` decides where each mark is allowed, before any type is known:

- `ReadonlyHint` is allowed on a parameter, a lambda parameter, a method's return type, a computed property's type, a typed local, a type argument, a function type's parameter or return, and an extension's receiver (question 4).
- On a field, a stored property or a parameter that declares a member, `ReadonlyHint` gets the `{ get; }` message (lines 1241 and 1252). On a class, `Modifier::Readonly` does (line 1272).
- `Method::readonly` on a static method, a constructor, an enum method or an extension member is an error that says why it can't matter, as "A static method has no `this` to keep readonly: remove `readonly`." So is `readonly` after `hash()` or `toString()`, and before an operator's operand, which question 2 makes readonly by definition.
- `readonly readonly`, `readonly` before `int`, `float`, `bool`, `string`, `void` or `Any`, and `is readonly Order` are errors (question 4). The analyzer refuses `readonly` before an enum or a plain PHP class, which semantics cannot tell apart from a PHP# class.

## The checker: types

**The flag.** `TNamedObject` gains `is_readonly: bool`, false by default, and so does `TReference::Symbol`, the class name a type holds until the codebase resolves it, which hands the flag to the object it becomes. `union_from_hint` in `crates/codex/src/scanner/ttype.rs` builds a `ReadonlyHint` as its inner type made readonly. A type made readonly has the flag on every named object in it, through nullable, union, `List`, `Map` and `Set` element types and type arguments. A function type keeps its own parameter and return types, because a function value is never written. `get_id` writes the flag first, as `readonly App\Order`, so a message shows the type as the user wrote it.

**Keeping it.** Every place that builds a named object from another one keeps the flag:

- The combiner keeps it per class name, as it keeps `is_static` in `TypeCombination::object_static`. `readonly Order|Order` is `readonly Order`, and a subclass merged into its parent keeps the parent readonly when either was.
- The reconcilers keep it when a pattern narrows: `if (shape is Circle c)` on a `readonly Shape` gives `readonly Circle`.
- Template inference keeps it, because the flag is part of the atom: `lines.first(…)` on a `List<readonly Line>` gives `readonly Line`, and a lambda's `l` in `lines.map(l => …)` is `readonly Line`.
- The expander keeps it when it replaces `Self` or a template.

**The comparator ignores the flag,** and `get_php_received_type`, the type plain PHP receives from a PHP# declaration, drops it, so a plain PHP file that reads a PHP# readonly type analyzes and words its messages as today. Question 1's check, `TUnion::drops_readonly_of`, runs only in a `.sharp` file, at the four sites that compare a value with a declared type: an argument, a spread's elements, a `return`, a property or field assignment and a typed local. One predicate, `TAtomic::is_readonly_value`, says what is readonly: a readonly object, or a value of `readonly T`, whose bound is readonly. It also keeps a narrowed value readonly. A type parameter not written `readonly` takes no readonly value: a method's own type parameter is compared as it is declared, and a class's as the receiver fills it. A function value fits a function type only when `TCallableSignature::drops_readonly_of` finds no loss: its parameters are compared in reverse, and its return forward. It reports the code each site reports today for a type that does not fit: `invalid-argument`, `invalid-return-statement`, `invalid-property-assignment-value` and `invalid-local-assignment-value`. The message names the fix, as in "Argument 1 of save is readonly, and save takes `Order`: declare it `save(readonly Order order)`." A plain PHP parameter gets "PHP# cannot check that save does not change it."

**The reads and writes.** `resolve_instance_properties` in `crates/analyzer/src/resolver/property.rs` resolves both a read and a write of `object.name`:

- A read through a readonly receiver gives the declared type made readonly (line 1217).
- A write through a readonly receiver reports `invalid-property-write` (lines 1211 and 1219), once per write, with the receiver and the method: "render cannot set order.number: order is readonly."

**The calls.** `resolve_method_targets` in `crates/analyzer/src/resolver/method.rs` resolves a call on a readonly receiver. A method without `MetadataFlags::READONLY` is recorded for the message (lines 1210 and 1223). A collection method that changes the collection, one of `CHANGING_COLLECTION_METHODS`, on a readonly receiver reaches the property write check through `check_changed_collection`, as above. The collection methods that read are marked mutation-free in the prelude stubs, and stay allowed.

**`this`.** `get_this_type` gives `this` the flag in a method whose metadata has `MetadataFlags::READONLY`, and in the bodies question 2 makes readonly. A lambda in such a method sees the same `this`. A static property is not `this`, so a readonly method may still change one: readonly is not pure.

**The calls a readonly value makes run through one check,** `check_call_through_readonly`: a call, a method value, which runs its method, and a call that a plain PHP class's `__call` runs. An extension member's receiver is checked as its argument, and the message names the call and `extension(readonly Order order)`. An operator is marked by the scanner with `MetadataFlags::OPERATOR`, and `int hash()` and `string toString()` with no parameters with `MetadataFlags::READONLY`.

**Plain PHP under readonly** (lines 1193 and 1214). `crates/analyzer/src/readonly_types.rs` reports `readonly` on a type that reaches a class that is not PHP#, at its top or in a type argument, using the class's `is_sharp()` flag, and `readonly` on a type that holds no PHP# object: an enum, a struct, a function type, or a collection of them. A PHP# class that extends a plain PHP class is PHP#, so `readonly Order` with `Order : Model` is allowed, and an inherited plain PHP method is unmarked (question 7).

## Messages

Line 1229's message says "totalIn does not change Order". Only the effects solve knows that, because files are analyzed in parallel and a body does not know its callees' changes. So the analysis records each call of an unmarked method through a readonly value in `AnalysisArtifacts`, as it records function values for section 29 today. A check after `Effects::solve`, beside `getters_must_be_pure` in `crates/analyzer/src/effects/check.rs`, reports each one with the new code `missing-readonly-marker`:

```text
render cannot call order.totalIn: order is readonly.
totalIn does not change Order, so declare it `totalIn(string currency) readonly`.
```

When the callee's solved items hold `Changed::This`, the second line is question 6's "markPaid changes Order: it writes `this.paid`.", which `Impurity::describe` already builds. A callee with no PHP# body is a plain PHP method (question 7). The orchestrator already reports the solve's issues in `mago analyze` and `mago compile`, one-shot and incremental, through `Effects::issues`.

Every new message passes `assert_speaks_sharp` in `crates/analyzer/tests/sharp.rs` and its twins in the semantics and bridge tests: "A `.sharp` message speaks PHP#."

# The bridge and the engine

The bridge erases both marks. The engine runs the PHP it runs today, so `ext/sharp/sharp.c` does not change, and `ext/sharp/sharp_unit.h` does not change either, because no new node kind is lowered.

- `Lowering::hint` in `crates/sharp-bridge/src/lower.rs` lowers `readonly Order` as `Order`.
- The shape checks that look inside a hint see through `ReadonlyHint`: `set_parameter`, so a `readonly Set<Line>` parameter still converts to a `Set`, `dropped_service`, `is_null_by_default` and `nullable_hint`.
- `Method::readonly` lowers to nothing.
- `readonly` before a type is no longer a parameter modifier, so `is_promoted_property()` is false for `readonly Order order`, and `parameter_place` reads its variable. `modifier_flags` keeps `Modifier::Readonly` unreachable, because semantics refuses it on every member.

**What plain PHP sees.** A plain PHP caller of `render(readonly Order order)` calls `public function render(\App\Order $order)`: it passes any `Order`, and may change the object before and after the call. `Reflection` shows no `readonly`. The mark is a promise about the PHP# body only, as Kotlin's read-only `List` is a `java.util.List` to Java code and TypeScript's `readonly` is gone in JavaScript. A plain PHP subclass of a PHP# class can override a readonly method with a body that changes `this`. PHP# checks PHP# code only, so a call through a readonly value reaches that body.

**A plain PHP project's analysis** does not change. The parser reads `readonly` as a type only in a `.sharp` file. Every check above runs only when `context.dialect.is_sharp()`, and only a `.sharp` hint sets the flag. The comparator ignores it, so a `.php` file reading a PHP# type with the flag analyzes as today.

# What meets the other Slices

- **Section 12, collections (master).** Line 1219 is section 12's write-back, refused at the property write check. Line 1220 is section 12's copy. A `List` index is a place (section 12, ruling 7), so `order.lines[0].add(5)` writes back through `order.lines` and is refused there too.
- **Section 6, the properties Slice (`feat/properties`, slot 1, not pushed, `c6e00e11da`).** It owns `{ get; }`, `init`, accessor access levels, `lazy` and `nameof`. Section 13 meets it in four places. The `{ get; }` messages of lines 1241, 1252 and 1272 name its get-only property, which works on master (line 1263). A `get` accessor is allowed through readonly, `set` and `init` are not, whatever their access level. A `lazy` property is a read. Both Slices change `is_slice_property` and `parse_modifier_sequence`, so whichever lands second merges them.
- **Section 11, generics (#102, about to merge).** It owns type parameters, and changes `type_hint.rs`, `Method` (`type_parameters`, and `on` moved into the arena), `scanner/ttype.rs`, the builder and the comparators. `ReadonlyHint` and `Method::readonly` sit beside its changes, so the build starts from master after #102 merges. Section 13 meets it at `readonly T` (question 4) and at template inference keeping the flag.
- **Section 10, structs (#123, open).** It builds line 1275.
- **Section 14, functions as values.** `readonly` in a lambda parameter (line 1301) and in a function type (line 1285) is section 13's rule in section 14's syntax. This Slice builds both. A lambda that takes `Line` does not fit a function type that passes `readonly Line`, by question 1.
- **Section 26, extensions.** The receiver of question 4.
- **Section 29, effects.** The message reads the solve. A readonly method is not pure: it may have effects and change other objects.

# Harness changes

The PR body lists each one.

- `sharp/harness/app/Product.sharp`: `public readonly Money? priceIn(string currency) readonly => this.price;`. Section 14.2's `lambdas` calls `line.product.priceIn(currency)` on a `readonly Line`. Its only error today is the refusal of `readonly` on the lambda parameter, so with this line it can pass.
- Question 2 makes operators, `hash()` and `toString()` readonly by definition, so `Line`'s and `Money`'s `operator ==` and `hash()` need no change.

# The tests

One test per rule, each seen failing before its code, with its forms as rows. The existing test for each rule grows a row before a new test is added.

**`crates/syntax/tests/sharp.rs`:** `readonly` before every type position as a `ReadonlyHint`, including a lambda parameter and a function type's parameter; `readonly` after `)` with a block, an expression body and no body, and before `uses` and `on`; `readonly` before `class` as a modifier; `readonly readonly Order` as a parse error; a `.php` file where `readonly` is still PHP's modifier.

**`crates/semantics/tests/sharp.rs`:** each allowed position compiles; each `{ get; }` refusal (field, stored property, promoted parameter, class); each position of question 4 as its ruling says; the marker on each member without `this`.

**`crates/analyzer/tests/sharp.rs`:**

- A read through a readonly value is readonly: a property, a nested property, a `List` element, a `Map` value, a `for … of` variable, a method's readonly return, a template's return (`first`), a lambda parameter from a template (`map`).
- A write through a readonly value is refused: `=`, `+=`, `++`, a `set` with each access level, an `init`, a changing collection method on a property, a `List` index write, a write through a narrowed `is` binding.
- A call: a readonly method compiles, an unmarked one is refused, through a parameter, a readonly return and `this` in a readonly method, and in a lambda inside one.
- A copy: `let copy = order.lines; copy.add(line);` compiles, and `copy[0].amount = 1;` is refused.
- Question 1, each site, and plain PHP under readonly, each form.
- The combiner and each reconciler keep the flag.
- The message: a callee that does not change `this`, one that does, and a plain PHP one, after the solve.
- Plain PHP unchanged: a `.php` file that calls a PHP# readonly method and writes the readonly value it returns reports nothing new.

**`crates/sharp-bridge/tests/lower.rs`:** a parameter, a return, a computed property, a method and a lambda with `readonly` lower to the same PHP as without it; a `readonly Set<Line>` parameter still converts to a `Set`; a constructor's `readonly Order order` declares no property.

**The engine, in every opcache mode** (`sharp/bin/test --opcache`): `Zend/tests/sharp/Readonly.sharp`, `readonly.inc` and `readonly.phpt` run a readonly parameter, method, return and lambda; a constructor's `readonly` parameter is not a property; and a plain PHP caller passes an `Order`, then changes it.

**Plain PHP analysis:** `mago analyze` on `sharp/mago/composer/`, a plain PHP project, prints the same issues before and after.

**The spec:** `sharp/bin/spec` passes with the three `§13` entries gone, plus `§14.2/lambdas` and `§14.1/function-types` if they pass, and `sharp/bin/spec --completes` passes on the body `Completes: §13`.

# Names

The new names, each with its callsite. `ReadonlyHint` and `Hint::Readonly` follow `NullableHint` and `FunctionHint`, and `Method::readonly` follows `Method::on` and `Method::uses`, so neither has candidates.

**The flag on `TNamedObject`,** at `if named.is_readonly && for_assignment { … }`:

- `named.is_readonly`: the spec's own word, as `is_this` and `is_static` are; "the named object is readonly". Downside: `flags.is_readonly()` on class metadata means PHP's write-once `readonly`.
- `named.only_read`: what code may do with it; "the named object is only read". Reads like a past fact.
- `named.writable`: the opposite flag; "the named object is writable". Every constructor must set it to true.
- `named.reached_through_readonly`: how the value was reached. A declared `readonly Order` parameter reached it through nothing.
- `named.forbids_writes`: the rule it carries. A call of an unmarked method is not a write.

Recommended: `is_readonly`, because it is the word the spec and the user write. Runner-up: `only_read`.

**The new issue code,** at `IssueCode::MissingReadonlyMarker` and `@mago-expect analysis:missing-readonly-marker`:

- `missing-readonly-marker`: the fix, in line 1225's words "A missing marker". Downside: the code sits at the call, the marker is missing at the callee.
- `invalid-readonly-call`: the `invalid-*` family, as `invalid-property-write`; "an invalid call through readonly".
- `unmarked-method-call`: the callee's state, line 1193's "unmarked methods". Says nothing of readonly.
- `readonly-receiver`: the receiver. Says nothing of what went wrong.
- `changes-readonly-value`: the outcome. Wrong for a callee that changes nothing.

Recommended: `missing-readonly-marker`, because it names the spec's rule and its fix. Runner-up: `invalid-readonly-call`.

**The codex function that makes a type readonly,** at `let read_type = property_type.to_readonly();`:

- `to_readonly()`: Rust's `to_` for a conversion that gives a new value; "the property type, made readonly".
- `with_readonly_objects()`: what changes inside it. Long.
- `as_read_through_readonly()`: the read it serves. Long, and `as_` in Rust borrows.
- `readonly()`: the shortest. Reads like a getter of a flag.
- `marked_readonly()`: the state after. Close to `to_readonly`.

Recommended: `to_readonly`. Runner-up: `with_readonly_objects`.

# Files

```text
docs/
├── architecture/slices/13-readonly.md      new: this note
└── decisions/051-readonly.md               question 7: plain PHP under readonly follows spec line 1193
sharp/
├── baseline/spec/
│   ├── §13/readonly-fields                 removed
│   ├── §13/readonly-positions              removed
│   ├── §13/readonly-values                 removed
│   ├── §14.1/function-types                removed if it passes
│   └── §14.2/lambdas                       removed if it passes
├── harness/app/Product.sharp               priceIn readonly, with a readonly return (Harness changes)
└── mago/
    ├── composer/src/Sdk/Syntax/NodeKind.php    + ReadonlyHint, written by just regen-sdk-node-kinds
    └── crates/
        ├── syntax/
        │   ├── src/cst/cst/type_hint.rs        + Hint::Readonly, ReadonlyHint
        │   ├── src/cst/cst/class_like/method.rs + Method::readonly
        │   ├── src/cst/node.rs                 + Node::ReadonlyHint, NodeKind::ReadonlyHint
        │   ├── src/walker/mod.rs               walks ReadonlyHint and Method::readonly
        │   ├── src/parser/internal/type_hint.rs    readonly starts a type in a .sharp file
        │   ├── src/parser/internal/modifier.rs     readonly before a type is no modifier in a .sharp file
        │   ├── src/parser/internal/class_like/method.rs  readonly after the parameters
        │   └── tests/sharp.rs
        ├── semantics/
        │   ├── src/internal/checker/sharp.rs   where each mark is allowed; the { get; } messages
        │   └── tests/sharp.rs
        ├── codex/
        │   ├── src/ttype/atomic/object/named.rs    + TNamedObject::is_readonly, in get_id
        │   ├── src/ttype/union.rs              + to_readonly, without_readonly, drops_readonly_of
        │   ├── src/ttype/atomic/mod.rs         + TAtomic::is_readonly_value
        │   ├── src/ttype/atomic/callable/mod.rs    + TCallableSignature::drops_readonly_of, DroppedReadonly
        │   ├── src/ttype/mod.rs                get_php_received_type drops the flag
        │   ├── src/ttype/combiner.rs           keeps the flag per class name
        │   ├── src/ttype/expander.rs           keeps the flag
        │   ├── src/metadata/flags.rs           + MetadataFlags::OPERATOR
        │   ├── src/scanner/ttype.rs            ReadonlyHint to a readonly type
        │   ├── src/scanner/mod.rs              an operator gets MetadataFlags::OPERATOR
        │   └── src/scanner/function_like.rs    Method::readonly to MetadataFlags::READONLY
        ├── names/src/binding.rs                + MethodParts::is_hash_or_to_string, sharp_operator_symbol
        ├── analyzer/
        │   ├── src/readonly_types.rs           new: plain PHP under readonly, and a readonly that can't matter
        │   ├── src/code.rs                     + missing-readonly-marker (and regen-analyzer-issue-codes.php)
        │   ├── src/resolver/property.rs        a read gives readonly; a write is refused
        │   ├── src/resolver/method.rs          an unmarked call is recorded
        │   ├── src/statement/function_like/mod.rs  get_this_type
        │   ├── src/reconciler/*.rs             a narrowed type keeps the flag
        │   ├── src/invocation/, src/statement/return.rs, src/expression/assignment/   question 1's four sites
        │   ├── src/effects/check.rs, effects/mod.rs    the message after the solve
        │   ├── src/artifacts.rs                the recorded calls
        │   ├── src/utils/missing_type_hints.rs a Hint::Readonly arm
        │   └── tests/sharp.rs
        ├── sharp-bridge/
        │   ├── src/lower.rs                    erases both marks
        │   └── tests/lower.rs
        ├── formatter/, fingerprint/, guard/, linter/ (3 rules), hir/, sharp-lean/   a Hint::Readonly arm each
Zend/tests/sharp/
├── Readonly.sharp                          new
├── readonly.inc                            new: the plain PHP caller
└── readonly.phpt                           new
```

`ext/sharp/` does not change.

# Risks

- **A place that builds a named object from another one and drops the flag** opens a hole: the code compiles a write the spec refuses. 22 files call `TNamedObject::new` today, the expander 17 times. The tests list a row for each narrowing form, each template form and the combiner. A hole found later is a missing row, fixed with its row.
- **A shape check on a hint that does not look through `ReadonlyHint`** changes what the engine runs, as `set_parameter` would for `readonly Set<Line>`. The bridge test above has a row for each shape check.
- **Merging with #102 and `feat/properties`.** Both change the files the parser and the semantics changes sit in. The build starts after #102 merges, and merges `feat/properties` if it lands first.

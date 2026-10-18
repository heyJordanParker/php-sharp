# Slice 25: Class values

Spec section 25, "Referring to classes", with the parts of sections 9, 11 and 19 that point to it. team-lead approved this design with every decision below as written, and ruled on the four questions under "Rulings".

## The answer first

Half of section 25 already works on master: `typeof(X)` of a class, a static call through a class value held in a local, `Self` as a return type, `new Self(…)`, `required` constructors and every PHP form section 25 removes. Generics (#102) builds most of the rest: the `Class<T>` type, `typeof` of a type parameter and of a generic class, `new (type)(…)`, and `==` between class values. On #102's head, `class-values` and `class-value-equality` already pass.

What neither has, and this Slice builds:

- **`Class.of(value)`.** It is "Method `Class.of` does not exist." on master and on #102.
- **`typeof(order)` on a local is silently accepted.** The binder resolves `order` to the class `Order`, because PHP compares class names without case. The spec makes it a compile error that names `Class.of(order)`.
- **`Class`'s own members.** `type.attributes<Listen>()` is "Method `Order.attributes` does not exist." on both, and `public static string attributes()` compiles on both.
- **`new (typeof(Loose))(1)` is silently accepted on #102** without a `required` constructor. Ruling 3 keeps the spec's line, so this Slice refuses it.
- **The harness and two examples.** Three examples fail on #102 only because `sharp/harness/` lacks a `required` constructor or the class `PaginatedList`. `self-return` cannot compile as written, and `required-constructors` needs a `DatabaseEntity` with a `required` constructor that the shared harness cannot give it.
- **The `required` rule's message.** `Invoice` is refused, but with upstream Mago's "`Invoice.__construct` must accept at least 1 required parameters like `Entity.__construct`", which names PHP's `__construct` and states the count backwards.

The Slice is one php-sharp pull request. It builds the parts that need nothing from #102 on master now, merges `origin/master` once #102 lands, and then builds the rest.

## Decisions team-lead approved

1. **`Class.of` is a static method of an engine class `Sharp\Class`,** declared in Mago's prelude `crates/prelude/assets/extensions/sharp.php` and implemented in `ext/sharp/sharp.c`, as `List.wrap` is `Sharp\List::wrap`. `crates/names/src/lib.rs` adds `Sharp\Class` to `default_class` beside `Sharp\List`, because PHP reserves `Class` as it reserves `List`, so no other class can have the name. The analyzer types the call from the prelude's `@template T of object`, `@param T $value`, `@return class-string<T>`, which is how master and #102 already represent a class value. The bridge lowers it as any static call. The engine returns the object's class name, or, for an object of a generic PHP# class, the interned class value of its class with the type arguments #102 stores on the object, so `Class.of(box) == typeof(Box<Order>)` holds and `new (Class.of(box))()` creates a `Box<Order>`.
   - `Class.of(5)` gets one `template-constraint-violation` at the argument, "`5` does not satisfy the bound `Object` of type parameter `T`." Master reported a second, `invalid-argument`, and #102 reports the bound once. Without the `of object` bound the prelude's `T` takes an `int` and nothing is reported.
   - Not chosen: a standard library static class `Sharp.Class` with `of` as a native body, after decision 040. `sharp/bin/native` writes glue only for `string`, `int`, `float`, `bool` and a returned `List<string>`, so it would grow a generic object parameter for one method. A body in PHP# cannot give the runtime class: `typeof(T)` in `of<T>(T value)` is the type the call site shows, not the subclass the object may be.
2. **`typeof` of a local is a binding error.** `walk_in_type_of` in `crates/names/src/internal/walker.rs` looks a name that is no type parameter up in `LocalScopes`, as `walk_in_type_pattern` looks up a local. `typeof`'s operand is a type, `TypeOf::type`, so `walk_in_hint` resolves its names as every type's. It records a new `BindingError` variant, and `check_binding_errors` in `crates/semantics/src/internal/checker/sharp.rs` reports it beside `OutOfScope` and `Redeclared`: "`typeof` takes a type, and `order` is a local: write `Class.of(order)`." The variant's name, by `/naming` against its siblings `OutOfScope` and `Redeclared`:
   - `LocalAsType`: a local written where a type goes. Recommended.
   - `NotAType`: the name is no type. Runner-up. It could also serve section 21's pattern rule, which is not this Slice's.
   - `LocalInTypeOf`: names the one construct, so a second user needs a second variant.
   - `ValueInTypeOf`: "value" is wider than the one case the binder sees, a local.
   - `TypeOfLocal`: reads as "the type of a local", the opposite of the error.
3. **`Class`'s own members are a default standard library extension on `Class<T>`,** decision 040's rule for a built-in type's members, as `Sharp.Text`'s `extension(string text)` gives `name.trim()`. It is `extension<T>(Class<T> type)` in `sharp/composer/library/Sharp/ClassMembers.sharp`, imported by default as every class directly in `Sharp` is. Its body is PHP# over PHP's reflection, so the engine doesn't change, and the bridge already lowers an extension call before a class value's static call (`method_call` in `crates/sharp-bridge/src/lower.rs`). One rule decides a class value and its members by the receiver's analyzed type, whatever expression gives it: `resolve_extension` in `crates/analyzer/src/resolver/extension.rs` keeps only `Class`'s members for a receiver whose type is a class value, so an imported `string` extension never takes over, and a member access that resolves none of them runs as the static access of the class the value holds (`analyze_through_class_value` in `crates/analyzer/src/expression/access/mod.rs`): "`Class`'s own members win". `class_member` names `Class`'s member of a name. Ruling 1 fixes the one member, `attributes<TAttribute : Object>()`, and semantics refuses an extension of `Class<…>` outside the standard library.
   - The extension needs a block type parameter, so Jordan ruled that an extension block declares its own type parameters, as C# 14 does: `extension<TItem>(List<TItem> items)`. The parser reads them after `extension`. The binder scopes them over the receiver and every member. `MethodParts` puts them before each member's own, so the static method a member runs as is generic in both, and the scanner, the HIR and the bridge read it as any generic method. `fits` infers them from the receiver before it compares, so a `Pair<int, Order>` fits `Pair<TLeft, TRight>` and a `List<int>` misses `TEntity : DatabaseEntity`. A call's written type arguments fill only the member's own (`seed_extension_type_arguments`), and the lowered call carries all of them. Semantics refuses a block type parameter its receiver doesn't use, as C#'s CS9295 does, a member type parameter that reuses a block's name, and `in` or `out` on a block's. The fingerprint hashes them. Spec section 26 states the rule with the `generic-extensions` and `extension-unused-type-parameter` examples.
   - The class's name, by `/naming`: `ClassMembers`, since the spec calls `attributes` "`Class`'s own members". Runner-up `ClassValues`. `Classes` was left out because a user namespace `Classes` would meet a class every file imports.
4. **A PHP# class may not declare a static named like a member of `Class`,** TypeScript's error 2699. `report_statics_named_like_class_members` in `crates/analyzer/src/statement/class_like/mod.rs` reads the member names through `class_member`, so the list lives in one place: "`type.attributes` on a class value reaches `Class`'s member `attributes`, never this static. Rename it.", quoting the static's own name.
5. **The `required` rule speaks section 25's words.** `report_signature_compatibility_issue` in `crates/analyzer/src/statement/class_like/mod.rs` already gets the constructor mismatch through Mago's consistent constructor flag, which the scanner sets for `required` and `merge.rs` passes to every PHP# subclass. For a PHP# constructor below a `required` one, `report_required_constructor_mismatch` reports the two bullets of spec line 2941 and 2942 instead of upstream's text, and keeps upstream's issue code. Each subclass is compared with the nearest ancestor whose own constructor is written `required` (`required_constructor_class`), not with its parent, and the call is the shortest `new Self(…)` that constructor takes:
   - "`Reordered`'s constructor must start with `Row row`, as `DatabaseEntity`'s does, so `new Self(row)` can call it." It is the message when a leading parameter is missing, renamed or of another type.
   - "`Invoice`'s constructor gives `clock` no default, so `new Self(row)` cannot call it: give `clock` a default." It names each parameter past the call that has no default, one the `required` constructor defaults included.
6. **The harness carries what the examples need, and two examples carry their own base class.** The harness left `SPEC_GUARD` in `chore/spec-guard-feature-prs`, so a feature pull request changes it and lists each change in its body. #102's body planned a harness-only pull request for `PaginatedList.sharp` and the `Element.sharp` change after it merges. This Slice carries both unless that pull request lands first.
   - `sharp/harness/app/Element.sharp`: the constructor becomes `public required Element(…)`.
   - `sharp/harness/app/Order.sharp`: gains `public required Order(int id = 1) { }`, so `new (type)(id)` in `typeof-values` compiles and the 13 spec lines that write `new Order()` keep compiling. Its body sets nothing: §22's `virtual-methods` declares a `DatabaseEntity` with no `id`, and `this.id = id` failed that example.
   - `sharp/harness/app/PaginatedList.sharp`, new after #102: `public class PaginatedList<TItem : DatabaseEntity>` with a `required` constructor whose one parameter, the rows, defaults to `[]`, so `new (pages)()` and §11's plain PHP `new PaginatedList($rows)` both call it.
   - `self-return` and `required-constructors` declare a `DatabaseEntity` whose constructor is `required(Row row)`. The harness `DatabaseEntity` cannot have it: every line that writes `new Order()` would break. So `self-return`'s hidden setup adds `public class Order : DatabaseEntity { public Order(Row row) : super(row) { } }`, and `required-constructors`' first marker becomes a template whose setup declares `DatabaseEntity`, as the self-return block does. Today the harness `Order` fails beside `self-return`'s `DatabaseEntity`: "`App.Order` must declare a constructor that calls `App.DatabaseEntity`'s with `: super(…)`."
7. **Smaller refusals named in section 25's words,** each a refusal today:
   - `Self copy = new Self();` is a generic parse error. The parser reads `Self` as a local's type, and `check_self_type` reports "`Self` is only a return type in PHP#." as it does for a parameter and a property.
   - `Order::class` fails as a typo does, at the `::`.
8. **`docs/decisions/041-typeof-value.md` follows the spec.** It still decides `typeof(order)`, which commit `4ad060046` replaced with `Class.of(order)` on Jordan's ruling.

## Rulings

1. **`attributes<TAttribute>()` is `Class`'s only member.** It returns a `List<TAttribute>` of the attributes the class itself declares, `TAttribute`'s subclasses included, as PHP's `ReflectionClass::getAttributes(…, IS_INSTANCEOF)` and `newInstance()` give. It is standard library API, team-lead's call.
2. **The clash rule refuses all four statics:** a static method, a static property, a constant and an enum case, because `type.y` reaches each one.
3. **`new (type)(…)` needs a `required` constructor whatever the value holds,** `typeof(Loose)` included, as spec line 2869 says. #102 accepts a value that holds exactly one class, `new (typeof(Loose))(1)` and `const type = typeof(Loose); new (type)(1)`. That is a deviation, and this Slice refuses both, with a row.
4. **`self-return` declares `row`, as an erratum that keeps the example's meaning:**

   ```csharp
   public static Self fromSchema(Any value, VerifiedUser caller)
   {
       Row row = …;
       return new Self(row);
   }
   ```
5. **A class value holds any one type, a nullable or union one included,** as C#'s `typeof(int?)` does, from the spec line "`typeof(X)` is typed `Class<X>`, for any one type `X`". `TClassLikeString::OfType`'s `constraint` in `crates/codex/src/ttype/atomic/scalar/class_like_string.rs` holds a `TUnion`, so `typeof(int|string)` is one `Class<int|string>`, as it runs as the one value `"int|string"`. `Class<X>` fits `Class<Y>` when `X` fits `Y` (`class_string_comparator`), so `Class<int>` fits `Class<int|string>` and not the reverse. PHP's `class-string<A|B>` still gives one class string per class, so plain PHP keeps its types.
   - A written `Class<int>`, `Class<int?>` or `Class<int|string>` holds that type in a field, a parameter and a local (`union_from_hint` in `crates/codex/src/scanner/ttype.rs`), and a `Class<T>` parameter given a `Class<int|string>` infers `T` as `int|string`.
   - A value entering PHP# is checked where it enters, as the checker checks it. A `Class<int|string>` parameter takes from plain PHP the class value of any type that fits `int|string`: `"int"`, `"string"` and `"int|string"`. Plain PHP passing `"Classes\Badge"`, `"float"` or `"string|int"`, which `typeof` never spells, gets the engine's type error. A `Class<(Badge|int)?>` takes `"Classes\GoldBadge"`. `checked_text` in `crates/sharp-bridge/src/lower/types.rs` sends the type, and `sharp_class_value_fits` in `ext/sharp/sharp.c` reads the value as a type and checks it with `sharp_type_argument_takes` and `'+'` variance. `Class<Any>`, `Class<Object>` and a type parameter no type argument resolves still take any string.
   - `new (t)()` of one is "`t` is a `Class<int>`, and only a class's `Class` creates objects.", in `crates/analyzer/src/expression/instantiation.rs`. A static read, written or called through one, `t.MAX` or `t.make()`, is "`t` is a `Class<int>`, and only a class's `Class` has statics.", once, by `refuse_statics_of_no_class` in `crates/analyzer/src/expression/access/mod.rs`.
   - The extension SDK's `ClassLikeStringType` holds one `AtomicType`, so the analyzer's extension protocol sends a class value of several types as the `class-string` it is while the code runs.

Also ruled: once `PaginatedList` exists, every annotated line of `generic-class-values` gives its own annotated result, each seen with a row.

## A class value in plain PHP

`Order::class` is gone, so plain PHP that takes a class name, as Laravel's `app()->make(Foo::class)` does, receives a class value instead. A class value is its class name while the code runs, on master and on #102, so it crosses with no conversion written:

```csharp
public static Badge made() => Factory.make(typeof(GoldBadge));                // Factory.make(string $class) receives "Classes\GoldBadge"
public static Badge remade(Badge badge) => Factory.make(Class.of(badge));    // receives the class of the object badge holds
```

`Zend/tests/sharp/class_value_to_plain_php.phpt` proves it with `Zend/tests/sharp/harness/Factory.inc`, a plain PHP factory that runs `new $class()`. The checker reads `Factory.make`'s `@param class-string<T> $class` and `@return T`, so `Factory.make(typeof(GoldBadge))` is a `GoldBadge` with no cast. After #102, a row passes a `Class<Badge>` parameter the same way: #102 interns a class value with type arguments as its plain class name, so plain PHP reads `Classes\GoldBadge` for it too.

## Existing work

- **php-sharp #34 and mago-sharp #5, merged,** `feat/slice-classes`: `typeof(X)` as PHP's `X::class`, `Class.y` and a member read or call through a class value held in a local. This Slice reuses `TypeOf`, `class_value_classes` and `analyze_class_value_member` unchanged.
- **mago-sharp #16, merged:** `Self` as a return type and `new Self(…)` with its `required` check, `check_instantiation`. The refusals of PHP's `self`, `static` and `::` came with the earlier syntax work. All are reused unchanged.
- **mago-sharp #43, merged:** `+` joins a class value to a string. A class value stays a `string` while the code runs, which the events Slice relies on.
- **php-sharp #110, merged,** `feat/slice-constructors`: `: super(…)`, so a subclass of a `required` constructor can be written. `docs/architecture/slices/09-constructors.md` records that `required` needs nothing new in the checker.
- **php-sharp #102, merged at `753cb0120c`,** `feat/slice-generics`, observed at `32ba9db945` before it merged: R5 builds class values. `Class<T>`, `typeof` of a type parameter and of `Box<Order>`, `new (type)(…)` with the `required` check, `new (any)<Order>()`, static reads and writes through a class value, `==` through `\Sharp\Internal\equals`, the entry check of a `Class<…>` value from plain PHP, and a class value refused as a `Map` key. This Slice builds on all of it after the merge and changes none of it.
- **`feat/events-engine`, local, unpublished:** `Events.on<TEvent>` passes `typeof(TEvent)` to `Sharp\Internal\listen`, which takes the class name. `typeof` of a method's type parameter stays the plain `Class<T>` value #102 gives.
- **Decisions 013, 017, 030, 054, 058 and 060** state section 25's rules. Decision 041 is stale (decision 8).
- No branch holds `Class.of`, `Class`'s own members or the static clash rule.

## How the states were observed

Master is `c6a673cdde`. The engine was built with `sharp/bin/build` in `/Users/jordan/Developer/sharp-worktrees/2/php-sharp`, and a release `mago` with `CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --release --locked --bin mago` into `sharp/build/darwin-arm64/mago/`.

#102 is its head `32ba9db945`, written into `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-class-values/pr102` with `git read-tree` into a private index file and `git checkout-index`, so no branch was created or merged. Its engine and release `mago` were built there the same way.

`sharp/bin/spec` ran on each with `TEST_MAGO_EXECUTABLE` set. Master: "Checked 180 examples, ignored 1, and 77 fail", the nine of section 25 among them. #102: seven of section 25 fail. Every rule below was also probed in one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-class-values/ws`, whose `composer.json` maps `Demo\` to `app/`, and whose `vendor/` holds `sharp/composer/`:

```sh
mago --no-version-check --colors never --threads 2 --workspace $P/ws compile   # MAGO_REPORTING_FORMAT=emacs
```

`app/Support.sharp` declares the classes the probes use: `Order` with `public required Order(public int id { get; })` and `static string defaultTag()`, `RushOrder : Order`, `Loose` with a constructor that is not `required`, `Entity` with `public required Entity(public Row row { get; })` and `static Self fromRow(Row row) => new Self(row)`, and `Ledger : Entity` with `public Ledger(Row row, Clock? clock = null) : super(row)`. Each state below quotes the probe's file and line. A file with a parse error skips the checks after parsing, so each parse error has a file of its own.

The engine states come from `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-class-values/run`, compiled by master's `mago` and run by master's engine:

```text
typeof(RuntimeOrder)                     string(17) "Demo\RuntimeOrder"
RuntimeOrder.fromRow(new Row())          an object that is RuntimeOrder: new Self(…) creates the subclass
RuntimeOrder.tagOfSelf(), Self.tag()     string(5) "order": Self.m() calls the subclass's static
const type = typeof(RuntimeOrder);
type.tag()                               string(5) "order"
typeof(RuntimeOrder) == typeof(RuntimeOrder)   bool(true)
```

# Every rule of section 25

The states are **works**, **refused** (rejected, with its message), **silently accepted** (code the spec refuses compiles) and **missing** (code the spec accepts is refused). "#102" is #102's head. "Slice" is this Slice's work.

| Spec line | Rule | Master | #102 | Mechanism | Reuses |
|---|---|---|---|---|---|
| 2776 | `typeof(X)` is typed `Class<X>` | missing: `Class<Order> type = typeof(Order);` is "This type is not supported yet in PHP#." (`TypeOfTypes.sharp:7`). `typeof(X)` itself works | works for one type that is not nullable or a union, which is "This type is not supported yet in PHP#." | #102, and Slice for a nullable or union type and a written `Class` of any type, ruling 5 | `TypeOf`, `TClassLikeString` |
| 2776 | it works on every type parameter, a method's included | missing: a generic method is a parse error (`Generic.sharp:5`) | works: `string name<TItem>() => typeof(TItem)` compiles | #102 | |
| 2776 | PHP's `Order::class` is removed | refused at the `::` (`PhpForms.sharp:5`) | fails as a typo does | Slice, decision 7 | the parser's `::` refusal |
| 2793 | `typeof(TItem)` works for any type argument, `int` included | missing: parse error | works | #102 | |
| 2793 | it creates objects only when the bound is a class | missing: parse error | works: `new (type)()` in `blank<TItem>` is "`TItem` can stand for a type that is not a class." (`Generic.sharp:16`), `make<TItem : Order>` compiles | #102 | |
| 2814 | `typeof` takes a type only | silently accepted: `typeof(order)` on a parameter `order` compiles (`TypeOfTypes.sharp:26`) | silently accepted | Slice, decision 2 | `LocalScopes`, `check_binding_errors` |
| 2814 | `Class.of(value)` is a `Class<T>` of the runtime class | missing: "Method `Class.of` does not exist." (`ClassOf.sharp:7`) | missing, the same | Slice, decision 1. Its engine half reads #102's type arguments | `Sharp\List::wrap`'s prelude and engine pattern, `default_class` |
| 2828 | a class value works wherever a class name works, and a `Class<T>` field, parameter or local holds one | missing: `Class<T>` is "This type is not supported yet in PHP#." | works: `class-values` passes | #102 | |
| 2869 | `new (type)(…)` creates an object from a class value | missing: "This expression is not supported yet in PHP#." (`NewThrough.sharp:5`, `10`, `16`) | works | #102 | |
| 2869 | it needs a `required` constructor | missing | works for a `Class<Loose>` parameter, "`new (type)(…)` needs a `required` constructor: the class value can hold any subclass of `Loose`…" (`NewLooseParameter.sharp:5`). A value holding exactly `Loose` compiles (`NewLooseParameter.sharp:7`). On the merge, `new (typeof(Loose))()` and `const type = typeof(Loose); new (type)()` get the same error, which now says "a class value can hold any subclass" | #102, and Slice for the exact value, ruling 3 | |
| 2869 | for a plain PHP class, the call is checked against its own constructor | missing | works: `new (type)(1, 2)` on a `Class<Model>` is "Too many arguments provided for method `Model.__construct`." (`PlainClassValues.sharp:9`) | #102 | |
| 2883 | `new (kind)(key)` on a `string` is a compile error | missing: refused only as "This expression is not supported yet in PHP#." (`NewThrough.sharp:21`) | works: "`kind` is a `string`, not a `Class`: `new (…)` creates an object from a class value." | #102 | |
| 2870 | a static call through a class value calls the static of the class it holds | works through `typeof` and a local, engine included. Missing through a `Map` element, because `Class<Element>` is refused | works | #102 | `analyze_class_value_member`, `class_value` in the bridge |
| 2871 | `Class`'s own members win | missing: `type.attributes()` is "Method `Order.attributes` does not exist." (`TypeOfTypes.sharp:19`) | missing, the same. An extension block could not name `Class<T>`: `extension<T>(…)` was a parse error and `extension(Class<T> type)` "Cannot find type `T`." | Slice, decision 3, ruling 1, and Jordan's ruling for a block's type parameters | extensions (#115), `class_value_classes` |
| 2871 | a class cannot declare a static named like one of them | silently accepted: `public static string attributes() => "";` compiles (`StaticNamedLikeClass.sharp:5`) | silently accepted | Slice, decision 4, rulings 1 and 2 | the class member checks in `statement/class_like/mod.rs` |
| 2872 | a class value's type need not be written | works for `const type = typeof(Order); type.defaultTag();` (`TypeOfTypes.sharp:12`). Missing for `this.elements[kind] ?? throw …` | works | #102 | |
| 2873 | a property named by a variable has no form | works: `order.(column)` is a parse error (`PropertyByVariable.sharp:7`), and a `.sharp` file has no `$` variables | the same | nothing to build | |
| 2886 | a class value of a generic class carries its type arguments or leaves them out; `new (any)()` on an open one is an error | missing: parse error | works, the example fails only on the missing harness class `PaginatedList` | #102, decision 6 | |
| 2903 | two class values are equal when they name the same class with the same type arguments | works for classes without type arguments, engine included. Missing for `Box<T>`: parse error | works: `class-value-equality` passes on #102's engine | #102 | `\Sharp\Internal\equals` |
| 2914 | `Self` is the class a static method was called on | works, engine included | the same | nothing to build | `check_self_type`, the bridge's `static` |
| 2914 | it replaces PHP's `static` | refused: `new static()` (`PhpNewStatic.sharp:7`) | fails as a typo does | nothing to build | `report_unsupported` |
| 2914 | PHP's `self` is removed | refused (`PhpSelf.sharp:5`) | fails as a typo does | nothing to build | `report_unsupported` |
| 2930 | `Self` replaces the C# workaround `class Order : Entity<Order>` | prose, no rule | | | |
| 2932 | `Self` is written only as a return type, and as `new Self(…)` and `Self.m()` in bodies | works for a return type, `new Self()` and `Self.count()` (`SelfForms.sharp:7`, `11`) | the same | nothing to build | |
| 2932 | a `Self` parameter is a compile error | refused: "`Self` is only a return type in PHP#." (`SelfForms.sharp:15`), and a property too (`SelfForms.sharp:17`). A local is a generic parse error (`SelfLocal.sharp:9`) | the same | Slice, decision 7, for the local | `check_self_type` |
| 2939 | `new Self(…)` compiles only when the constructor is `required` | refused: "`new Self(…)` needs a `required` constructor: `Self` can be any subclass, so every subclass must keep a constructor that `new Self(…)` can call." (`NotRequired.sharp:7`) | the same | nothing to build | `check_instantiation` |
| 2941 | a subclass constructor's leading parameters match the `required` one's | refused: a renamed parameter is "Parameter #1 of `Renamed.__construct` is named `other` but parent `Entity.__construct` names it `row`". A wrong type gets the count text below. A wider type, `Row? row`, compiles | the same | Slice, decision 5 | the consistent constructor flag |
| 2942 | every parameter it adds has a default | refused with upstream's text: "`Invoice.__construct` must accept at least 1 required parameters like `Entity.__construct`" (`Invoice.sharp:5`). It holds below a subclass that is not marked: `BelowLedger : Ledger` is refused. `Ledger`, with `Clock? clock = null`, compiles | the same | Slice, decision 5 | the consistent constructor flag |
| 2966 | `required` is optional | works: `Loose` compiles | the same | nothing to build | |

Sections 9, 11 and 19 point here. Section 9's line 451 is lines 2939 to 2942. Section 11's line 711, `Self` on a receiver typed by a type parameter, is #102's: `self-on-type-parameters` passes there. Section 19's line 1746, class values compare by value, is line 2903.

A class value is a `string` in PHP# code on master and on #102: `public string asString() => typeof(Order);` and `"class " + typeof(Order)` compile (`TypeOfTypes.sharp:22`, `34`). No line of section 25 says otherwise, so it stays.

# The examples

| Example | Master | #102 | What passes it |
|---|---|---|---|
| `typeof-types` | the setup's `run<TItem>()` is a parse error, so no later check runs | "Method `App.Order.attributes` does not exist." | #102, decision 3 |
| `typeof-type-parameters` | parse errors | "`new (type)(…)` needs a `required` constructor: `TItem` can be any subclass of `Element`…" | #102, decision 6's `Element` |
| `typeof-values` | `Class<Order>` and `new (type)(id)` refused, `Class.of` missing, `typeof(order)` accepted | `Class.of` missing, `new (type)(id)` is "Too many arguments provided for method `App.Order.__construct`.", `typeof(order)` accepted | #102, decisions 1, 2 and 6's `Order` |
| `class-values` | `Class<Element>` and `new (type)(key)` refused | passes | #102 |
| `class-members` | parse error on `attributes<Listen>()` | "Method `Element.attributes` does not exist.", and the static `attributes` compiles | #102, decisions 3 and 4 |
| `generic-class-values` | parse errors | "Cannot find type `PaginatedList`." | #102, decision 6's `PaginatedList` |
| `class-value-equality` | `class Box<T>` is a parse error | passes | #102 |
| `self-return` | the `…` line is a parse error, and the harness `Order` refuses the example's `DatabaseEntity` | the same | ruling 4, decision 6's setup |
| `required-constructors` | "`App.DatabaseEntity` has no constructor to call: remove the arguments." | the same | decision 6's template |
| `self-parameter` | passes | passes | |

`generic-class-values` line 2899 expects a compile error, and on #102 it passes for the wrong reason: the line names `PaginatedList`, and the missing class's error satisfies a line that names it. A probe with no `PaginatedList` gets only "Cannot find type `PaginatedList`." (`OpenGeneric.sharp:5`). With a declared `Shelf<TItem : Order>`, #102 gives the rule's own error, "`Shelf` needs its type argument." (`OpenGenericDeclared.sharp:10`), so the harness class turns the line's error into the right one. On the merge with the harness class, `fresh()` and `ofOrders()` compile and `open()` gets "`PaginatedList` needs its type argument.", and the analyzer test `new_through_a_class_value_gives_the_object_the_type_arguments_it_names_or_the_value_carries` shows the type each form gives. §11's `raw-generic-objects` now passes the checker and fails only when it runs, on the example's elided bodies, so it stays in `sharp/baseline/spec/§11/`.

# Files

```text
sharp/mago/crates/
├── names/src/
│   ├── binding.rs                       BindingError::LocalAsType { name, local };
│   │                                    MethodParts: a block's type parameters first       (decisions 2, 3)
│   ├── internal/walker.rs               walk_in_type_of: a local in scope is the error;
│   │                                    an extension block scopes its type parameters      (decisions 2, 3)
│   └── lib.rs                           default_class: Sharp\Class beside Sharp\List        (decision 1)
├── prelude/assets/extensions/sharp.php  final class Class { of }, after final class List   (decision 1)
├── semantics/src/internal/checker/
│   └── sharp.rs                         check_binding_errors: LocalAsType's message;
│                                        check_self_type: a Self local;
│                                        check_extension_type_parameters; an Object bound;
│                                        only the standard library extends Class            (decisions 2, 3, 7)
├── syntax/src/
│   ├── cst/cst/type_of.rs               TypeOf.type: typeof takes any type
│   ├── cst/cst/class_like/extension.rs  Extension.type_parameters                          (decision 3)
│   ├── cst/node.rs, walker/mod.rs       visit and walk them                                (decision 3)
│   ├── error.rs                         ParseError::ClassNameConstantInSharp               (decision 7)
│   └── parser/internal/                 Self read as a local's type; `Order::class`;
│                                        `extension<T>(…)`                                  (decisions 3, 7)
├── fingerprint/src/class_like/mod.rs    an extension block's type parameters               (decision 3)
├── hir/src/lower/error/mod.rs           the new parse error is an unexpected token          (decision 7)
├── analyzer/src/
│   ├── expression/access/mod.rs         analyze_through_class_value: a receiver whose
│   │                                    type is a class value reaches its class's statics  (decision 3)
│   ├── expression/call/method_call.rs   the same for a call; a call's written type
│   │                                    arguments reach an extension member                (decision 3)
│   ├── expression/type_of.rs            typeof of any type is the Class of that type
│   ├── invocation/template_result.rs    seed_extension_type_arguments                      (decision 3)
│   ├── resolver/extension.rs            fits infers a block's type parameters and names
│   │                                    the bound a receiver misses; class_member          (decisions 3, 4)
│   ├── expression/instantiation.rs      new (typeof(Loose))(…) needs required too          (ruling 3)
│   └── statement/class_like/mod.rs      a static named like a member of Class;
│                                        report_required_constructor_mismatch               (decisions 4, 5)
├── sharp-bridge/src/lower.rs, types.rs  a generic extension call carries its type arguments (decision 3)
├── sharp-bridge/tests/lower.rs          Class.of lowers to \Sharp\Class::of
└── each crate's tests/                  one test per rule, forms as rows
sharp/composer/library/Sharp/
└── ClassMembers.sharp                   NEW: extension<T>(Class<T> type) with attributes   (decision 3)
ext/sharp/
└── sharp.c                              Sharp\Class::of, beside Sharp\List::wrap           (decision 1)
Zend/tests/sharp/
├── ClassValues.sharp                    NEW: Class.of, a static call through it, a generic
│                                        Crate<TItem>, and class values passed to plain PHP
├── class_of.phpt                        NEW: Class.of gives the runtime class, with its type
│                                        arguments, equal to typeof(Crate<Badge>)
├── class_value_to_plain_php.phpt        NEW: plain PHP takes a class value as its name
├── class_attributes.phpt                NEW: type.attributes<Listen>() runs, subclasses of
│                                        the attribute included, the class's own only
├── harness/Factory.inc                  NEW: a plain PHP factory that takes a class name
└── self_return.phpt, SelfReturn.sharp   NEW: new Self(…) and Self.m() create and call the
                                         subclass, below a required constructor
sharp/harness/app/
├── Element.sharp                        a required constructor                             (decision 6)
├── Order.sharp                          public required Order(int id = 1)                  (decision 6)
└── PaginatedList.sharp                  NEW, after #102                                    (decision 6)
docs/
├── spec.md                              self-return: its hidden Order, and ruling 4's
│                                        body; required-constructors: a template marker;
│                                        section 26: an extension block's type parameters
├── decisions/041-typeof-value.md        Class.of(value)                                    (decision 8)
└── architecture/slices/25-class-values.md   this note
sharp/baseline/spec/§25/                 all nine entries leave
```

A static call on `Sharp\Class` lowers as any static call, and an extension call already lowers before a class value's static call. The bridge's one change is that a generic extension member's call ends with its type arguments.

# Build order

1. **On master. Built.** Decisions 2, 5, 7 and 8. Decision 1 for an object without type arguments: the prelude's `Class`, `default_class`, and `Sharp\Class::of` returning the object's class name. Decision 6's `Element` and `Order`, and the spec's setup with ruling 4. `self-return` and `required-constructors` pass and leave `sharp/baseline/spec/§25/`.
2. **Merge `origin/master` once #102 lands. Built.** `typeof-type-parameters` and `typeof-values` pass and leave `sharp/baseline/spec/§25/`.
3. **On the merge.** Built: decision 1 for an object with type arguments, which reads #102's type arguments, ruling 3, decision 6's `PaginatedList`, and a `Class<Badge>` parameter passed to plain PHP. `generic-class-values` passes and leaves the list. Then, on Jordan's ruling, extension blocks with type parameters, and on them decisions 3 and 4: `typeof-types` and `class-members` pass, and `sharp/baseline/spec/§25/` is empty.
4. **Verify.** `sharp/bin/spec` shows every section 25 example passing, and `sharp/bin/spec --completes` passes on a body with `Completes: §25`. The engine tests this Slice adds or changes pass under `sharp/bin/test --opcache --repeat --passes`. `mago analyze` over a plain PHP corpus prints the same lines before and after, as #102 measured it, since the prelude gains a class only PHP# code names.

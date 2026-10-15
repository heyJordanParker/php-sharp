# Slice: the rest of section 11

Spec section 11 and 11.1, every rule line, with the generic statics that properties (#132) left refused. #102 built generics R1 to R5, and `11-generics.md` is its note. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Most of section 11 holds on master `55769621b5`, in the checker, the bridge and the engine. Every probe below ran on it. What does not hold falls in five groups:

- **The three listed examples fail for their setup, not for a rule.** `generic-method-inference` collides with the harness folder `app/Cache/`, `type-parameter-bounds` needs a generic `Query<T>` the harness lacks, and `raw-generic-objects` runs `count`'s elided body after the entry check accepted the page. With each setup fixed by hand, every annotated line holds.
- **Generic statics are refused in two forms.** A static with accessors and a static whose initial value runs as code, in a generic class, are "not supported yet". Section 11 lets a static use `T`, so both get built.
- **A value of a type parameter whose bound has type arguments enters from plain PHP unchecked.** `boxedId<TBox : Loose<T>>` on a `Bounds<Order>` takes a raw `Loose` that holds `1`, and its body reads `.id` of an int. Line 766 says such a value is checked where it enters.
- **A default body whose bound names its interface's type parameter is refused falsely.** `class OrderShelf : Shelved<Order>` gets "`OrderShelf.kind<TBox>` must keep the bound `Loose<Order>` of `Shelved.kind<TBox>`", which it keeps. An explicit implementation of the same method compiles. This is the bound the task names.
- **The declared type does not reach a generic call through a `match` arm or a lambda's result.** Through `?:` and `??` it does.

Three rules need a decision about what users write. They are questions for Jordan below, each with its spec line.

## Questions for Jordan

Each question decides what users write. The recommendation is mine, and the build waits for the answer.

1. **Line 750, a generic call inside a generic call.** `List<Order> f = this.keep(this.get("kk"));` is refused: "Nothing fixes the type argument `T` of the call to `InferMore.get`." The outer declared type fixes `keep`'s `TValue` as `List<Order>`, and `get`'s result goes into that parameter. `this.take(this.get("p"))` already compiles, because `take`'s parameter is declared `List<Order>`. Swift fixes both calls. C# fixes neither (CS0411). Recommended: it compiles, as in Swift. A user who wraps a call in a generic helper, such as `Option.of(cache.get("k"))`, then writes the type once, in the declaration, the same as without the wrapper.
2. **Line 809, a bound that is a bare type parameter.** `public void add<TSub : T>(TSub item)` is refused: "A bound is `Object`, a class or an interface, as in `<TItem : DatabaseEntity>`." The spec lists no such bound and no section 11 example needs one. C# and Java allow it. Recommended: keep it refused, and line 809 says so: "A bound is a class, an interface, a generic class type or `Object`, never a type parameter."
3. **Lines 749 and 751, a plain PHP `@template` class without type arguments.** `public void unnamed(Shelf shelf)`, where `Shelf` declares `@template TItem of DatabaseEntity`, compiles. Line 749 says a type names its type arguments. Decision 50 says plain PHP generics keep their fallback. Recommended: it keeps compiling, and line 751 says so: "Without type arguments it takes its bounds, as PHPDoc does." Laravel's `Collection`, `Builder` and `Factory` are written bare in most code that PHP# calls.

## Decisions team-lead approves

1. **`generic-method-inference` declares `Cache` in a namespace of its own.** The example's `App.Cache` class meets the harness folder `app/Cache/`, which section 32's `module-services` imports as `App.Cache.RedisStore`. The checker reports "`App.Cache` is both a class and a namespace: rename the class or the folder." The marker's hidden setup gains `namespace App.Inference;` and `import App.Order;`, which `sharp/bin/spec` already moves to the head of the file. No rendered line changes. With the folder removed by hand, line 762 compiles and line 763 gets "Nothing fixes the type argument `T` of the call to `Cache.get`."
2. **The harness `Query` becomes `Query<T>`.** `type-parameter-bounds` writes `Query<TItem>`, and `sharp/harness/app/Query.sharp` declares `Query` with no type parameter: "Too many type arguments for `Query`: expected 0, but found 1." Section 6.3's `property-references` creates one with `new Query()`, so its hidden setup becomes `Query<Order> query = new Query<Order>();`. With `Query<T>` the bounds example compiles with no error.
3. **`raw-generic-objects`'s `accepted:` line passes on an `App\Elided` throw.** Line 776 is accepted: the run reaches `count`'s body, which is `{ … }` and throws `App\Elided`, and the uncaught throw fails the run. Line 775 throws the claimed `TypeError`: "App\Report::show(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given". Options:
   - A: `sharp/bin/spec` runs a plain PHP line annotated `accepted:` or `compiles` inside a `try`, and an `App\Elided` it throws holds the claim, because the body the spec leaves out is the only code that throws it. This is #138's precedent: the checker reads `…` as the spec writes it. `SPEC_GUARD` puts it in its own pull request first, with a `sharp/spec-self-test.md` row.
   - B: an erratum that gives `count` a body, as `=> 0`.
   - Recommended: A. The claim is that the entry check accepts the page, and the throw from the elided body proves the call got past it.
4. **Generic statics run through one hidden static method per generic class.** See "Generic statics" below. It adds a hidden method name and a third parameter to `\Sharp\Internal\statics`, a function `ext/sharp/sharp.stub.php` declares.
5. **The entry check reads a method's bounds for a value of its type parameter.** A plain PHP caller that passes a raw object into such a method throws `TypeError` where it passed before. That is line 766's rule, and no test on master passes one.
6. **The names below,** each with the slate it was picked from.
7. **Order:** this PR lands after collections (#135), hosts (#136) and built-in types. Built-in types changes `semantics/src/internal/checker/sharp.rs` (253 lines), `sharp-bridge/src/lower.rs` and `lower/types.rs`, and collections changes the same three and `ext/sharp/sharp.c`, so this branch resolves them when it merges. None of the three touches section 11's text.

## How the states were observed

`mago` was built in release from `55769621b5` with `CARGO_PROFILE_RELEASE_LTO=off` into `sharp/build/darwin-arm64/mago/release/mago`, and the engine with `sharp/bin/build`, a debug build, from the same commit.

```sh
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

It printed "Checked 200 examples, ignored 1, and 32 fail", the three section 11 entries among them, as the list holds.

Each probe is one file in a copy of the project `sharp/bin/spec` writes for `raw-generic-objects`, at `/private/tmp/claude-501/-Users-jordan-Developer-php-sharp/345d8519-d589-441b-947d-8f4fe33a0768/scratchpad/gr/ws`, with its classes in `app/Probe/`. A plain PHP `entry.php` calls them on the engine. `illuminate/container` 12 was installed beside it with Composer for the container rows.

```sh
MAGO=sharp/build/darwin-arm64/mago/release/mago
WS=/private/tmp/claude-501/-Users-jordan-Developer-php-sharp/345d8519-d589-441b-947d-8f4fe33a0768/scratchpad/gr/ws
env -u FORCE_COLOR MAGO_REPORTING_FORMAT=emacs $MAGO --no-version-check --colors never --threads 2 --workspace $WS compile
SHARP_SPEC_OUTCOMES=$WS/outcomes sharp/build/darwin-arm64/sapi/cli/php -n -d error_reporting=E_ALL $WS/run.php
```

The states are **works**, **refused** (with the message), **wrong** (it compiles or runs differently from the spec), and **fails** for a listed example.

# Every rule of section 11, its state and its mechanism

Paths under `crates/` are in `sharp/mago/crates/`. "Semantics" is `crates/semantics/src/internal/checker/sharp.rs`. "The bridge" is `crates/sharp-bridge/src/lower.rs`.

## Reified generics

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 724, 726 | `page is PaginatedList<Order>` is checked while the code runs | Works: a raw page gives `false` (`Run.isOrders`) | None new | #102's `sharp_type_list_holds` |
| 727 | A wrong value throws `TypeError` where it enters from plain PHP | Works: `Run.sum([1, 'two'])` throws "must be of type List<int>, array given: [1] expected int, got string 'two'", and `['a' => 1]` throws for `List<int>` | None new | #102's `sharp_type_accepts` |
| 727 | An `int` where PHP# expects a `float` becomes a `float` | Works: `Run.half(3)` gives `1.5`. A string `'3'` gives `1.5` too, under the plain caller's weak mode, as line 823 checks every parameter under the caller's `strict_types` | None new | |

## Each type argument's own statics

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 729, 731 to 742 | `generic-statics`: `Counter<Order>.count` and `Counter<User>.count` are two values, and `List<T> seen` compiles | Works: the example passes | None new | #102 R4's store |
| 729 | A static may use `T`, with an initial value that runs as code | Refused: `Tally.made = Map.from(…)` and `Tally.fresh = List.from([])`, "A static member of a generic class with an initial value that runs as code is not supported yet in PHP#." | "Generic statics" below | R4's store, R4 part 1 |
| 729 | A static may use `T`, with accessors: a computed static, `get` and `set` bodies, observers, `static lazy` | Refused: `label`, `doubled`, `watched`, `once`, "A static property with accessors in a generic class is not supported yet in PHP#." | "Generic statics" below | Decision 3 of properties, `\Sharp\Internal\keep` |
| 744 | A bare `Counter.count` is a compile error | Works: "`Tally` needs its type argument." Inside the class, `Inside<T>.count += 1` compiles | None new | |

## Writing type arguments

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 748 | `new` names them | Works: `new PaginatedList(rows)` is "`PaginatedList` needs its type argument.", also where a declared type could fix it | None new | |
| 748 | `new (pages)()` takes a class value's | Works: section 25's examples pass | None new | #102 R5 |
| 748 | `new Self(…)` names none | Works: `new Self<T>()` is "`Self` already carries its class's type parameters." | None new | |
| 749 | A type names them, `Class<PaginatedList>` aside | Works: `PaginatedList page` and `class Mine : PaginatedList` are errors, `Class<PaginatedList>` compiles | None new | |
| 750 | A call's arguments fix them | Works: `inferred-type-arguments` passes | None new | |
| 750 | The declared type fixes them: a typed local, a field write, `return`, an expression body, an argument to a typed parameter, `Any`, `List<Order>?`, a reassignment, `?:` and `??` | Works, and the engine carries the fixed type: `Run.declared('x')` fails `value as T` against `List<Order>` | None new | |
| 750 | ... through a `match` arm: `List<Order> d = match (flag) { true => this.get("m"), default => [] };` | Wrong: "Nothing fixes the type argument `T` of the call to `InferMore.get`." | `takes_declared_type` takes a `match`, which hands the declared type to each arm, as `?:` hands it to each side | `expect_type` |
| 750 | ... into a lambda's declared result: `Function<List<Order>()> c = () => this.get("l");` | Wrong: the same error, and "Invalid assignment to `c`" | The arrow function and the closure hand the result type of the function type they go into to their body expression and to each `return` | `closure_parameter_types`, which hands them the parameter types today |
| 750 | ... through a generic call's parameter | Refused | Question 1 | |
| 750 | Nothing fixes them: `const c = this.get("c");` | Works: an error, with the help "Write the type argument on the call, or give the result a declared type." | None new | |
| 751 | A `@template` class takes type arguments, and the checker checks them | Works: `Shelf<Order, int>` "Too many type arguments for `Shelf`: expected 1, but found 2.", `Shelf<string>` "Type argument `string` does not satisfy `Shelf`'s `TItem`.", `shelf.put(customer)` "expected `Order`, but found `Customer`" | None new | |
| 751 | Its object carries none while the code runs | Works: `getTypeArguments()` of a PHP# `new Shelf<Order>()` is `[]` | None new | |
| 751 | `x is Shelf<Order>` is a compile error that says why | Works: "`Shelf<Order>` can't be tested: `Shelf` is a plain PHP class, so its objects carry no type arguments while the code runs.", for `as` too | None new | |
| 751 | A bare `@template` class in a PHP# type | Compiles | Question 3 | |
| 753 to 764 | `generic-method-inference` | Fails for its setup | Decision 1 | |

## Generic objects that plain PHP creates

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 766 | A raw object has its bounds as its type arguments | Works: `new App\PaginatedList([])` reports `['App.DatabaseEntity']`, and an unbounded `new Loose(5)` reports `['Any?']` | None new | #102's `sharp_class_bounds` |
| 766 | ... created by Laravel's container | Works: `Container::make(PaginatedList::class)` reports `['App.DatabaseEntity']`, and autowiring `Needs(PaginatedList<Order> page)` throws "App\Probe\Needs::__construct(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given". `ReflectionClass::newInstanceArgs` and `newInstanceWithoutConstructor` report the bounds too | None new | |
| 766 | A parameter that needs `PaginatedList<Order>` throws | Works: `Container::call([$report, 'show'])` throws | None new | |
| 766 | A generic method takes any page, and its `TItem` is the bound | Works: `count` gives `App\DatabaseEntity` for a raw page | None new | |
| 766 | Checked where it enters: a value of a type parameter whose bound has type arguments | Wrong: on a `Bounds<Order>`, `boxedId<TBox : Loose<T>>` and `fixedId<TBox : Loose<Order>>` take a raw `Loose` holding `1` from plain PHP, warn "Attempt to read property "id" on int", then throw "Return value must be of type int, null returned" | "A type parameter's bound at entry" below | |
| 766 | PHP# never creates a raw object | Works: the `new` rows above | None new | |
| 768 to 786 | `raw-generic-objects` | Fails for its setup | Decision 3 | |

## Inferred type arguments at runtime

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 788 to 802 | `inferred-type-arguments` | Works: the example passes | None new | |
| 804 | A collection's elements are checked where it enters | Works: the 727 rows | None new | |

## Declaring type parameters

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 808 | Names start with `T` | Works: `<Item>` is "A type parameter's name is `T`, or `T` and a capital letter, as in `TItem`." | None new | |
| 809 | The bound is written inline | Works | None new | |
| 809 | A bound that names the class's type parameter inside a type, `<TBox : Loose<T>>` | Works in a class, and in a class's own implementation of an interface method (`OrderStock`, `Stock<TItem>`). Wrong for a body the class takes from an interface default body: `OrderShelf : Shelved<Order>` gets "`OrderShelf.kind<TBox>` must keep the bound `Loose<Order>` of `Shelved.kind<TBox>`." | "A default body's bound" below | |
| 809 | A bare type parameter as a bound, `<TSub : T>` | Refused | Question 2 | |
| 810 | Several bounds use `&` | Works: `Both<TItem : DatabaseEntity & Shareable>` | None new | |
| 811 | `Object` bounds a type parameter to any object | Works: `keep(5)` is "`5` does not satisfy the bound `Object` of type parameter `TValue`." | None new | |
| 811 | "It is a bound alone" | Works, read as: `Object` joins no other bound. `<TValue : Object & Shareable>` is "Type `Object` cannot be part of an intersection." `Object` as a type outside a bound is section 24's, which built-in types owns | None new | |
| 812 | A type parameter with no bound may hold `null` | Works: `Holder<Order?>` and `Holder<int?>` compile, `this.value.toString()` on an unbounded `T` is "Attempting to access a method on a non-object type (`T`).", and a raw `Loose` has `Any?` | None new | |
| 813 | A generic class can't implement `Serializable`, and the error points to `__serialize` and `__unserialize` | Works: "`Kept` has type parameters, so it cannot be a `Serializable`: `serialize` drops the type arguments its objects carry.", with the help "Leave `Serializable` out: `serialize()` then uses `__serialize` and `__unserialize`, or its default form, which keep them." | None new | |
| 814 | A class value with type arguments keys no `Map` | Works: a literal key and `Map<Class<PaginatedList<Order>>, int>` both get the `Map` key error | None new | |
| 816 to 821 | `type-parameter-bounds` | Fails for its setup | Decision 2 | |

## Implementing a generic type, and `Self`

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 823 to 847 | `generic-implementation` | Works: the example passes | None new | |
| 849 to 860 | `self-on-type-parameters` | Works: the example passes | None new | |

## Section 11.1: variance

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 864 | Classes and interfaces declare `out` and `in` | Works: `Feed<out TItem>`, `Validator<in TItem>`, `Check<in TItem>` | None new | |
| 866 | `out`: `Feed<Order>` is a `Feed<DatabaseEntity>` | Works in the checker, and at the engine's entry: plain PHP passes an `Out<Order>` to `Out<DatabaseEntity>` | None new | #102's `sharp_type_fits` |
| 867 | `in`: `Validator<DatabaseEntity>` is a `Validator<Order>` | Works in the checker and at entry | None new | |
| 868 | No marker is invariant | Works: the checker refuses, and the engine throws "must be of type App.Probe.Same<App.DatabaseEntity>, App.Probe.Same<App.Order> given" | None new | |
| 870 | The checker enforces the marker on every member, on that member's line | Works: a method parameter, a settable property, a `protected` field, a static property and a `List<TItem>` parameter each get "`TItem` is declared `out`, so … cannot take it in." A return and a `Function<void(TItem)>` parameter compile | None new | |
| 871 | A private member may break it, and is reachable only through `this` | Works: "`keep` breaks `out TItem`, so it is reachable only through `this`." | None new | |
| 873 to 878 | The error names the marker to add | Works: "Plain<Order> cannot be used as Plain<DatabaseEntity>.", help "TItem is only returned by Plain, so declare it `out TItem`." | None new | |
| 880 | No variance at the point of use | Works: `Members<out Order>` is a parse error | None new | |

# Mechanisms

## Generic statics

#102 keeps each type argument's statics in one hidden static of the class, `"\0<sharp>\0statics"`, an array keyed by the type argument text. Its `""` entry holds the constant initial values, which `\Sharp\Internal\statics` copies when a key is new. Each access lowers to `\Sharp\Internal\statics(\App\Counter::class, <key>)['count']` in `Lowering::static_property`. A code initial value has no constant to copy, and an accessor has no storage that names its key, so semantics' `report_generic_static` refuses both.

The decisions say a static lives as long as the app, and its initial value runs once, when the app first uses `Counter<Order>`'s statics. The store is a PHP static, so it already lives as long as the app does: one request under php-fpm, the worker under a long-running host, and every request again for a preloaded class.

**One hidden static method per generic class with static fields returns the entry of its type arguments.** It takes the class's type arguments as its leading own ones, as #102's part 1 gives every static method of a generic class, so its body spells the key and runs each initial value with `T` known:

```php
public static function &'#entry'(): array
{
    $entry = &\Sharp\Internal\statics(self::class, <SHARP_TYPE_OF list of #0…>, ${'created#1'});
    if (${'created#1'}) {
        $entry['made'] = \Sharp\Map::from([['a', 1]]);    // each initial value that runs as code, in declaration order
    }
    return $entry;
}
```

- **Every access goes through it,** `\App\Counter::{'#entry'}<App.Order>()['count']`, so `Lowering::static_property` keeps one path. A write through a class value and a subclass's header reach it the same way, since they already give the access its type arguments.
- **`\Sharp\Internal\statics` gets `?bool &$created = null`,** set when this call adds the key, from the branch that copies the defaults today. The store keeps the one record of which keys exist.
- **A throw in an initial value leaves the entry with its defaults** and runs nothing again, as a non-generic class's `#statics` leaves its statics after `zend_class_init_sharp_statics` returns `FAILURE`.
- **A static with accessors** keeps decision 3's hidden `name#get` and `name#set`. In a generic class they take the class's type arguments as their leading own ones, the access passes them as `Counter<Order>.track()` passes its own, and `field` is `\App\Counter::{'#entry'}<#0>()['name']`.
- **`static lazy`** keeps its value with `\Sharp\Internal\keep`, under a key that adds the type arguments: `'App\Counter<' . <SHARP_TYPE_OF list> . '>::once'`.
- **The cost** of one more PHP frame per access is measured in Phase 2 against master, in instructions per pass in a release build, interpreter, opcache and tracing JIT, as `11-generics.md` measures.
- **Plain PHP** still reaches the store only through `\Sharp\Internal\statics` and the hidden method, whose name it can't write as a member access.

## A type parameter's bound at entry

`ZEND_SHARP_RECV_TYPE_ARGS` gives a plain PHP call its method's bounds, spelled for `this`, as its own type arguments. `sharp_type_check_arguments` then spells each parameter's text with no method and `unresolved` set, so `#0` matches any value. The PHP type of `TBox box` is the bound's class, `Loose`, so PHP's own check takes any `Loose`.

**An unresolved `#i` matches only what fits the method's bound `i`.** `sharp_type_node_substitute` gives the unresolved node the bound the call already spelled, and the walk accepts at that node a value whose type fits the bound, through `sharp_type_fits`. At the top of a parameter, `TBox box` refuses a raw `Loose<Any?>` where the bound is `Loose<Order>`. Inside a type, `PaginatedList<TItem>` still takes a `PaginatedList<Order>` from plain PHP, since `Order` fits `DatabaseEntity`, so line 776 holds.

## A default body's bound

`report_signature_compatibility_issue` in `crates/analyzer/src/statement/class_like/mod.rs` compares a class's `kind<TBox>` with the interface's. A class's own implementation compares bounds with the header's type arguments in place of the interface's type parameters (`an_override_keeps_a_bound_that_names_the_parents_type_parameter`). A method the class takes from the interface's `Defaults` trait is compared with the interface's own, unsubstituted bound, `Loose<T>`, so it can never match. It takes the same substitution.

The engine then runs that body. `ZEND_SHARP_RECV_TYPE_ARGS` spells the bounds in `EX(func)->common.scope`, which for a method a class took from a trait is the class, so `$0` would read the class's first type parameter. Inheritance decision 8 maps a trait's code to the interface its header names in `sharp_type_scope(scope, func)`. The handler passes its function, so a default body reads the type arguments the class gives the interface. `TwoShelf<TOther, TItem> : Shelved<TItem>` is the row that tells the two apart.

# Existing work, and what the Slice takes from it

- **#102, `feat/slice-generics` (merged), with `11-generics.md`.** R1 to R5: type parameters, bounds and variance in the checker, objects and calls that carry type arguments, the one value check `sharp_type_accepts`, the statics store and `\Sharp\Internal\statics`, part 1's static type arguments, and class values. Every "works" row above is its work. The Slice reuses the store, part 1, the entry check and `type_arguments_call_statics.phpt`, and changes none of their shape.
- **The local branch `feat/generics-r3`.** An earlier head of #102. `git merge-base --is-ancestor feat/generics-r3 HEAD` succeeds, so it holds nothing master lacks.
- **mago-sharp #152, `feat/slice-generics` (closed).** R1 and R2, which reached master through #102's subtree merge.
- **#132, properties (merged), with `06-properties.md`.** Rows 127, 137 and 138 build static code initial values, static accessors and `static lazy` for a class that is not generic, and leave the generic class refused. The Slice builds on `Lowering::static_accessors`, `static_lazy_value` and the `keep` store, and updates those rows.
- **#133, inheritance (merged), with `22-inheritance.md`.** Decision 8 maps a trait's code to its interface. The Slice extends it to a default body's own type parameters.
- **#138, `fix/spec-elided-body` (open).** It makes a body that ends in `…` throw `Elided`, and is the precedent for decision 3's option A. It does not touch `accepted:`.
- **#135 collections, #136 hosts and built-in types (open).** None changes section 11. Hosts rewords section 6 for a static's lifetime, which the store already has.
- **No branch builds inference through `match`, raw objects past #102, or generic statics' initial values.** `git branch -a --no-merged HEAD` lists `feat/slice-lazy-and-types` and `feat/slice-traits` too, neither about section 11.

# Names this Slice adds

The hidden method, read at its callsite `\App\Counter::{'X'}<App.Order>()['count']`:

- `#entry`: the store's entry of these type arguments. "Counter<Order>'s entry, count." The store's own word, from `11-generics.md`.
- `#statics`: the word a non-generic class already uses, for a `void` method the engine runs itself when it builds the statics. One name with two shapes, and `zend_class_init_sharp_statics` would run the generic one with no type arguments.
- `#own`: "Counter<Order>'s own (statics)". The reader adds "statics".
- `#typeStatics`: "the statics of this type". Long, and "type" reads as the class.

Recommended: `#entry`. Runner-up: `#own`.

The parameter, read at `\Sharp\Internal\statics(self::class, $key, $created)`:

- `$created`: this call created the entry.
- `$first`: this is the first use. The reader adds "of what".
- `$fresh`: the entry is new. Close to `$created`, and less exact.

Recommended: `$created`. Runner-up: `$fresh`.

# Files

```text
docs/
├── architecture/slices/
│   ├── 11-generics-rest.md          <- new: this note
│   ├── 11-generics.md               <- "Left as they are": a static's code initial value is built
│   └── 06-properties.md             <- rows 127, 137, 138: a generic class's statics are built
└── spec.md                          <- hidden setups of generic-method-inference (decision 1) and
                                        property-references (decision 2); lines 751 and 809 if
                                        questions 2 and 3 say so
sharp/
├── baseline/spec/§11/               <- the three entries go
├── harness/app/Query.sharp          <- Query<T> (decision 2)
├── bin/spec                         <- decision 3 A, in its own pull request first
├── spec-self-test.md                <- decision 3 A's row, in that pull request
└── mago/crates/
    ├── semantics/src/internal/checker/sharp.rs
    │                                <- report_generic_static goes; the stale lines 366 to 369 that
    │                                   call is, as and typeof of a type parameter "not supported yet"
    ├── analyzer/src/
    │   ├── statement/function_like/mod.rs   <- takes_declared_type takes a match
    │   ├── expression/match.rs              <- hands the declared type to each arm
    │   ├── expression/arrow_function.rs     <- hands the function type's result to its body
    │   ├── expression/closure.rs            <- and to each return
    │   ├── invocation/analyzer.rs           <- question 1, if it says yes
    │   └── statement/class_like/mod.rs      <- a default body's bound takes the header's type arguments
    └── sharp-bridge/src/
        ├── lower.rs                 <- #entry; static_property calls it; static_accessors and
        │                               static_lazy_value of a generic class; statics_store keeps
        │                               constant defaults only
        └── lower/types.rs           <- the hidden methods' metadata: the class's bounds lead
ext/sharp/
├── sharp.c                          <- statics' $created; the bounded unresolved #i
├── sharp.stub.php                   <- statics(string $class, string $key, ?bool &$created = null)
└── sharp_arginfo.h                  <- regenerated from the stub
Zend/
├── zend_vm_def.h                    <- ZEND_SHARP_RECV_TYPE_ARGS spells bounds in its function's scope
└── zend_vm_execute.h                <- regenerated
```

# The tests

One test per rule, forms as rows, each seen failing on master before its code:

- **`crates/semantics/tests/sharp.rs`:** `a_static_member_takes_any_initial_value_and_is_get_only_or_settable` and `a_static_property_takes_accessors_except_in_a_generic_class` lose their generic refusals, the second renamed `a_static_property_takes_accessors`.
- **`crates/analyzer/tests/sharp.rs`:** `a_generic_call_takes_the_declared_type_its_result_goes_into`, rows `?:`, `??`, `match`, a lambda's expression body, a closure's `return`, and question 1's nested call. `a_default_body_keeps_a_bound_that_names_its_interfaces_type_parameter`, rows `OrderShelf`, `TwoShelf<TOther, TItem>`, and a `LineShelf` that keeps the wrong bound and still gets the error. Each with its PHP twin, which keeps Mago's issues.
- **`crates/sharp-bridge/tests/lower.rs`:** a generic static access lowers to `#entry` with its type arguments, `#entry`'s body sets each code initial value under `$created`, a generic static's `name#get` takes the class's type arguments, and a generic `static lazy` key carries them.
- **`Zend/tests/sharp/type_arguments_call_statics.phpt`, rows, each twice for the tracing JIT:** a code initial value runs once per type argument, at its first use, counted by a static the initializer bumps; through a class value and a subclass's header; a plain PHP `Counter::track()` initializes the bounds' key; a throw leaves the defaults; accessors, observers, a get-only static and `static lazy` keep one value per type argument.
- **`Zend/tests/sharp/type_arguments_preload.phpt`:** a preloaded generic class's code initial value runs in the request.
- **`Zend/tests/sharp/type_arguments_entry_argument.phpt`:** a raw `Loose` into `boxedId` and `fixedId` throws, a `Loose<Order>` enters, and a `PaginatedList<Order>` and a raw page still enter `count`.
- **The inheritance engine test that runs default bodies:** `TwoShelf<int, Order>.kind(box)` reads `TItem`.
- **The spec run:** the three examples pass, and `sharp/bin/spec --completes` passes on `Completes: §11`.

Every new and changed engine test runs under `sharp/bin/test --opcache --repeat --passes`, preloading included, and the statics and entry tests in a loop of 50 with `USE_ZEND_ALLOC=0 MallocScribble=1`. A plain PHP project's analysis is compared by running master's `mago` and this branch's over the same plain PHP tree, `illuminate/container` and its dependencies, and diffing the reports.

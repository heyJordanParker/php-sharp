# Slice: the rest of section 11

Spec section 11 and 11.1, every rule line, with the generic statics that properties (#132) left refused. #102 built generics R1 to R5, and `11-generics.md` is its note. This note holds the design team-lead approved, each rule's state on master `55769621b5`, and what this Slice built for it.

## The answer first

Most of section 11 held on master `55769621b5`, in the checker, the bridge and the engine. Every probe below ran on it. What did not hold fell in five groups, and this Slice builds each:

- **The three listed examples failed for their setup, not for a rule.** `generic-method-inference` collided with the harness folder `app/Cache/`, `type-parameter-bounds` needed a generic `Query<T>` the harness lacked, and `raw-generic-objects` ran `count`'s elided body after the entry check accepted the page. Decisions 1 to 3 fix the setups, and all three pass.
- **Generic statics were refused in two forms.** A static with accessors and a static whose initial value runs as code, in a generic class, were "not supported yet". Both are built: "Generic statics" below.
- **A value of a type parameter whose bound has type arguments entered from plain PHP unchecked.** `boxedId<TBox : Loose<T>>` on a `Bounds<Order>` took a raw `Loose` that held `1`. It is now checked against its bound where it enters: "A type parameter's bound at entry" below.
- **A default body whose bound names its interface's type parameter was refused falsely.** `class OrderShelf : Shelved<Order>` got "`OrderShelf.kind<TBox>` must keep the bound `Loose<Order>` of `Shelved.kind<TBox>`". It now compiles, and the body reads the type argument the class gives: "A default body's bound" below.
- **The declared type did not reach a generic call through a `match` arm or a lambda's result.** It now does, and through an outer generic call's parameter (question 1).

Building the statics found one more gap of the same kind. A generic class's property initial value and accessor bodies could not name the class's type parameter in a type: `Holder<T>.total` in an accessor was "Cannot find type `T`." It is fixed: "Generic statics" below.

## The three questions, settled

team-lead settled each from its spec line, and each spec line now states the rule.

1. **Line 750, a generic call inside a generic call: it compiles.** `List<Order> f = this.keep(this.get("kk"));` fixes `keep`'s `TValue` from the declared type, and `get`'s result goes into that parameter, so its `T` is `List<Order>` too, as Rust, Swift, Kotlin and TypeScript infer nested calls.
2. **Line 809, a bound that is a bare type parameter: allowed.** `public void add<TSub : T>(TSub item)` compiles, as C#'s `where TSub : T`, Java, Kotlin and TypeScript's `<U extends T>` do. A type parameter is a bound alone, never joined with `&`, and a bound that leads back to its own type parameter is an error.
3. **Lines 749 and 751, a plain PHP `@template` class without type arguments: a compile error.** `Shelf shelf` is "`Shelf` needs its type argument.", with the help "Write a type for its type parameter: `Shelf<TItem>`." A type parameter with a default needs none. A type test reads no type arguments of a plain PHP class, so `value is Shelf` tests the class alone, and `Class<Shelf>` names the class itself.

## Decisions team-lead approved

1. **`generic-method-inference` declares `Cache` in a namespace of its own.** The example's `App.Cache` class met the harness folder `app/Cache/`, which section 32's `module-services` imports as `App.Cache.RedisStore`. The marker's hidden setup gains `namespace App.Inference;` and `import App.Order;`. No rendered line changes.
2. **The harness `Query` becomes `Query<T>`.** `type-parameter-bounds` writes `Query<TItem>`. Section 6.3's `property-references` creates one, so its hidden setup becomes `Query<Order> query = new Query<Order>();`.
3. **`raw-generic-objects`'s `accepted:` line passes on an `App\Elided` throw.** `sharp/bin/spec` runs a plain PHP line annotated `accepted:` or `compiles` inside a `try`, and an `App\Elided` it throws holds the claim. It landed first, in its own pull request, #140.
4. **Generic statics run through one hidden static method per generic class.** See "Generic statics" below. It adds the hidden method name `#entry` and the third parameter `?bool &$created = null` to `\Sharp\Internal\statics`, which `ext/sharp/sharp.stub.php` declares.
5. **The entry check reads a method's bounds for a value of its type parameter.** A plain PHP caller that passes a raw object into such a method throws `TypeError` where it passed before. That is line 766's rule.
6. **The names:** `#entry` for the hidden method, from the slate `#entry`, `#statics`, `#own` and `#typeStatics`, and `$created` for the parameter, from `$created`, `$first` and `$fresh`.
7. **Order:** this PR lands after collections (#135), hosts (#136) and built-in types. It merged master once, after #135 and #140 landed.

## How the states were observed

`mago` was built in release from `55769621b5` with `CARGO_PROFILE_RELEASE_LTO=off` into `sharp/build/darwin-arm64/mago/release/mago`, and the engine with `sharp/bin/build`, a debug build, from the same commit.

```sh
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

On master it printed "Checked 200 examples, ignored 1, and 32 fail", the three section 11 entries among them. On this branch, merged with master `24bd9909cf`, it prints "Checked 202 examples, ignored 1, and 22 fail", none of them in section 11, and each of the 22 has its entry in `sharp/baseline/spec/`.

Each probe is one file in a copy of the project `sharp/bin/spec` writes for `raw-generic-objects`, with its classes in `app/Probe/`. A plain PHP `entry.php` calls them on the engine. `illuminate/container` 12 was installed beside it with Composer for the container rows.

The states are **works**, **refused** (with the message), **wrong** (it compiles or runs differently from the spec), and **fails** for a listed example. Each row gives the state on master, and what this Slice built where it was not "works".

# Every rule of section 11, its state and its mechanism

Paths under `crates/` are in `sharp/mago/crates/`. "Semantics" is `crates/semantics/src/internal/checker/sharp.rs`. "The bridge" is `crates/sharp-bridge/src/lower.rs`.

## Reified generics

| Line | Rule | On master | Built |
|---|---|---|---|
| 724, 726 | `page is PaginatedList<Order>` is checked while the code runs | Works: a raw page gives `false` (`Run.isOrders`) | |
| 727 | A wrong value throws `TypeError` where it enters from plain PHP | Works: `Run.sum([1, 'two'])` throws "must be of type List<int>, array given: [1] expected int, got string 'two'", and `['a' => 1]` throws for `List<int>` | |
| 727 | An `int` where PHP# expects a `float` becomes a `float` | Works: `Run.half(3)` gives `1.5`. A string `'3'` gives `1.5` too, under the plain caller's weak mode, as line 823 checks every parameter under the caller's `strict_types` | |

## Each type argument's own statics

| Line | Rule | On master | Built |
|---|---|---|---|
| 729, 731 to 742 | `generic-statics`: `Counter<Order>.count` and `Counter<User>.count` are two values, and `List<T> seen` compiles | Works: the example passes | |
| 729 | A static may use `T`, with an initial value that runs as code | Refused: "A static member of a generic class with an initial value that runs as code is not supported yet in PHP#." | Each type argument runs it once, on its first use, in `#entry`. Engine: `type_arguments_call_statics.phpt`, `type_arguments_preload.phpt` |
| 729 | A static may use `T`, with accessors: a computed static, `get` and `set` bodies, observers, `static lazy` | Refused: "A static property with accessors in a generic class is not supported yet in PHP#." | `name#get` and `name#set` take the class's type arguments, and each type argument has its own storage and `static lazy` value. Engine: `type_arguments_call_statics.phpt` |
| 744 | A bare `Counter.count` is a compile error | Works: "`Tally` needs its type argument." Inside the class, `Inside<T>.count += 1` compiles | |

## Writing type arguments

| Line | Rule | On master | Built |
|---|---|---|---|
| 748 | `new` names them | Works: `new PaginatedList(rows)` is "`PaginatedList` needs its type argument.", also where a declared type could fix it | |
| 748 | `new (pages)()` takes a class value's | Works: section 25's examples pass | |
| 748 | `new Self(…)` names none | Works: `new Self<T>()` is "`Self` already carries its class's type parameters." | |
| 749 | A type names them, `Class<PaginatedList>` aside | Works for a PHP# class: `PaginatedList page` and `class Mine : PaginatedList` are errors, `Class<PaginatedList>` compiles | |
| 750 | A call's arguments fix them | Works: `inferred-type-arguments` passes | |
| 750 | The declared type fixes them: a typed local, a field write, `return`, an expression body, an argument to a typed parameter, `Any`, `List<Order>?`, a reassignment, `?:` and `??` | Works, and the engine carries the fixed type | |
| 750 | ... through a `match` arm: `List<Order> d = match (flag) { true => this.get("m"), default => [] };` | Wrong: "Nothing fixes the type argument `T` of the call to `InferMore.get`." | `takes_declared_type` takes a `match`, and `MatchAnalyzer` gives each arm the declared type, as `?:` gives each side |
| 750 | ... into a lambda's declared result: `Function<List<Order>()> c = () => this.get("l");` | Wrong: the same error, and "Invalid assignment to `c`" | An arrow function and a closure give the result type of the function type they go into to their body expression and to each `return` (`closure_result_type`) |
| 750 | ... through a generic call's parameter: `this.keep(this.get("kk"))` | Refused | Question 1: the outer call reads its declared type before its arguments, and gives each argument the parameter type that type fixed (`declared_type_arguments`) |
| 750 | Nothing fixes them: `const c = this.get("c");` | Works: an error, with the help "Write the type argument on the call, or give the result a declared type." | |
| 751 | An argument fixes a type PHP# writes: `Box<int> b = Factory.of(5);`, and `Collection.make([new Order()])` into `Collection<int, Order>` | Wrong: "`Box<int>` cannot be used as `Box<int>`.": the type argument is the literal `5`, or a list's `non-negative-int` key, which PHP# shows as `int` | Inference's lower bound in PHP# is `written_type_argument` of the value: each scalar general, each enum case its enum, each array its `List`, `Map` or `Set`. The engine's recorded type arguments take the same function |
| 751 | A `@template` class takes type arguments, and the checker checks them | Works: `Shelf<Order, int>` "Too many type arguments for `Shelf`: expected 1, but found 2.", `Shelf<string>` "Type argument `string` does not satisfy `Shelf`'s `TItem`." | |
| 751 | Its object carries none while the code runs | Works: `getTypeArguments()` of a PHP# `new Shelf<Order>()` is `[]` | |
| 751 | `x is Shelf<Order>` is a compile error that says why | Works: "`Shelf<Order>` can't be tested: `Shelf` is a plain PHP class, so its objects carry no type arguments while the code runs.", for `as` too | |
| 749, 751 | A bare `@template` class in a PHP# type, or after `new` | Compiles, and `new Shelf()` infers from the constructor's arguments | Question 3: "`Shelf` needs its type argument.", with the type parameters to write. `new` and a type call one check, `report_missing_type_arguments`, and only a type test (`HintPlace::TypeTest`) and a static member leave a plain PHP class alone. Swept: `Zend/tests/sharp/IndexRead.sharp` writes `SplFixedArray<int>` and `new SplFixedArray<int>(3)` |
| 753 to 764 | `generic-method-inference` | Fails for its setup | Decision 1. Passes, and its rows include `cache.keep(cache.get("orders"))` |

## Generic objects that plain PHP creates

| Line | Rule | On master | Built |
|---|---|---|---|
| 766 | A raw object has its bounds as its type arguments | Works: `new App\PaginatedList([])` reports `['App.DatabaseEntity']`, and an unbounded `new Loose(5)` reports `['Any?']` | |
| 766 | ... created by Laravel's container | Works: `Container::make(PaginatedList::class)` reports `['App.DatabaseEntity']`, and autowiring `Needs(PaginatedList<Order> page)` throws "App\Probe\Needs::__construct(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given" | |
| 766 | A parameter that needs `PaginatedList<Order>` throws | Works: `Container::call([$report, 'show'])` throws | |
| 766 | A generic method takes any page, and its `TItem` is the bound | Works: `count` gives `App\DatabaseEntity` for a raw page | |
| 766 | Checked where it enters: a value of a type parameter whose bound has type arguments | Wrong: `boxedId<TBox : Loose<T>>` and `fixedId<TBox : Loose<Order>>` took a raw `Loose` holding `1` from plain PHP | A value whose type is a method's own type parameter is checked against its bound. Engine: `type_arguments_entry_unresolved.phpt` |
| 766 | PHP# never creates a raw object | Works: the `new` rows above | |
| 768 to 786 | `raw-generic-objects` | Fails for its setup | Decision 3. Passes |

## Inferred type arguments at runtime

| Line | Rule | On master | Built |
|---|---|---|---|
| 788 to 802 | `inferred-type-arguments` | Works: the example passes | |
| 804 | A collection's elements are checked where it enters | Works: the 727 rows | |

## Declaring type parameters

| Line | Rule | On master | Built |
|---|---|---|---|
| 808 | Names start with `T` | Works: `<Item>` is "A type parameter's name is `T`, or `T` and a capital letter, as in `TItem`." | |
| 809 | The bound is written inline | Works | |
| 809 | A bound that names the class's type parameter inside a type, `<TBox : Loose<T>>` | Works in a class, and in a class's own implementation of an interface method. Wrong for a body the class takes from an interface default body: `OrderShelf : Shelved<Order>` got "`OrderShelf.kind<TBox>` must keep the bound `Loose<Order>` of `Shelved.kind<TBox>`." | "A default body's bound" below. Engine: `inheritance_defaults.phpt` |
| 809 | A bare type parameter as a bound, `<TSub : T>` | Refused | Question 2: allowed alone (`is_type_parameter_bound`), refused beside `&`, and refused where it leads back to itself (`bounds_itself`). A value of `TSub` goes where a `T` goes. Engine: `type_arguments_entry_unresolved.phpt` (`Rack.add<TSub : TItem>`) |
| 810 | Several bounds use `&` | Works: `Both<TItem : DatabaseEntity & Shareable>` | |
| 811 | `Object` bounds a type parameter to any object | Works: `keep(5)` is "`5` does not satisfy the bound `Object` of type parameter `TValue`." | |
| 811 | "It is a bound alone" | Works, read as: `Object` joins no other bound. `<TValue : Object & Shareable>` is "Type `Object` cannot be part of an intersection." | |
| 812 | A type parameter with no bound may hold `null` | Works: `Holder<Order?>` and `Holder<int?>` compile, and a raw `Loose` has `Any?` | |
| 813 | A generic class can't implement `Serializable`, and the error points to `__serialize` and `__unserialize` | Works | |
| 814 | A class value with type arguments keys no `Map` | Works | |
| 816 to 821 | `type-parameter-bounds` | Fails for its setup | Decision 2. Passes |

## Implementing a generic type, and `Self`

| Line | Rule | On master | Built |
|---|---|---|---|
| 823 to 847 | `generic-implementation` | Works: the example passes | |
| 849 to 860 | `self-on-type-parameters` | Works: the example passes | |

## Section 11.1: variance

| Line | Rule | On master | Built |
|---|---|---|---|
| 864 | Classes and interfaces declare `out` and `in` | Works | |
| 866 | `out`: `Feed<Order>` is a `Feed<DatabaseEntity>` | Works in the checker, and at the engine's entry | |
| 867 | `in`: `Validator<DatabaseEntity>` is a `Validator<Order>` | Works in the checker and at entry | |
| 868 | No marker is invariant | Works: the checker refuses, and the engine throws "must be of type App.Probe.Same<App.DatabaseEntity>, App.Probe.Same<App.Order> given" | |
| 870 | The checker enforces the marker on every member, on that member's line | Works | |
| 871 | A private member may break it, and is reachable only through `this` | Works | |
| 873 to 878 | The error names the marker to add | Works | |
| 880 | No variance at the point of use | Works: `Members<out Order>` is a parse error | |

# Mechanisms

## Generic statics

#102 keeps each type argument's statics in one hidden static of the class, `"\0<sharp>\0statics"`, an array keyed by the type argument text. Its `""` entry holds the constant initial values, which `\Sharp\Internal\statics` copies when a key is new.

A static lives as long as the app, and its initial value runs once, when the app first uses `Counter<Order>`'s statics. The store is a PHP static, so it already lives as long as the app does: one request under php-fpm, the worker under a long-running host, and every request again for a preloaded class.

**One hidden static method per generic class with static fields returns the entry of its type arguments.** It takes the class's type arguments as its own, as #102's part 1 gives every static method of a generic class, so its body spells the key and runs each initial value with `T` known:

```php
public static function &'#entry'(/* type args: the class's bounds */)
{
    ${'entry#1'} = &\Sharp\Internal\statics(\App\Counter::class, /* the text of #0 */, ${'created#2'});
    if (${'created#2'}) {
        ${'entry#1'}['made'] = \App\Counter::fresh(/* type args #0 */);    // each initial value that runs as code
    }
    return ${'entry#1'};
}
```

- **A class whose initial values are all constant** returns the call's entry: `return \Sharp\Internal\statics(\App\Counter::class, /* the text of #0 */);`.
- **Every access calls it,** `\App\Counter::{'#entry'}(/* type args App.Order */)['count']`, so `Lowering::static_property` keeps one path. A write through a class value and a subclass's header reach it the same way, since they already give the access its type arguments.
- **`\Sharp\Internal\statics` takes `?bool &$created = null`,** set when this call adds the key. The store keeps the one record of which keys exist.
- **A throw in an initial value leaves the entry's other fields as they are** and runs nothing again, as a class's `#statics` does after `zend_class_init_sharp_statics` returns `FAILURE`.
- **A static with accessors** keeps decision 3's hidden `name#get` and `name#set`. In a generic class they take the class's type arguments as their own, the access passes them, and `field` is `\App\Counter::{'#entry'}(/* type args #0 */)['name']`.
- **`static lazy`** keeps its value with `\Sharp\Internal\keep`, under a key that adds the type arguments: `'App\Counter<' . /* the text of #0 */ . '>::once'`. A body that reads `T` is a closure that captures the type arguments.
- **`#entry` declares no return type,** since its accesses are its only callers. `zend_compile_params` returned early for a method with neither parameters nor a return type, before it emitted `ZEND_SHARP_RECV_TYPE_ARGS`, so such a method read type arguments its frame never got. `#entry` is the first such method, and the receive is emitted on that path too.
- **A property's initial value and accessor bodies name the class's type parameters,** as a method's body does. The analyzer analyzes a PHP# class's properties with the class's type parameters in scope (`analyze_class_like`), where it had none.
- **Plain PHP** still reaches the store only through `\Sharp\Internal\statics` and `#entry`, whose name it can't write as a member access.

### Cost of the frame

Instructions retired per access, from `/usr/bin/time -l`, release builds of both heads configured `--disable-all --disable-cgi --disable-phpdbg --without-pear`, 10,000,000 passes, median of 3 runs, each head measured the same hour. Per access is the loop's instructions less the same loop without the static, divided by the passes. Master is `24bd9909cf` with its own `mago`.

```text
                     read: master  branch  ratio    write: master  branch  ratio
interpreter                 465.3   864.4   1.86            460.9   862.3   1.87
opcache                     519.1   926.2   1.78            517.2   924.2   1.79
tracing JIT                 452.0   832.2   1.84            502.0   878.2   1.75
```

The first shape, with an `array` return type and `${'entry#1'}` bound on every call, read at 942.5 in the interpreter, 2.03 times master's. The return type's check and the reference bind were most of the difference.

## A type parameter's bound at entry

A call from plain PHP gives a method's own type parameters no type arguments, so `#0` matched any value, and the PHP type of `TBox box` is the bound's class, `Loose`, which takes any `Loose`.

**A parameter whose type is a method's own type parameter is checked against its bound.** The bridge writes the check text of such a parameter as its bound, in `Types::checked_text`, which already did so for a bound that names a trait. `TBox box` then refuses a raw `Loose<Any?>` where the bound is `Loose<Order>`. A type parameter nested in a type still matches any type argument, so `PaginatedList<TItem>` takes a `PaginatedList<Order>` from plain PHP and line 776 holds. The approved design put this in the engine's unresolved `#i`. The bridge's check text reaches the same values with the precedent the trait bound set, and leaves the engine unchanged.

## A default body's bound

A method a class takes from an interface's `Defaults` trait was compared with the interface's own, unsubstituted bound, `Query<T>`, so it could never match. The codex's `reparent_types` already gives a default body's parameters the header's type arguments. It now gives its own type parameters' bounds them too, and `inferred_type_replacer` replaces inside a reference the populator has not yet resolved, as `Query<T>` is while a file is scanned. The approved design put this in `report_signature_compatibility_issue`. The codex fix gives the method the right bound once, for every reader.

The engine then runs that body. `ZEND_SHARP_RECV_TYPE_ARGS` spelled the bounds in `EX(func)->common.scope`, which for a method a class took from a trait is the class, so `$0` read the class's first type parameter. It now spells them in `sharp_type_scope(scope, func)`, inheritance decision 8's map from a trait's code to the interface its header names. `Ranked<TRank, TName>` is the row that tells the two apart.

# Existing work, and what the Slice takes from it

- **#102, `feat/slice-generics` (merged), with `11-generics.md`.** R1 to R5. Every "works" row above is its work. The Slice reuses the store, part 1, the entry check and `type_arguments_call_statics.phpt`.
- **#132, properties (merged), with `06-properties.md`.** Rows 127, 137 and 138 built static code initial values, static accessors and `static lazy` for a class that is not generic. The Slice builds on `Lowering::static_accessors`, `static_lazy_value` and the `keep` store, and updates those rows.
- **#133, inheritance (merged), with `22-inheritance.md`.** Decision 8 maps a trait's code to its interface. The Slice extends it to a default body's own type parameters.
- **#138 and #140.** #138 makes a body that ends in `…` throw `Elided`. #140 makes an `accepted:` line hold on that throw, decision 3.
- **#135 collections (merged).** Merged into this branch once, with no conflict but the generated `sharp_arginfo.h`.

# Files

```text
docs/
├── architecture/slices/
│   ├── 11-generics-rest.md          <- this note
│   ├── 11-generics.md               <- "Left as they are": a static's code initial value is built
│   └── 06-properties.md             <- rows 127, 137, 138: a generic class's statics are built
└── spec.md                          <- lines 749, 750, 751 and 809 state questions 1 to 3; the hidden
                                        setups of decisions 1 and 2; plain-php-generic-types
sharp/
├── baseline/spec/§11/               <- the three entries go
├── harness/app/Query.sharp          <- Query<T>
└── mago/crates/
    ├── semantics/src/internal/checker/sharp.rs
    │                                <- report_generic_static goes; a bound may be a type parameter,
    │                                   alone, and never leads back to itself
    ├── codex/src/
    │   ├── scanner/mod.rs           <- reparent_types gives a default body's bounds the header's types
    │   ├── ttype/template/inferred_type_replacer.rs
    │   │                            <- replaces inside an unresolved reference
    │   └── ttype/comparator/atomic_comparator.rs
    │                                <- a type parameter holds one whose bound names it
    ├── analyzer/src/
    │   ├── statement/function_like/mod.rs   <- takes_declared_type takes a match; a lambda's result
    │   ├── expression/match.rs, mod.rs      <- each arm gets the declared type
    │   ├── statement/return.rs              <- a lambda's return gets its result type
    │   ├── invocation/analyzer.rs           <- an outer call's declared type reaches its arguments
    │   ├── artifacts.rs                     <- the result type a lambda's function type gives it
    │   ├── expression/instantiation.rs      <- question 3's error and help
    │   ├── statement/mod.rs, try.rs, invocation/template_result.rs
    │   │                                    <- a type test or catch names a plain PHP class alone
    │   └── statement/class_like/mod.rs      <- a property names its class's type parameters
    └── sharp-bridge/src/
        ├── lower.rs                 <- #entry; static_property calls it; static_accessors and
        │                               static_lazy_value of a generic class; statics_store keeps
        │                               constant defaults only
        └── lower/types.rs           <- a method type parameter's bound at entry
ext/sharp/
├── sharp.c                          <- statics' $created; sharp_type_scope is shared
├── php_sharp.h                      <- declares sharp_type_scope
├── sharp.stub.php                   <- statics(string $class, string $key, ?bool &$created = null)
└── sharp_arginfo.h                  <- regenerated from the stub
Zend/
├── zend_compile.c                   <- a method without parameters or a return type gets its receive
├── zend_vm_def.h                    <- ZEND_SHARP_RECV_TYPE_ARGS spells bounds in its function's scope
└── zend_vm_execute.h                <- regenerated
```

# The tests

One test per rule, forms as rows, each seen failing before its code:

- **`crates/semantics/tests/sharp.rs`:** `a_static_member_takes_any_initial_value_and_is_get_only_or_settable` and `a_static_property_takes_accessors` lose their generic refusals. `a_bound_that_is_not_object_a_class_an_interface_a_generic_class_type_or_a_type_parameter_is_an_error` and `a_type_parameter_whose_bound_leads_back_to_it_is_an_error` hold question 2's forms.
- **`crates/analyzer/tests/sharp.rs`:** `the_declared_type_a_call_goes_into_fixes_its_type_arguments`, with rows for `match`, a lambda's expression body, a closure's `return` and question 1's nested call. `a_type_names_the_type_arguments_of_a_plain_php_generic_class` with its PHP twin, `a_bound_that_names_another_type_parameter_holds_values_of_that_type_parameter`, `a_default_body_keeps_a_bound_that_names_the_interfaces_type_parameter`, and `a_property_names_its_class_type_parameters_in_its_initial_value_and_accessors`.
- **`crates/sharp-bridge/tests/lower.rs`:** `a_generic_class_runs_its_statics_initial_values_and_accessors_with_its_type_arguments` (`#entry` with a code initial value and with constant ones, `name#get`, `static lazy`, the access), `a_generic_class_keeps_the_initial_values_of_its_static_fields_in_one_hidden_static`, and `a_parameter_of_a_methods_own_type_parameter_checks_its_bound_from_plain_php`.
- **`Zend/tests/sharp/type_arguments_call_statics.phpt`,** each row twice for the tracing JIT: an initial value that runs as code runs once per type argument, at its first use, as the log shows; through a class value and a subclass's header; a plain PHP `Tally::bump()` runs the bounds' entry; a throw runs nothing again; accessors, observers, a get-only static and `static lazy` keep one value per type argument.
- **`Zend/tests/sharp/type_arguments_preload.phpt`:** a preloaded generic class's initial value runs once per type argument.
- **`Zend/tests/sharp/type_arguments_entry_unresolved.phpt`:** `Pages.boxed<TBox : Box<T>>` takes a `Box<Order>` and throws for a raw `Box` and a `Box<Customer>`, nullable too, and `Rack<Order>.add<TSub : TItem>` throws for a `Customer`.
- **`Zend/tests/sharp/inheritance_defaults.phpt`:** a default body's bound reads the type argument the class gives.
- **The spec run:** the three examples pass, and `sharp/bin/spec --completes` passes on `Completes: §11`.

Every engine test runs without opcache, with the file cache primed and used, twice per process with shared memory, with each of the 16 optimizer passes alone, and under the tracing and function JITs. The statics, preload, entry, defaults and class value tests run 50 times each with `USE_ZEND_ALLOC=0 MallocScribble=1`, without opcache and with it. A plain PHP project's analysis is unchanged: master's `mago` and this branch's report the same 1,417 issues over `illuminate/container` and its dependencies.

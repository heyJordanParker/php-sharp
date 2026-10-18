# Slice 14: Functions as values

Spec section 14: function types, lambdas, methods as values and the null operators. This note holds every rule line of the section, its state on master `c4adf74516`, its mechanism and the code it reuses. Hosts (#136) and effect classes (#151) land in their own pull requests, and this Slice builds on them without building them again: a method takes on the effects of each function it is given, a function parameter whose type states no effects never escapes, and an effect object is an ordinary type.

## The answer first

All eight examples of section 14 pass on master, and most rule lines hold, engine included. Behind the examples, seven gaps remain:

1. **A function value is called only through a local, a parameter or a field.** `steps[0](1)`, `this.handlers["double"](1)`, `this.make()(1)`, `(this.step)(1)`, `(flag ? a : b)(1)`, `((int a) => a)(1)` and `CallForms.factory()(1)` are each "This expression is not supported yet in PHP#." The same expressions with `?.(…)` compile and run. Functions stored in collections, which the story names, can't be called without a local first (Q1).
2. **A static field holding a function is not called like a method.** `StaticFunctionField.shared(5)` is "Method `StaticFunctionField.shared` does not exist." Line 1577 says "a field or property".
3. **A field named like an inherited method compiles, and the call runs the method.** `ChildWithField` declares a field `discount` below its parent's method `discount`, and `this.discount(5)` runs the parent's method. Line 1577 promises that `this.discount(…)` always has one meaning (Q4).
4. **A lambda parameter nothing types is silently `Any?`.** `const identity = x => x;` and `Any f = x => x;` compile. Line 1523 makes a parameter's type optional only when the checker can infer it.
5. **A method of a collection, of a built-in type or of an extension is no function value.** `numbers.map(allowed.contains)` is "Attempting to access a property on a non-object type (`List<int>`).", and `names.map(name.trim)` is "`trim` is a method of `string`, not a computed property: call it as `trim(…)`." Line 1565 says a method named without parentheses is a function value (Q5).
6. **A lambda can't capture a loop variable that changes.** It is "This capture of a loop variable that changes is not supported yet in PHP#.", and the help tells the user to copy it into a `const` because "the engine cannot give a lambda" one. That is code written around PHP's closures, which Jordan's rule forbids.
7. **Two PHP forms get "not supported yet" instead of section 14's words.** PHP's first-class callable `Item.doubled(...)` is "This expression is not supported yet in PHP#.", and `Function<int(void)>` is "This type is not supported yet in PHP#."

Everything else holds: function types with their effects, every lambda form, capture of the variable itself, changes through a captured collection, method values of PHP# and plain PHP classes found when the code runs, a plain PHP property winning over its method, a field or property called like a method and read before its arguments, all five null operators with their short-circuits, the four leave forms and their `return` rules, and every needless null check and needless `?` the spec lists.

The Slice is one pull request across `sharp/mago/`, the bridge and engine tests. The engine and the standard library don't change: PHP already calls any expression, a static property's closure and a closure that binds its receiver, and the bridge writes each. When it lands, section 14 still has no entry in `sharp/baseline/spec/`, and the pull request writes `Completes: §14`.

## Questions for team-lead

Each decides what a user writes or what a running program shows. Each has a proposal, which the design below assumes.

### Q1. Calling any expression that gives a function

Spec lines 1512 and 1594: a function type is a type like any other, and `f?.(x)` "calls `f` only when it is not null". No line shows a plain call of an expression that is not a name.

- **Proposed:** any expression whose type is a function type is called by writing its arguments after it: `steps[0](1)`, `this.make()(1)`, `(flag ? a : b)(1)`. C#, TypeScript, Kotlin and Swift all allow it, and `?.(…)` already takes any expression on master. Section 14.1 gains one line and one example, `function-calls`, with a list element, a call result and a conditional.
- Keep the restriction. A stored function is copied into a local before it is called, and the refusal names that instead of "not supported yet".

### Q2. PHP's `Closure` as a type

Spec line 1512: "One name covers every case." `callable` fails as a typo does. A bare `Closure` is "Cannot find type `Closure`.", but after `import Closure;` it compiles as a type, reads as `Function<Any?(Any?)>`, refuses `(int a) => a`, and reaches `Closure.fromCallable` (`ImportedClosure.sharp:7`, `11`, `15`).

- **Proposed:** `Closure` names no class in a `.sharp` file, so it and `import Closure;` get what a name nothing declares gets. A `.sharp` file can still call a plain PHP method that returns a `Closure`, and gets the function type its docblock gives.
- Allow it as any imported plain PHP class. Users then choose between two names for one thing, one of them PHP's.

### Q3. How closely a lambda fits its function type

Spec line 1512: a function type is written "in the same order as a method declaration". Two fits compile today (`FunctionTypes.sharp:8`, `10`):

```csharp
Function<int(int, int)> tooFew = (int a) => a;             // compiles: the lambda takes one parameter of two
Function<void(int)> voidGivenValue = (int a) => a + 1;     // compiles: a value nobody reads
```

- **Proposed:** both are compile errors. A lambda takes exactly the function type's parameters, as in C#, Kotlin and Swift: "`Function<int(int, int)>` takes 2 parameters, and this lambda takes 1." A `void` lambda's expression body must be a call or an assignment, as C#'s CS0201 requires and as PHP# already refuses a statement with no effect.
- **Built, with the review's additions:** the count message names a method value as written, "`Function<int(int, int)>` takes 2 parameters, and `Report.one` takes 1.", in a field, a local, an argument, a return and a property write. A `void` lambda's block body returns no value, as C#'s CS8030 refuses, while a lambda inside it returns for itself: "This lambda gives a value, and `Function<void(int)>` returns nothing: remove the value, or give the function type a return type." A conditional of two calls is "A conditional gives a value, and `Function<void(int)>` returns nothing: choose with `if` in a block body, as in `{ if (…) { … } else { … } }`." The result of a call that returns `void` is no value anywhere code uses it, as in C# and Java: "`this.add(1)` returns nothing, so it has no value to use: call it as a statement of its own."
- TypeScript's fit: fewer parameters pass, and a `void` type takes any body.

### Q4. How far "one name, one member" reaches

Spec line 1577: "A `.sharp` class cannot declare a method and a field or property with the same name, so `this.discount(…)` always has one meaning." The check reads one class body, so a member named like an inherited one compiles in both directions (`ChildWithField.sharp`, `ChildWithMethod.sharp`).

- **Proposed:** the rule covers every member the class inherits and can see, `public` and `protected`, from PHP# and plain PHP ancestors alike. A field or property named like an inherited method, and a method named like an inherited field or property, are errors: "`ChildWithField` declares a field `discount`, and inherits the method `discount` from `ParentWithMethod`: `this.discount(…)` would call the method. Rename one of them."
- PHP# ancestors only. A PHP# class below a plain PHP class keeps PHP's meaning: the method wins a call and the property wins a read.

### Q5. What a method value of a collection sees

Spec line 1565, with decision 5: a `List`, `Map` or `Set` is a value. Once Gap 5 is built, a method value of a collection binds the collection:

```csharp
const has = allowed.contains;
allowed.add(5);
has(5);                // ?
```

- **Proposed:** `false`. The method value holds the collection as it was when it was named, as C# copies a struct into a delegate made from its method, and as a `const` copy of the list would. An object's method value keeps binding the object, as it does on master.
- `true`: the value reads the variable each call, as a lambda `x => allowed.contains(x)` does. Then a method value of a collection is a lambda, and of an object it is not.

### Q6. A plain PHP function as a value

Spec line 1565 names methods only. `names.map(global.strtoupper)` is "`global.strtoupper` is a plain PHP function, not a constant: call it, as in `global.strtoupper(…)`." (`MethodValueKinds.sharp:11`).

- **Proposed:** a plain PHP function named without parentheses is a function value, as a plain PHP static method is, so `names.map(global.strtoupper)` compiles and runs PHP's `strtoupper(...)`. The value has the effects its `extern` lists.
- Keep the refusal. Users write `n => global.strtoupper(n)`.

### Q7. Spec line 1550 names a method no collection has

"A lambda that calls `add`, `set` or `remove` on a captured variable changes the variable itself." No `List`, `Map` or `Set` has a `set` method (`crates/prelude/assets/extensions/sharp.php`). An index write inside a lambda does change the captured variable: `seen[0] = 9` gives `9`, and `prices["tea"] = 4` gives a count of `1`.

- **Proposed:** line 1550 reads "A lambda that changes a captured collection, with `add`, `insert`, `remove`, `clear` or an index write, changes the variable itself."

### Q8. Spec line 1616 and overridable methods

"A nullable return type on a method that never returns null" is a compile error. Commit `40924ab804` exempts abstract, interface and overridable methods, "so a private or final method is checked", because an override may need the `?`. `NullableOverride.find`, an `override` that returns `new Item()` with a return type `Item?`, compiles.

- **Proposed:** line 1616 reads "a nullable return type on a method no subclass can override that never returns null". The code stays.
- Check overridable methods too. An override that keeps its parent's `?` is then an error, and C# lets it narrow the return type only since C# 9.

### Q9. PHP's `&` on a lambda parameter

No spec line. `(&value) => value` is "A by-reference parameter is not supported yet in PHP#." A method's `int &value` is a parse error.

- **Answered (Jordan, 10-18):** `&` is no PHP# syntax, so a by-reference parameter fails as a typo does.

### Q10. A method value of a method that changes the collection

Found while building Q5. Q5's copy means `numbers.forEach(seen.add)` would add to a copy of `seen` and leave `seen` empty, with no error.

- **Built:** refused, as Swift refuses the value of a `mutating` method and Rust has no value of a method that takes `&mut self` without a closure: "`seen.add` changes the collection, and a function value holds a copy of it: write a lambda that calls it, as in `value => seen.add(value)`." A refusal can be lifted later without breaking code that compiles.
- Allow it, as C# allows a delegate of a struct's method, which changes the boxed copy. The value then changes only its copy. TypeScript reads `seen.push` too, and its call throws, because the value lost its `this`.

## Existing work, and how the Slice reuses it

- **The function type and lambdas** came with mago-sharp's syntax work: `Function<R(P)>` in `crates/syntax`, the bare arrow, `check_lambda_captures`, and the bridge's arrow function or closure with a `use` list. Reused unchanged but for the loop capture (Gap 6).
- **Effects in function types,** decision 71: `Function<Charge(Cart) uses Http>`. Reused unchanged.
- **#136, Hosts, open,** observed at `40467d2382`: decision 79 replaces `uses f` with taking on each argument's effects, and a function parameter whose type states no effects can't be stored, returned or captured by an escaping lambda. `effects/summary.rs` `method_value` records a method value's effects. Gap 5's method values reach the effects through it after it lands.
- **A property called like a method:** `resolve_called_property` in `crates/analyzer/src/resolver/method.rs` records `CallTarget::Property`, `is_property_call` in the bridge lowers `($x->priceOf)($line)`, and `property_call` in `effects/summary.rs` records the property's effects. Gap 2 extends all three to a static property.
- **Method values:** `resolve_method_value` in `crates/analyzer/src/resolver/property.rs`, and the bridge's `method_value`, which writes PHP's first-class callable or, for a generic method, `(function ($receiver) { return function (...$arguments) use ($receiver) { … }; })($object)` with `returning_closure`. Gap 5 reuses that closure for a receiver PHP can't bind.
- **A fresh `let` in each loop pass:** the bridge unsets a `let` that a lambda captures by reference before its assignment (`a_let_in_a_loop_captured_by_reference_is_unset_before_its_assignment`). Gap 6 reuses it for a loop variable.
- **The null operators and leave forms:** decisions 19, 21, 23 and 46, Slice 21's `ZEND_SHARP_LEAVE`, and commit `40924ab804`, which made every needless null check and `?` an error. Reused unchanged.
- **A generic method value nothing types is refused** (`a_generic_method_value_without_a_function_type_is_refused`), as C#'s CS0411. Gap 4 is the same rule for a lambda's parameter.

## How the states were observed

Master is `c4adf74516`, in `/Users/jordan/Developer/sharp-worktrees/6/php-sharp`. The engine was built with `sharp/bin/build`, and a release `mago` with `CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --release --locked -j 2 --bin mago` into `sharp/build/darwin-arm64/mago/`.

`sharp/bin/spec` with that `mago` and engine: "Checked 202 examples, ignored 1, and 11 fail", none of them in section 14, each on `sharp/baseline/spec`.

Every rule was probed in one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-functions/ws`, whose `composer.json` maps `Demo\` to `app/`, and whose `vendor/heyjordanparker/php-sharp-composer/` holds master's `sharp/composer/`:

```sh
MAGO_REPORTING_FORMAT=emacs mago --no-version-check --colors never --threads 2 --workspace $P/ws compile
php $P/ws/<probe>.php
```

`app/Item.sharp` declares `refunded` and `price` properties, `priceIn(string)` and a static `doubled(int)`. `app/Plain.php` is a plain PHP class with a property and a method both named `label`, a `\Closure` property `onSave` and a static `shout`. `app/Remote.php` is a plain PHP class that `app/Stubs/Externs.sharp` declares `extern Remote uses Http;`. Each state below quotes the probe's file and line. A file with a parse error skips the checks after parsing, so each parse error has a file of its own. The engine states come from `captures.php`, `methods.php`, `fields.php`, `nulls.php`, `nullsafe.php`, `generic.php` and `short.php` in the same folder.

# Every rule of section 14

The states are **works**, **refused** (rejected, with its message), **silently accepted** (code the spec refuses compiles) and **missing** (code the spec accepts is refused).

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| T1 | 1512: a function type is `Function<ReturnType(ParameterTypes)>`, in a method declaration's order | works: a nullable result, a `readonly` parameter, a nullable function type, a function-typed field, local and return (`FunctionTypes.sharp:5`–`7`, `12`). A lambda with too many parameters, a wrong parameter type or a wrong result is refused (`FunctionTypeErrors.sharp:5`–`7`). A lambda with too few parameters, and a value body for `void`, compile (Q3) | Q3: `callable_comparator::is_contained_by` in `crates/codex` lets a function with fewer parameters fit unless it is built in, and under `sharp_rules` `TCallableSignature::takes_more_parameters_than` refuses one that takes fewer than a call passes, a parameter that is optional or variadic not counted, so a plain PHP `callable` takes any function; `can_argument_be_identical` uses it too, so an argument is `invalid-argument`, not "possibly". `report_value_for_void` refuses, as `invalid-return-statement`, an expression body that is no call, change, `new` or `throw` where every expected type returns `void` | `ComparisonResult::sharp_rules` |
| T2 | 1512: one name covers every case, `void` included | works: `Function<void(Item)>`. `callable` fails as a typo does (`PhpTypes.sharp:5`). `import Closure;` makes `Closure` a type (Q2). `Function<int(void)>` is "This type is not supported yet in PHP#." (`NotSupportedYet.sharp:5`) | Gap 7: "A function type takes no `void` parameter: write `Function<int()>` for a function that takes nothing." Q2's refusal beside `callable`'s | `check_hint` in semantics |
| T3 | 1512: effects go inside the brackets (section 29) | works: `Function<int() uses Http>` holds a lambda that calls `remote.fetch()` (`FunctionTypes.sharp:16`), and `Function<int()>` refuses it: "This lambda calls `Remote.fetch`, which has the effect `Http`, so a `Function<int()>` cannot hold it." (`FunctionTypeErrors.sharp:11`) | nothing to build | decision 71, #136 |
| T4 | 1512 and 1594: a function value is called | works through a local, a parameter and a field. Missing through any other expression: seven forms are "This expression is not supported yet in PHP#." (`CallForms.sharp:10`–`22`), while `this.make()?.(1)` and `steps[0]?.(1)` run (`nullsafe.php`: `NULL`, `41`, `NULL 2`) | Gap 1, Q1 | the bridge's `NullSafeFunction` lowering, `declared_effects` in `effects/summary.rs` |
| L1 | 1523: a lambda is a bare arrow | works: `i => !i.refunded`, `(Item item) => …`, a block body with two `return`s, `() => 5` (`LambdaForms.sharp`). `fn (int a) => a` and `function (int a) { … }` fail as a typo does, at `fn` or `function` (`PhpArrowFn.sharp:7`, `PhpClosure.sharp:7`) | nothing to build | |
| L2 | 1523: parameter types are optional when the checker can infer them | works where a function type or a generic call gives them (`LambdaForms.sharp:7`, `13`, `18`). Silently accepted where nothing does: `const identity = x => x;` and `Any f = x => x;` compile with `x` as `Any?` (`LambdaUninferred.sharp:7`, `19`). `(a, b) => a + b` is refused only by its body: "Left operand in binary operation has type `Any?`." (`:13`) | Gap 4 | `missing-parameter-type`, `closure_parameter_types` |
| L3 | 1538: a lambda captures the variables it uses, with no `use` list, the variable itself | works, engine included: `incremented 1`, a write after the lambda is made is seen (`seesLaterWrite 5`), `this` is read when called (`readsThis 20`), a counter outlives its method (`captureOutlivesCall 3`), two lambdas share one variable (`sharedBetweenTwo 2`), a `const` loop variable gives each pass its own (`loopPasses 123`). A write to a captured `const` is refused: "Cannot assign to `total`: it is declared with `const`." (`CaptureConst.sharp:8`). Missing for a loop variable that changes: "This capture of a loop variable that changes is not supported yet in PHP#." (`NotSupportedYet.sharp:11`, `21`) | Gap 6 | the unset-before-assignment of a captured `let` |
| L4 | 1550: `add`, `set` or `remove` on a captured variable changes it | works: `listAdd 3`, `listRemove 2`, `setAdd 1`, `mapRemove 0`, and index writes `listIndexWrite 9`, `mapIndexWrite 1` (`captures.php`). No collection has `set` (Q7) | spec wording, Q7 | |
| M1 | 1565: a method named without parentheses is a function value | works for a plain PHP static `Plain.shout`, a PHP# static `Item.doubled`, `this.plus`, `item.priceIn`, a stored value, a returned value and a generic static method (`methods.php`: `A,B`, `2,4`, `11,12`, `4,4`, `8`, `101`, `Closure 10`; `generic.php`: `1,2`, `4`). A wrong type is refused (`MethodValueErrors.sharp:7`), and so is an effect the type doesn't list: "`Remote.fetch` has the effect `Http`, so a `Function<int()>` cannot hold it." (`:13`). Missing for a collection's method (`MethodValueKinds.sharp:5`) and a built-in type's or extension's method (`ExtensionMethodValue.sharp:5`). A plain PHP function is refused (Q6) | Gap 5, Q5, Q6, Q10 | `method_value`, `returning_closure` and `collection_call`'s inline PHP in the bridge, `record_call_target`, #136's `method_value` in `effects/summary.rs` |
| M2 | 1565: it is found when the code runs, so it works when the class lives in another file | works: `Demo\Plain` and `Demo\Item` autoload only when `map` runs the value (`methods.php`: `plainStatic autoload Demo\Plain`) | nothing to build | PHP's first-class callable |
| M3 | 1565: PHP writes this as `Str::slug(...)` | refused, but as "This expression is not supported yet in PHP#." (`PhpFirstClass.sharp:5`) | Gap 7: fails as a typo does, at the `...` | `report_unsupported` |
| M4 | 1575: in a plain PHP class with a property and a method of one name, the property wins | works: `plain.label` gives `property` (`methods.php`) | nothing to build | |
| M5 | 1577: a field or property holding a `Function` is called like a method when the class has no method of that name | works for a field, a property, a computed property, another object's property, a `?.` receiver and a plain PHP `\Closure` property (`fields.php`: `800`, `901`, `90`, `6`; `nulls.php`: `callFieldIfSet NULL 6`). Missing for a static field: "Method `StaticFunctionField.shared` does not exist." (`StaticFunctionField.sharp:7`) | Gap 2 | `resolve_called_property`, `CallTarget::Property`, `is_property_call` |
| M6 | 1577: a `.sharp` class can't declare a method and a field or property of one name | works in one class body: "The class `SameName` declares a method and a property named `discount`." (`SameName.sharp:7`, `SameNameProperty.sharp:7`). Silently accepted across inheritance: `ChildWithField.sharp` and `ChildWithMethod.sharp` compile, and `ChildWithField`'s `this.discount(5)` runs its parent's method: `childWithField 5` (`fields.php`) | Gap 3, Q4 | `report_methods_named_as_properties`, `name-already-in-use` |
| M7 | 1587: the field or property is read before the arguments run | works: an argument that replaces `discount` leaves the old function running, `argumentReplaces 400` (`fields.php`) | nothing to build | PHP's `($this->discount)(…)` order |
| N1 | 1591: `a ?? b` | works: `coalesce 7 2`, and the right side runs only for a null left side (`short.php`: `0`, then `12`) | nothing to build | |
| N2 | 1592: `a ??= b` | works on a property and a local: `coalesceAssign 3`, `coalesceAssignLocal 9 1` | nothing to build | |
| N3 | 1593: `a?.b`, PHP's `?->` | works for a read, a call and a chain, and a null receiver runs no argument (`nulls.php`, `short.php`) | nothing to build | |
| N4 | 1594: `f?.(x)` | works on a parameter, a field, a call result and a list element, and a null function runs no argument (`nulls.php`: `callIfSet NULL 10`; `nullsafe.php`; `short.php`) | nothing to build | |
| N5 | 1595: `?? throw`, `?? return`, `?? continue`, `?? break` leave; `return` takes a value when the method returns one | works: `leaveReturn -1 5`, `leaveVoid NULL`, `leaveContinue 3`, `leaveBreak 1`, `leaveThrow missing`. Refused: a bare `?? return` in an `int` method, `?? return 5` in a `void` method, a `string` for `int`, and `?? continue` outside a loop (`LeaveErrors.sharp:9`, `15`, `26`, `20`) | nothing to build | `ZEND_SHARP_LEAVE` |
| N6 | 1613–1615: a null check, `?.` or `??` on a type without `?` is a compile error | works for `!=`, `==`, `is null`, `??`, `?.` on a read and a call, `?.(…)` and `??=` (`NeedlessNullChecks.sharp:8`–`24`) | nothing to build | commit `40924ab804` |
| N7 | 1616: a nullable return type on a method that never returns null is a compile error | works for a block body, an expression body, a literal and a function type (`NeedlessNullChecks.sharp:27`–`33`). An abstract method and one that can return null compile. An overridable method is exempt (Q8): `NullableOverride.sharp` compiles | spec wording, Q8 | |

Section 4's line 149 points here: `Class.y` without a call reads a static method as a function value, which M1 covers. Section 19's line 2044, no `==` between function values, and section 21's `is Function<int(int)>` belong to those sections' Slices.

## Gap 1: calling any expression that gives a function (Q1)

`enter` in `crates/semantics/src/internal/checker/sharp.rs` admits `Call::Function` only when the callee is a local's name (line 2149). Every other callee falls to `report_unsupported`. The analyzer already types any callee: it is upstream Mago's call of a closure-typed expression, and it checks the arguments against the function type, as the refusals in `FunctionTypeErrors.sharp:18` and `24` show for a local. In `CallForms.sharp` the analyzer ran past the semantics refusals and reported nothing, so each of the seven calls typed as the `int` its method returns.

- **Semantics:** a `Call::Function` in a body whose callee is any expression enters `Place::FunctionCall`, as a local's does. A bare name that is no local stays refused, so `strlen(x)` keeps its error.
- **Bridge:** `Expression::Call(Call::Function(…))` with a callee that is not a name lowers as the `NullSafeFunction` branch does: `CALL(<the callee's expression>, ARG_LIST)`. `closure_parameters` and `function_value_name` in `lower/types.rs` take any callee, so a call of a plain PHP closure still checks its result, and the return check names the callee as the source writes it.
- **Effects:** `Recorder::call` in `effects/summary.rs` already reads `declared_effects` of any callee's type. Nothing changes.

## Gap 2: a static field called like a method

`resolve_called_property` refuses a static property (`resolution.property.flags.is_static()`), and `analyze_static_method_call` never asks it. The call `StaticFunctionField.shared(5)` gets the analyzer's "does not exist".

- **Analyzer:** `resolve_static_method_targets` in `resolver/static_method.rs` falls back, when the class has no static method of that name, to its static property of that name, through `resolve_called_static_property` in `resolver/static_property.rs`. That reads the property as a static read does, with `find_static_property_in_class`: the visibility check, and the type arguments the class is written with, so `FunctionShelf<int>.shared` holds a `Function<int(int)>`. It records those type arguments at the call for the bridge. A private property called from outside is the read's "Cannot read private property", and the call reports nothing more. The call records `CallTarget::Property` and types through `resolve_callable_targets`, as `analyze_method_call` does for an instance property, in one shared `add_called_property_targets`.
- **Bridge:** `method_call` lowers a `STATIC_CALL` whose target is a static property as `CALL(<the static read>, ARG_LIST)`, the read being `static_property`'s, so a generic class's static field reads through `Sharp\Internal\statics` as every static read does. The call passes no type arguments: the ones it records pick the statics entry. PHP runs `(StaticFunctionField::$shared)(5)`.
- **Effects:** `property_call` reads the property's type through the codebase's `get_property`, whose map holds static and instance properties alike. This was read, not run.

## Gap 3: a member named like an inherited one (Q4)

`report_methods_named_as_properties` reads one class body in semantics, which knows no parent. The inherited half moves to the analyzer, where the codebase knows each ancestor.

- `statement/class_like/mod.rs` gains `report_members_named_like_inherited_members` beside `report_statics_named_like_class_members`: for each method the class declares, an inherited visible property of that name, and for each field or property it declares, an inherited visible method of that name, compared ignoring case as PHP finds a method. It reports `name-already-in-use`, the code the static clash uses: "`Sale` declares the field `discount` and inherits the method `discount` from `Pricing`, so `x.discount(…)` could run either. Rename one of them."
- The one-body check stays in semantics, since a file with a parse error still gets it.
- The standard library's `Sharp.InvalidFields` declared a private static method `message` below plain PHP's `Error.message`. The method is renamed `describe`.

## Gap 4: a lambda parameter nothing types

`analyze` in `expression/arrow_function.rs` and `expression/closure.rs` takes the parameter types from `closure_parameter_types` of the expected type, and a parameter with neither a written type nor an inferred one becomes `Any?`.

- In a `.sharp` file, a parameter with no written type whose expected type gives none is `missing-parameter-type`, an error from `report_untyped_lambda_parameters` in `statement/function_like/mod.rs`: "Nothing gives the parameter `x` a type here: write it, as in `(int x) => …`, or give the lambda a function type." It is the rule `a_generic_method_value_without_a_function_type_is_refused` applies to a generic method value, and C#'s CS8917.
- A field's or property's initial value takes the declared type through `expect_type`, as a typed local's value does. Before, `private Function<int(int)> step = n => n + 1;` gave `n` the type `Any?`.
- A lambda passed to plain PHP takes its parameter types from the plain PHP signature (`a_lambda_passed_to_php_takes_its_parameter_types_from_the_php_signature_as_in_php`). A plain PHP parameter typed `callable` or `\Closure` with no docblock gives each parameter `Any?`, as its signature says and as section 24 gives an untyped plain PHP value. Of the existing tests, only `yield_is_a_yield_statement_and_yield_spread_is_a_loop_that_yields_each_element` relied on a parameter nothing typed, and it now writes `(int x) => x`.

## Gap 5: a method value of a collection, a built-in type or an extension (Q5)

PHP's first-class callable binds an object's method only. A collection runs as a PHP array, a `string` as a PHP string, and an extension method as a static method that takes its receiver first, so none has a PHP spelling.

- **Analyzer:** a read that reaches no computed property gives `resolve_extension`'s `Extension::Value`, the extension method the receiver picks as a call picks it, and `resolve_extension_value` in `resolver/property.rs` types it as the static method's closure without its first parameter, with the block's type parameters the receiver gives and the method's own from the function type. A read of a `List`, `Map`, `Set` or `Iterable` goes to `resolve_collection_method_value`, which resolves on every collection `get_receiver_collections` finds, the declared ones included, as a call does (`get_receiver_atomics` now holds that rule for both), and types it with `resolve_method_value`. A tuple keeps its named elements. `global.strtoupper` and `Pdf.render` are `resolve_function_value`'s closure of the function (Q6). Each read records what it runs with `record_call_target` at its span, and such a read is never memoized, so every read records it. A call of an extension method's value is checked against the value's own signature, not the static method's.
- **Bridge:** `receiver_closure` writes `(function ($receiver) { return function (string $key) use ($receiver) { return <call>; }; })($target)`, the shape `method_value` writes for a generic method. The inner closure takes the method's parameters by name, from `value_parameters`, with the PHP types the value's function type gives them, so a plain PHP library that reads a callback's parameters by reflection finds them. From the first optional or variadic parameter on, it takes `...$arguments`, so a default stays the method's own. An extension method's value calls `\Class::member($receiver, $separator)`. A collection method the lowering writes as PHP runs that PHP from `inlined_collection_method`, split out of `collection_call`, on `CollectionReceiver::Hidden`, as `\in_array($value, $receiver, true)`. Any other collection method runs `$receiver->has($key)` on `Sharp\Collection`, and an `Iterable`'s on `\Sharp\Sequence::from($receiver)`. A function value is `\strtoupper(...)`. The receiver is read once, where the value is named, so Q5's answer holds: a `List`, `Map` or `Set` is copied there, and `has(5)` after `allowed.add(5)` is `false` in the engine, a `Map` included.
- **Effects:** #136's `method_value` in `effects/summary.rs` records an extension method's value as a call with the receiver as its first argument, and `function_value_runs` gives a function's value the effects its `extern` declares, as a call of it has, wherever it is passed. `function_values_must_fit_their_types` names it `global.strtolower`.
- **Q10, a method that changes the collection:** its value would change only the copy Q5 gives it, so `seen.add` is refused, as Swift refuses the value of a `mutating` method: "`seen.add` changes the collection, and a function value holds a copy of it: write a lambda that calls it, as in `value => seen.add(value)`." A method without `@mutation-free` in the prelude's `ListMethods`, `MapMethods` and `SetMethods` changes it. The spec's `changing-method-values` example shows it.

## Gap 6: a lambda that captures a loop variable that changes

Spec section 3 gives each loop pass a fresh binding, and line 1538 has the lambda capture the variable itself. PHP reuses one variable across passes.

- **Semantics:** `check_lambda_captures` and `is_loop_variable` are deleted.
- **Bridge:** a `for … of` variable that a lambda captures by reference is read into a hidden variable, then unset and assigned at the top of each pass, as a captured `let` in a loop body already is. A `for (let i = …; …; i++)` counter that a lambda captures by reference is moved into a fresh PHP variable at the end of each pass, before the increments run, as JavaScript copies a `let` counter into the next pass. PHP's increments take expressions only, and `unset` is a statement, so the body lowers inside `do { … } while (false)` with the move after it: a `continue` in the body ends the `do` and reaches the move, and a `break` that leaves the `for` becomes `break 2`. A step that rebinds by reference, `$step#1 = $step, $step = &$step#1`, fails from the second pass on, because `$step#1` is then the slot the second lambda holds. Both shapes ran as plain PHP on master's engine (`/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-functions/loop_move.php`): the planned one gives `0,12` for a loop whose pass 1 continues and whose pass 2 adds 10 after its lambda is made, and the rebinding step gives `0,3,3` where each pass should keep `0,1,2`.
- What a user sees, by spec section 3: `for (let step = 0; step < 3; step++) { reads.add(() => step); }` gives `0`, `1` and `2`, and a lambda made in a pass sees a change the same pass makes after it.

## Gap 7: PHP forms named in section 14's words

- `Item.doubled(...)` fails as a typo does, at the `...`.
- `Function<int(void)>`: the type check refuses a `void` parameter with "A function type takes no `void` parameter: write `Function<int()>` for a function that takes nothing."
- Q2's `Closure` refusal, with the message Q2 proposes. A bare `Closure` type gets the same refusal as the import. A `Closure` a plain PHP method's docblock types, as `\Closure(int): int`, arrives as that function type and is called, held and passed as one. An untyped `\Closure` return assigned to `Function<int(int)>` is refused, as an untyped `array` return assigned to `List<int>` is: "`Callbacks.untyped()` gives a function whose types plain PHP doesn't declare, so it is no `Function<int(int)>`: hold it with `const`, and check what each call gives." A `callable(int): int` docblock return is refused because a plain PHP `callable` may be a function's name or an array: "`Callbacks.named()` gives a plain PHP `callable`, which may be a function's name or an array, so it is no `Function<int(int)>`."
- A plain PHP `callable` or `\Closure` parameter, and a `?\Closure` property, with no docblock take any PHP# function value, as in PHP: `callable_comparator` passes a `mixed ...` parameter under `sharp_rules` only when it is variadic, which is what such a signature declares and PHP# never writes.
- A call of a value that is no function is "Only a function value is called with `(…)`, and `(5)` is `5`.", and a nullable one is "`this.maybe` may be null: call it as `this.maybe?.(…)`." A lambda called where it is written without parentheses is "Write the lambda in parentheses to call it where it is written, as in `((int a) => a + 1)(2)`."
- A plain PHP function that changes the variable it is given, as `global.sort`, or reads its caller's variables, as the six that PHP's `zend_forbid_dynamic_call` guards (`compact`, `extract`, `get_defined_vars`, `func_get_args`, `func_get_arg`, `func_num_args`), is no function value: "`global.sort` changes the variable it is given, and PHP# passes every argument by value, so it is no function value." and "`global.compact` reads the variables of the code that calls it, so it is no function value." A direct call that passes a PHP# variable to a by-reference parameter is refused, naming the method that does the work for `array_push`, `array_unshift`, `sort`, `rsort` and `usort`: "… write `numbers.add(…)`." The standard library keeps its by-reference calls, as `headers_sent(file, line)`, since it writes PHP#'s own out-parameters.
- A method value as a field's initial value, `private Function<int(int)> doubler = FunctionValues.twice;`, runs as code in the constructor, as a static's runs after the class: `Property::initial_value_runs_as_code` is the one decision the checker, the analyzer and the bridge read.
- A wrong call of a function value names it as the code writes it. `InvocationTarget::guess_name` gives a PHP# function value's source text and `guess_kind` calls it a "function value", and a lambda a "lambda": "Invalid argument type for argument #1 of `f`: …" and "Too few arguments provided for function value `this.scale`." replace the two messages that named PHP's `Closure`.

# What changes

```text
sharp/mago/crates/
├── codex/src/ttype/atomic/callable/mod.rs
│                                        <- takes_more_parameters_than, Q3's count
├── codex/src/ttype/comparator/callable_comparator.rs
│                                        <- Q3's parameter count under sharp_rules
├── semantics/
│   ├── src/internal/checker/sharp.rs   <- a call of any expression in a body (Gap 1); `(...)` and a void
│   │                                      parameter refused in section 14's words (Gap 7); Q2's Closure and
│   │                                      Q9's & refused; check_lambda_captures and is_loop_variable
│   │                                      deleted (Gap 6)
│   └── tests/sharp.rs
├── analyzer/
│   ├── src/resolver/method.rs           <- get_receiver_atomics and get_receiver_collections: the collections a
│   │                                      call and a method value resolve on (Gap 5)
│   ├── src/resolver/static_method.rs    <- a static call of a static property holding a function (Gap 2)
│   ├── src/resolver/static_property.rs  <- resolve_called_static_property (Gap 2)
│   ├── src/invocation/mod.rs, src/invocation/arguments.rs, src/invocation/analyzer.rs
│   │                                    <- a function value named as written (Gap 7); Q3's argument
│   ├── src/statement/function_like/mod.rs
│   │                                    <- report_untyped_lambda_parameters (Gap 4), report_value_for_void (Q3)
│   ├── src/statement/class_like/property.rs
│   │                                    <- an initial value takes its declared type (Gap 4)
│   ├── src/resolver/extension.rs        <- Extension::Value, an extension method read without parentheses (Gap 5)
│   ├── src/resolver/property.rs         <- resolve_collection_method_value with Q10's refusal,
│   │                                      resolve_extension_value and resolve_function_value (Gap 5, Q6)
│   ├── src/expression/constant_access.rs
│   │                                    <- global.strtoupper read without parentheses is its value (Q6)
│   ├── src/expression/access/property_access.rs
│   │                                    <- a method value's read is never memoized (Gap 5)
│   ├── src/expression/call/function_call.rs
│   │                                    <- an extension method's value is called by its own signature (Gap 5)
│   ├── src/effects/summary.rs, src/effects/check.rs
│   │                                    <- function_value_runs, an extension method value's receiver (Gap 5, Q6)
│   ├── src/expression/call/static_method_call.rs
│   │                                    <- records CallTarget::Property for Gap 2's call
│   ├── src/expression/arrow_function.rs, src/expression/closure.rs
│   │                                    <- a parameter nothing types is missing-parameter-type (Gap 4);
│   │                                      Q3's void body
│   ├── src/statement/class_like/mod.rs  <- a member named like an inherited one (Gap 3)
│   └── tests/sharp.rs
├── sharp-bridge/
│   ├── src/lower.rs                     <- a call of any expression (Gap 1); a static property's call
│   │                                      (Gap 2); receiver_closure, collection_method_value and
│   │                                      inlined_collection_method, an extension's and a function's value
│   │                                      (Gap 5, Q6); a fresh loop variable in each pass (Gap 6)
│   ├── src/lower/types.rs               <- closure_parameters and function_value_name take any callee (Gap 1);
│   │                                      is_extension_method_value, function_value, value_parameter_type (Gap 5)
│   └── tests/lower.rs
sharp/composer/library/Sharp/InvalidFields.sharp
                                         <- its private `message` method renamed `describe` (Gap 3)
Zend/tests/sharp/
├── FunctionValues/
│   ├── FunctionValues.sharp             <- new: Gap 1's calls, Gap 2's static fields, Gap 5's method values,
│   │                                       Gap 6's loops
│   ├── FunctionShelf.sharp              <- new: a generic class's static field holding a function (Gap 2)
│   └── Shouting.sharp                   <- new: an extension method whose value runs (Gap 5)
├── function_values.inc                  <- new: loads the folder with fixture() and `Sharp\Text\Text`, runs each
│                                           row twice, so the JIT variants compile it first
├── function_values.phpt                 <- new
├── function_values_preload.inc          <- new: the folder's files and `Sharp\Text\Text`, for opcache.preload
└── function_values_preload.phpt         <- new: the same rows under opcache preloading
docs/spec.md                             <- section 14 as Q1, Q3, Q5, Q6, Q7, Q8 and Q10 answer: Q1's line and its
                                            example `function-calls`; the method value kinds and the examples
                                            `method-value-kinds` and `changing-method-values`; line 1550; line 1616
docs/architecture/slices/14-functions-as-values.md   <- this note
```

`sharp/bin/spec` and `sharp/harness/` do not change, so no pull request goes first. The engine's C code and the prelude do not change, and the standard library changes one private method's name.

The PHP the bridge emits:

```text
($steps[0])(1)                                                                  <- steps[0](1)
($this->make())(1)                                                              <- this.make()(1)
(StaticFunctionField::$shared)(5)                                               <- StaticFunctionField.shared(5)
(function ($receiver) { return function ($value) use ($receiver) {
    return \in_array($value, $receiver, true); }; })($allowed)                  <- allowed.contains, without parentheses
(function ($receiver) { return function (...$arguments) use ($receiver) {
    return $receiver->has(...$arguments); }; })($prices)                        <- prices.has, without parentheses
(function ($receiver) { return function (...$arguments) use ($receiver) {
    return \Sharp\Text\Text::trim($receiver, ...$arguments); }; })($name)       <- name.trim, without parentheses
\strtoupper(...)                                                                <- global.strtoupper, without parentheses
foreach ($values as $value#1) { unset($value); $value = $value#1; … }           <- for (let value of values), captured
for ($step = 0; $step < 3; $step++) {
    do { … } while (false);
    $step#1 = $step; unset($step); $step = $step#1;
}                                                                               <- for (let step = 0; …; step++), captured
```

# Public names this Slice adds or changes

team-lead approves these. Everything not here is tactical.

- **New engine test files:** the folder `Zend/tests/sharp/FunctionValues/` with `FunctionValues.sharp`, `FunctionShelf.sharp` and `Shouting.sharp`, and `function_values.inc`, `function_values.phpt`, `function_values_preload.inc` and `function_values_preload.phpt`.
- **No new issue code.** Gap 3 reuses `name-already-in-use`, Gap 4 reuses `missing-parameter-type`, Q3's refusals reuse `invalid-argument`, `invalid-property-default-value`, `invalid-local-assignment-value` and, for a `void` type's value, `invalid-return-statement`, Q10's refusal reuses `invalid-property-access`, the code the same read gets on master, and the semantics refusals carry no code, as every semantics refusal.
- **No new node, type, engine function or library class.** One new harness file, `Zend/tests/sharp/harness/Callbacks.inc`: a plain PHP class that takes callbacks with no docblock and reads their parameters by reflection.
- **The spec:** under section 14.1, one line and its example, and the `function-fit` example of the parameter count and `void`. Under section 14.3, the method value kinds with Q5's copy and Q10's refusal with two examples, and the `by-reference-functions` example. The wording of lines 1550 and 1616, and section 29's `global.usort(list, f)` becomes `global.array_filter(list, f)`, since `usort` is no function value.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. The existing test for a rule grows a row before a new test is added, and each row is seen failing before its code lands.

**`sharp/mago/crates/semantics/tests/sharp.rs`:**

- `a_function_called_through_an_expression_is_not_supported_yet` becomes `any_expression_that_gives_a_function_is_called` (T4): rows a list element, a map element, a call result, a parenthesized field, a conditional, a lambda, a static call's result; a bare name that is no local stays refused.
- `a_void_parameter_of_a_function_type_is_not_supported_yet` becomes `a_function_type_takes_no_void_parameter` (T2).
- `an_error_for_a_php_form_without_a_sharp_form_names_no_php_code` grows the rows `Item.doubled(...)`, `this.plus(...)`, Q2's `Closure` and Q9's `(&value) => value` (M3, T2).
- `a_lambda_capturing_a_loop_variable_that_changes_is_not_supported_yet` is deleted, and its three loops become rows of the bridge test below (L3).

**`sharp/mago/crates/analyzer/tests/sharp.rs`:**

- `a_lambda_parameter_nothing_types_is_refused` (new, L2): rows a `const` local, an `Any` local, two parameters, a plain PHP `callable` parameter; accepted rows a function-typed local, a field's initial value, a generic collection call and a plain PHP signature with a closure docblock.
- `a_function_type_refuses_a_lambda_of_another_type_and_a_string_or_array_callable` grows Q3's rows: too few parameters, and a `void` type given `a + 1`, `this.log(a)` and `count = a` (T1).
- `a_call_of_a_property_holding_a_function_is_checked_against_its_function_type` grows the rows a static field, a static property, a generic class's static field, a wrong argument to each, and a private static read from outside (M5).
- `a_call_of_a_property_holding_a_function_records_the_property` grows a static row (M5).
- `a_member_named_as_an_inherited_member_is_an_error` (new, M6): rows a field below a method, a property below a method, a method below a field, a method below a constructor-declared property, two levels down, a plain PHP parent's property and method; accepted rows a `private` ancestor member and a plain PHP class with both.
- `a_method_value_of_a_collection_an_extension_or_a_plain_php_function_has_its_function_type` (new, M1): rows `allowed.contains`, `prices.has`, `seen.contains` on a `Set`, `allowed.count`, `title.trim`, an imported extension method and Q6's `global.strtoupper` and `global.strlen`, each also in a function type it doesn't fit, and a call of an inferred extension method value with a wrong argument. `an_imported_extension_is_a_member_of_its_receiver`'s `title.slug` row is now its value, and `a_read_through_a_name_that_reaches_no_constant_is_refused` drops its two function rows.
- `a_method_value_of_a_collection_method_that_changes_the_collection_is_refused` (new, Q10): rows `List.add`, `List.insert`, `Map.remove` and `Set.clear`, each with its lambda.
- From the review: `a_lambda_fits_a_function_type_only_with_its_parameters_and_its_void` grows a method value in a field and a local, a block body's `return`, a nested `if`'s `return`, a nested lambda's `return`, a conditional, and three `void` results used as values, each with its message. `a_lambda_parameter_nothing_types_is_refused` grows lambdas in `List` and `Map` literals, nested and as an argument. New: `a_plain_php_function_that_changes_or_reads_the_callers_variables_is_no_function_value`, `a_call_of_no_function_and_a_closure_type_are_refused_in_php_sharp_words`, `a_function_value_crosses_plain_php_callbacks_that_declare_no_types` and `a_read_of_an_extension_the_file_does_not_import_is_annotated_as_a_read`. `a_by_reference_write_that_can_never_fit_the_written_type_of_a_local_is_reported` moves into the standard library, where a by-reference call stays.
- `a_function_or_extension_method_value_has_its_effects_where_it_runs` (new, M1 with section 29): a function value through a function parameter and through plain PHP, an extension method value through a collection method, and a function without an `extern` in a type that lists effects.

**`sharp/mago/crates/sharp-bridge/tests/lower.rs`:** one tree per row of "The PHP the bridge emits": `a_collection_method_value_is_a_closure_over_the_collection` and `an_extension_method_or_plain_php_function_value_calls_what_it_names` for Gap 5 and Q6, a second read of one value included, and `a_loop_variable_a_lambda_captures_is_fresh_in_each_pass` (new, L3) with the three loops of the deleted semantics test. From the review, `a_for_counter_a_lambda_captures_inside_a_loop_is_unset_before_the_for` (new): the `for` header holds the counter's assignment alone and its unset runs before the `for`, with a `continue` and a `break`, since an unset inside a header expression crashed the engine.

**`sharp/mago/crates/syntax/tests/sharp.rs`:** `a_by_reference_parameter_is_a_parse_error` becomes `a_typed_by_reference_parameter_is_its_type_and_its_ampersand`, with a bound `A & B` that stays an intersection.

**Engine, `Zend/tests/sharp/`:** `function_values.phpt` runs one row per form of T4, M5's static field, M1's new method values with Q5's copy, `has(5)` and `size()` after `allowed.add(5)` and `hasCoffee("coffee")` after a `Map` write, and L3's loops: `for (let step …)` gives `0,1,2`, and a `for … of` variable changed after its lambda is made gives the changed value. From the review: a `for` inside a `for`, both counters captured, with `continue` and `break` in both, gives `[0,2,10,12,30,32]`; a method value as a field's initial value; and `Lib\Callbacks` from the new harness file, which calls a method value through an undocumented `callable`, a plain PHP function value through `\Closure`, a lambda stored in its `?\Closure` static property, and reads the parameters of an extension method value and two collection method values by reflection: `string $tail`, `int $value` and `string $key`. `sharp/bin/test --opcache --repeat --passes` runs it and `function_values_preload.phpt` in every opcache mode, and a `USE_ZEND_ALLOC=0 MallocScribble=1` loop runs both, since the preloaded classes share their closures.

**The spec:** `sharp/bin/spec` shows every section 14 example passing, Q1's `function-calls` included, and `sharp/bin/spec --completes` passes on a body with `Completes: §14`.

**Plain PHP:** `mago analyze` of `build/gen_stub.php`, `run-tests.php` and the SDK's sources gives the same issues before and after, as #141 checks its corpus. Every change is behind `context.dialect.is_sharp()` or in the bridge, which lowers `.sharp` files only.

# Build order

1. **On master.** Gap 7's `(...)` and `void`, Gap 4, Gap 2, Gap 6, and, once answered, Gap 1 (Q1), Gap 3 (Q4) and Q3's refusals. Each with its rows, seen failing first.
2. **Merge `origin/master` once #136 lands,** when team-lead says the pull request heads the landing queue. #136 changes `semantics/src/internal/checker/sharp.rs`, `effects/summary.rs` and `statement/class_like/method.rs`, which this Slice also changes.
3. **On the merge.** Gap 5 with Q5's and Q6's answers, since a method value's effects go through #136's `method_value`. Q2's and Q9's refusals, and the spec lines of Q1, Q7 and Q8.
4. **Verify.** The tests above, the plain PHP corpus, `sharp/bin/spec` and `--completes`, then the pull request with `Completes: §14` and CI green.

# What the Slice refuses as "not supported yet", and why

Nothing. Every "not supported yet" that section 14's rules reach, a call of an expression, a loop capture, `(...)`, a `void` parameter and a `&` parameter, becomes a working form or a refusal in the spec's words.

# Found, not fixed

Each was probed on master's `mago` in `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-functions/ws`. None blocks a rule of section 14.

- **Messages print a method's name in lowercase.** "Declared return type `int?` for `saferead` has unused branches: `null`." (`NeedlessNullChecks.sharp:16`), and "Parameter `value` is nullable, but `leavethrow` rejects null on every path." from an earlier probe of `NullOperators.sharp`. The methods are `safeRead` and `leaveThrow`.
- **Null check messages keep PHP's words.** "Redundant null coalesce: left-hand side can never be `null` or undefined." and "Redundant nullsafe operator (`?.`) used on an expression that is never `null`." (`NeedlessNullChecks.sharp:14`, `16`). PHP# has no undefined value, and the spec calls `?.` a null check.
- **`item is null` on a non-null value gets two errors for one fault:** "This pattern never matches the value it tests." and "Redundant `is null` check: `Item` is never `null`." (`NeedlessNullChecks.sharp:12`).
- **An instance method read through its class says it doesn't exist.** `const f = Item.priceIn;` is "`Item.priceIn` does not exist." (`MethodValueErrors.sharp:25`). Section 4's line 149 makes it an error, and the message should say `priceIn` is an instance method, read through an `Item`.
- **A bare `Function` names no form.** `Function f` is "Cannot find type `Function`." (`PhpTypes.sharp:15`), where `callable` gets "write `Function<R(P)>`".
- **The one-name clash calls a field a property:** "The class `SameName` declares a method and a property named `discount`." for `private Function<int(int)> discount` (`SameName.sharp:7`).

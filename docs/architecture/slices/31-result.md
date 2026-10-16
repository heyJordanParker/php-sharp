# Slice 31: Expected failures as values

Spec section 31: `Result`, the `try` operator and the `Error` interface. This note holds every line of the section, its state on master `39b305352e`, its mechanism and the code it reuses. team-lead approved it with the answers under "Answers in force", which replace the proposals of the questions below wherever they differ.

## Answers in force

team-lead answered the seven questions in Queue 1. Each answer is written into the spec as a plain rule, with an example line where it changes what users write.

1. **Q1:** as proposed. `try` returns a new `Err` holding the same error, with the enclosing method's type arguments, `==` to the original. An operand error type that does not fit the method's is a compile error that names both. Line 4039 says "an `Err` holding the same error".
2. **Q2:** as proposed. `try` is a prefix operator with `!`'s precedence; member access and calls bind tighter. The operand is any `Result` expression, and `try` stands anywhere an expression can. Line 4039 names Zig alone, and the spec gains the line `(try gateway.charge(cart)).total`.
3. **Q3:** every enum can declare a computed property, a backed enum included. No enum declares a stored property. Line 2638 becomes "An enum declares no stored property; a computed property is allowed." One mechanism serves every enum, so a backed enum stays a PHP backed enum: the engine lets a PHP# enum declare a virtual property, one with a `get` body and no storage (see E3). `Enum::runs_as_classes()` replaces `has_data_case()`, since an enum with type parameters runs as classes too.
4. **Q4:** as proposed. A bare `Error` always names `Sharp.Error`. An import whose last part is `Error` is a compile error that says `Error` is PHP#'s interface. `catch (Error …)` is a compile error that names `Throwable`.
5. **Q5:** as proposed. A type is `Result<Order, PaymentError>.Ok`. A call takes its type arguments from the declared type its result goes into or the method's return type, and one nothing fixes names them, `Result<int, PaymentError>.Ok(5)`. A pattern names the bare case. The declared-type inference extends to every static call on a generic class or enum, as section 11's line 750 says, so `Holder<int> h = Holder.of(5);` compiles, and the wrong second message "Holder<int> cannot be used as Holder<int>." goes.
6. **Q6:** as proposed. Any enum without a backing type declares type parameters, with bounds and no `in` or `out`. A plain case is one object per list of type arguments.
7. **Q7:** line 4034 reads `public Result<Order, PaymentError> charge(Cart cart) => Result.Err(PaymentError.Declined("card expired"));`.

`Expression::Propagate` is approved. The harness files `PaymentError.sharp` and `CheckoutGateway.sharp`, and `Order.receipt()`, go in this PR.

## The answer first

None of section 31 runs on master. The three examples fail at three missing pieces, and each piece hides more rules behind it:

1. **An enum takes no type parameters.** `public enum Result<T, TError : Error>` is a parse error ("Expected one of `LeftBrace`, found `LessThan`"). This fails `result-enum`, and every rule about `Result` waits on it: making a case, matching it, the bound on `TError`, `==`, and what plain PHP receives.
2. **`try` before a value is a parse error** ("Unexpected token `Try`"). This fails `try-propagation`.
3. **There is no `Error` interface, and an enum holds no property.** A bare `Error` is "Cannot find type `Error`.", and `PaymentError`'s `message` is "Enum `PaymentError` cannot have properties." This fails `error-interface`.

Behind them, four rules are silently accepted or refused for the wrong reason:

4. **`import Error;` compiles,** and so does `catch (Error failure)` after it. The spec says PHP's engine class `Error` is never named in PHP# code.
5. **A case of a generic class can't be made without type arguments.** `Holder.of(5)` is "`Holder` needs its type argument.", so `Result.Err(…)` and `Result.Ok(…)`, which the spec writes bare, would be refused once `Result` parses.
6. **A pattern can't name a generic class bare.** `value is Holder h` is "`Holder` needs its type argument.", so `Result.Ok(Order order)` would be refused, where the spec says `match` and `is` work on `Result` unchanged.
7. **Spec line 4034 can't pass as written.** `{ … return Result.Err(…); }` becomes `{ throw new Elided() return …; }`, a parse error, and with a `;` the `return` is "This statement never runs".

What already holds: an exception still passes through PHP# code, `try`/`catch` still wraps a plain PHP exception, the checker reports no undeclared exception in a `.sharp` file, and `catch (Throwable …)` and `catch (TypeError …)` work.

The Slice builds all of it in one PR across `sharp/mago/`, the standard library, the harness and the engine. The engine's one change lets a PHP# enum declare a virtual property (answer 3). `try` lowers to a conditional whose failure branch is the RETURN node that `?? return` already leaves through (`ZEND_SHARP_LEAVE`, Slice 21 decision 2), and a generic enum's classes are generic classes the engine already runs (#102). When it lands, the three entries of `sharp/baseline/spec/§31/` leave, and the PR writes `Completes: §31`.

## Questions for team-lead

Each decides what a user writes or what a running program shows. Each has a proposal, which the design below assumes.

### Q1. What `try` returns on a failure

Spec line 4039: "If the call returns `Err`, the enclosing method returns that same `Err` immediately."

Generics are reified (section 11, "The running program knows every type argument"). The `Err` that `charge` makes carries `<Order, PaymentError>`. If `checkout`, declared `Result<Receipt, PaymentError>`, returns that object itself, then `failure is Result<Receipt, PaymentError>` is false on a value the checker proves is one.

- **Proposed:** `try` returns a new `Err` holding the same `error` value, with the enclosing method's type arguments. It is `==` to the original, since it is the same case with an equal field (section 20). The operand's error type must fit the method's error type: `PaymentError` into `Error` passes, `Error` into `PaymentError` is a compile error. This is what Rust's `?` does, which builds a new `Err` from the old error.
- Kotlin's shape instead: each case carries only the type parameters its fields name, so `Err` is a `Result<never, TError>`, and `Result` is declared `Result<out T, out TError : Error>`. The same object then fits. This changes the spec's declaration, needs variance on an enum and `never` as a type argument.

### Q2. How far `try` reaches, and what it takes

Spec lines 4039 and 4047: "`try` passes a failure up to the caller", "Zig and Swift use `try` the same way", `const order = try gateway.charge(cart);`.

Zig and Swift differ. Swift's `try` covers the whole expression to its right, so `try a.load() ?? b` is `try (a.load() ?? b)`. Zig's `try` is a prefix operator, so it is `(try a.load()) ?? b`.

- **Proposed:** `try` is a prefix operator with the precedence of `!`, as C#'s and TypeScript's `await` is. It takes the whole member chain after it, so a member of the `Ok` value is written `(try gateway.charge(cart)).receipt()`. Its operand is any expression whose type is a `Result`, not only a call. It stands wherever an expression stands, as `?? return` does. A `try` outside a method or lambda, such as in a property's initial value, and a `try` in a method whose return type is not a `Result`, are compile errors.
- Swift's reach: `try` covers everything to its right, binary operators included.

### Q3. An enum that implements `Error`

Section 22, line 2638: "An enum has no property, so an enum that implements an interface with one is a compile error." Section 31, lines 4069 to 4074: `public enum PaymentError : Error { … public string message => match (this) { … }; }` compiles.

The two lines contradict each other. PHP itself refuses a property on a PHP enum: the engine printed "Enum Missing cannot include properties", and for an enum that implements the interface without one, "Enum Missing must implement 1 abstract method (Failure::$message::get)". `PaymentError` has a case with data, so it runs as classes (section 20), and a class holds a property. An enum of plain cases runs as a PHP enum, and a backed enum must, for `from` and `tryFrom`.

- **Proposed:** a not-backed enum that declares a computed property runs as classes, as an enum with a data case does, so `enum LoginError : Error { case Locked; public string message => "locked"; }` compiles. A backed enum still holds no property. Line 2638 becomes "A backed enum has no property, so a backed enum that implements an interface with one is a compile error." The cost: plain PHP reaches a plain case of such an enum as `LoginError\Locked::instance()`, as it reaches a plain case of a mixed enum today, and not as `LoginError::Locked`.
- Only an enum with a data case may declare a property. An enum of plain cases then can't implement `Error`, so it can't be a `Result`'s error type.
- `Error` declares a method, `string message();`, which every enum can implement. This changes the spec's declaration at line 4061.

### Q4. What "`Error` always means this interface" refuses

Spec line 4077: "Inside `.sharp` files, `Error` always means this interface. PHP's global engine class `Error` is never named in PHP# code. Bugs are caught as `Throwable`, and engine subclasses such as `TypeError` keep their names."

Section 23, line 2833, says a class of the file's own namespace, or one it imports, shadows a default name.

- **Proposed:**
  - A bare `Error` in a `.sharp` file always names `Sharp.Error`, as `List` and `Int` always name theirs. A type named `Error` in the file's namespace does not win. `error-interface`'s first block declares `App.Error` and still compiles, and its second block's `PaymentError : Error` names `Sharp.Error`.
  - An import whose last name is `Error`, `import Error;` included, is a compile error: "PHP#'s `Error` is the standard library's interface for failures: catch a bug as `Throwable`." An import of another library's `Error` renames it with `as`.
  - `catch (Error …)` is a compile error with the same reason. No exception can implement `Error`: PHP refuses a typed `message` on a subclass of `Exception` ("Type of Sharp\Failed::$message must be omitted to match the parent definition in class Exception"), so the catch could never run.
- `Error` shadows as every default name does (section 23). Then "always" holds only where the file's namespace has no `Error`.

### Q5. How a type and a call name a case of a generic enum

Section 20, line 2234: "Each case is its own type, so a method can take `PaymentResult.Paid` alone." Section 11, line 744: "A static member names its type arguments". Section 31, line 4037: "`match`, `is` and the exhaustive-case check work on it unchanged."

Line 4037 settles patterns: a pattern names the bare case, `Result.Ok(Order order)` or `Result.Err e`, and takes the subject's type arguments. Line 4034 settles a call whose arguments and declared type fix the type arguments: `Result.Err(PaymentError.Declined(reason))`. Two forms are open.

- **Proposed:** a type writes the enum's type arguments before the case, `Result<Order, PaymentError>.Ok`, and so does a call nothing fixes, `Result<int, PaymentError>.Ok(5)`, as `Counter<Order>.track(order)` names a generic class's type arguments today. One spelling serves both.
- Kotlin's spelling, the case with the type arguments after it: `Result.Ok<Order, PaymentError>`.

### Q6. Generic enums a project writes

Spec line 4037: "`Result` is an ordinary enum with data from the standard library". Once the parser takes type parameters on an enum, a project can write one too.

- **Proposed:**
  - Any not-backed enum declares type parameters, with section 11's rules for their names and bounds.
  - An enum declares no `in` or `out`. Section 11.1 gives variance to classes and interfaces, and the spec's `Result` writes none, so `Result<Order, PaymentError>` is not a `Result<Order, Error>`.
  - A plain case of a generic enum is one object per list of type arguments, as each list has its own statics (section 11): `Option<int>.None` and `Option<string>.None` are two objects, and each `is` its own type.
  - A backed enum takes no type parameters, since a PHP enum carries none.
- Only the standard library declares a generic enum.

### Q7. Spec line 4034 as written

`public Result<Order, PaymentError> charge(Cart cart) { … return Result.Err(PaymentError.Declined(reason)); }`

`sharp/bin/spec` turns the `…` into `throw new Elided()` with no `;`, a parse error. With the `;`, the checker reports "This statement never runs, because line 8 ends the block." (`ElidedReturn.sharp`). `reason` is also declared nowhere.

- **Proposed:** the line reads `public Result<Order, PaymentError> charge(Cart cart) => Result.Err(PaymentError.Declined("card expired"));`. It shows the same call, and the checker then reads all of it.
- `sharp/bin/spec` learns a `…` that opens a body before other statements. That changes the guarded checker in a PR of its own, and still leaves `reason` undeclared.

## Existing work, and how the Slice reuses it

Every open and closed PR of php-sharp and of `heyJordanParker/mago-sharp` was searched by title and body for `Result`, `try`, propagation and the `Error` interface (`gh pr list --state all --search`). All php-sharp commits were searched by message (`git log --all -i -E --grep "try operator|Result<|Result\.Err|Error interface|expected failure|section 31|§31|propagat"`). The five php-sharp branches not merged into master (`feat/built-in-types`, `feat/hosts`, `feat/slice-lazy-and-types`, `feat/slice-traits`, `fix/spec-elided-accepted`) were searched by diff (`git log -G "Result|enum [A-Za-z]+<|Expression::Try|TryExpression|interface Error|Sharp\\Error"` over the parser, the codex, the library and the spec). mago-sharp's branches were checked by name only, and none names any of this.

No branch or PR builds `Result`, a generic enum, a `try` expression or an `Error` interface. The one diff hit, `2fa96c1313` on `feat/built-in-types`, matched Rust's own `Result` type in a parser change for class casts. The work this Slice builds on:

- **#131, pattern matching and enums with data, merged.** An enum with a data case is an abstract class with a final class per case (`register_case_classes`, `data_enum`), its cases are its `permitted_inheritors`, `PaymentResult.Declined(reason: x)` calls a static factory, case patterns test `instanceof`, and `missing_cases` and `handled_test` decide coverage. `Expression::Leave` and `ZEND_SHARP_LEAVE` leave a method from inside an expression. `Result` is one more such enum, and `try`'s failure branch is one more RETURN node in an expression.
- **#102, generics, merged.** A generic class's type parameters (`scan_type_parameters`), its class metadata for the engine (`class_metadata`), type arguments around a `new` (`SHARP_TYPE_ARGS`), a static method that takes its class's type arguments as its leading own ones (R4 part 1), inference of a generic call from its arguments and its declared type (`expect_type`, `report_unfixed_type_arguments`), and the entry check of a generic object from plain PHP. A generic enum reuses each.
- **#133, inheritance and interface default bodies, merged.** An interface declares a property, `string designColumn { get; }`, and a class implements it with a computed one. `Error` is such an interface.
- **mago-sharp #179, merged.** The default names a `.sharp` file reads from `Sharp` (`shadowable_default_classes`, `default_class`), and the file's own namespace winning over them. `Result` and `Error` join that list.
- **`57f9f013a3`, "feat: skip check-throws in PHP# files, which declare no exceptions", merged.** Line 4054 holds through it. `check_throws_skips_sharp_files_and_keeps_reporting_in_php` tests it.
- **#141, built-in types, open.** The Slice builds nothing on it. `Any?`, the bound of an unbounded `T`, is on master since mago-sharp #26. The PR lands after #141, as the landing order says, and the merge then runs the spec again.

## How the states were observed

Every state below comes from a release `mago` and a debug engine built in this worktree from master `39b305352e`, with `git status` clean:

```sh
CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --locked --release -j 2 \
  --manifest-path sharp/mago/Cargo.toml --target-dir sharp/build/darwin-arm64/mago --bin mago
CARGO_INCREMENTAL=0 CARGO_BUILD_JOBS=2 MAKEFLAGS=-j4 sharp/bin/build
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

The spec run ended "Checked 201 examples, ignored 1, and 25 fail.", matching `sharp/baseline/spec`. Its section 31 lines are quoted under each rule.

Every rule without an example of its own was probed in one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-result/ws`: the harness and the standard library laid out as the spec run lays them out, with one probe class per question in `app/Probes/`. `mago compile` checked it with `MAGO_REPORTING_FORMAT=emacs`, and the engine ran the compiled probes through `run.php`, a PSR-4 autoloader that also finds a case class through its enum's file, and the plain PHP driver `drive-master.php`. Three plain PHP files beside it, `php-enum-property.php`, `php-enum-interface.php` and `php-exception-error.php`, show what PHP itself refuses. Each message and value quoted below is that run's output.

# Every rule of section 31, its state and its mechanism

The states are **works**, **refused** (an error that names the feature as missing, or a parse error), **silently accepted** (code the spec refuses compiles), and **wrong error** (refused, but not for the spec's reason). The mechanism is what the Slice changes: **checker** is `sharp/mago/` up to the analyzer, **bridge** is `crates/sharp-bridge`, **library** is `sharp/composer/library/Sharp/`, **engine** is `ext/sharp` and `Zend/`. "none" means the rule holds on master and the Slice keeps it under test.

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| F1 | 4024: an expected failure is returned as a value; an exception means a bug and fails loudly | the value half waits on R1 to R4; an exception passes through PHP# code: works | none for the exception; R1 to T3 for the value | |
| R1 | 4028–4032: `Result<T, TError : Error>`, an enum with the cases `Ok(T value)` and `Err(TError error)`, from the standard library | refused: parse error at `<` | checker (parser, codex, semantics), bridge, library | the class header's type parameters, `register_case_classes`, `data_enum`, `class_metadata` |
| R2 | 4028: `TError`'s bound is `Error` | refused with R1 | none beyond R1 | `check_template_arguments` |
| R3 | 4034: `Result.Err(PaymentError.Declined(reason))` makes a case; the declared type and the argument fix the type arguments | a case made by position works; a generic class's static call without type arguments is refused | checker (codex, analyzer), bridge | generic call inference, `expect_type`, R4 part 1 |
| R4 | 4037: `match`, `is` and the exhaustive-case check work on `Result` unchanged | refused with R1; on a data enum works; a bare generic class in a pattern is refused | checker (analyzer) | `php_shape`, `missing_cases`, `handled_test` |
| R5 | 4037 with section 20, line 2235: two cases are `==` when the same case has equal fields | refused with R1 | checker (analyzer), bridge | `value_equality`, `\Sharp\Internal\equals` |
| T1 | 4039: `try` gives the `Ok` value | refused: parse error at `try` | checker (parser, analyzer), bridge | `Expression::Leave`'s footprint |
| T2 | 4039: on `Err`, the enclosing method returns that `Err` immediately (Q1) | refused with T1 | checker (analyzer), bridge; engine none | `ZEND_SHARP_LEAVE` |
| T3 | 4039, implied: `try` takes a `Result`, in a function that returns one whose error type holds the failure (Q2) | refused with T1 | checker (analyzer) | `invalid-operand`, `invalid-return-statement` |
| T4 | 4052: `try`/`catch` blocks remain for exceptions, such as wrapping a plain PHP exception | works | none; the parser keeps `try {` a statement | |
| T5 | 4054: methods declare no thrown exceptions, and the checker reports no undeclared one | works | none | `check_throws`, `57f9f013a3` |
| E1 | 4056: every failure type implements `Error`; one handler renders any failure | refused: no `Error` | library, checker (names) | an interface property (#133) |
| E2 | 4061: `public interface Error { string message { get; } }` in `Sharp` | refused: "Cannot find type `Error`." | library, checker (names) | `default_class` |
| E3 | 4069–4074: `enum PaymentError : Error` with `public string message => match (this) { … };` (Q3) | refused: "Enum `PaymentError` cannot have properties." | checker (semantics, codex), bridge, Composer plugin | `data_enum`, the class property lowering |
| E4 | 4077: inside `.sharp` files, `Error` always means this interface (Q4) | refused: "Cannot find type `Error`." | checker (names) | `default_class` |
| E5 | 4077: PHP's engine class `Error` is never named in PHP# code (Q4) | silently accepted: `import Error;` and `catch (Error failure)` compile | checker (semantics, analyzer) | `invalid-catch-type` |
| E6 | 4077: bugs are caught as `Throwable`; `TypeError` keeps its name | works | none | |

Section 20's rules apply to `Result` as to any enum: each case is its own type (Q5), at runtime each case is a real class, and the checker rejects a `match` that misses a case (R4).

## F1, T4, T5 and E6: what holds on master

`Payments.sharp` holds them, run by `drive-master.php`:

- A plain PHP `RuntimeException` thrown by `Legacy::boom()` inside the PHP# `passesThrough()` reached the plain PHP caller: `propagated: boom from plain PHP` (F1).
- `wraps()` caught it in a `try`/`catch` and returned a value: `\App\Probes\Payment\Declined::__set_state(array('reason' => 'boom from plain PHP'))` (T4).
- `Undeclared.sharp` throws from two methods, one with a `@throws` docblock and one without, and its caller declares nothing. The compile reported no issue on the file (T5). The docblock is a comment, and the Slice leaves it so.
- `ImportError.sharp`'s `catch (TypeError wrong)` and `catch (Throwable other)`, after `import TypeError;` and `import Throwable;`, compiled with no issue (E6).

## R1 and R2: a generic enum

"`public enum Result<T, TError : Error>`", with "`case Ok(T value);`" and "`case Err(TError error);`".

Refused. The spec run printed, for `result-enum`:

```text
docs/spec.md:4028 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `LeftBrace`, found `LessThan`
docs/spec.md:4029 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `LeftBrace`
```

and parse errors on every line after it. `GenericEnum.sharp`, `enum GenericEnum<T> { case Some(T value); case None; }`, gave 15 parse errors.

- **Parser:** `Enum` gains `type_parameters: Option<TypeParameterList>`, as `Class` and `Interface` have it. `parse_enum_with_attributes_and_modifiers` reads it after the name in a `.sharp` file, with `parse_optional_type_parameter_list`.
- **Codex:** `register_enum` passes the list to `scan_class_like`, as `register_class` does. `register_case_classes` gives each case class the enum's type parameters and the header `Result<T, TError>`, types a field by them, as `Ok`'s `value` is `T`, and gives each factory the enum's type parameters as its leading own ones, as R4 part 1 gives a static method of a generic class.
- **Semantics:** the type parameters get the checks a class's get (`check_type_parameters`). A backed enum with type parameters is a compile error (Q6).
- **Bridge:** `data_enum` writes the enum's `class_metadata` and each case's, so the engine knows `Sharp\Result`'s bounds, `Any?, Sharp.Error`, and that `Ok` and `Err` pass their type arguments to `Sharp.Result<$0, $1>`. A field typed by a type parameter erases to its bound's PHP type and keeps its PHP# type text, as any generic property does.
- **Library:** `sharp/composer/library/Sharp/Result.sharp` declares the enum as line 4028 writes it. `Result` joins `shadowable_default_classes`, so a class of the file's namespace still wins (section 23), as `result-enum`'s own `App.Result` does.
- **Engine:** none. A generic abstract class with generic final subclasses is what #102's headers run.
- **R2** holds through `check_template_arguments`: `Result<Order, string>` breaks `TError`'s bound.

## R3: making a case

"`return Result.Err(PaymentError.Declined(reason));`" and "`return Result.Ok(order.receipt());`".

The two parts were probed apart. A case made by position compiles and runs: `Payment.Declined(reason)` gave `Declined` with `'reason' => 'card'`. A static call on a generic class without type arguments is refused: `Holder.of(5)` in a method returning `Holder<int>` printed "`Holder` needs its type argument." and, beside it, "Holder<int> cannot be used as Holder<int>." (`Inference.sharp:5:34`).

- **Analyzer:** `resolve_static_method_targets` skips `report_missing_type_arguments` for the factory of a case of a generic enum. The call then fixes the enum's type arguments as any generic call fixes its own: `PaymentError.Declined(reason)` fixes `TError` as `PaymentError`, and the method's declared return type, which `expect_type` records, fixes `T` as `Order`. When nothing fixes one, `report_unfixed_type_arguments` reports it with section 11's CS0411 rule, naming the form Q5 sets. A bare static member of a generic class stays refused, as section 11 says.
- **Bridge:** the call lowers to `new \Sharp\Result\Err(…)` inside a `SHARP_TYPE_ARGS` holding the fixed type arguments, as `new PaginatedList<Order>(…)` lowers.
- **Spec:** line 4034 changes as Q7 proposes.

## R4 and R5: `match`, `is`, coverage and `==` on `Result`

"`Result` is an ordinary enum with data from the standard library, so `match`, `is` and the exhaustive-case check work on it unchanged."

On a data enum this works on master: `Payments.describe` printed `'declined card'` for a `match` with the arms `Payment.Declined(string reason)` and `Payment.Expired` and no `default`. On a generic class, a pattern without type arguments is refused: `value is Holder h` printed "`Holder` needs its type argument." (`GenericPatterns.sharp:5:44`).

- **Analyzer:** a case pattern of a generic enum, `Result.Ok(Order order)` or `Result.Err e`, takes the subject's type arguments. `e` is then a `Result<Order, PaymentError>.Err` (Q5), and a field's declared type is substituted before `handled_test` reads it, so `Result.Ok(Order order)` holds every `Ok` of a `Result<Order, PaymentError>` and two such arms cover the `match`. On a subject that names no type arguments, as an `Any`, the pattern writes them (Q5).
- **`==`:** section 20's rule reads each field's type with the type arguments substituted. `Result<int, PaymentError>` has `==`, and `Result<Order, PaymentError>` has none, since `Order` declares none (section 19). The bridge's `op_Equality` compares a field typed by a type parameter with `\Sharp\Internal\equals`, the engine's section 19 equality.
- **Engine:** none. The test is `instanceof` and a property read.

## T1, T2 and T3: `try`

"**`try` passes a failure up to the caller.** If the call returns `Err`, the enclosing method returns that same `Err` immediately. Otherwise `try` gives the `Ok` value."

Refused. The spec run printed, for `try-propagation`:

```text
docs/spec.md:4047 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `Try`
```

`TryOperator.sharp`, `const doubled = try value;`, gave three parse errors on the line.

- **Parser:** in a `.sharp` file, `try` where an expression starts reads the new `Expression::Propagate(Propagate { r#try, value })`, at the precedence Q2 sets. At the start of a statement, `try` followed by `{` stays the `try` statement, and any other `try` starts an expression statement. A `.php` file parses as before.
- **Analyzer:** the operand's type must be a `Result`, or `invalid-operand` reports it. The enclosing function's return type must be a `Result` whose error type holds the operand's, or `invalid-return-statement` reports it, as a `return` of that `Err` would be reported. The expression has the operand's `T`. The failure branch counts as a return for the function's flow, as `?? return` does. A lambda takes the result type written for it, or the one the function type it is written for gives, and a lambda whose result type only its body gives is a compile error that says to write it.
- **Bridge:** the operand goes into a hidden local, as `$nullsafe#N` does, and `try` lowers to a conditional whose failure branch is a RETURN node: the new `Err` with the method's type arguments that Q1 proposes.
- **Engine:** none. `zend_compile_expr_inner` compiles a RETURN node anywhere in an expression as a leave, with `ZEND_SHARP_LEAVE` freeing the calls and temporaries the expression holds, and `zend_calc_live_ranges` splits each range it frees (Slice 21 decision 2). The tests run `try` inside a call's arguments, a template, a `try` with `finally` and a `catch`, in every opcache mode.

## E1 to E6: the `Error` interface

**E2:** "`public interface Error { string message { get; } }`" in the standard library's `Sharp`. A bare `Error` is refused today: `BareError.sharp:5:28`, "Cannot find type `Error`.". An interface with a property already works: `Payments.render(Failure failure) => failure.message` printed `'class'` for a class with `public string message => "class";`.

- **Library:** `sharp/composer/library/Sharp/Error.sharp` declares it as line 4061 writes it. It lowers to `interface Error { public string $message { get; } }` in `Sharp`, as #133 lowers an interface property.
- **Names:** `Sharp\Error` joins `default_class`'s fixed names, beside `Sharp\List` and `Sharp\Int`, so no class of the file's namespace wins over it (Q4).

**E3:** "`public enum PaymentError : Error`" with "`public string message => match (this) { … };`". Refused: the spec run printed `docs/spec.md:4073 expects no error, and the checker reports: Enum `PaymentError` cannot have properties.`. `CardFailure.sharp` (a data case and a plain case) and `MissingFailure.sharp` (one plain case) gave the same.

- **Semantics:** `check_enum` accepts a computed property, one with no storage and a `=>` or `get` body, on every enum in a `.sharp` file, and still refuses a stored one.
- **Bridge:** `r#enum` and `data_enum` lower each computed property as a class's computed property is lowered: a property with a `get` hook and its PHP# type text, so `PaymentError.Declined("x").message` runs the enum's `match`, and `Status.Active.label` reads a backed enum's.
- **Engine:** PHP refuses any property on an enum, at compile ("Enum %s cannot include properties" in `zend_compile_prop_decl`) and at link (`zend_verify_enum_properties`). A PHP# enum's property carries its PHP# type text, so `zend_compile_prop_decl` lets a property with one through on an enum, and `zend_verify_enum_properties` accepts a property flagged both `ZEND_ACC_VIRTUAL` and `ZEND_ACC_SHARP_TYPED`. A virtual property has no slot, so an enum case object keeps its `name` and `value` and nothing more. A plain PHP enum still refuses every property, as upstream PHP does. A backed enum stays a PHP backed enum, so `from`, `tryFrom` and Laravel's casts are unchanged.

**E1:** "Every failure type implements `Error`". The bound of R2 holds it for every `Result`. "One handler can then render any failure" is a method that takes an `Error` and reads `message`, which E2 makes work.

**E4 and E5:** "Inside `.sharp` files, `Error` always means this interface. PHP's global engine class `Error` is never named in PHP# code." Today a bare `Error` names nothing, and `ImportError.sharp`'s `import Error;`, `string engine(Error failure) => failure.getMessage();` and `catch (Error failure)` all compiled with no issue: silently accepted.

- **Semantics:** an import whose name is `Error` is a compile error (Q4).
- **Analyzer:** `catch (Error …)` is `invalid-catch-type` (Q4). Today a catch of an interface that is no `Throwable` compiles: `CatchFailure.sharp`'s `catch (Failure failure)` gave no issue. No exception can implement `Error`: the engine refused `class Failed extends \RuntimeException implements Error` with "Type of Sharp\Failed::$message must be omitted to match the parent definition in class Exception", and the checker already refuses `ThrownFailure.sharp`'s `class ThrownFailure : RuntimeException, Failure`.

## Plain PHP crossings

Each is a row of the engine tests, and each was run on master on the data enum `Payment`, which `Result` will be:

| Crossing | On master | Once built |
|---|---|---|
| A plain PHP caller receives a `Result` | plain PHP received `\App\Probes\Payment\Declined` with its public `reason` | it receives `Sharp\Result\Ok` or `Sharp\Result\Err` with its public readonly `value` or `error`, tests it with `instanceof`, and reads `$result->error->message` |
| A plain PHP exception thrown inside code that returns a `Result` | it reached the plain PHP caller | the same: no exception becomes an `Err`, and `try` catches nothing |
| Laravel code catches exceptions around a call that returns a `Result` | a `catch (\Throwable $e)` around `positional('card')` did not run: `returned App\Probes\Payment\Declined, no catch ran` | the `catch (\Throwable $e)` around a call that returns an `Err` does not run, and the `Err` is the call's value |
| Plain PHP makes a `Result` itself | | `new \Sharp\Result\Ok($order)` has the bounds `Any?, Sharp.Error` as its type arguments, and a PHP# parameter of type `Result<Order, PaymentError>` throws `TypeError` for it, as section 11 says of every raw generic object |
| Plain PHP implements `Sharp\Error` | | a class with `public string $message { get => …; }` is a failure type a PHP# `Result` holds |
| A `Result` in a queue or a cache | | `unserialize(serialize($err))` keeps its type arguments and is `==` to it |
| A plain PHP project's analysis | | unchanged: every parser change is behind `dialect.is_sharp()`, and the default names are read only in `.sharp` files |

# What changes

```text
sharp/mago/crates/
├── syntax/
│   ├── src/cst/cst/class_like/mod.rs      <- Enum.type_parameters, as Class and Interface have it
│   ├── src/cst/cst/expression.rs          <- Expression::Propagate(Propagate { try, value }), beside Leave
│   ├── src/cst/node.rs, src/walker/mod.rs <- Propagate and an enum's type parameters, walked in source order
│   ├── src/parser/internal/class_like/mod.rs
│   │                                      <- an enum's type parameters in a .sharp file
│   ├── src/parser/internal/expression.rs  <- `try` before a value in a .sharp file, at Q2's precedence
│   ├── src/parser/internal/statement.rs   <- `try` then `{` stays the statement; any other `try` starts an expression
│   ├── src/utils/control_flow.rs, utils/mod.rs, utils/reference.rs, utils/assignment.rs
│   │                                      <- Propagate's operand read as Leave's value is
│   ├── src/utils/pattern.rs               <- a case pattern's field tests read the object at the field, so two
│   │                                         fields never share a span
│   └── tests/sharp.rs                     <- parse rows (The tests)
├── names/src/lib.rs                       <- Sharp\Result among the shadowable default names, Sharp\Error among the fixed ones
├── names/src/internal/walker.rs           <- an enum case and a computed property each declare their own names
├── codex/src/scanner/class_like.rs        <- a generic enum, its generic case classes and factories; the enums that run as classes
├── semantics/
│   ├── src/internal/checker/sharp.rs      <- an enum's type parameters; none on a backed enum; Propagate in the slice;
│   │                                         an import named Error refused
│   ├── src/internal/checker/class_like/mod.rs
│   │                                      <- check_enum takes a computed property on an enum that runs as classes
│   └── tests/sharp.rs
├── analyzer/
│   ├── src/expression/mod.rs              <- Propagate: its operand, its function's return type, its type; a case
│   │                                         pattern of a generic enum takes the subject's type arguments; coverage
│   │                                         and == read a field's substituted type
│   ├── src/resolver/static_method.rs      <- a generic enum's case factory fixes its type arguments as a generic call
│   ├── src/statement/try.rs               <- catch (Error …) refused
│   └── tests/sharp.rs                     <- one test per rule, forms as rows (The tests)
├── sharp-bridge/
│   ├── src/lower.rs                       <- data_enum writes class metadata and properties; a case factory with its
│   │                                         type arguments; Propagate as a conditional with a RETURN node
│   ├── src/lower/types.rs                 <- the header and bounds of a generic enum's case classes
│   └── tests/lower.rs                     <- one tree per row of "The PHP the bridge emits"
└── formatter/, linter/, fingerprint/, hir/, sharp-lean/
                                           <- each exhaustive match on Expression takes Propagate: the formatter prints
                                              it, sharp-lean reports it unmodeled, the others treat it as its operand
sharp/composer/library/Sharp/
├── Result.sharp                           <- new: section 31's Result
└── Error.sharp                            <- new: section 31's Error
Zend/zend_compile.c                        <- zend_compile_prop_decl lets a property with a PHP# type through on an enum
Zend/zend_enum.c                           <- zend_verify_enum_properties accepts a virtual PHP# property
Zend/zend_execute.c, Zend/zend_vm_def.h    <- SHARP_LEAVE frees a `new` that is still live where the leave returns, as
                                              try's returned Err in a lambda needs
ext/sharp/sharp.c                          <- \Sharp\Internal\equals compares by PHP#'s ==, so a type parameter's
                                              value compares by its type argument's operator ==
sharp/harness/app/
├── PaymentError.sharp                     <- new: error-interface's PaymentError, with its message
├── CheckoutGateway.sharp                  <- new: try-propagation's gateway, charge(Cart) returning Result<Order, PaymentError>
└── Order.sharp                            <- gains receipt(), which try-propagation calls
Zend/tests/sharp/
├── Results.sharp, results.inc, results.phpt
│                                          <- rows: Ok and Err made with their type arguments, try's value and its
│                                             returned Err (ReflectionObject::getTypeArguments), try inside a call's
│                                             arguments, a template, a try with finally and catch; a match over every
│                                             case; ==; PaymentError's message; every plain PHP crossing above
└── results_preload.phpt, results_preload.inc
                                           <- the same Result classes and a try under opcache preloading
docs/spec.md                               <- section 31 states each answer; line 4034 as Q7 says; section 22's line
                                              2638 as Q3 says; section 23 names Error beside the default names that never shadow
sharp/baseline/spec/§31/                   <- error-interface, result-enum and try-propagation removed
docs/architecture/slices/31-result.md     <- this note
```

`sharp/bin/spec` and `sharp/harness/`'s guarded files do not change, so no PR goes first.

The PHP the bridge emits:

```text
abstract class Result implements … { public static function op_Equality(…) { … } }        <- public enum Result<T, TError : Error> { … }
  + class metadata: bounds "Any?, Sharp.Error"
final class Ok extends \Sharp\Result implements \Sharp\Struct {
    public function __construct(public readonly mixed $value) {} }                           <- case Ok(T value);
  + class metadata: header "Sharp.Result<$0, $1>", bounds "Any?, Sharp.Error"
final class Err extends \Sharp\Result implements \Sharp\Struct {
    public function __construct(public readonly \Sharp\Error $error) {} }                   <- case Err(TError error);
SHARP_TYPE_ARGS(new \Sharp\Result\Err(new \App\PaymentError\Declined('card expired')),
                "App.Order, App.PaymentError")                                              <- Result.Err(PaymentError.Declined("card expired"))
                                                                                               in a method returning Result<Order, PaymentError>
(($try#1 = $this->gateway->charge($cart)) instanceof \Sharp\Result\Err
    ? RETURN SHARP_TYPE_ARGS(new \Sharp\Result\Err($try#1->error), "App.Receipt, App.PaymentError")
    : $try#1->value)                                                                        <- try gateway.charge(cart), in a method returning
                                                                                               Result<Receipt, PaymentError> (a RETURN node in an
                                                                                               expression: no PHP spelling)
abstract class PaymentError implements \Sharp\Error {
    public string $message { get => …the match… } }                                         <- public enum PaymentError : Error { … message => … }
```

# Public names this Slice adds or changes

team-lead approves these. Everything not here is tactical.

- **`Sharp\Result`, with the cases `Ok` and `Err` and the fields `value` and `error`,** and **`Sharp\Error`, with `message`.** The spec names all of them. Plain PHP sees the classes `Sharp\Result\Ok` and `Sharp\Result\Err`, as Slice 21's question 6 names a case.
- **`sharp/composer/library/Sharp/Result.sharp` and `Error.sharp`,** new library files.
- **`sharp/harness/app/PaymentError.sharp` and `CheckoutGateway.sharp`,** new harness files, and `Order.receipt()`.
- **`Enum.type_parameters: Option<TypeParameterList>`,** a field on an existing node, named as on `Class` and `Interface`.
- **`Enum::runs_as_classes()`,** replacing `has_data_case()`: an enum runs as classes when a case carries data or the enum declares type parameters.
- **`Expression::Propagate(Propagate { r#try, value })`,** with `NodeKind::Propagate`, `Node::Propagate` and the walker's `walk_propagate`. The variant can't be `Try`, which the statement's struct already is. Its callsite is `Expression::Propagate(propagate) => self.propagate(propagate)` in the bridge. The slate:
  - `Propagate` — what happens to the failure; the spec's "passes a failure up", and Rust's reference calls `?` the "error propagation expression"; reads "the expression propagates (its failure)". Recommended.
  - `PassUp` — the spec's own words; a verb phrase, which no sibling node is. Runner-up.
  - `EarlyReturn` — the failure branch; reads "an early return", which misses the `Ok` value it also gives.
  - `TryValue` — the success branch, "the value `try` gives"; misses the return.
  - `Forward` — the failure goes to the caller; readers add different words, "forward (the value? the call?)".
  - `Unwrap` — removed: Rust's `unwrap` panics on `Err`, so a reader adds the wrong meaning.
  - `TryOperator` — removed: `Operator` is a declared `operator ==` member in the CST (`class_like/operator.rs`).
  - `TryExpression` — removed: it repeats `Expression::`.
- **No new issue code.** `try` reuses `invalid-operand` and `invalid-return-statement`, a case nothing fixes reuses `missing-template-parameter`, and `catch (Error …)` reuses `invalid-catch-type`.
- **No new engine function or opcode.** The engine's one change is that a PHP# enum may declare a virtual property (Q3).

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. The existing test for a rule grows a row before a new test is added, and each is seen failing before its code lands.

**`sharp/mago/crates/syntax/tests/sharp.rs`:** `an_enum_takes_type_parameters` (new): `enum Result<T, TError : Error>` parses into `Enum.type_parameters` in a `.sharp` file and stays a parse error in a `.php` file. `try_before_a_value_is_an_expression` (new): rows `try x` as a local's value, an argument, a statement, the left side of `??`, after `return`, and the reach Q2 sets; `try { … } catch` stays a statement; a `.php` file keeps PHP's parse.

**`sharp/mago/crates/semantics/tests/sharp.rs`:** a row each for an enum's type parameters named and bounded as a class's, a backed enum with type parameters refused, a computed property on an enum that runs as classes accepted and on a backed enum refused, a stored property refused on every enum, and `import Error;` and `import Vendor.Error;` refused with `import Vendor.Error as VendorError;` accepted.

**`sharp/mago/crates/analyzer/tests/sharp.rs`:**

- `result_is_an_enum_of_the_standard_library` (new): `Result<Order, PaymentError>` types; `Result<Order, string>` breaks the bound; `Result<Order>` has too few type arguments; a project's `App.Result` wins over `Sharp.Result`.
- `a_case_of_a_generic_enum_fixes_its_type_arguments_as_a_generic_call` (new): rows fixed by the declared return type, by the argument, by both, by a typed local, and by nothing, which is `missing-template-parameter` naming Q5's form; Q5's written form compiles; a bare static member of a generic class stays refused.
- `try_gives_the_ok_value_and_returns_the_failure` (new): `try` types `T`; a non-`Result` operand is `invalid-operand`; a method returning `int`, a `void` method and a property's initial value refuse it; an error type that fits passes and one that does not is `invalid-return-statement`; a lambda with and without a known result type.
- `a_match_over_a_generic_enum_is_covered_by_its_cases` (new): two case arms cover it; one missing is `match-not-exhaustive`; a `when` arm does not count; `Result.Ok(Order order)` narrows `order` to `Order`; `Result.Ok(int n)` on a `Result<Order, PaymentError>` never matches.
- `two_cases_of_a_generic_enum_compare_by_their_type_arguments` (new): `==` on `Result<int, PaymentError>` compiles, on `Result<Order, PaymentError>` is refused naming `Order`.
- `error_always_names_the_standard_library_interface` (new): a bare `Error` is `Sharp.Error` in a namespace that declares its own `Error`; `catch (Error e)` is `invalid-catch-type`; `catch (Throwable e)` and `catch (TypeError e)` compile.
- `an_enum_that_implements_error_gives_its_message` (new): a data enum, an enum of plain cases (Q3) and a class implement `Error`; a backed enum that implements it is refused.
- `check_throws_skips_sharp_files_and_keeps_reporting_in_php` grows a row with a `@throws` docblock in a `.sharp` file.

**`sharp/mago/crates/sharp-bridge/tests/lower.rs`:** one tree per row of "The PHP the bridge emits".

**Engine, `Zend/tests/sharp/`, in every opcache mode:** `sharp/bin/test --opcache --repeat --passes` on `results.phpt` and `results_preload.phpt`, and a `USE_ZEND_ALLOC=0 MallocScribble=1` loop of both, since the library's classes are shared under preloading.

**Plain PHP:** `mago analyze` of `build/gen_stub.php`, `run-tests.php` and the SDK's sources gives the same issues before and after, as #141 checks its corpus.

# What the Slice refuses as "not supported yet", and why

- **A law that reads a generic enum or `try`** (section 28). `sharp-lean`'s `Translator::r#enum` writes an enum as a Lean `inductive` with no parameters, and `try` has no Lean form yet. Both are reported through `Body::unmodeled`, as Slice 21 reports a law that reads a data case's field. Section 28 has no example of one, so no entry moves.

# Found outside the Slice

- **A bare static call of a generic class reports a second, wrong message.** `Holder.of(5)` in a method returning `Holder<int>` reports "`Holder` needs its type argument." and also "Holder<int> cannot be used as Holder<int>." with the help "T is both taken in and returned by Holder, so neither marker fits." (`Inference.sharp:5:34`). The first is section 11's rule. The second compares a type with itself. R3 changes the path for a case factory only, so a static method of a generic class keeps both.
- **`data_enum` writes no class metadata,** so an enum with a data case that implements a generic interface gives the engine no header. This was read in `lower.rs`, not run. R1's change writes the metadata for every enum that runs as classes, which covers it.

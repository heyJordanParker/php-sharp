# Slice 21: Pattern matching, enums with data, sealed interfaces and leaving on null

Spec section 21 (pattern matching), section 14.4 (null operators), section 20 (enums) and section 20.1 (sealed interfaces). team-lead approved the design of sections 21 and 14.4 at `ac74f1fec9`, with the answers recorded under "Answers in force". Sections 20 and 20.1 joined the Slice with those answers, and their design, under "Sections 20 and 20.1", waits for team-lead's approval before anyone builds it.

## The answer first

Most of section 21 and section 14.4 already works on master `f208b11e74`. `match` with constant, comparison, `and`, `or`, `not` and bare properties patterns, `is`, `is not`, narrowing, pattern scope, `as` to a scalar or a class, coverage of an enum, impossible patterns, `bool` conditions, `??`, `??=`, `?.`, `?? throw` and every needless null check compile, refuse and run as the spec annotates. 10 of section 21's 15 examples and 1 of section 14.4's 2 pass.

Nine rules do not hold. Each one is refused today except two, which are silently accepted:

1. **`?? return`, `?? continue` and `?? break` are parse errors** ("Unexpected token `Return`"). This is the one failing §14.4 example, `leave-on-null`.
2. **`f?.(x)` is a parse error** ("Expected one of `Variable`, … found `LeftParenthesis`").
3. **A type and its properties in one pattern, `Paid { amount: > 1000 } big`, is a parse error.** This fails `property-patterns`.
4. **A list pattern is refused** as "A list pattern is not supported yet in PHP#."
5. **A pattern that reads a local, a parameter, `this` or a static property is silently accepted** and compares at run time. The spec says a pattern takes types and constants only. This fails `pattern-locals`.
6. **A function type in `is`, `as` or an arm is refused** as "This type is not supported yet in PHP#." This fails `type-tests`.
7. **`switch` and PHP's `and`, `or` and `xor` are refused as "not supported yet".** The spec removes them, so each must be an error that names its replacement.
8. **`a ? b : c ? d : e` is refused with PHP's own error.** The spec says it means `a ? b : (c ? d : e)`.
9. **An enum case pattern is refused** as "An enum case pattern is not supported yet in PHP#." This fails `match-forms` and `is-and-as`. Their every line matches a `PaymentResult` data case, so section 20 joined the Slice (answer 1).
10. **An enum case that carries data is refused** as "A case that carries data is not supported yet in PHP#." This fails §20 `enum-data-cases` and `enum-shapes`.
11. **`sealed interface` is a parse error** ("Expected one of `Class`, found `Identifier`"). This fails §20.1 `sealed-interfaces`.

The Slice builds all eleven in one PR across `sharp/mago/`, the engine and the Composer package. Rules 3 to 5, 7 and 8 need only the checker and the bridge: every form lowers to PHP the engine already runs. Rule 1 needs a new engine opcode, because `?? return` stands wherever `?? throw` does (answer 2). Rule 6 needs the engine, on top of the type test #102 adds, so the branch merges `origin/feat/slice-generics` once #102 is on master. Rule 10 lowers each case to a PHP class, which the Composer package's autoloader finds through its enum's file.

When the Slice lands, every entry of §14.4, §20, §20.1 and §21 leaves `sharp/baseline/spec`, nine in all, and the PR writes `Completes: §14.4, §20, §20.1, §21`.

## Decisions team-lead approves

1. **The branch carries #102 once #102 is on master.** Until then everything but rule 6 is built on master. When #102 merges, `feat/pattern-matching` merges `origin/feat/slice-generics` and builds rule 6 on `ZEND_SHARP_IS_TYPE` and its type walk.
2. **`?? return`, `?? continue` and `?? break` stand wherever `?? throw` stands, and leave through a new engine opcode** (answer 2). The parser reads `return`, with its value when it has one, `continue` and `break` as the right side of `??`, into the new CST expression `Leave`, which holds the `Return`, `Continue` or `Break` statement it runs, without its `;`. Every check of those statements runs on it as it is: the semantic check's "must return a value" and "must not return a value", and the analyzer's return type check, its loop bookkeeping and its "Continue statement used outside of loop.". The analyzer runs a loop's condition, increments and a `do` loop's condition with that loop's scope in place, as its body, through `in_loop_scope`, so a `?? break` or `?? continue` there leaves that loop, as the engine compiles it, never an outer one. The analyzer types the leave `never`, so `a ?? return` has `a`'s type without null. The bridge lowers the statement in the place of the right side of `??`, as the RETURN, CONTINUE or BREAK node it lowers today, and `zend_compile.c` compiles those nodes in an expression as their statement with a `ZEND_SHARP_LEAVE` before its finally blocks and loop variables, so no new AST kind exists. `ZEND_SHARP_LEAVE` frees the calls the expression has started and the temporaries it holds, with the `cleanup_unfinished_calls` and `cleanup_live_vars` that exception unwinding runs, so `charge(order, prices[plan] ?? return)` leaves no half-built call behind. The statement's ops sit in place, inside the `try` ranges, so a `finally` runs and a `catch` sees a throwing return value. `zend_calc_live_ranges` splits each live range that covers the leave and outlives its RETURN or JMP, as php-src already splits a loop variable's range at an early return: the part before ends at `ZEND_SHARP_LEAVE`, which frees the ranges that end at it, and the part after starts past the RETURN or JMP, so no exception or GC there sees a freed temporary. Both JITs store a temporary whose range ends at `ZEND_SHARP_LEAVE`, and `sharp/bin/test --passes` runs every optimizer pass alone on it.
3. **`f?.(x)` is the new CST call `Call::NullSafeFunction(NullSafeFunctionCall)`.** A new variant, not a flag on `FunctionCall`, so every exhaustive `match` on `Call` in the formatter, linter, analyzer and bridge must decide what it does with it, and none treats it as a plain call by accident. The analyzer types it as a call of the callee's non-null type, with a nullable result, and reports `redundant-nullsafe-operator` on a callee that is never null. The bridge lowers it through `Lowering::null_safe_chain`, the precedent of `f9f478332`: `($nullsafe#N = f) === null ? null : $nullsafe#N(x)`, a local callee read as itself, and the rest of the chain skipped on null.
4. **A type pattern takes properties.** `TypePattern` gains `properties: Option<PropertiesPattern>`, between its `hint` and its `variable`, so `Paid { amount: > 1000 } big` is one pattern. `php_shape` tests the type on the named variable, or on the subject, and then each property on it, as the properties arm does today: `($big = x) instanceof Paid && $big->amount > 1000`. Decision 13 adds the positional fields of a case to the same node.
5. **A list pattern is the new `Pattern::List(ListPattern)`.** Each element is any pattern, and one `RestPattern`, `...List<Line> rest` or a bare `...`, stands anywhere among them (answer 3, then Jordan's ruling in Queue 3); a second rest is a compile error. `php_shape` lowers it on a `List` subject to PHP on the array: `\count(s) === 1 && ($only = s[0]) instanceof Line`, and with a rest `\count(s) >= n`, each element before the rest read as `s[i]`, each after it as `s[\count(s) - m]`, and a named rest at place `k` assigned `\array_slice(s, k, \count(s) - n)`, or `\array_slice(s, k)` at the end, and tested against its declared type as a typed element is: the checker says "This pattern never matches the value it tests." for a `List<T>` no element can be, and the engine tests the slice with the type's own test, so `...List<Circle> rest` on a `List<Figure>` holding a `Square` does not match. On a subject whose type is not a `List`, the first test is #102's `is List<T>`. The binder records `\count` and `\array_slice` as the names their spans call, as it records `is_int` today (`called_function`).
6. **A pattern takes types and constants only.** The semantic check refuses a type pattern whose bare name the binder binds to a local or a parameter, and a value or comparison pattern that reads a local, a parameter or `this`, with the spec's reason: "`limit` is a local: compare its value in `when`, as in `int n when n == limit`." The analyzer refuses `Class.y` when `y` is a static property, because only the codebase tells it from a constant. Reading (a) leaves the binder (`walk_in_type_pattern`), the CST doc of `TypePattern` and `php_shape`'s first `test` arm.
7. **A function type is tested like any other type.** The semantic check accepts `Function<R(P…)>` in `is`, `as` and an arm. The engine's `sharp_type_holds`, which on #102 held every value for `SHARP_TYPE_FUNCTION`, holds a `Closure` that could be assigned to the tested type: the parameter, result and effect rules of assignment, nothing looser (answer 4).
   - **Where the type lives:** each PHP# method and lambda carries its whole type, `Function<Charge(Cart) uses App.Http>`, in the new field `zend_op_array.sharp_type`, as `zend_property_info.sharp_type` carries a property's. It is engine data, not an attribute plain PHP sees in `getAttributes()`. The bridge wraps a declaration's result type in the `SHARP_TYPE_ARGS` a property's type uses, and `zend_compile_func_decl_ex` unwraps it with `zend_sharp_declared_type`, the renamed `zend_sharp_property_type`. Opcache persists it, sizes it and file-caches it, `destroy_op_array` frees it, and a call trampoline has none. A closure reads it from its function.
   - **What the bridge spells:** a method's declared parameter and result types, and a lambda's as the analysis gives them, written or inferred from the type it is written for, which `resolve_closure_like_type` now keeps. The effects are `Effects::function_effects` of the whole solve, sorted, which compile solves once and gives `check`. A function no function type can say has no type: one whose solve finds plain PHP no `extern` declares, or one with a type PHP# can't write, as the `never` of a lambda that always throws.
   - **What the engine compares:** `sharp_type_function_fits`, which `sharp_type_fits` also uses for a function type inside a type argument. The tested type lists each effect of the closure's, each tested parameter type is within the closure's, the closure's result is within the tested one unless that is `void` or `Any?`, and an extra parameter must be optional. Classes, unions and type arguments by the class's declared `in` and `out` compare through the existing `sharp_type_argument_takes`, so the engine agrees with the checker. The closure's `$i` is spelled for the object it is bound to and its `#i` for the method type arguments it captured.
   - **`sharp_holds_mode`** replaces `int_as_float`: `is`, `as` and an arm find no type on a closure plain PHP made, while a value entering PHP# from plain PHP keeps PHP's own `Closure` check, as raw generics do.
   - **The cache:** a function's effects come from the bodies it calls, in any file. `Unit::function_types` lists each type the file spells, `Reads::function_types` puts them in the key, and `Effects::callee_files` makes each file whose bodies a call reaches an input, along every chain of calls.
   - The analyzer refuses a test of a function whose source declares no signature, such as a plain PHP method returning `Closure`, with the new issue code `unknown-function-type`: "The type of `Legacy.doubler()`'s function is unknown: its source declares no parameter or result types."
8. **`switch`, `and`, `or` and `xor` are errors that name their replacement,** as `?:` is today: "PHP# has no `switch`: write `match`." "PHP# has no `and`: write `&&`." "PHP# has no `or`: write `||`." "PHP# has no `xor`: write `!=` between two `bool`s." Each stops being "not supported yet", because the spec removes them.
9. **The PHP# parser reads `?:` right-associatively,** as C#, Java, TypeScript, Swift and Dart do, so `a ? b : c ? d : e` is `a ? b : (c ? d : e)`. `check_conditional` loses its nested-condition arm, which a right-associative parse never reaches: a ternary as the condition of another is then always written in parentheses. A `.php` file parses as before. The bridge already lowers a conditional in the else branch, as `Operators.sharp`'s `nested` runs today with its parentheses written.

10. **A type pattern without a type takes no name** (answer 5). `Paid { amount: > 1000 }` compiles. `{ status: 200 } r` is a compile error: "A pattern creates a variable only through a typed declaration: write the type, as in `Response { status: 200 } r`." The parser reads the name, so the semantic check names it.
11. **Decision 041 stops citing the pattern rule.** It states its bare-name rule for `typeof` alone.

Decisions 12 to 17, for sections 20 and 20.1, are under "Sections 20 and 20.1".

One new engine opcode, `ZEND_SHARP_LEAVE`. One new issue code, `unknown-function-type`, beside the analyzer's other `unknown-…-type` codes. No new crate.

## Answers in force

team-lead answered the five questions of `ac74f1fec9`:

1. **`Completes:`** — the PR completes §21 and §14.4, so it builds sections 20 and 20.1 too, and adds `PaymentResult` to the harness. `Completes: §14.4, §20, §20.1, §21`.
2. **`?? return`, `?? continue`, `?? break`** — they stand wherever `?? throw` stands, because the spec lists them as one form. The Slice builds the engine opcode that leaves mid-expression (decision 2).
3. **List patterns** — each element is any pattern, because patterns compose. Jordan ruled that a rest stands anywhere, once per pattern, as in Rust, C# and Python: `[...List<Line> before, Line last]` and `[Line first, ..., Line last]`.
4. **`is Function<…>`** — a value `is T` exactly when it could be assigned to `T`: the parameter, result and effect rules of assignment, nothing looser. A PHP# lambda's closure carries its effects for the engine to compare.
5. **Names on a properties pattern** — `Paid { amount: > 1000 }` with no name compiles. `{ status: 200 } r` is a compile error that says to write the type (decision 10).

Also: decision 041 is updated in this PR, and the branch merges `origin/feat/slice-generics` only once #102 is on master.

## Questions for team-lead on sections 20 and 20.1

Each decides what a user writes or what plain PHP sees. Each has a proposal, which the section 20 design assumes.

6. **The PHP name of a case, and how plain PHP makes one.** Spec lines "Each case is its own type, so a method can take `PaymentResult.Paid` alone." and "At runtime, each case is a real class, so Reflection sees it."
   - **Proposed:** `PaymentResult.Paid` in namespace `App` is the PHP class `App\PaymentResult\Paid`, a `final` subclass of the `abstract` class `App\PaymentResult`. That is the name PHP#'s own `.` already resolves `PaymentResult.Paid` to, as a type and in `is`. Plain PHP writes `new PaymentResult\Paid(transactionId: 'tx')`. A plain case of a mixed enum, `CheckoutState.Open`, is the one instance of `App\CheckoutState\Open`, which plain PHP reads as `CheckoutState\Open::instance()`. A class `Paid` in the namespace `App.PaymentResult`, in `app/PaymentResult/Paid.sharp`, is a compile error, because it would take the case's name.
   - Kotlin's JVM name instead, one flat class per case such as `App\PaymentResult_Paid`, which no PHP# name resolves to.
7. **`==` on two data cases.** Answered: two data cases are `==` when they are the same case and every field is `==`, as two structs are, built on #123's struct `==`.
   - **Built:** the codex gives an enum with a case that carries data the `op_Equality` it gives a struct with none declared, and the bridge writes it into the enum's abstract class: `if ($a instanceof \App\PaymentResult\Paid && $b instanceof \App\PaymentResult\Paid) { return …each field's ==…; }` per case, then `return $a === $b;`. A plain case of such an enum is a case with no fields, whose branch returns `true`, so the copy `unserialize` makes of it, which a queue or a cache gives back, is `==` to it. A struct's `op_Equality` is the same method with one class, so one lowering writes both. The analyzer compares a data case as an enum case (`Comparand::Enum`), so `===` and an ordering stay refused as on any enum case, and refuses `==` on an enum whose case holds a field `==` can't compare, naming the field, as it refuses a struct's.
   - **`Map` and `Set`:** every case of such an enum implements `\Sharp\Struct`, so the engine hashes it by its class and fields, as it hashes a struct, and its `op_Equality` finds an equal key. A case that holds a field with no `==` can't key a `Map` or join a `Set`, as a struct can't. This widens #123's ruling 18, "only a lowered struct implements `Sharp\Struct`", to a case of an enum with data, a value compared by its fields; the engine's check (a final class from a `.sharp` file) already accepts it.
   - **`readonly`:** spec section 13's "A `readonly` that can't matter is a compile error, … before a type whose value never changes, … an enum, a struct" decides it, so `readonly PaymentResult`, `readonly PaymentResult.Paid` and `readonly List<CheckoutState>` are refused like a struct, with "A `PaymentResult` never changes, so `readonly` changes nothing: write `PaymentResult`."

## Questions for team-lead, answered

The questions of `ac74f1fec9`, kept for the record:

1. **The PR cannot write `Completes: §21`.** Jordan's definition of done says the PR completes sections 21 and 14.4. The ruling in force says a pattern over an enum data case stays refused until the section 20 Slice. `match-forms` and `is-and-as` match nothing but `PaymentResult` data cases, so both stay listed, and `COMPLETES` fails a claim on a section that keeps an entry. The readings:
   - **Proposed:** the PR writes `Completes: §14.4`, builds every other §21 rule, and the section 20 PR writes `Completes: §21` when it builds the case patterns (spec lines "case unpacked by position" and "whole case, named") and the harness's `PaymentResult`.
   - This PR also builds section 20's data cases and sealed interfaces. That reverses the ruling and doubles the Slice.
2. **Where `?? return`, `?? continue` and `?? break` may stand.** Spec line "`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`: leave when `a` is null." Its example writes them only as the whole value of a typed local. PHP has no expression that leaves a frame, so the engine cannot leave in the middle of a call whose arguments are half pushed.
   - **Proposed:** only as the whole value of a local's declaration (`int price = a ?? return;`, `let`, `const`), of an assignment to a local (`price = a ?? return;`), or of an expression statement (`a ?? return;`). Anywhere else it is a compile error: "`?? return` stands only as the whole value of a declaration, an assignment to a local or a statement." In those three places, running `a` first changes no order of evaluation. Kotlin's `?: return` and Rust's `let … else` are the precedents for the meaning; Swift's `guard` for a statement-level form.
   - Anywhere `?? throw` may stand, as Kotlin allows. This needs a new engine node that frees the half-built call frames and temporaries of the expression around it before it leaves.
3. **What a list pattern holds.** The spec's table gives `[] =>`, `[Line only] =>` and `[Line first, ...List<Line> rest] =>`, and no example.
   - **Proposed:** each element is any pattern (`[200, int code]`), as in C#. At most one rest, anywhere in the list, written `...List<T> name` or a bare `...` that names nothing (`[Line first, ...]`). A list pattern matches only a `List`. On a `Map` or a `Set` it never matches, which is the existing "This pattern never matches the value it tests." C#'s list patterns (`[var first, .. var rest]`) are the precedent.
   - The narrow reading: only typed elements and a typed rest last, exactly as the table writes them.
4. **What `value is Function<int(int)>` compares.** Spec line "`value is Function<int(int)>` tests the function's parameter and result types while the code runs".
   - **Proposed:** each parameter type and the result type must equal the tested one, as Go's type switch and a C# delegate of value types do. Effects are not compared, since the spec names only parameter and result types. A plain PHP `Closure` that reaches PHP# as `Any?` at run time with untyped parameters does not match, because an untyped parameter is `Any?`, not `int`.
   - Variance, as Swift's `is (Int) -> Int`: a `Function<int(Any?)>` also matches `Function<int(int)>`.
5. **A name after a bare properties pattern, and a type with properties but no name.** The spec writes `{ status: 200 }` and `Paid { amount: > 1000 } big`.
   - **Proposed:** both `{ status: 200 } response` and `Paid { amount: > 1000 }` are allowed, as C# allows `{ Status: 200 } r` and `Paid { Amount: > 1000 }`.
   - Only the two forms the spec writes.

## The order #102 and #123 set

#102 lands `is` and `match` on type arguments. #123 lands struct `==`. Both are on master. Section 20 is now this Slice's.

**Rules that depend on #123:** `==` on a data case (question 7), built.

**Rules that depend on #102:**

- "Every type answers `is`, `as` and `match` the same way, function types included." #102 builds `Any`, a union, a `List`, a `Map`, a `Set`, a generic class and a type parameter. This Slice builds the function type on #102's type walk (decision 7). `type-tests` passes only after both.
- "A test whose type the value's source never declares is a compile error." It needs the function type test (decision 7).
- "`as` to a collection type checks every element." #102 builds it whole: its `value as Map<string, float>?` walks the elements. This Slice adds nothing.
- A list pattern on a subject that is not a `List`, which needs #102's `is List<T>` (decision 5). On a `List` subject it needs nothing from #102.

**Rules that depend on section 20, now built here:** "case unpacked by position" and "whole case, named" (`match-forms`, `is-and-as`), and `match` coverage of a sealed interface.

**Rules that depend on neither:** every other line.

## Existing work, and how the Slice reuses it

Every open and closed PR and every branch of php-sharp and of `heyJordanParker/mago-sharp` was searched for list patterns, a type with properties, `?.(…)` and `?? return`, `continue` and `break`: by title, by commit message (`git log --grep`) and by diff (`git log -G "ListPattern|PropertiesPattern|parse_primary_pattern|\?\? return|\?\.\("` over each unmerged branch). No branch holds any of the four. The work this Slice builds on:

- **mago-sharp #22 (`feat/slice-pattern-matching`), #23, #27, #31, #38, #89, #119, #132 and php-sharp #44, all merged.** They built `is`, `as`, `match`, `php_shape` and the refusals the parser makes today, including `parse_primary_pattern`'s `skip_balanced` for a list and `skip_case_pattern` for an enum case. The Slice replaces those two skips with real parses and extends `php_shape`, `analyze_php_shape` and `Lowering::pattern`, adding no second path.
- **mago-sharp `f9f478332`, "lower a null-safe method value or property call as a conditional".** `Lowering::null_safe_chain` and `$nullsafe#N` are the whole lowering of `f?.(x)` (decision 3).
- **mago-sharp #14 and php-sharp #37 (`feat/null-checks`), merged.** They built every needless null check of section 14.4, which `f?.(x)` and `?? return` reach through the existing `redundant-nullsafe-operator` and `redundant-null-coalesce`.
- **#102 (`feat/slice-generics`), open.** Its `type_test_of`, `is_engine_tested`, `ZEND_AST_SHARP_IS_TYPE`, `ZEND_SHARP_IS_TYPE`, `sharp_type_holds` and `ZEND_SHARP_RECV_TYPE_ARGS` are what decisions 5 and 7 build on. Its PR body records Jordan's ruling that "a function type is tested like any other type, and a test whose type the value's source never declares is a compile error. Until then `value is Function<int(int)>` stays refused." This Slice is that "then".
- **Decisions 002 (ternary), 020 (`is not` scope), 021 (leaving early after `??`), 044 (`is` precedence) and 045 (`not` in patterns).** 020, 044 and 045 are built. 021 is decision 2 and question 2.
- **Unmerged branches with no overlap:** mago-sharp `feat/slice-lazy-and-types` (`yield`, `Iterable`), `feat/slice-traits` (one parking commit), `feat/sharp-function-effects` (one commit, merged in another form as #122), and php-sharp `feat/slice-events`, `feat/slice-structs`, `feat/foreign-classes` and `feat/plain-php-calls`. None changes a pattern, `??` or `?.`.

## How the states were observed

Every state below comes from a release `mago` and a debug engine built in this worktree from master `f208b11e74`, with `git status --short` empty:

```sh
CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --locked --release -j 2 \
  --manifest-path sharp/mago/Cargo.toml --target-dir sharp/build/darwin-arm64/mago --bin mago
CARGO_INCREMENTAL=0 CARGO_BUILD_JOBS=2 MAKEFLAGS=-j4 sharp/bin/build
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

The spec run ended "Checked 180 examples, ignored 1, and 82 fail.", matching `sharp/baseline/spec`. Its section 21 and 14.4 lines are quoted under each rule.

Every rule without an example of its own was probed in one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-patterns/ws`, a copy of the spec run's `harness` project with one probe class per rule group (`NullOps`, `NullCall`, `LeaveThrow`, `LeaveReturn`, `LeaveLoop`, `LeaveReturnWrong`, `NeedlessNull`, `MatchBasics`, `MatchCoverage`, `SwitchGone`, `ListPattern`, `PropertyTyped`, `PropertyPlain`, `PatternNames`, `PatternValues`, `IsAs`, `Narrowing`, `AsCollection`, `FunctionTests`, `OtherTypeTests`, `BoolOps`, `ReturnShapes`), and a plain PHP `Show::value` that prints with `var_export`. `mago compile` checked it, and the engine ran each compiled probe through a class-map runner, `run.php`, beside it. Each message and value quoted below is that run's output.

# Every rule of sections 21 and 14.4, its state and its mechanism

The states are **works**, **refused** (an error that names the feature as missing, or a parse error), **silently accepted** (code the spec refuses compiles), and **wrong error** (refused, but not for the spec's reason). The mechanism is what the Slice changes: **checker** is `sharp/mago/` up to the analyzer, **bridge** is `crates/sharp-bridge`, **engine** is `ext/sharp` and `Zend/`. "none" means the rule holds on master and the Slice only keeps it under test.

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| N1 | `a ?? b` | works | none | `Nulls.sharp`, `null_coalesce.phpt` |
| N2 | `a ??= b` | works | none | `Nulls.sharp`, `null_coalesce_assign.phpt` |
| N3 | `a?.b` | works | none | `null_safe.phpt` |
| N4 | `f?.(x)` calls `f` only when it is not null | refused: parse error | checker (parser, analyzer), bridge | `null_safe_chain` (decision 3) |
| N5 | `a ?? throw …` | works | none | |
| N6 | `a ?? return`, `?? continue`, `?? break`; `return` takes a value when the method returns one | refused: parse error | checker (parser, analyzer, semantic check), bridge, engine | the analysis of `throw`, exception unwinding's cleanup (decision 2) |
| N7 | a null check, `?.` or `??` on a value whose type has no `?` is a compile error | works | none, and N4 and N6 inherit it | `redundant-comparison`, `redundant-nullsafe-operator`, `redundant-null-coalesce` |
| N8 | a nullable return type on a method that never returns null is a compile error | works | none | `overly-wide-return-type` |
| P1 | `match` is the only branching construct on a value; `switch` is removed | wrong error: "This statement is not supported yet in PHP#." | checker | the `?:` error's form (decision 8) |
| P2 | `match` works as an expression and, with block arms, as a statement | works | none | |
| P3 | it compares strictly and never falls through | works | none | `match (true)` lowering |
| P4 | arms are `pattern => value,` and `pattern => { … },`, tried top to bottom; a statement takes block arms | works | none | |
| P5 | on an enum, arms that handle every case need no `default`; a missed case is an error that names it | works | none | `report_unhandled` |
| P6 | on a sealed interface, the same holds for its implementers | refused: needs `default` | section 20.1 | |
| P7 | on any other value, `match` must have a `default` arm | works | none | `check_pattern_match` |
| P8 | an arm with `when` does not count toward covering a case | works | none | |
| P9 | constant pattern `200 =>`, `Status.Paid =>` | works | none | |
| P10 | comparison pattern `< 1000 =>`, `>= 1000 and < 10000 =>` | works | none | |
| P11 | case unpacked by position `PaymentResult.Paid(string transactionId) =>` | refused: "An enum case pattern is not supported yet in PHP#." | section 20 | |
| P12 | whole case, named `PaymentResult.Paid p =>` | refused: same | section 20 | |
| P13 | properties pattern `{ status: 200, body: string body } =>` | works | none | |
| P14 | list pattern `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>` | refused: "A list pattern is not supported yet in PHP#." | checker (parser, binder, `php_shape`), bridge; #102 on a non-`List` subject | `php_shape`, `called_function` (decision 5) |
| P15 | a pattern takes types and constants only; a local's value is compared in `when` | silently accepted | checker (semantic check, analyzer) | binder's local binding (decision 6) |
| P16 | a bare name in a pattern is a type | works when no local has that name; P15 otherwise | checker, with P15 | |
| P17 | a pattern creates a variable only through a typed declaration | works | none | |
| P18 | `and`, `or` and `not` combine patterns, and exist only inside patterns | works inside; outside see P34 | checker, with P34 | |
| P19 | `when` adds a condition, an ordinary expression | works | none | |
| P20 | a property pattern matches only the properties it lists, and can nest | works | none | |
| P21 | it can test the type at the same time, and bind: `Paid { amount: > 1000 } big` | refused: parse error | checker (parser, binder, `php_shape`), bridge | the properties arm of `php_shape` (decision 4) |
| P22 | `is` tests a value against any pattern and creates its variables | works for every form above | none | |
| P23 | after `is` without a name, a local or parameter counts as the tested type; assigning to it ends that | works | none | |
| P24 | properties are not narrowed | works | none | |
| P25 | a variable `is` creates exists only where the test held, and after an always-exiting `if` for `is not` | works | none | |
| P26 | a negative test is `is not`; `!entity is HasDesign` is a compile error | works | none | |
| P27 | in a pattern, `not` beside `or` needs parentheses | works | none | |
| P28 | `as` converts a value to a type, or gives null | works for a scalar or a class; other types refused | #102 | |
| P29 | `as` to a collection type checks every element | refused: "This type is not supported yet in PHP#." | #102 | |
| P30 | `x is int?` is a compile error | works | none | |
| P31 | a pattern that can never match is a compile error | works | none | `impossible-type-comparison` |
| P32 | every type answers `is`, `as` and `match` the same way, function types included | scalar and class work; the rest refused | #102 for the rest; checker and engine for function types | `sharp_type_holds` (decision 7) |
| P33 | a test whose type the value's source never declares is a compile error | wrong error: "This type is not supported yet in PHP#." | checker (analyzer) | decision 7 |
| P34 | `&&`, `||` and `!` exist only in expressions; PHP's `and`, `or` and `xor` are removed | wrong error: "This operator is not supported yet in PHP#." | checker | decision 8 |
| P35 | conditions are `bool` in `if`, `while`, `do … while`, `for`, `? :`, `&&`, `||`, `!` and `when` | works | none | |
| P36 | the ternary `c ? a : b` | works | none | |
| P37 | PHP's `a ?: b` does not exist | works | none | |
| P38 | `a ? b : c ? d : e` means `a ? b : (c ? d : e)` | wrong error: PHP's "Unparenthesized …" | checker (parser, semantic check) | decision 9 |

The examples: `match-coverage`, `bare-name-patterns`, `narrowing`, `property-narrowing`, `pattern-scope`, `is-not-scope`, `negative-tests`, `not-or-parentheses`, `impossible-patterns`, `bool-conditions` and `needless-null-checks` pass. `leave-on-null`, `property-patterns`, `pattern-locals`, `type-tests`, `match-forms` and `is-and-as` fail.

## Rules that work on master

`MatchBasics.sharp` holds P2 to P10, P19 and P27 on values. The engine printed `'small'`, `'medium'`, `'large'` for `size(5)`, `size(5000)`, `size(50000)`; `'ok'` and `'other'` for `200 or 201`; `'paused'` for a `match` on `Status` with no `default`; `'string one'` for `"1"` against the arms `1` and `"1"`; `'loud 3'` and `'quiet 3'` for a `when` arm before a plain one; `'has code'`, `'none'`, `'none'` for `not null and not ""` on `"A-1"`, `""` and null; and `'closed arm'` for a block arm of a `match` statement.

`MatchCoverage.sharp` printed `match-not-exhaustive: This `match` misses `Status.Active`.` for four arms whose `Status.Active` arm has a `when` (P8), and "A block arm is only in a `match` statement: a `match` that gives a value gives an expression in each arm." for a block arm in a `match` expression (P4).

`IsAs.sharp` holds P22, P23, P25 and P28. The engine printed `'design:design'` for `entity.designKey()` after `entity is HasDesign`, `1.0` for `circle.radius` after `shape is Circle circle`, `'5 at 20'` and `'bad payload'` for decision 020's `checkout`, `false` for `Refunded is not (Paid or Refunded)`, `true` for `"A-1" is not null and not ""`, `NULL` for `result as Paid` on a `Refunded`, `1500` for `(result as Refunded)?.amount`, `NULL` for `"5" as int` and `'5'` for `"5" as string`.

`PropertyPlain.sharp` holds P13 and P20: `true`, `false`, `true`, `false` for `{ status: 200 }`, `{ status: 404 }`, `{ status: 200, body: { type: "json" } }` and `{ …, body: { type: "xml" } }` on a `Response`, `200` for `code` after `{ status: int code }`, and `false` for `{ status: 200 }` on the string `"text"`. A missing property is "Property `Response.missing` does not exist."

`Narrowing.sharp` printed, one per line: "Attempting to access a method on a non-object type (`5`)." after `entity = 5` inside the `is` block (P23); "`order.total` is `Any?` here: a property is not narrowed, because it could change between the test and the use." (P24); "`circle` exists only where `shape is Circle circle` is true." and "`circle` exists only where `shape is not Circle circle` is false." (P25); "Write `entity is not HasDesign`: `!` applies to `entity` before `is` tests it." (P26); "Write `not (Paid or Refunded)`, or `(not Paid) or Refunded`." (P27); "A type pattern is never nullable: null never matches a type." (P30); and "This pattern never matches the value it tests." for `circle is Square`, `n is "text"` on an `int` and `{ status: "text" }` on a `Response` (P31).

`BoolOps.sharp` printed "`if` takes a `bool`, but this is `int`." and the same for `while`, `do … while`, `for`, `? :`, `&&`, `||`, `!` and `when` (P35), and "PHP# has no `?:`: write `a ?? b` to replace null, or `c ? a : b` with a `bool` condition." (P37).

`NullOps.sharp` and `LeaveThrow.sharp` hold N1, N2, N3 and N5: `5`, `0`, `7`, `NULL`, then `20` and `'threw'`. `NeedlessNull.sharp` holds N7 and N8: "Redundant `==` comparison: `Customer` is never `null`.", the same for `!=`, "Redundant nullsafe operator (`?.`) used on an expression that is never `null`.", "Redundant null coalesce: left-hand side can never be `null` or undefined." and "Declared return type `Customer?` for `current` has unused branches: `null`."

## N6: `?? return`, `?? continue`, `?? break`

"`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`: leave when `a` is null. `return` takes a value when the method returns one."

Refused. The spec run printed, for `leave-on-null`:

```text
docs/spec.md:1374 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `Return`
docs/spec.md:1376 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `Continue`
```

`LeaveReturn.sharp`, `LeaveLoop.sharp` and `LeaveReturnWrong.sharp` give the same parse error at `return`, `return -1`, `continue` and `break`.

- **Mechanism (decision 2):** the parser reads the keyword after `??` in a `.sharp` file into `Expression::Leave`. It stands wherever `?? throw` stands (answer 2). The semantic check refuses a `return` whose value disagrees with the method's return type, with the messages `return` statements get today: "Method `ReturnShapes.run` with return type of `void` must not return a value." and "Method `ReturnShapes.price` with return type must return a value." (`ReturnShapes.sharp`). The analyzer types it `never`, reports `continue` outside a loop as it does today, "Continue statement used outside of loop.", and reports `redundant-null-coalesce` when `a` is never null.
- **Engine:** `ZEND_SHARP_LEAVE` and the live range split (decision 2).
- **Reuses:** the analysis of `throw`, the return and loop checks, and the unwinding helpers of `zend_execute.c`.

## N4: `f?.(x)`

"`f?.(x)`: calls `f` only when it is not null."

Refused. `NullCall.sharp` printed, at `doubler?.(4)` and `none?.(4)`: "Parse error encountered during parsing" with "Expected one of `Variable`, `DollarLeftBrace`, `Dollar`, `LeftBrace`, `Identifier`, found `LeftParenthesis`".

- **Mechanism (decision 3):** the parser reads `?.(` in a `.sharp` file as `Call::NullSafeFunction`. `Call::is_null_safe` covers it. The analyzer analyzes it as `FunctionCall` analyzes a call of a local holding a function, on the callee's type without null, and adds null to the result. The bridge lowers it through `null_safe_chain`.
- **Engine:** none.

## P21: a type and its properties in one pattern

"A property pattern matches only the properties it lists, as in C#. It can nest, and it can test the type at the same time", with `if (payment is Paid { amount: > 1000 } big) { … }`.

Refused. The spec run printed `docs/spec.md:2085 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `RightParenthesis`, found `LeftBrace``. `PropertyTyped.sharp` gave the same and six more parse errors on the line.

- **Mechanism (decision 4):** `parse_primary_pattern`'s type arm reads an optional `{ … }` after the type, then the optional name. The binder's `pattern_variables`, the semantic check's `report_hidden_pattern_variables` and `report_chained_pattern`, and the walker visit the new field. `php_shape`'s type arm tests the type and then each property, reading the named variable when there is one.
- **Engine:** none. `instanceof` and a property read are PHP.

## P14: list patterns

"| list | `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>` |"

Refused. `ListPattern.sharp` printed "A list pattern is not supported yet in PHP#." at each of the three arms.

- **Mechanism (decision 5):** `parse_primary_pattern`'s `[` arm parses `ListPattern` instead of `skip_balanced`. The binder declares each element's variables and the rest's name. `php_shape` lowers the count test, each element's test on `s[i]` read from the end for elements after the rest (`s[\count(s) - 1]`), and the rest's `\array_slice`. A `match` on a `List` still needs `default`, since the spec skips it only on an enum or a sealed interface.
- **Engine:** none on a `List` subject. On another subject, #102's `ZEND_SHARP_IS_TYPE` for `List<T>`.

## P15: a pattern takes types and constants only

"A pattern takes types and constants only, such as `Circle`, `200` or `Status.Paid`, as in C#. A local's value is compared in `when`."

Silently accepted. The spec run printed `docs/spec.md:2071 expects a compile error, and the checker accepts it`. The engine printed `'local'` for `match (value) { limit => "local", default => "other" }` with `const limit = 10` and `value` 10 (`PatternNames.sharp`). `PatternValues.sharp` compiled `value is this.limit`, `value is PatternValues.ceiling` (a static property) and `n is < top` with a local `top`, and the engine printed `true`, `true` for the first two. `value is PatternNames.LIMIT` (a class constant), `value is -10`, `value is "10"` and `value is 2 + 8` are constants, compiled, and stay.

- **Mechanism (decision 6):** the semantic check and the analyzer refuse each non-constant form. `Zend/tests/sharp/Operators.sharp`'s `limit => "at the limit",` and its twin in `Operators.inc` move to `int n when n == limit`, and the bridge test `values_comparisons_and_properties_are_the_php_comparisons_they_name` loses its `extra is limit` row.
- **Engine:** none.

## P32 and P33: function types

"`value is Function<int(int)>` tests the function's parameter and result types while the code runs … A test whose type the value's source never declares is a compile error that says the type is unknown".

Refused for the wrong reason. The spec run printed:

```text
docs/spec.md:2197 expects no error, and the checker reports: This type is not supported yet in PHP#.
docs/spec.md:2198 expects no error, and the checker reports: This type is not supported yet in PHP#.
docs/spec.md:2199 expects a compile error, and the checker reports: This type is not supported yet in PHP#.
```

`FunctionTests.sharp` gave the same, with the note "A PHP# pattern is a type of `int`, `float`, `string`, `bool` or a class with an optional name …". `OtherTypeTests.sharp` gave it for `value is Any` and `value is int|float`, and `AsCollection.sharp` for `as List<string>`, `is List<string>` and `as Map<string, int>`, with the note "`as` converts to `int`, `float`, `string`, `bool` or a class." Those last five are #102's.

- **Mechanism (decision 7):** the semantic check's type-pattern arm accepts `Function<…>`. `type_test_of` gives it #102's engine test. The analyzer reports `unknown-function-type` when the tested expression's type is a function with no declared signature. The bridge spells each method's and lambda's whole type, effects included, into `zend_op_array.sharp_type`.
- **Engine:** `sharp_type_holds`'s `SHARP_TYPE_FUNCTION` arm compares the closure's type with the tested one through `sharp_type_function_fits` (question 4).

## P1 and P34: `switch`, `and`, `or`, `xor`

"`match` is the only branching construct on a value. `switch` is removed." "PHP's `and`, `or` and `xor` operators are removed".

Wrong error. `SwitchGone.sharp` printed "This statement is not supported yet in PHP#.", and `BoolOps.sharp` printed "This operator is not supported yet in PHP#." at `a and b`, `a or b` and `a xor b`. The refusal is right and the reason is not: "not supported yet" says a later Slice will build them.

- **Mechanism (decision 8):** the semantic check reports each as a removed form with its replacement, before the walk's generic refusal.
- **Engine:** none.

## P38: `a ? b : c ? d : e`

"`a ? b : c ? d : e` means `a ? b : (c ? d : e)`, as in C#, Java, TypeScript, Swift and Dart."

Wrong error. `BoolOps.sharp` printed "Unparenthesized `a ? b : c ? d : e` is not supported. Use either `(a ? b : c) ? d : e` or `a ? b : (c ? d : e)`." at `a ? 1 : b ? 2 : 3`. That is PHP 8's rule, which reads the unparenthesized form as `(a ? b : c) ? d : e`.

- **Mechanism (decision 9):** the parser, in a `.sharp` file only, parses the else branch of `?:` at the ternary's own precedence. `check_conditional`'s nested-condition arm goes. A plain PHP project parses as before.
- **Engine:** none.

# Sections 20 and 20.1

Added by answer 1. team-lead approves this part before anyone builds it.

## Their state on master

The spec run printed:

```text
FAIL §20 enum-data-cases (docs/spec.md:1929)
  docs/spec.md:1932 expects no error, and the checker reports: A case that carries data is not supported yet in PHP#. Not supported yet.
  docs/spec.md:1933 … same, and 1934
FAIL §20 enum-shapes (docs/spec.md:1946)
  docs/spec.md:1950 expects no error, and the checker reports: A case that carries data is not supported yet in PHP#. Not supported yet.
  docs/spec.md:1951 … same
FAIL §20.1 sealed-interfaces (docs/spec.md:1966)
  docs/spec.md:1970 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
```

`backed-enum` and `enum-interfaces` pass.

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| E1 | an enum can give each case a raw value, `case None = "none"` | works | none | PHP enums, as master lowers them |
| E2 | the header lists the backing type and the interfaces after `:` | works | none | |
| E3 | an enum case can carry data; a closed set, every case declared in one block; `PaymentResult.Declined(reason: "card expired")` makes one | refused: "A case that carries data is not supported yet in PHP#." | checker (parser, codex, analyzer), bridge, Composer autoloader | `parse_function_like_parameter_list`, promoted constructor parameters (decision 12) |
| E4 | backed: every case has a value, and no case carries data | works for a backed enum; a data case refused as E3 | checker | |
| E5 | not backed: any mix of plain cases and cases that carry data | refused as E3 | checker, bridge | decision 12 |
| E6 | a case with a value and a case with data in one enum is a compile error | wrong error: E3's "not supported yet" | checker | the semantic check of enum shapes |
| E7 | each case is its own type, so a method can take `PaymentResult.Paid` alone | refused as E3 | checker (codex), bridge | PHP#'s `.` name resolution (question 6) |
| E8 | at runtime each case is a real class, so Reflection sees it | refused as E3 | bridge, Composer autoloader | decision 12 |
| E9 | the checker rejects a `match` over the enum that misses a case | works for a plain or backed enum; refused as E3 for data cases | checker (analyzer) | `permitted_inheritors`, `report_unhandled` (decision 14) |
| E10 | case unpacked by position `PaymentResult.Paid(string transactionId) =>` (§21) | refused: "An enum case pattern is not supported yet in PHP#." | checker (parser, `php_shape`), bridge | `TypePattern`, decision 13 |
| E11 | whole case, named `PaymentResult.Paid p =>`, and `as PaymentResult.Paid` (§21) | refused as E10 | checker | the type pattern and `as`, unchanged once E7 holds |
| S1 | a `sealed interface` is a closed set of classes; its implementers sit in its namespace; the checker gathers the set from that namespace | refused: parse error | checker (parser, codex populator) | `permitted_inheritors` (decision 15) |
| S2 | outside the namespace, implementing it is a compile error | refused as S1 | checker (analyzer) | `check_class_like_implements`'s `is_permitted_to_inherit` |
| S3 | a `match` over it needs no `default`, as long as it handles every implementer (§21 too) | refused: "A `match` needs a `default` arm." | checker (semantic check, analyzer) | decision 14 |
| S4 | adding an implementer makes every `match` that does not handle it a compile error | refused as S1 | checker (orchestrator) | the recorded reads of mago-sharp #46 (decision 16) |

"Use an enum when the cases are small data. Use a sealed interface when each case is a full class with its own methods." is advice, and holds no rule.

## Decisions team-lead approves

12. **An enum with a data case lowers to classes.** A backed enum and an enum of plain cases stay PHP enums, as master runs them. An enum with a data case is an `abstract` PHP class, `App\PaymentResult`, holding the enum's interfaces, methods and constants, and each case is a `final` subclass whose fields are public `readonly` promoted constructor parameters, `App\PaymentResult\Paid(string $transactionId)` (question 6). A plain case of such an enum is a case class with no fields and one instance. `PaymentResult.Declined(reason: x)` lowers to `new \App\PaymentResult\Declined(reason: x)`, and `CheckoutState.Open` to `\App\CheckoutState\Open::instance()`. The parser reads `case Paid(string transactionId);` into `EnumCaseItem::Data`, where it now parses and drops the list. The codex scans the enum as that abstract class with the cases as its `permitted_inheritors`, and each case as a class of its own flagged `ENUM_CASE_CLASS`, so `d.reason` reads a property the analyzer knows. The enum's class gets, for each case that carries data, a static method of the case's name that takes its fields and gives the case, and, for each plain case, a constant of the case's name typed as the case, so the analyzer checks `PaymentResult.Declined(reason: x)` as a call and `CheckoutState.Open` as a read with no new code; the bridge lowers both as above. A type names a case as `PaymentResult.Paid`, a dotted name of two parts, and the analyzer refuses one that names no case. A case named as a type PHP reserves, as `Never`, a method named as a case that carries data, and a plain case read in a constant expression are refused.
13. **A positional case pattern is a type pattern with fields.** `TypePattern` gains `fields: Option<…>`, the positions in parentheses, each any pattern (answer 3), beside the `properties` of decision 4. `php_shape` tests the case class with `instanceof`, then each position against the field declared there: `($p = x) instanceof \App\PaymentResult\Paid && \is_string($transactionId = $p->transactionId)`. Field names come from the declaration, so `php_shape`'s `is_local` callback grows into a small trait that also answers a case's field names. The analyzer's and the bridge's implementations answer from the codebase. A wrong count of positions is "`PaymentResult.Paid` has 1 field, and this pattern unpacks 2."
14. **Coverage of a closed set reuses Mago's sealed classes.** A data-case enum's base and a sealed interface carry `permitted_inheritors`. The analyzer's `missing_cases` lists the classes of that set that no arm without `when` tests with `instanceof`, beside the plain cases the reconciler leaves, as it does for an enum today. A case pattern's test is `($p = x) instanceof C && …`, which narrows `$p` rather than the subject, so `handled_test` reduces a test whose other parts are always true, as a field unpacked with its declared type, to `x instanceof C`. The semantic check stops requiring `default` when every arm is a type or case pattern, and the analyzer then reports the cases left, "This `match` misses `PaymentResult.RequiresAction`.", with `match-not-exhaustive`, which `MOVES` already lists. On a subject that is not a closed set, it reports "A `match` needs a `default` arm." with the same code.
15. **`sealed` is a modifier of an interface.** The CST's `Interface` gains `sealed: Option<Keyword>`, and the scanner marks the interface with `MetadataFlags::SEALED`. The codex populator, which sees the whole codebase, sets a sealed interface's `permitted_inheritors` to the classes and enums of its namespace that implement it, on every population and before parents merge into children. A member of the set inherits the set through Mago's merge, so coverage treats a class inside its own set as that class alone. A class elsewhere gets `check_class_like_implements`'s refusal, reworded for a `.sharp` file: "`App.Shop.Banner` cannot implement the sealed interface `App.Interaction`: its implementers sit in the namespace `App`." The interface lowers to a plain PHP interface, and the engine checks nothing at run time.
16. **A new implementer re-checks every file that matches on its interface.** The recorded reads of mago-sharp #46 reach a file's state only through the plugin session, which a plain PHP# compile does not open. So the analyzer adds `Listing::DirectDescendants` of the interface to the new `AnalysisArtifacts::listings` wherever a `match` over a sealed interface reads its set, as `effect_summaries` carries a file's effects. The orchestrator merges each file's listings into its reads, and the existing comparison of listings analyzes the file again when an implementer joins or leaves. A warm compile after adding or removing `ScrollInteraction.sharp` then refuses or accepts the `match`, as a cold compile does, with and without `diff`.
17. **The Composer autoloader finds a case class through its enum's file.** `sharp/composer/autoload.php` tries the file of `App\PaymentResult` when `App\PaymentResult\Paid.sharp` does not exist, and `Plugin::dumpClassMap` adds each case class of an enum file to the optimized class map. Plain PHP's `new PaymentResult\Paid(…)` then loads it, as do Reflection and an `instanceof` after the enum's file loaded.

The harness gains `sharp/harness/app/PaymentResult.sharp`, the enum of `enum-data-cases`, so `match-forms` and `is-and-as` compile against it.

# What changes

```text
sharp/mago/crates/
├── syntax/
│   ├── src/cst/cst/pattern.rs            <- TypePattern.properties; ListPattern, ListPatternElement, RestPattern;
│   │                                        Pattern::List; TypePattern's doc loses reading (a)
│   ├── src/cst/cst/call.rs               <- Call::NullSafeFunction(NullSafeFunctionCall); is_null_safe covers it
│   ├── src/cst/cst/expression.rs         <- Expression::Leave(Leave): return, continue or break after ??
│   ├── src/cst/cst/class_like/enum_case.rs
│   │                                     <- EnumCaseItem::Data(EnumCaseDataItem): a case and its fields
│   ├── src/cst/cst/class_like/mod.rs     <- Interface.sealed
│   ├── src/parser/internal/class_like/   <- enum_case.rs keeps the data case's fields; `sealed interface`
│   ├── src/cst/node.rs, src/walker/mod.rs
│   │                                     <- the new nodes, walked in source order
│   ├── src/parser/internal/pattern.rs    <- list patterns, a type's properties and a case's fields replace
│   │                                        skip_balanced, skip_case_pattern and is_at_case_with_data
│   ├── src/parser/internal/expression.rs <- ?.( and the right side of ?? in a .sharp file; ?: right-associative
│   ├── src/utils/pattern.rs              <- php_shape: list patterns, a type's properties and fields;
│   │                                        is_local grows into a trait that also gives a case's fields;
│   │                                        reading (a) deleted
│   └── tests/sharp.rs                    <- parse rows for each new form; list_and_enum_case_patterns_are_
│                                            not_supported_yet becomes their parse test
├── names/src/internal/walker.rs          <- pattern_variables: list elements, the rest, a type's properties
│                                            and its fields; walk_in_type_pattern's doc loses reading (a)
├── codex/
│   ├── src/scanner/class_like.rs         <- a data-case enum as an abstract class, each case a final class,
│   │                                        the cases its permitted_inheritors
│   └── src/populator/                    <- a sealed interface's permitted_inheritors from its namespace
├── semantics/
│   ├── src/internal/checker/sharp.rs     <- constants only (P15), Leave's values (N6), Function<…> in a
│   │                                        pattern (P32), switch/and/or/xor (P1, P34), check_conditional's
│   │                                        nested arm deleted (P38), a name on an untyped properties
│   │                                        pattern, enum shapes (E6), `default` left to the analyzer for
│   │                                        type and case arms (S3); the contract doc above `enter` restated
│   └── tests/sharp.rs                    <- rows move out of the "not supported yet" tests (The tests, below)
├── analyzer/
│   ├── src/code.rs                       <- IssueCode::UnknownFunctionType, "unknown-function-type"
│   ├── src/effects/mod.rs                <- Effects::function_effects, Effects::callee_files
│   ├── src/statement/function_like/mod.rs
│   │                                     <- a lambda keeps the parameter types it infers
│   ├── src/expression/call/              <- a NullSafeFunction call, typed on the callee without null
│   ├── src/expression/                   <- Leave typed never, with the return and loop checks; a data case
│   │                                        made by `PaymentResult.Declined(…)`; coverage of a closed set
│   ├── src/statement/class_like/mod.rs   <- the sealed-interface refusal in PHP#'s words
│   ├── src/                              <- the recorded read of an interface's implementer set (S4)
│   └── tests/sharp.rs                    <- one test per rule, forms as rows (The tests, below)
├── orchestrator/src/service/incremental_analysis/compile.rs
│                                         <- the effects solve given to check; each file a call reaches
│                                            an input; each spelled function type in the key
├── sharp-bridge/
│   ├── src/lower.rs, src/lower/types.rs  <- NullSafeFunction through null_safe_chain; Leave as its
│   │                                        RETURN, CONTINUE or BREAK node; each method's and lambda's
│   │                                        whole type around its result type; a data-case enum as an
│   │                                        abstract class and its cases
│   ├── src/lower/checked.rs              <- check takes the effects solve
│   ├── src/lib.rs, src/unit.rs           <- Unit::function_types, Reads::function_types in the key
│   └── tests/lower.rs                    <- one tree per new lowering; the `extra is limit` row removed
└── formatter/, linter/, codex/, hir/, fingerprint/, sharp-lean/
                                          <- each exhaustive match on Call, Expression and Pattern takes the
                                             new variants: the formatter prints them, the others treat them
                                             as the forms they lower to

ext/sharp/sharp.c                         <- the type text reads and spells `uses`; sharp_holds_mode;
                                             sharp_type_function_fits; sharp_closure_is reads a closure's
                                             zend_op_array.sharp_type
Zend/zend_compile.h                       <- zend_op_array.sharp_type: a PHP# function's whole type
Zend/zend_compile.c                       <- a RETURN, BREAK or CONTINUE node in an expression compiled as
                                             its statement with ZEND_SHARP_LEAVE before its finally blocks
                                             and loop variables; zend_sharp_declared_type, renamed from
                                             zend_sharp_property_type, unwraps a function's whole type
Zend/zend_opcode.c, zend_object_handlers.c, ext/opcache/zend_persist.c, zend_persist_calc.c,
zend_file_cache.c                         <- sharp_type initialized, freed, persisted, sized and file-cached
Zend/zend_vm_def.h                        <- ZEND_SHARP_LEAVE: cleanup_unfinished_calls, cleanup_live_vars
Zend/zend_opcode.c                        <- zend_calc_live_ranges splits each range a leave frees
ext/opcache/jit/                          <- both JITs store a temporary whose range ends at ZEND_SHARP_LEAVE
sharp/composer/
├── autoload.php                          <- a missing case class loads its enum's file
└── src/Plugin.php                        <- the optimized class map lists each case class
sharp/harness/app/PaymentResult.sharp     <- new: enum-data-cases' PaymentResult
docs/decisions/041-typeof-value.md        <- states the bare-name rule for typeof alone
Zend/tests/sharp/
├── Patterns.sharp, Patterns.inc, pattern_matching.phpt, pattern_matching_jit_{function,tracing}.phpt
│                                         <- rows: list patterns, a type with properties and a name
├── Nulls.sharp, Nulls.inc, null_safe.phpt
│                                         <- rows: f?.(x) on a local, a property and a call
├── Operators.sharp, Operators.inc        <- `limit =>` becomes `int n when n == limit`; `nested` gains a row
│                                            without its parentheses
├── enums.inc, enums.phpt, enums_jit_{function,tracing}.phpt, and a data-case enum and a mixed enum
│   beside Stage.sharp and Suit.sharp     <- rows: making each case, a case as a parameter type, a field
│                                            read, Reflection on a case, a match over every case
├── autoload.phpt, library_autoload*.phpt <- rows: plain PHP's new PaymentResult\Paid(…) loads its enum
├── Leave.sharp, leave_on_null.inc, leave_on_null.phpt, leave_on_null_jit_{function,tracing}.phpt
│                                         <- ?? return, continue and break inside a call's arguments, a
│                                            template, a constructor's arguments, a try with finally or
│                                            catch, and each other, in every opcache mode
├── opcodes.phpt                          <- each new form's opcodes equal its PHP twin's
├── FunctionTypes.sharp, function_types.inc, function_types.phpt
│                                         <- 28 rows of Function<…> on lambdas, a method value and a plain
│                                            PHP closure, true and false
└── type_arguments_requests.phpt          <- its key type holds the template's App.Order
sharp/baseline/spec/                      <- every §14.4, §20, §20.1 and §21 entry removed, nine in all
docs/architecture/slices/21-pattern-matching.md   <- this note
```

The PHP the bridge emits:

```text
$price = $this->prices[$plan] ?? return;                                                <- int price = this.prices[plan] ?? return;
                                                                                          (a RETURN node as the right side of ??: no
                                                                                          PHP spelling)
($doubler === null ? null : $doubler(4))                                                 <- doubler?.(4), a local
(($nullsafe#1 = $this->next()) === null ? null : $nullsafe#1(4))                         <- this.next()?.(4)
($big = $payment) instanceof \App\Paid && $big->amount > 1000                             <- payment is Paid { amount: > 1000 } big
\is_array($lines) && \count($lines) === 0                                                 <- [] on a List
\is_array($lines) && \count($lines) >= 1 && ($first = $lines[0]) instanceof \App\Line
    && ($rest = \array_slice($lines, 1)) is List<Line>                                    <- [Line first, ...List<Line> rest]
                                                                                          (the engine's test of the rest's type, as a
                                                                                          typed element's: SHARP_IS_TYPE)
$a ? 1 : ($b ? 2 : 3)                                                                     <- a ? 1 : b ? 2 : 3
new \App\PaymentResult\Declined(reason: 'card expired')                                   <- PaymentResult.Declined(reason: "card expired")
($p = $result) instanceof \App\PaymentResult\Paid && \is_string($transactionId = $p->transactionId)
                                                                                          <- PaymentResult.Paid(string transactionId)
```

# Public names this Slice adds or changes

team-lead approves these. Everything not here is tactical.

- **`Expression::Leave(Leave)`**, the right side of `?? return`, with `Leave::{Return, Continue, Break}` holding the statement it runs. Its callsite is `Expression::Throw(_) | Expression::Leave(_) => …` in the walker and the checker. The slate:
  - `Leave` — the spec's own verb, "leave when `a` is null"; reads "the expression leaves". Recommended.
  - `Jump` — the compiler's view, control jumps elsewhere; a reader must know the term, and PHP has `goto`. Runner-up.
  - `EarlyExit` — decision 021's "leaving early"; removed, because `exit` is PHP's `Construct::Exit` in Mago.
  - `ControlFlow` — removed: `syntax/src/utils/control_flow.rs` already uses the words for something else.
  - `Escape` — removed: a string escape in the lexer.
  - `NullExit` — names the trigger, but the node itself tests nothing; `??` does.
- **`Call::NullSafeFunction(NullSafeFunctionCall { function, question_mark_dot, argument_list })`**, `f?.(x)`. It follows `Call::NullSafeMethod(NullSafeMethodCall)`, the convention of the enum it joins. The slate: `NullSafeFunction` (recommended), `OptionalFunction` (Swift's "optional chaining"; PHP# does not say "optional"), `FunctionIfPresent` (reads well; no sibling has that shape), `NullSafeInvocation` (removed: "invocation" is the analyzer's word for every call), `ConditionalFunction` (removed: `Conditional` is the ternary).
- **`Pattern::List(ListPattern { left_bracket, elements, right_bracket })`**, with `ListPatternElement::{Pattern, Rest}`. It follows `TypePattern` and `PropertiesPattern`, and the spec's table calls it "list".
- **`RestPattern { ellipsis, hint, variable }`**, `...List<Line> rest`, with `hint` and `variable` both absent for a bare `...`. The slate: `RestPattern` (JavaScript's and TypeScript's "rest element" for `...` that collects; recommended), `SpreadPattern` (PHP#'s word for `...`, but a spread expands and this collects; runner-up), `SlicePattern` (C#'s `..`; PHP# says "slice" nowhere), `TailPattern` (removed if question 3 allows a rest in the middle), `RemainderPattern` (long; `%` is remainder in section 24).
- **`TypePattern.properties: Option<&PropertiesPattern>`**, a field on an existing node.
- **`IssueCode::UnknownFunctionType`, `"unknown-function-type"`**, beside `unknown-iterator-type` and `unknown-match-subject-type`.
- **`zend_op_array.sharp_type`**, a PHP# function's whole type, beside `zend_property_info.sharp_type` (decision 7, approved).
- **`Effects::function_effects`** and **`Effects::callee_files`**, what a function type lists and the files its effects come from.
- **`check(file, program, names, artifacts, codebase, effects, inline_forms, issues)`**, which gains `effects`.
- **`Unit::function_types`** and **`Reads::function_types`**, each function type a file spells, in its key.
- **The opcode `ZEND_SHARP_LEAVE`**, number 216 (decision 2). It follows #102's `ZEND_SHARP_IS_TYPE` and takes the number after #102's last, so neither renumbers when both land, and the CST's `Leave`.
- **`EnumCaseItem::Data(EnumCaseDataItem { name, parameter_list })`**, beside `EnumCaseItem::Unit` and `EnumCaseItem::Backed`, with `Node::EnumCaseDataItem` and `Enum::has_data_case()`, which the codex, the semantic check and the bridge share.
- **`MetadataFlags::ENUM_CASE_CLASS`**, beside `EVENT`: the class a case of an enum with data runs as, which the bridge and the analyzer's clash and namespace checks read.
- **`Interface.sealed: Option<Keyword>`** and **`TypePattern.fields`**, fields on existing nodes.
- **`MetadataFlags::SEALED`** (bit 53, `is_sealed()`), beside `ENUM_CASE_CLASS` (bit 52) and #118's `FOREIGN` (bit 51).
- **`AnalysisArtifacts::listings`**, the sets of names a file's analysis read whole.
- **`App\PaymentResult\Paid` and `CheckoutState\Open::instance()`**, what plain PHP sees of a case (question 6).
- **`sharp/harness/app/PaymentResult.sharp`**, a new harness file.

No new crate or `Sharp\` class.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. The existing test for a rule grows a row before a new test is added, and each is seen failing before its code lands.

**`sharp/mago/crates/syntax/tests/sharp.rs`:** a parse test each for `Leave` (rows `?? return`, `?? return value`, `?? continue`, `?? break`, and `return` after anything but `??` staying a statement), `?.(` (rows on a local, a property, a call, and a `.php` file where it stays a parse error), a type with properties (with and without a name, nested), a list pattern (any pattern as an element, a named or bare rest first, in the middle and last, and a second rest), and `?:` right-associative in `.sharp` and left-associative in `.php`.

**`sharp/mago/crates/analyzer/tests/sharp.rs`:**

- `leave_on_null_leaves_the_method_or_the_loop` (new): rows for each keyword in each allowed place compile and type the local without null; `?? return 5` in a `void` method, `?? return` in an `int` method, `?? continue` outside a loop, and `?? return` on a value that is never null each get their error.
- `a_null_safe_call_calls_only_a_function_that_is_there` (new): `f?.(x)` types `int?` for a `Function<int(int)>?`, and `redundant-nullsafe-operator` for a `Function<int(int)>`.
- `a_pattern_takes_constants_of_the_codebase_only` (new): a static property through its class is refused with P15's message; a class constant and an enum case through their class compile.
- `a_type_with_properties_tests_both_and_binds` (new): `big` is `Paid` inside the `if` of `payment is Paid { amount: > 1000 } big`, its property test reads `Paid`'s `amount`, and an unknown property of `Paid` is `non-existent-property`.
- `list_patterns_test_the_count_and_each_element` (new): element and rest variables typed from the subject, a list pattern on a `Map` never matching, and a `match` on a `List` without `default` refused.
- `function_types_are_tested_like_any_type` (new): `Function<int(int)>` on a lambda, a method value and a `Closure` from plain PHP (`unknown-function-type`), in `is`, `as`, an arm and a bare statement.
- orchestrator: `a_body_edit_that_changes_a_callees_effects_changes_the_callers_key` (new): a lambda's file lists the file of the body it calls, and an edit there that changes its effects changes the lambda's key.
- `a_pattern_that_can_never_match_is_an_error_at_the_pattern` grows rows for a list pattern on a `Map` and a type with properties on a type it can't be.

**`sharp/mago/crates/semantics/tests/sharp.rs`:**

- `operators_outside_the_slice_are_not_supported_yet` loses `xor`, `and` and `or`, and `every_construct_outside_the_slice_is_not_supported_yet` loses `switch`. Both move to a new `switch_and_or_and_xor_are_errors_that_name_their_replacement`.
- `a_ternary_nested_in_a_condition_without_parentheses_is_an_error_as_in_php` becomes `a_ternary_in_the_else_branch_nests_to_the_right`, and `an_expression_refused_at_part_of_it_has_the_rest_checked` loses its nested-ternary row.
- `is_as_and_match_with_their_patterns_are_in_the_slice` grows a row per new pattern form, and a new `a_pattern_reads_no_local` refuses a local, a parameter and `this`.
- `a_pattern_variable_under_or_or_not_is_an_error_but_under_the_not_that_starts_is` grows rows for a list element and a property under `or`.

**`sharp/mago/crates/sharp-bridge/tests/lower.rs`:** one tree per row of "The PHP the bridge emits" above.

**Engine, `Zend/tests/sharp/`, in every opcache mode** (`sharp/bin/test --opcache --repeat --passes` on the files below): each new row of `Patterns`, `Nulls` and `Operators` runs and prints what its PHP twin prints, and `opcodes.phpt` shows equal opcodes. The function type rows run on the engine only, since PHP has no twin for the test.

**Sections 20 and 20.1, in the same files:**

- syntax: `case Paid(string transactionId);` parses into `EnumCaseItem::Data` and stays a parse error in a `.php` file; `sealed interface` parses, and `sealed class` stays refused.
- semantics: `enum_shapes_are_backed_or_not` (new), with a value case beside a data case refused and every mix of plain and data cases compiling; `{ status: 200 } r` refused with answer 5's message.
- analyzer: `a_data_case_is_a_type_of_its_own` (new): `PaymentResult.Declined(reason: x)` types `PaymentResult.Declined`, a method taking `PaymentResult.Paid` refuses a `Declined`, `d.reason` is `string`; `a_match_over_a_closed_set_needs_no_default` (new), rows for a data-case enum, a mixed enum and a sealed interface, each covered, each missing one case, and each with a `when` arm that does not count; `a_sealed_interface_is_implemented_only_in_its_namespace` (new); a positional pattern's wrong count of fields; `==` on a data case, by question 7.
- orchestrator: a warm compile after adding an implementer reports the `match` it leaves uncovered, as a cold one does.
- bridge: the abstract class, the case classes and their construction as trees.
- engine: the rows listed in the file tree, and plain PHP's `new PaymentResult\Paid(…)` loading the enum through `sharp/composer/autoload.php`.

**Answers 2, 4 and 5:** rows of `?? return`, `?? continue` and `?? break` as a call's argument, inside a template, inside a `try` with `finally`, and inside a loop's condition; a pure `Function<int(int)>` refusing a lambda with an effect; `Paid { amount: > 1000 }` compiling without a name.

**Plain PHP:** every parser change is behind `dialect.is_sharp()`. `mago analyze` on a plain PHP project gives the same issues before and after, checked on `sharp/mago/crates/analyzer/tests/cases/` as #102 checks its corpus.

# What the Slice refuses as "not supported yet", and why

- **A law that reads a data case's field** (section 28). `sharp-lean`'s `Translator::r#enum` writes an enum as a Lean `inductive` with one constructor per case, and gains each data case's fields as constructor arguments, `| Paid (transactionId : String)`. `PaymentResult.Paid(x)` in a law is the constructor applied, `(App.PaymentResult.Paid x)`, and a plain case `CheckoutState.Open` is the constructor without fields. A law that reads `d.reason` is reported through `Body::unmodeled` as "the field PaymentResult.Declined.reason of a case". The compile step's namespace list for Lean leaves out the case classes, whose namespace is the enum's own name, which Lean declares as the `inductive`. Section 28 has no example of one, so no entry moves.

# Found outside the Slice

- **A `match` statement over a closed set does nothing for a value outside it.** A plain PHP class can still implement a sealed interface, since the engine checks nothing at run time. A `match` expression then throws PHP's `UnhandledMatchError`, and a `match` statement, an `if` chain, runs no arm, as a `match` statement over a plain enum does on master.
- **#102 accepts `as` to a nullable type,** as in its `value as Map<string, float>?`, while master's semantic check refuses "`x as T` to a type that is … nullable". Section 21 says nothing about it either way. #102's review owns it.

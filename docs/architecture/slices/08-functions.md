# Slice 8: Functions

Spec section 8, lines 478 to 528 of `docs/spec.md` on master `605ac2e800`, with the parts of sections 7, 23 and 29 it names. team-lead approved this design in Queue 1, with the answers under "Answers" below.

## The answer first

Most of section 8 already holds on master: top-level and nested functions are refused, `name.trim()` and `Json.encode` run, a bare `trim(name)` names `name.trim()`, `global.levenshtein` and `global.PHP_EOL` run, a bare `PHP_EOL` names `global.PHP_EOL`, `echo`, `print` and `die` are refused, `global.exit(3)` skips a `finally`, and printing and exiting carry `Console` and `Process`. Two examples fail:

- **`standard-library-calls` fails because `Math.max` does not exist.** "Method `Math.max` does not exist." The same missing method fails section 7's `variadic-parameters` (`Math.max(0, ...prices)`). With `Math.max` added in the worktree and reverted, both pass.
- **`output-and-exit` fails because `Console` and `Process` do not exist.** #151 ships them, rewords the `echo`, `print` and `die` refusals, and adds the `printf` and `exit` rows to `REPLACEMENTS`. Its body says the example passes there. This Slice builds none of it.

Probing every rule line found three more gaps in line 498, "calling one by its bare name … is a compile error that names the method":

- **A bare `max(a, b)` or `min(a, b)` names `global.max(…)`,** because `REPLACEMENTS` has no row for a member that does not exist yet.
- **A bare `str_getcsv(line)` names `global.str_getcsv(…)`,** though `line.parseCsv()` ships in `Sharp/Text/Text.sharp`. Its row was never added.
- **A bare `count(items)` names `global.count(…)`,** though `items.count()` compiles. No collection method of section 12 has a row, and `member_form` cannot write one, because a collection method is neither an extension nor a static method.

Three findings shape the work more than the code does:

- **`Math.max` can't be a generic that calls `global.max`.** PHP's `max` orders numeric strings as numbers, so `max("10", "9")` gave `"10"`, while section 19 line 2118 says `"10" < "9"`, and PHP# itself gives `true` for `"10" < "9"`. A generic `max<T>` also accepted two objects of a class with no `operator <=>`. So `Math.max` takes `int` (Jordan question 1).
- **PHP's `max` with one argument takes an array.** `global.max(first, ...rest)` threw "max(): Argument #1 ($value) must be of type array, int given" for `Math.max(0, ...[])`, which section 7 line 441 says gives 0. `global.max([first, ...rest])` gave 0.
- **Every replaced function stays callable through `global`.** `global.printf("x")`, `global.var_dump(1)`, `global.exit(1)` and `global.time()` compile, each with its effect. Section 6 line 262 writes `global.time()` and section 7 line 459 writes `global.exit(…)`. So line 502's "output and exit go through the standard library" holds for bare calls only (Jordan question 3).

## What waits on #151 and what builds now

**Waits on #151:**

- `output-and-exit` and its `sharp/baseline/spec/§8/output-and-exit` entry, which #151 deletes.
- Line 498's effect-class half: a bare `printf`, `time()` or `file_put_contents` names `console.write`, `clock.now()` or `files.write`. On master each names `global.printf(…)`, `global.time(…)` or `global.file_put_contents(…)`.
- Line 527 through `Process.exit`. The engine holds it for the function `Process.exit` calls: `global.exit(3)` inside `try … finally` exited with status 3 and never printed "finally ran". #151's `effect_console_process.phpt` runs it through `Process.exit(4)`.
- Line 528 through the two classes. It holds on master for the functions they call.
- The `global.` refusal of the functions the effect classes replace (decision 7), and with it section 6 line 262 and section 7 line 459, which write `global.time()` and `global.exit(…)` today.

**Builds now,** with no file #151 adds:

- `Math.max` and `Math.min` (decision 1).
- The `max`, `min` and `str_getcsv` rows, and the collection rows (decisions 2 and 3).
- The `global.` refusal for every row whose member is on master (decision 7).
- The tests, and the two baseline entries `standard-library-calls` and `variadic-parameters`.

## Decisions team-lead approves

1. **`Sharp.Math.Math` gains `max` and `min`, on `int`,** with one parameter before the `...` so a call always has a value, as section 7 line 441 says:

   ```csharp
   public static int max(int first, int ...rest) => global.max([first, ...rest]);

   public static int min(int first, int ...rest) => global.min([first, ...rest]);
   ```

   - The list form is what makes `Math.max(0, ...[])` give 0 (observed above).
   - `min` is not in the spec. Without it, a bare `min(a, b)` names `global.min(…)`, which makes PHP# code name PHP's function. It is a new public name, so it lands only with this approval.
   - `global.max` and `global.min` are declared pure in `Builtins.sharp` (lines 1045 and 1125), so both methods are pure.
   - Observed with these two bodies on the engine: `Math.max(2, 7)` 7, `Math.max(-3, -9)` -3, `Math.max(0, ...[900, 2900])` 2900, `Math.max(0, ...[])` 0, `Math.max(0, ...[-5])` 0, `Math.max(4)` 4.
2. **`REPLACEMENTS` gains three `Members` rows,** under decision 76's rule that a function gets its row in the pull request that ships its member:

   ```rust
   ("max", Replacement::Members(&[("Sharp\\Math\\Math", "max")])),
   ("min", Replacement::Members(&[("Sharp\\Math\\Math", "min")])),
   ("str_getcsv", Replacement::Members(&[("Sharp\\Text\\Text", "parseCsv")])),
   ```

   A bare `max(a, b)` then reads "`max` is wrapped by the standard library: write `Math.max(…)`. Add `import Sharp.Math.Math;` to the file.", as `ceil` does today, and `str_getcsv(line)` reads "write `line.parseCsv()`.", as `trim` does. `every_member_a_replacement_names_is_declared_by_the_standard_library` holds each row to the library.
3. **A collection method gets a row of its own kind, `Replacement::CollectionMethod`,** whose form names the receiver among the call's arguments, as `Replacement::Syntax` forms already do with `{a}` and `{b}`. It is reported as `wrapped-function`, "`count` is wrapped by the standard library: write `items.count()`.", because the method is the library's, not syntax. The rows are the functions whose method every PHP# collection the function takes has, `List` and `Map`:

   ```rust
   ("count", Replacement::CollectionMethod("`{a}.count()`")),
   ("array_filter", Replacement::CollectionMethod("`{a}.filter({b})`")),
   ("array_map", Replacement::CollectionMethod("`{b}.map({a})`")),
   ("array_any", Replacement::CollectionMethod("`{a}.any({b})`")),
   ("array_find", Replacement::CollectionMethod("`{a}.first({b})`")),
   ```

   - The rendering reuses `syntax_form`. No new issue code.
   - The bare-call refusal runs in `resolve_targets` before the arguments are analyzed, so a row can't pick a method by the argument's type. A function whose method only one kind has names that kind (team-lead answer 1):

   ```rust
   ("in_array", Replacement::CollectionMethod("`{b}.contains({a})` on a `List`")),
   ("array_search", Replacement::CollectionMethod("`{b}.indexOf({a})` on a `List`")),
   ("end", Replacement::CollectionMethod("`{a}.last()` on a `List`")),
   ("array_push", Replacement::CollectionMethod("`{a}.add({b})` on a `List`")),
   ("array_key_exists", Replacement::CollectionMethod("`{b}.has({a})` on a `Map`")),
   ("array_keys", Replacement::CollectionMethod("`{a}.keys()` on a `Map`")),
   ("array_values", Replacement::CollectionMethod("`{a}.values()` on a `Map`")),
   ```
4. **Tests, one per rule, with forms as rows, each seen failing first:**
   - `a_bare_call_of_a_plain_php_function_names_the_call_to_write` in `crates/analyzer/tests/sharp.rs` gains one row per new `REPLACEMENTS` row. Its stub library gains `max` and `min`, and its `.php` twin still reports nothing.
   - `Zend/tests/sharp/library_math.phpt` and `MathCalls.sharp` gain the `max` and `min` rows of decision 1.
   - New `Zend/tests/sharp/library_math_preload.phpt`, with the preload script `library_math.inc`, runs the same rows from preloaded classes, as `names_imports_preload.phpt` does. It runs under `USE_ZEND_ALLOC=0 MallocScribble=1`.
   - The other rule lines already have tests that hold, listed under each row below. No test is added for a line that holds.
5. **The two baseline entries leave:** `sharp/baseline/spec/§8/standard-library-calls` and `sharp/baseline/spec/§7/variadic-parameters`. The PR body says `Completes: §8` only once #151 has deleted `§8/output-and-exit`. Before that, `COMPLETES` fails on it (team-lead question 2).
6. **The variant's name, `CollectionMethod`,** from these candidates, each in `Some(Replacement::…(form))` inside `report_bare_function_call`:
   - `CollectionMethod`: what the caller writes, in section 12's words and `INLINED_COLLECTION_METHODS`'. Reads "the replacement is a collection method".
   - `CollectionMember`: the same, with `Members`' noun. Reads as a sibling of `Members`, but a collection has no property to name.
   - `ReceiverCall`: the shape of the call. Says how it is written, not what it is.
   - `OnArgument`: where the receiver comes from. Reads as how the code works.
   - `ListMethod`: the commonest receiver. Wrong for a `Map`.

   Runner-up: `CollectionMember`.
7. **A `global.` call of a function the library replaces is refused with its bare call's message** (Jordan answer 3). `analyze_namespace_function_call` reports it for a `Members` or `CollectionMethod` row whose member the codebase declares, in a `.sharp` file outside the standard library, so `global.trim(name)` reads "`trim` is wrapped by the standard library: write `name.trim()`." The standard library keeps calling `global.trim`, because its members are made of those calls. A `Syntax` row is replaced by PHP# syntax, not by the library, and keeps its `global.` form: section 6 line 218 writes `global.strval(1)`. The effect-class rows join when #151 lands, through the same check. Every `.sharp` fixture and test source that calls a replaced function through `global.` moves to the member, and section 29 line 4064 names `global.usort` and `global.call_user_func` in place of `global.array_map`.

8. **What decision 7 took away comes back** (team-lead, Queue 2):
   - **Every method a `List` shares with a `Map` or a `Set` runs on a value that may be either** (team-lead, Queue 3), as `let held = listed ? names : stock; held.remove("tea");` does. A `List`'s `count` is PHP's `\count`, which counts a `Map` and a `Set` too. `remove`, `contains` and `clear` test the value: `(\is_array($held) ? <the List's PHP> : $held->remove("tea"))`. Every other shared method already ran on the collection the value holds. The "not supported by the engine yet" refusal is gone. A value that may also be an object is refused as `invalid-method-access`, "`List<int>|Box` may be a collection or an object, and `count` runs a different method on each."
   - **A declared `List<int>|Map<string, int>` stays refused** (team-lead, Queue 4), as C#, Java, Kotlin and Swift have no such union, in words that name no PHP: "A type can't be both a `List<int>` and a `Map<string, int>`.", with "Take the `List<int>` and the `Map<string, int>` as two parameters, or as an enum with one case that holds each." `a_union_of_two_collections_or_of_two_function_types_is_refused` and `a_union_refuses_a_type_written_twice_two_collections_and_a_collection_beside_its_iterable` pin it, and section 24 line 3023 says the same.
   - **`count` on an empty value with no collection type,** an empty literal the semantic checks refuse or an emptied plain PHP array property, is a `List`'s `count`, typed `int`.
   - **A method call on an unchecked `Any` is refused once:** `mixed-method-access` only, its result refused, so a return or an operator that uses it reports nothing more.
   - **A `List<int>` has `max()` and `min()`,** declared in `Sharp\Internal\ListMethods` beside `sumOf` with `@where T is int`, and lowered as `last()` is: `($list === [] ? throw new \OutOfRangeException("The list is empty.") : \max($list))`. A list of another element type reads "`max` needs elements of type `int`, and this is a `List<string>`." `Tally.sharp` writes `Pager.sizes().max()`.
   - **`sizeof` and `key_exists` get rows,** naming `{a}.count()` and `` `{b}.has({a})` on a `Map` ``.

9. **#151's effect rows and the environment rows** (team-lead, Queue 6):
   - **#151's rows join decision 7's check unchanged.** `global.printf("x")`, `global.exit(1)`, `global.time()` and `global.file_put_contents(path, text)` name `console.write(…)`, `process.exit(…)`, `clock.now()` and `files.write(…)` or `files.append(…)`, as their bare calls do. A project without the standard library has no member to name, so its `global.exit(1)` still compiles.
   - **The `global.exit` status check is gone.** Every `global.exit(…)` outside the standard library gets the bare `exit`'s message, so `global.exit("failed")` names `process.exit(…)` once instead of being refused twice. Inside the library, `Process.exit(int code)` types the status. `global.die` keeps its own refusal, `wrapped-function` at "Called here." as every `global.` refusal, with the bare `die`'s note "`die("…")` prints the message and exits with status 0, which reports success."
   - **Section 6 line 262** initializes a static with `[10, 25].map(size => size * 2)`. **Section 7 line 459** lists `return`, `throw`, `break`, `continue` and a call that never returns, and gives `if (throw new ValueError("empty"))` as a condition that never finishes. `process.exit(…)` joins them when the singles PR makes it a `never` method (Jordan answer 4).
   - **`Environment` gains `Map<string, string> variables()`,** which reads every variable on each call, for `getenv()` with no name, which `global.` no longer reaches. `getenv` names `environment.variable(…)` or `environment.variables()`, `apache_getenv` names `environment.variable(…)`, `apache_setenv` names `environment.setVariable(…)`, and `getopt` names `environment.arguments`. A row names a property with `$`, as `$arguments`. A read of `$argv` names `environment.arguments`, and `$argc` names `environment.arguments.count()`. A parameter named `$argv` reads nothing, so it keeps "PHP# variables have no `$`: write `argv`."
   - **The observer and override fixtures trace through `Lib\Witness::say`,** because a static's hook has no object to hold a `Console`. `Exits` and `PhpForms` take a `Console` and a `Process`.

10. **#157's review** (team-lead, Queue 7), with the reviewer's probes as rows:
   - **A replaced function read as a value names its member.** `names.map(global.trim)` reads "`trim` is wrapped by the standard library: write `x.trim()`.", with "Pass a lambda that makes the call in its place." `analyze_namespace_constant` asks `report_replaced_function_value`, which shares `replaced_global_refusal` with the `global.` call. A refused read is typed as refused, so the call it is given to reports nothing more. `global.strlen` and `global.strval` are function values, as #155 makes every other plain PHP function read without parentheses.
   - **A `global.` call #155 refuses for an argument passed by reference is refused once.** The by-reference refusal runs only when no `global.` refusal names the member, so `global.array_push(numbers, 4)` names `numbers.add(…)` once and `global.shuffle(numbers)` names `random.shuffle(…)`.
   - **A collection function's form fits the kind it is given.** Both refusals run after the call's arguments are analyzed. A `CollectionMethod` row lists its method per group of kinds, as `in_array`'s `{b}.contains({a})` on a `List` or a `Set` and `{b}.values().contains({a})` on a `Map`, and `end`'s `{a}.values().last()` on a `Map`. A collection of a kind the row names gets its method with the call's arguments: `in_array(item, stock)` on a `Map` names `stock.values().contains(item)`. A kind with no method, or no known kind, names each group on `x`, as `` `x.indexOf(…)` on a `List` ``, so the user's variable never goes into another kind's method.
   - **An argument that is no name is written `…`,** as `member_form` writes it, and a call with an argument the method drops writes none of its arguments: `count(names, 1)` names `x.count()`, and `array_push(names, "a", "b")` names `x.add(…)`.
   - **A method one kind of a collection value lacks is one error.** `held.contains("tea")` on a `List`-or-`Map` value reports `non-existent-method` once, and `MethodResolutionResult::lacks_collection_method` types the call as refused, so no "Could not infer" follows.
   - **`Process.exit` throws `ValueError` for a code outside 0 to 254,** "Process.exit: the code 255 is not from 0 to 254", since PHP keeps 255 for its fatal errors.
   - **`@global.trim(name)` is refused as `@`.** The singles PR makes `@` before a name the keyword escape and refuses `@` before a value in every file, and the bridge runs a built-in that returns its failure without PHP's warning, so `silence-outside-library` is gone.
   - **`Math.max(0, ...[])` gives 0.** An empty list adds no element to a spread, so it is neither "a value of type `never`" nor a value that is no list.
   - **`apache_getenv(name);` gets no "pure function" note,** since its `extern` in `Builtins.sharp` declares `uses Environment`. In a `.sharp` file, a function whose `extern` declares an effect is never a pure call whose result goes unused. PHP's own stub, which calls it pure, stays as it is for plain PHP.
   - **Decision 80 records the `global.` refusal.** Decisions 34, 43, 63, 76 and 77 are superseded by it in part, and decision 72's union line names why two collections are refused. Spec section 8 states the refusal under line 499, and `standard-library-calls` writes `rawGlobal`.

## Answers

From team-lead in Queue 1.

- **Jordan 1:** `Math.max` and `Math.min` take `int`, in the list form. A `float` form waits until PHP# has a number bound, after v1.
- **Jordan 2:** decision 76's reading. A bare call names the member when one exists, and `global.name(…)` otherwise.
- **Jordan 3:** `global.` is refused on every function the library replaces, with the bare call's message (decision 7). Section 6 line 262 and section 7 line 459 change with the effect rows, after #151.
- **Jordan 4:** `Process.exit` becomes a `never` method in the singles PR. Nothing changes here.
- **team-lead 1:** a row for a function only one kind has names that kind (decision 3).
- **team-lead 2:** this PR lands after #151. It merges master once #151 is on it, then adds the effect rows and `output-and-exit`, and opens with `Completes: §8`.
- **team-lead 3:** `library_math_preload.phpt` and `library_math.inc` are approved.
- **team-lead, Queue 6:** #151's rows keep their shape. `getenv` names `environment.variable(name)`, and `getopt` and `$argv` name `environment.arguments`. A function with no member gets one following `Environment`'s names (decision 9).

## Questions as asked

1. **Lines 482 and 492, and section 7 line 441: does `Math.max` take `int` only, or `float` too?** Every spec example passes `int`. PHP# has no overloading (section 7 line 449) and no bound that means "a number" (section 11 line 822, and mago refuses `<T : int|float>` with "A bound can't be a union"). So one `Math.max` can't take both.
   - Recommended: `int` only, as decision 1 writes, matching every example, decision 40's example `int max(int a, int b)` and the `Math` stub `a_name_without_its_import_names_the_import_or_the_namespace` declares. A `float` call is refused. The cost: the refusal reads "`int` is not `float`: convert with (float) Math.max(a, b)", which points at the result instead of the arguments.
   - Alternative: `Int.max` and `Float.max` on the existing `Sharp.Int` and `Sharp.Float`, as `Int.parse` and `Float.parse` split today, and as Rust's `i32::max` and `f64::max` do. Section 7 line 441, section 8 lines 482 and 492, and section 23 line 2931 change their text.
   - Rejected: a generic `max<T>` over `global.max`, for the numeric-string order and the objects above.
2. **Line 498: does "every PHP built-in function is a standard library method" mean every one?** Line 499 then says some built-in has no method, and master names `global.strlen(…)`, `global.strtolower(…)`, `global.abs(…)`, `global.implode(…)` and `global.sprintf(…)` for bare calls. Recommended: decision 76's reading, "a bare call names the member that does the function's work, and `global.name(…)` when none does", with line 498 reworded to say it.
3. **Lines 499 and 502: should `global.` reach a function the standard library replaces?** `global.printf`, `global.var_dump`, `global.exit(1)`, `global.time()` and `global.strval(1)` compile today, each with its effect, so PHP# code can write around `Console`, `Process` and `Clock` by naming PHP's function. Line 499 also makes code name PHP's functions, as `global.levenshtein(a, b)`, wherever the library has no member, which Jordan's rule that users never cater to the PHP underneath counts as a Problem.
   - Recommended: a `global.` call of a function `REPLACEMENTS` lists is refused with the same message as its bare call. Section 6 line 262 (`global.time()`) and section 7 line 459 (`global.exit(…)`) change their text.
   - Alternative: keep `global.` as the escape hatch that decision 76 and `29-effect-classes.md` describe, and line 502 says it covers bare calls.
4. **Line 502 with section 7 line 459: does `process.exit(1)` end the block, as `global.exit(1)` does?** #151 declares `Process.exit` as `void`. A stand-in class whose `void exit(int code)` calls `global.exit(code)` shows what that gives: `global.exit(1); return 0;` is refused, "This statement never runs, because line 9 ends the block.", while `this.ender.exit(1); return 0;` compiles, and a method `int onlyMethod() { this.ender.exit(1); }` is refused with "Missing return statement". PHP# has no written `never` type.
   - Recommended: a call of a method whose body always ends the process or throws ends the block. Kotlin's `exitProcess` returns `Nothing` and Swift's `exit` returns `Never` to say the same. PHP# works it out from the body, as it works out effects, so `Process.exit` needs no new keyword.
   - Alternative: section 7 line 459 lists `global.exit(…)` only, and code after `process.exit` is accepted.
5. **team-lead: the collection functions whose method only one kind has:** `in_array` and `array_search` (`List`: `contains`, `indexOf`), `end` and `array_push` (`List`: `last`, `add`), and `array_key_exists`, `array_keys` and `array_values` (`Map`: `has`, `keys`, `values`). A static row names the method for both kinds. Recommended: each gets a row naming its kind, as "write `items.contains(a)` on a `List`", so line 498 holds for them. The alternative leaves them naming `global.…` until the refusal can read argument types.
6. **team-lead: landing order with #151.** Both change `REPLACEMENTS` and `a_bare_call_of_a_plain_php_function_names_the_call_to_write` (#151 rewrites its `exit` row), in different lines. This PR claims `Completes: §8` only after #151 lands. Recommended: this PR lands after #151, and merges it then.

## How the states were observed

- **mago:** release, built from master `605ac2e800` with `rustup run 1.97.0 cargo build --release --locked -j 2`, `CARGO_INCREMENTAL=0` and `CARGO_PROFILE_RELEASE_LTO=off`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 7. It prints `mago 0.3.1`.
- **Engine:** `sharp/bin/build` at `605ac2e800`, PHP 8.5.11 NTS DEBUG.
- **Spec run:** `sharp/bin/spec` with that pair: "Checked 202 examples, ignored 1, and 8 fail", equal to `sharp/baseline/spec`. Section 8's two examples fail:

```text
FAIL §8 standard-library-calls (docs/spec.md:485)
  docs/spec.md:492 expects no error, and the checker reports: Method `Math.max` does not exist.
FAIL §8 output-and-exit (docs/spec.md:505)
  docs/spec.md:508 expects no error, and the checker reports: Cannot find type `Console`.
```

- **Trial:** with decision 1's `max` added to the worktree's `Math.sharp`, the same run gave "6 fail", "standard-library-calls passes and is still in sharp/baseline/spec." and "variadic-parameters passes and is still in sharp/baseline/spec.". The file was then restored, and `git status` was clean.
- **Probes:** `/private/tmp/claude-501/-Users-jordan-Developer-php-sharp/345d8519-d589-441b-947d-8f4fe33a0768/scratchpad/s8-functions/` is a project laid out as `sharp/bin/spec` writes one: `App\` maps to `app/`, and a copy of `sharp/composer/` sits at `vendor/heyjordanparker/php-sharp-composer/`. `mago compile` checks it, and `run.php` runs its classes on the engine. Every message and value below is their output.

# Every rule of section 8, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), **wrong message** (refused, naming the wrong fix) and **not built**.

## No top-level functions

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 480 | There are no top-level functions | Works: `function helper(): int { … }` at file level and `function inner()` inside a method body each get "PHP# has no top-level functions: move `helper` into a class as a static method." `function (int x) { … }` and `fn (int x) => x` get "PHP# writes a lambda as a bare arrow without `fn` or `function`" | None new | semantics `sharp.rs` |
| 480 | Shared code lives in static methods on a class | Works: every probe class above is static methods | None new | |

## Reaching a PHP function

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 482, 491 | Through the type it works on, as an extension: `name.trim()` | Works: `"  ada  "` gives `"ada"` | None new | `Text.trim`, inlined |
| 482, 492 | Otherwise through a static class: `Math.max(a, b)` | **Not built:** "Method `Math.max` does not exist." | Library (decision 1) | `Math.sharp` |
| 482, 493 | `Json.encode(body)` | Works: `standard-library-calls` reports nothing on line 493 | None new | `Json.sharp` |
| 494 | `trim(name)` is a compile error: write `name.trim()` | Works: "`trim` is wrapped by the standard library: write `name.trim()`." | None new | `REPLACEMENTS`, `member_form` |
| 498 | A bare call of a built-in names the method | Works for every row `REPLACEMENTS` has, as `ceil`, `trim` and `json_encode`. **Wrong message** for `max` and `min` ("write `global.max(…)`"), `str_getcsv` ("write `global.str_getcsv(…)`" though `line.parseCsv()` ships) and `count` ("write `global.count(…)`" though `items.count()` compiles) | Rows (decisions 2 and 3); Jordan question 2 | `REPLACEMENTS`, `syntax_form` |
| 498 | `printf`, `time()` and `file_put_contents` are compile errors too | Works as errors: each gets "A plain PHP function is called through its namespace: write `global.printf(…)`", `global.time(…)` and `global.file_put_contents(…)`. Naming the effect class member waits on #151 | #151 | |
| 499 | A built-in with no method is called through PHP's root namespace: `global.levenshtein(a, b)` | Works: `global.levenshtein("kitten", "sitting")` gives 3, and a bare `levenshtein(a, b)` names `global.levenshtein(…)` | None new; Jordan question 3 | `namespace_form` |
| 499 | The standard library calls PHP the same way | Works: every call in `sharp/composer/library/` but `Builtins.sharp` goes through `global.`, and a bare `abs(x)` added to the library copy is refused, "write `global.abs(…)`", and the package with it | None new | |
| 500 | PHP's constants through `global`: `global.PHP_EOL` | Works: gives `"\n"` | None new | |
| 500 | A bare `PHP_EOL` is a compile error | Works: "`PHP_EOL` is PHP's constant, and a bare name reads only this code's own: write `global.PHP_EOL`." | None new | |

## Output and exit

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 502, 520 | `echo` is removed: write `console.write(text)` | Refused: "PHP# has no `echo`: write `global.printf` or `global.fwrite`.", also for `echo "a", "b";`. #151 rewords it to `console.write(text)` | #151 | `REMOVED_CONSTRUCTS` |
| 502, 521 | `print` is removed | Refused, the same message. #151 rewords it | #151 | `REMOVED_CONSTRUCTS` |
| 522 | `printf("Pruned")` is a compile error: write `console.write(text)` | Refused: "write `global.printf(…)`". #151's row names `console.write` | #151 | `REPLACEMENTS` |
| 502, 523 | `exit(1)` is a compile error: write `process.exit(1)` | Refused: "write `global.exit(…)`", also for a bare `exit;`. #151's row names `process.exit(…)` | #151 | `report_bare_function_call` from `exit.rs` |
| 502, 524 | `die("…")` is removed: write the message with `console.writeError`, then `process.exit(1)` | Refused: "PHP# has no `die`: write the message to STDERR, then `global.exit(1)`.", also for a bare `die;`. #151 rewords it | #151 | `REMOVED_CONSTRUCTS` |
| 502 | `die`'s status-0 exit is not reachable another way | Works: `global.die("…")` and `global.exit("Prune failed")` are refused with the same `die` message | None new | `function_call.rs`'s string-status check |
| 502 | Output goes through `Console` | Works for bare calls and constructs. A `.sharp` file's `?>` is refused, "`?>` is PHP syntax: a `.sharp` file has no closing tag, so delete it", so no inline text prints. `global.printf` and `global.var_dump` compile | Jordan question 3 | |
| 508 to 516 | `this.console.writeError`, `this.process.exit(1)`, `this.console.writeLine` | **Not built:** "Cannot find type `Console`." | #151 | |
| 527 | Exiting skips every `finally` block | Works for `global.exit`: `try { global.exit(3); } finally { … }` exited with status 3 and never printed "finally ran". `Process.exit` waits on #151 | None new | PHP's `exit` |
| 527, section 7 line 459 | A statement after an exit never runs | Works for `global.exit(1)`. A `void` method that exits is not seen as ending the block | Jordan question 4 | `unevaluated-code` |
| 528 | Printing has the effect `Console` | Works: `Function<void()> f = () => global.printf("x");` gets "This lambda calls `global.printf`, which has the effect `Console`, so a `Function<void()>` cannot hold it.", and `Function<void() uses Console>` holds it. A `Function<void()>` refuses `() => global.var_dump(1)` the same way | None new | `Builtins.sharp` |
| 528 | Exiting has the effect `Process` | Works: a `Function<void()>` and a `Function<void() uses Console>` each refuse `() => global.exit(1)`, naming `Process`, and `Function<void() uses Process>` holds it | None new | `Builtins.sharp` |

## Existing work

| Work | What it holds | How this Slice uses it |
|---|---|---|
| Decision 76, php-sharp #124 | A bare call names the member that replaces it, or `global.name(…)`. One table, `REPLACEMENTS`, and a row lands with its member | Decisions 2 and 3 add rows |
| Decision 40 | The standard library's shape; one-call bodies inline at the call | `Math.max` is one call, so it may inline. The engine run above gave the right values either way |
| Decision 34 | `echo`, `print` and `die` removed, `exit` skips `finally`, effects `Console` and `Process` | The rows above; no change |
| php-sharp #151, open | `Console`, `Process`, their `REPLACEMENTS` rows and the reworded refusals | Everything listed under "Waits on #151" |
| php-sharp #152 | One type per `.sharp` file | `MathCalls.sharp` already declares one type named like the file |

Neither open PR, #151 nor #136, holds `Math.max`, `Math.min`, or a row for `max`, `min`, `str_getcsv` or a collection method (`trace grep` on `origin/feat/effects` and `origin/feat/hosts`). The only `max` in the tree is the `Math` stub of an analyzer test.

## Found, not fixed

Each is outside section 8 and blocks none of its rules.

- **`<` and `>` on an unbounded type parameter compile, and run PHP's comparison (sections 11 and 19).** `public static T of<T>(T a, T b) => a > b ? a : b;` compiles. `Larger.of("10", "9")` gives `"10"`, where section 19 line 2118 orders `"10"` before `"9"`, and PHP# itself gives `true` for `"10" < "9"`. `Larger.of(a, b)` on a class with no `operator <=>` compiles and runs, while `a < b` on that class is refused with "`<` cannot compare `Larger` with `Larger`: `Larger` declares no `operator <=>`."
- **A `float` passed to an `int` parameter gets mago's generic wording (section 24).** `float largerFloat(float a, float b) => Math.max(a, b)` with decision 1's `max` reports "Possible argument type mismatch … expected `int`, but possibly received `float`. The provided type `float` overlaps with `int` but is not fully contained." for each argument, then "`int` is not `float`: convert with (float) Math.max(a, b)".
- **A non-negative `int` parameter is named as `int` (section 24).** `global.str_repeat("x", items.count())` reports "Possible argument type mismatch for argument #2 of `global.str_repeat`: expected `int`, but possibly received `int`.", because `str_repeat` takes `int<0, max>` and the message prints both as `int`.
- **A partial application of a plain PHP function is "not supported yet", and the line after it reports again (section 8 with #155).** `const counter = global.count(...); return counter(items);` gets "This expression is not supported yet in PHP#." on the first line, then "Could not infer a precise return type for method `Reach.partial`. Saw type `Any?`." on the return. Probe: `review-output/p/app/Reach.sharp` lines 19 and 20.
- **A refused call of a replaced function still checks its arguments against PHP's signature (sections 8 and 14).** `array_any(items, keep)` with a `Function<bool(int)> keep` reads "`array_any` is wrapped by the standard library: write `items.any(keep)`.", then "`Function<bool(int, int)>` takes 2 parameters, and `keep` takes 1.", which asks for PHP's callback shape in code the first error says to delete. The same holds for `array_find`, and for a lambda. Probe: the `array_any` and `array_find` rows of `a_bare_call_of_a_plain_php_function_names_the_call_to_write`.
- **A method value on a value that may be a `List` or a `Map` is refused in PHP's words (sections 12 and 14).** `let held = listed ? names : stock; const has = held.contains;` reads "Attempting to access a property on a non-object type (`Map<string, string>`).", the same for `List<string>`, then "`has` is `null`" and "Could not infer a precise return type" on the call. The call `held.contains("tea")` runs. Probe: `s8-functions/app/EitherValue.sharp`.

## Files

```text
docs/
├── architecture/slices/08-functions.md                          new: this note
├── architecture/slices/24-built-in-types.md                     json_encode is the library's call (decision 10)
├── decisions/080-global-calls-of-replaced-functions.md          new: the global. refusal (decision 10)
├── decisions/034, 043, 063, 076, 077                            superseded in part by decision 80 (decision 10)
├── decisions/072-reified-generics.md                            why two collections are refused (decision 10)
└── spec.md                                                      sections 6, 7, 8, 12, 24 and 29 (decisions 7, 8, 9 and 10)
sharp/
├── baseline/spec/§7/variadic-parameters                         deleted (decision 5)
├── baseline/spec/§8/standard-library-calls                      deleted (decision 5)
├── composer/library/Sharp/Math/Math.sharp                       max and min (decision 1)
├── composer/library/Sharp/Environment.sharp                     variables() (decision 9)
├── composer/library/Sharp/Process.sharp                         exit takes a code from 0 to 254 (decision 10)
└── mago/
    ├── crates/analyzer/
    │   ├── src/expression/call/function_call.rs                 REPLACEMENTS rows; Replacement::CollectionMethod by kind; the global. refusal after the arguments; a property member; no exit status check (decisions 2, 3, 7, 8, 9, 10)
    │   ├── src/expression/call/pipe.rs                          resolve_targets takes no arguments (decision 10)
    │   ├── src/expression/constant_access.rs                    a replaced function read as a value names its member (decision 10)
    │   ├── src/expression/unary.rs                              the comment on @ (decision 10)
    │   ├── src/effects/mod.rs, src/effects/summary.rs           extern_of reachable from the crate; two comment examples that still compile (decision 10)
    │   ├── src/invocation/arguments.rs                          an empty list adds nothing to a spread (decision 10)
    │   ├── src/statement/mod.rs                                 a call whose extern declares an effect is never unused (decision 10)
    │   ├── src/statement/mod.rs, src/statement/loop/mod.rs, src/context/scope/loop_scope.rs  a throw as the example of a condition that never finishes (decision 9)
    │   ├── src/expression/call/method_call.rs                   no refusal on several collections; the object one in PHP# words; a call on an Any refused once; a method one kind lacks refused once (decisions 8 and 10)
    │   ├── src/expression/mod.rs                                a list pattern's own count call stays unreported
    │   ├── src/resolver/method.rs                               count on an empty value; the PHP# words of a @where refusal; lacks_collection_method (decisions 8 and 10)
    │   └── tests/sharp.rs                                       a row per new REPLACEMENTS row, the global. test, the decision 8 and 10 tests, sources moved to members
    ├── crates/names/src/lib.rs                                  INLINED_COLLECTION_METHODS gains List max and min (decision 8)
    ├── crates/prelude/assets/extensions/sharp.php               ListMethods max() and min() (decision 8)
    ├── crates/sharp-bridge/src/lower.rs                         max and min lowered as last is; a shared List method tests for the List (decision 8)
    ├── crates/sharp-bridge/src/lower/types.rs                   list_method_on_several_collections (decision 8)
    ├── crates/semantics/src/internal/checker/sharp.rs           $argv and $argc name Environment's arguments (decision 9); no @ refusal, and the die note and union comments (decision 10)
    ├── crates/semantics/tests/sharp.rs                          the $argv and $argc test (decision 9); the @ and union tests (decision 10)
    ├── crates/semantics/tests/fixtures/slice/                   Report.sharp and Suit.sharp moved to members (decisions 7 and 9)
    ├── crates/sharp-bridge/tests/lower.rs                       sources moved to members (decision 7); max and min (decision 8); global.exit lowered from a standard library file (decision 9)
    └── tests/sharp.rs                                           @global.strtoupper in place of @global.trim (decision 7); @ beside the call it silences (decision 10)
Zend/tests/sharp/
├── MathCalls.sharp                                              calls max and min, and spreads []
├── library_math.phpt                                            the max and min rows
├── library_math.inc                                             new: the preload script
├── library_math_preload.phpt                                    new: the same rows, preloaded
├── effect_console_process.phpt                                  Process.exit refuses a code outside 0 to 254 (decision 10)
├── CollectionMethods/Pantry.sharp, collection_methods*.{inc,phpt}  max, min, and count, remove, contains and clear on a List or another collection (decision 8)
├── environment.phpt                                             variables() (decision 9)
├── harness/Witness.inc                                          say(), for the hook fixtures (decision 9)
└── every fixture that called a replaced function through global., its PHP twin and its driver (decisions 7 and 9)
```

The bridge and the engine need no change. The new rows and the `global.` refusal run only in a `.sharp` file: each check runs only when `context.dialect.is_sharp()`. `Math.sharp` is a `.sharp` file of the standard library. A plain PHP project's analysis does not change: `mago analyze` on a plain PHP project that calls `\max`, `min`, `\array_filter`, `\in_array`, `str_getcsv`, `\trim` and `count` reports "No issues found."

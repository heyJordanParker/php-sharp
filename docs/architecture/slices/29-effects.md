# Slice 29: Effects

Spec section 29, lines 3688 to 3997 of `docs/spec.md` on master `849232f4e1`, with the parts of sections 6.1, 8, 13, 15, 23, 28 and 32 that section 29 reaches. This note is the design only. team-lead approves it before anyone builds.

## The answer first

The effect checker works on master. `crates/analyzer/src/effects/` records each body's effects, solves them over the whole call graph, and holds getters, laws, function values and implementations to them. Most rule lines hold when probed. What fails:

- **Nine of the ten effect classes do not exist.** Only `Sharp.Environment` ships. `Files`, `Clock`, `Random`, `Console`, `Process`, `Http`, `Cache`, `Mail` and `Database` are bare names the checker accepts after `uses`, with no class behind them. So `pure-interface` fails with "Cannot find type `Files`", and so does section 8's `output-and-exit` with `Console`.
- **An object a declared call returns carries nothing (line 3741).** With `extern DB uses Database;`, a real Laravel facade's `DB.table("orders").where(…).count()` is refused: "`App.Probe.Report.open` calls `Builder.count`, which has no `extern` declaration". Laravel's query builder is such an object, so every chain that reaches it from a facade or a model is refused. The harness hides it, because its `DB.table` returns `DB` itself.
- **Three rules are silently accepted:**
  - creating a `foreign` object outside a module binding (line 3871): `new Store()` and `new Environment()` compile in a method
  - importing a PHP built-in class the standard library replaces (line 3759): `import DateTime;` with `new DateTime().format("Y")`, and `import PDO;` with a `PDO?` result, compile
  - storing a `uses f` parameter, on `feat/hosts` (waiting for Jordan)
- **Two messages say the wrong thing:**
  - a bare call of a replaced function names `global.time(…)`, not the effect class member (line 3759)
  - `_ENV` gets "take a Request", not `Environment` (line 3819)
- **`uses f` is refused on master and works on `feat/hosts`** (#136), which also removes `§29/function-effects`. This Slice builds on it and adds nothing to it.
- **"The editor displays them" (line 3997) has no editor.** `mago --help` lists no language server, and section 28's lens (line 3686) is unbuilt for the same reason.
- **A law cannot reason about list code (line 3993).** The list code stays pure, but `law doubledKeepsCount(List<int> xs) => ListLaws.doubled(xs).count() == xs.count();` gets "Mago does not translate collections to Lean."

The Slice builds all of section 29 in one php-sharp PR, after #136 merges and after `fix/spec-plain-php-run`, which changes `sharp/bin/spec` alone.

## Decisions team-lead approves

1. **The nine effect classes ship in this PR,** as `docs/architecture/slices/29-effect-classes.md` on master already designs them, member by member, with `Duration`, `HttpMethod`, `HttpRequest`, `HttpResponse`, `ProcessResult`, `MailAddress` and `MailMessage`. That note says they "ship in their own PR". No branch or PR holds them (`git log --all -S "class Duration" -- sharp/composer` finds nothing), and Jordan's definition of done puts lines 3746 to 3757 and 3815 in this PR. Two follow-ups:
   - `NAMED_EFFECTS` in `effects/mod.rs` is deleted, because each name becomes a `foreign` class and `is_effect` already accepts every `foreign` class.
   - The standard library's `Module.sharp` binds the seven classes whose constructors take nothing (`Clock`, `Random`, `Console`, `Process`, `Files`, `Http`, `Mail`) beside `Environment`. `Database` and `Cache` take a connection string and a path, so the app's module binds them, as `29-effect-classes.md` says ("A module sets it").
2. **`Http` keeps the curl backend `29-effect-classes.md` names,** so `sharp/bin/build` gains `--with-curl`, and `sharp/docker/Dockerfile` and `sharp/docker/runtime/Dockerfile` carry `libcurl4-openssl-dev`. The built engine has no curl today (`php -n -m` lists none). Approved: real PHP apps and their HTTP clients depend on curl.
3. **Line 3741 is a type-level carry, decided in `Effects::solve`.** A plain PHP class with no `extern` of its own takes the effects of every declared call that returns it, as the spec's "inherited effect" says. `solve` builds one map per run, from each `extern`'d class's and method's return types, closed over chains such as `Builder.where(): static`. It then reads an `Effect::Unknown` whose class is in the map as those effects. No codex field and no incremental input are added, because `solve` already runs over every summary on each compile. The cost is that a class two libraries return gets both effects. The spec's own way out holds for that case: an `extern` on the returned class wins. Approved, with a row where one plain PHP class is returned by two declared calls with different effects and takes both.
4. **Line 3759's built-in classes may be imported and named, and only creating one is refused** (Jordan's answer, Option A), with the code `wrapped-class`, as `wrapped-function` refuses a function. `new` of one, and a static factory, a static method whose return type is the class, as `DateTimeImmutable.createFromFormat(…)` and `PDO.connect(…)`, are refused at the call. The error names the standard library members to write instead through `member_form`, as in "write `Date.parse(…)` or `clock.now()` on the `Clock` your constructor takes". `WRAPPED_CLASSES` sits beside `REPLACEMENTS` in `expression/call/function_call.rs`, and the test that holds every `REPLACEMENTS` member to the library holds its members too. The standard library is exempt, because `Database` must use `PDO`. The rows follow line 3759's own rule, "a standard library type that replaces it":
   - `PDO`, `PDOStatement`, `mysqli`, `SQLite3` to `Database`
   - `DateTime`, `DateTimeImmutable` to `Date`
   - `DateTimeZone` to `TimeZone`
   - `DateInterval` to `Duration`
   - `CurlHandle`, `CurlMultiHandle` to `Http`
   - `SplFileObject`, `SplFileInfo`, `DirectoryIterator`, `FilesystemIterator`, `RecursiveDirectoryIterator` to `Files`
   - `Random\Randomizer` to `Random`
5. **Line 3871 is the code `foreign-outside-module`,** reported by `expression/instantiation.rs` for a `new` of a `foreign` class in a `.sharp` file, except inside a module binding's expression, which `statement/module.rs` marks. A test module is a module, so a fake still works. Question 2 must be answered first, because `environment`'s run line breaks this rule.
6. **The collection methods take `uses f` from #136's mechanism (line 3993).** Their effects already hold: `carts.map(c => this.gateway.charge(c))` is refused naming `Http`, and `this.carts.map(c => c.orders.count())` fits a pure method. But `prelude_stub_call` in `effects/summary.rs` does that through a second path, beside #136's `uses_arguments`. The scanner will flag each `\Closure` parameter of a `Sharp\ListMethods`, `MapMethods`, `SetMethods` and `IterableMethods` stub `USES_PARAMETER`, as #136 flags `uses f`. `prelude_stub_call` then keeps only its change of the receiver.

   Built on #136 as it landed, where `uses f` became `TAKES_ON_EFFECTS`: a function parameter whose type states no effects. `runs_inside_collection_method` in `scanner/function_like.rs` sets that flag on each `\Closure` parameter of the four stubs, so `passed_functions` takes on what a collection method is given, as it does for a PHP# method. `prelude_stub_call` keeps only its change of the receiver, and `callbacks` runs only for plain PHP. `function-effects` passes.
7. **No editor in this PR (line 3997).** An editor package is its own feature, outside v1. Agents read effects in the checker's messages. Line 3997 changes to say the checker reports a method's effects in its errors, and section 28's line 3686 changes the same way. No language-server dependency is added.
8. **Four messages change, each gated to `.sharp` files:**
   - Line 3759: a bare call of a function an effect class replaces names the member on the object a constructor takes, as `29-effect-classes.md` writes it: "write `clock.now()` on the `Clock` your constructor takes". `REPLACEMENTS` in `expression/call/function_call.rs` gains one row per mapped function, and `member_form` writes an instance member that way.
   - Lines 3939 to 3942: an undeclared call names the class and offers the declaration's own effects, as the spec's message does: "Declare its effect in a .sharp file, such as `extern Mailchimp uses Http;`". Today the help offers `extern Mailchimp.addListMember;`.
   - `new ValueError("empty")` names `ValueError`, the class `new` names. Today it names `Error`, the class that declares the constructor, and offers `extern Error;`.
   - Line 3819: `_ENV`, and a `_SERVER` read of `"argv"` or `"argc"`, name `Environment`. Every other superglobal keeps "take a Request".
9. **A law reasons about list code (line 3993).** `crates/sharp-lean` refuses every collection with `unmodeled-construct`, and every lambda too: `issues.rs` holds "Mago does not translate collections to Lean." and "Mago does not translate lambdas to Lean." The options:
   - **A.** This PR translates a `List` to Lean's `List`, a lambda to a Lean `fun`, and the collection methods whose Lean function means the same, such as `map`, `filter` and `count` to `List.map`, `List.filter` and `List.length`. Every other construct stays refused by name.
   - **B.** Section 28's translator owns it, and line 3993's law clause stays refused by name until then.

   Approved: A, so a law over list code proves. Built: a `List` is Lean's `List`, a list literal a Lean list, `count()` is `List.length`, and `map` and `filter` of a lambda whose body cannot throw are `List.map` and `List.filter`. Any other `List` method is refused by name, as are a `Map`, a `Set` and any other lambda.

Decisions 4, 5, 6 and 8 are approved as written, with the codes `wrapped-class` and `foreign-outside-module`. Decision 6 builds on #136's mechanism. If Jordan's open answer drops the `uses f` syntax for an inferred one, as Swift's `rethrows`, the declarations change and the mechanism stays.

10. **Every built-in exception class comes declared pure** (team-lead's answer to question 4). `Builtins.sharp` declares each class by name, as it declares each function, and a row has pure code throw `new ValueError("empty")`.

## Questions for Jordan

Each one decides what users write. The note recommends, and decides none.

- **Answered:** question 2, the plain PHP block, built first in `fix/spec-plain-php-run`. Question 4 went to team-lead, as decision 10.
- **Question 1, answered Option B.** Pure limits only what a method reaches outside the program, so a pure method may change its own object and its arguments, and `readonly` after the parameters promises it changes nothing. The checker already holds this: `effect_outside` skips a change, and section 13 holds every implementation of a `readonly` method to it. Laws keep refusing a change, naming the method, because Lean sees an object as its values and cannot see two names that share one, so modeling a change as a new value would prove laws aliasing breaks. Line 3927 says so.
- **Question 3, answered Option B.** A `foreign` object is an ordinary type in a parameter and a return value, as the checker already treats it: a call on one a method takes or a call returns has its class's effect. `DateTimeImmutable` and `DateTimeZone` come declared pure, so `Date.format` no longer carries the `Clock` and `Environment` of every call that returns one. Line 3901 and its bullets say so, with the `foreign-parameter` example, and `foreign-in-static` and `foreign-outside-module` no longer say a `foreign` object reaches code only through constructors.
- **Problem 1, answered Option A:** decision 4.
- **Problem 2, answered Option A.** The exception classes in PHP's root namespace that `Builtins.sharp` declares are default names, as `Sharp`'s are, in `STANDARD_EXCEPTIONS` in the names crate, and a test holds the two lists equal.

1. **Lines 3873 and 3875: does a pure declaration also forbid changes?** Line 3873 says pure code "changes nothing it was given", and line 3768 says a `Formatter`'s implementations "must be pure". On master, an implementation of `void touch(Box box);` that writes `box.count = 1`, and one of `void keep(int n);` that writes `this.kept = n`, both compile. Refusing them would leave no way to declare an interface method whose implementations change their object. Recommended: a declaration without `uses` limits effects only, and section 13's `readonly` states what it may change, as `Money quote(Cart cart) uses Http readonly;` (line 1411) already does. Laws keep the full rule. Line 3875 would then read "Code without a body has no effect unless it says `uses`".
2. **Line 3871 against lines 3854 to 3856: where does the `environment` example get its `Environment`?** Its run line, `new Deploy(new Environment()).target();`, creates a `foreign` object outside a module, which line 3871 makes a compile error. The options:
   - the run line moves to a plain PHP block of the same example, which needs a `sharp/bin/spec` change in its own PR first, because a run line is the only line that may restate a `throws`
   - run lines are exempt, which no user code could be
   - line 3871 allows `new` of a `foreign` class in a test file too

   Recommended: the plain PHP block, because the rule then holds for all PHP# code.
3. **Line 3860: may a method take or return a `foreign` object?** "A `foreign` object reaches other code only through constructors." On master, `public Store make()`, `public Environment environment()` and `public string home(Environment environment)` all compile. Recommended: refuse a `foreign` type as a method's parameter or result, outside constructors and module bindings, so a class's effects stay visible from its constructor. The alternative keeps line 3860 as advice.
4. **Line 3744: do PHP's built-in exception classes come declared?** Pure code that throws one is refused: `public int parse(string text) => text == "" ? throw new ValueError("empty") : 1;` gets "calls `Error`, which has no `extern` declaration". Line 3744 promises built-in functions only. Recommended: the Composer package also ships `extern Exception;` and `extern Error;`, which cover every built-in exception through `extern_of`'s walk up the parents, and line 3744 says so.

**Waiting for Jordan, from #136:** whether a `uses f` parameter can be stored. On `feat/hosts`, `public void keep(Function<int()> body) uses body { this.kept = body; }` compiles, so the function's effects leave through the field. This Slice keeps #136's behaviour.

## How the states were observed

- **mago:** release, built from master `849232f4e1` with `CARGO_PROFILE_RELEASE_LTO=off`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 5.
- **mago on `feat/hosts`:** release, built from `origin/feat/hosts` `58ec82ca87` into `sharp/build/hosts-target/`, for the `uses f` rows.
- **Engine:** `sharp/bin/build` at `849232f4e1`, PHP 8.5.11 NTS DEBUG.
- **Spec run:** `sharp/bin/spec` with that pair: 201 examples checked, 1 ignored, 16 fail. Section 29 has 12 examples. 10 pass, and two fail:

```text
FAIL §29 pure-interface (docs/spec.md:3762)
  docs/spec.md:3793 expects no error, and the checker reports: Cannot find type `Files`.
  docs/spec.md:3779 expects no error, and the checker reports: Cannot find type `Files`.
  docs/spec.md:3797 expects a compile error, and the checker accepts it
FAIL §29 function-effects (docs/spec.md:3982)
  docs/spec.md:3986 expects no error, and the checker reports: Property `Cart.total` does not exist.
```

- **Probes:** seven copies of `sharp/bin/spec`'s harness project, each compiled with `mago --workspace <probe> compile`. Every message quoted in this note is that output. The sources are in `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-effects/probes/`:
  - `a/`: crossings (plain PHP with and without `extern`, a getter, a lambda and a method value passed to plain PHP, overrides, `foreign` classes, laws, superglobals, replaced functions and classes)
  - `b/`: `extern` rules, an `extern` on a returned class, `uses` on a body, `emit`
  - `c/`: Laravel facades and models with `extern`, a function value passed to plain PHP, a built-in exception, `uses f` and collection methods
  - `d/`: a declaration file's namespace, code in a declaration, `uses` on an import
  - `e/`: a real Laravel facade through `__callStatic`, returning a builder
  - `f/`: a law over list code, a `foreign` method parameter, a `virtual` override that adds an effect, and a root-namespace class without an import
  - `h/`: `uses f` and a stored `uses f` parameter, on `feat/hosts`

# Every rule of section 29, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), **missing** (nothing implements it), and **wrong** (it compiles or refuses with the wrong result or message).

## Plain PHP and `extern`

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3690 | Effects are tracked through held objects, every plain PHP call, and every `emit` | Works | None new | `effects/summary.rs`, `Effects::solve` |
| 3692, 3694 | Any PHP# code may call plain PHP; `plain-php-calls` | Works: the example passes | None new | |
| 3692 | PHP's built-in functions are the exception | Works: a bare `time()` is refused | Message, decision 8 | `report_bare_function_call` |
| 3704, 3706 | The effect is declared once with `extern`, after `uses`; `extern-declaration` | Works: the example passes | None new | `ExternMetadata`, `check_extern` |
| 3704 | A declaration holds no code | Refused by the parser: `extern Client uses Http { }` is a parse error | None new | |
| 3716 | A declaration file is an ordinary `.sharp` file whose namespace matches its path | Works: `namespace App.Elsewhere;` in `app/Stubs/` gets "This file's folder holds the namespace `App.Stubs`" | None new | |
| 3717 | `extern` covers a class, one method or a function, each after importing it | Works: `extern Thing.poke uses Clock;` lets `tick() uses Clock` call `Thing.poke()`. A full name without an import is a parse error, as section 23 makes every full name | None new | `extern_of` |
| 3718 | An `extern` with no effect declares its target pure | Works: `extern Run.apply;`, and `global.levenshtein` | None new | |
| 3720 to 3737 | `extern-functions`: `Pdf.render` and `global.now` compile, a bare `now` is refused | Works: the example passes. `extern now uses Clock;` gets "write `extern global.now`" | None new | `check_extern` |
| 3739 | At most one `extern` per target in the project | Works: a second `extern StripeClient uses Http;` gets `duplicate-extern` | None new | |
| 3740 | A declared call has that effect and fits a `uses` that names it | Works: `extern DB uses Database;` and `extern Model uses Database;` let `count() uses Database` and `saved() uses Database` run `DB.table(…).count()` and `OrderModel.find(1)?.save()` | None new | |
| 3741 | An object a declared call returns carries its effect | Missing: `Client.make().send()` under `uses Http` gets "calls `Session.send`, which has no `extern` declaration". A real facade's `DB.table("orders").where(…).count()`, and the same through `const query = DB.table("orders")`, are refused at `Builder.count` | `Effects::solve`'s carry, decision 3 | `extern_of`, `Effect::Unknown` |
| 3741 | An `extern` on the returned class wins | Works: with `extern Session uses Mail;`, `Client.make().send()` under `uses Http` gets "calls `Session.send`, which has the effect `Mail`" | None new | `extern_of` |
| 3742 | An undeclared call has an unknown effect: a body may make it, it is never pure, no `uses` accepts it, and the error names the declaration | Works: `go()` compiles, the law `pokedKeeps` gets `impure-law`, and `Pure.run` refuses `Thing.poke()` with "Declare it in a .sharp file: `extern Thing.poke;`" | Help text, decision 8 | `impure`, `laws_must_be_pure` |
| 3743 | A project declares every library; PHP# ships none for a framework | Works: `trace grep -i "illuminate\|laravel\|symfony\|carbon" sharp/composer` finds nothing | None new | |
| 3744 | PHP's built-in functions come declared | Works: `global.levenshtein` fits a pure method, `global.fopen` gets "has the effect `Files`". Each built-in exception class is declared pure by name (decision 10), and `extern_of` walks parents, so a plain PHP subclass with no `extern` of its own is pure too, as on master | `sharp/bin/builtins` writes an `extern` per exception class | `Builtins.sharp`, `sharp/bin/builtins` |

## The ten effect classes

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3746 to 3757 | Ten effect classes replace PHP's built-ins | Missing for nine: only `Environment.sharp` declares a `foreign class`. `uses Files` resolves to a name in `NAMED_EFFECTS` with no class | Nine `foreign` classes and seven types in `sharp/composer/library/Sharp/`, decision 1 | `29-effect-classes.md`, `Environment.sharp` |
| 3759 | A replaced function called directly names the method | Wrong: `time()` gets "write `global.time(…)`", which is the plain PHP call | `REPLACEMENTS` rows, decision 8 | `report_bare_function_call`, `member_form` |
| 3759 | Creating a built-in class the library replaces is refused, naming the member to use | Silently accepted: `new DateTime().format("Y")` compiles | `wrapped-class` at `new` and a static factory, decision 4 | `member_form`, `wrapped-function`'s shape |
| 3761 to 3801 | `pure-interface`: `greet` is pure, `remember` has `Files`, `FileFormatter.format` is refused | Fails: "Cannot find type `Files`" | `Sharp.Files`. The refusal itself already holds: `ClockShape.area` and `Loud.name` show it | `implementations_must_fit_their_declarations` |
| 3803, 3805 | A `foreign` class turns an effect into an object; holding one has that effect by name; `foreign-class` | Works: the example passes. `read(string key) uses Store` accepts `this.store.get(key)`, and `Pure.run` refuses it with "has the effect `Store`" | None new | `foreign_class` |
| 3815 | The ten are `foreign` classes; a project declares its own the same way | Missing for nine (above). A project's own works | Decision 1 | |
| 3817 | `Events` is `emit`'s effect and no `foreign` class | Works: `emit-effect` passes, and `Pure.run` refuses `emit new OrderPaid(id, 0)` with "has the effect `Events`" | None new | `Effect::Emit` |

## The environment and superglobals

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3819 | Each of the nine superglobals is a compile error | Works: `_SERVER`, `_GET`, `_POST`, `_COOKIE`, `_FILES`, `_REQUEST`, `_SESSION`, `_ENV` and `GLOBALS` each get "PHP# has no superglobals; take a Request" | None new | `report_superglobal` |
| 3819 | For the environment and the arguments, the error names `Environment` | Wrong: `_ENV["HOME"]` and `_SERVER["argv"]` get "take a Request". `Environment` appears only in the note | Message, decision 8 | `report_superglobal`, `standard_class` |
| 3819 | For request data, it names a parameter | Works: "take a Request" | None new | |
| 3821 to 3825 | `variable`, `arguments`, `currentDirectory`, `changeDirectory`, `require` and its message | Works: each has a row in `Zend/tests/sharp/environment.phpt` | None new | |
| 3827 to 3856 | `environment`: `region`, `target`, the `throws`, and `_SERVER` refused | Works: the example passes, run line included | None new. Question 2 | |
| 3858 | `Deploy` holds an `Environment`, so it has that effect | Works: a method that reads it, directly or through a getter, gets "reaches `Getter.region`, which calls `Environment.variable` with the effect `Environment`" | None new | |

## Where a `foreign` object lives

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3860, 3862 | A `foreign` object reaches other code only through constructors; `foreign-constructor` | The example passes. A method that returns one or takes one compiles (question 3) | After question 3 | |
| 3870 | A class's effects are the `foreign` classes it holds, directly or through fields, its plain PHP and its events, inferred | Works, per method: `Outer.run`, which calls `this.inner.region()`, gets "reaches `Inner.region`, which calls `Environment.variable`", and `Outer.name`, which calls `this.inner.plain()`, fits a pure declaration | None new | `Effects::solve` |
| 3871 | A `foreign` object is created in a module binding, and nowhere else | Silently accepted: `public Store make() => new Store();` and `public Environment environment() => new Environment();` compile | `foreign-outside-module`, decision 5, after question 2 | `foreign_class` |
| 3871 | No static stores one | Works: `private static Environment? shared = null;` gets `foreign-in-static` | None new | `check_foreign_statics` |

## Pure code and declarations

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3873 | Pure code reaches no `foreign` object, emits nothing, and calls no undeclared plain PHP | Works (rows above) | None new | |
| 3873 | Pure code changes nothing it was given | Works for laws. A pure declaration's implementation may change its argument or `this` (question 1) | After question 1 | `body_impurity` |
| 3873 | A `get` body's effects ride on each read | Works: `Pure.run` returning `this.region` is refused, reaching `Getter.region` | None new | `read_property` |
| 3873 | Laws reason only about pure code | Works: `pokedKeeps`, over a method that calls `Thing.poke()`, gets `impure-law` | None new | `laws_must_be_pure` |
| 3875 | Code without a body is pure unless it says `uses`: interface methods, abstract methods, function types | Works: `ClockShape.area` against `Shape.area`, and `Function<int()> pure = () => global.time()` gets "a `Function<int()>` cannot hold it" | None new | |
| 3875 | Every implementation is held to its declaration | Works: `Loud.name` against `Named.name`. An override of a `virtual` method with a body may add an effect, and a call through the base reaches it: `CallsBase.run` gets "reaches `Loud.label`, which calls `global.time` with the effect `Clock`" | None new | `unfit_implementations`, `solve`'s overrides |
| 3877 | `effectful-declarations` | Works: the example passes | None new | |
| 3889, 3891 | A function type lists its effects inside its brackets; `function-type-effects` | Works: the example passes | None new | |
| 3899 to 3942 | `extern-implementations`: a plain PHP call fits only through an `extern`, with the spec's message | Works: the example passes. The message differs from lines 3939 to 3942, which `sharp/bin/spec` does not compare | Message, decision 8 | `impure` |
| 3944 to 3973 | `uses Events` lets an implementation emit; `emit-effect` | Works: the example passes | None new | |

## Functions passed on

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3976 | `uses f`: each call has the effects of the function passed as `f`, with or without a body | Refused on master: `Carries.carried(() => this.environment.require("HOME"))` gets "a `Function<string()>` cannot hold it". Works on `feat/hosts`: the call compiles, and a pure method that makes it is refused naming `Environment` | #136 | |
| 3976 | A stored `uses f` parameter | Silently accepted on `feat/hosts` | Waiting for Jordan | |
| 3978 to 3991 | `function-effects` | Fails on master on the harness (`Cart.total`). #136 adds `Cart.total` and removes the entry | #136 | |
| 3993 | The collection methods are declared with `uses f`, so pure list code stays pure | Works in behaviour: `this.carts.map(c => this.gateway.charge(c))` gets "calls `PaymentGateway.charge`, which has the effect `Http`", and `this.carts.map(c => c.orders.count())` fits. Declared through prelude stubs and `prelude_stub_call`, not `uses f` | Decision 6 | #136's `uses_arguments` |
| 3993 | Laws can reason about that list code | Refused by name: `doubledKeepsCount` over `xs.map(x => x * 2)` is pure, and gets `unmodeled-construct`, "Mago does not translate collections to Lean." | Decision 9 | `crates/sharp-lean/src/translate/body.rs`, `issues.rs` |
| 3995 | A plain PHP call has its `extern`'s effects plus those of each lambda, method value and function value passed | Works: `Run.apply(() => global.time())`, `Run.apply(this.tick)`, `Run.apply(step)` for `step` of type `Function<int() uses Clock>`, and the spec's own `global.call_user_func(() => global.time())` in a getter, each refused naming `Clock`. With `extern Run.apply uses Http;`, `uses Http` refuses the `Clock` | None new | `callbacks` |
| 3997 | Code with a body writes no `uses` but `uses f` | Works: `public void declares() uses Http { }` gets "A method with a body takes no effect in `uses`" | None new | semantics |
| 3997 | The checker works out each method's effects | Works | None new | `Effects::function_effects` |
| 3997 | The editor displays them | Missing: no language server | Decision 7: the line changes to the checker's messages | |

## The crossings real code hits

- **Plain PHP with an `extern`:** works, for a class, a method and a function.
- **Plain PHP without one:** refused in pure code and under every `uses`, with the declaration to write. A body with no declaration still compiles.
- **Laravel facades:** a facade's own call works through `__callStatic` and `extern DB uses Database;`. Every call on what it returns fails until line 3741 holds.
- **Laravel models:** `extern Model uses Database;` covers `OrderModel`, through `extern_of`'s walk up the parents. A query builder a model returns fails the same way.
- **A getter with effects:** works. A read carries the getter's effects, and a getter never writes `this`.
- **A lambda passed to plain PHP:** works, and so do a method value and a function value.
- **An interface that promises purity:** works for effects. It does not limit changes (question 1).
- **An override that adds an effect:** works. An override of a pure interface method is refused. An override of a `virtual` method with a body is not, and every call through the base gets its effect.

# Existing work, and what the Slice takes from it

- **mago-sharp #75, `feat/slice-effects` (merged).** Impure getters and `extern`. The summary, the solve and the getter rule come from it, and the Slice keeps all of them.
- **mago-sharp #122 and #126 (merged).** `uses` inside a function type's brackets, and a function value held to it. Kept.
- **mago-sharp #99 and #177 (merged).** Effect names in messages, and a file that imports a class named like an effect. `sharp_effect_name` keeps both after `NAMED_EFFECTS` goes.
- **mago-sharp #170 and #162, php-sharp #127 (merged).** `emit` and `Events`, `extern event`, and `Dispatcher.dispatch` held to nothing. Kept.
- **mago-sharp #150, #83 and #77, php-sharp #113 (merged).** Laws, and the rule that a law is pure. Kept.
- **mago-sharp #180 closed, moved to php-sharp #118 (merged).** `foreign` classes, `foreign-in-static`, effects on interfaces, and `Environment`. Its design note, `29-effect-classes.md`, is the design decision 1 builds.
- **php-sharp #124 (merged).** Calls into plain PHP through their namespace, and the bare-call refusal. `REPLACEMENTS` gains the effect-class rows.
- **php-sharp #136, `feat/hosts` (open).** `uses f`: `USES_PARAMETER` in the scanner, `effect_free_parameter_type` in `invocation/analyzer.rs`, and `uses_arguments`, `passed_function` and `method_value_runs` in `effects/summary.rs`. It removes `§29/function-effects`. The Slice merges after it, reuses `uses_arguments` for decision 6, and changes none of it.
- **Issue #60 (open).** The first statement of section 29's work. Its "Done when" list is this Slice's rows, except `Php` for unclassified built-ins, which line 3744 replaced with `Builtins.sharp`. The PR closes it.
- **Upstream Mago `feat/language-server`, `1b7f67494e`.** The protocol layer of decision 7.

# Boundaries

- **Hosts (#136).** Owns `uses f`. Both PRs change `effects/summary.rs`, and this one merges second, onto #136's version.
- **Section 8.** `output-and-exit` fails for the same missing `Console` and `Process`. They ship here, so its entry leaves `sharp/baseline/spec/§8/` in this PR, which the list's ratchet requires. `standard-library-calls` (`Math.max`) stays.
- **Section 28.** Its editor line (line 3686) changes to the checker's messages, as line 3997 does. Decision 9 extends section 28's Lean translator, and changes no law that compiles today.
- **Section 12.** Decision 6 changes how the effects summary reads the collection stubs, and no collection behaviour. `prelude_stub_call`'s change of the receiver stays.
- **Section 32.** Decision 1 adds bindings to the standard library's `Module.sharp`. Decision 5 reads where a module binding's expression is. The container's behaviour does not change.
- **The harness.** `App.Clock` stays: section 25's `Order(Row row, Clock? clock = null)` resolves to it, and a file of namespace `App` keeps it over `Sharp.Clock`. The Slice changes no harness file. A harness change would go in its own PR first.

# Public names this Slice adds or changes

| Name | Where | What it is |
|---|---|---|
| `Clock`, `Random`, `Console`, `Process`, `Files`, `Http`, `Cache`, `Mail`, `Database` | `sharp/composer/library/Sharp/` | The spec's names. Members as `29-effect-classes.md` lists them |
| `Duration`, `HttpMethod`, `HttpRequest`, `HttpResponse`, `ProcessResult`, `MailAddress`, `MailMessage` | `Sharp.Time`, `Sharp.Net`, `Sharp` | As `29-effect-classes.md` lists them |
| `foreign-outside-module` | `IssueCode::ForeignOutsideModule` | Line 3871 |
| `wrapped-class` | `IssueCode::WrappedClass` | Line 3759 |

`foreign-outside-module` candidates, read at the callsite `IssueCode::ForeignOutsideModule` in "`new Store()` is `foreign-outside-module`":

- `foreign-outside-module` names where the object was made, and pairs with `foreign-in-static`, the other half of line 3871. The reader adds "created".
- `foreign-created-outside-module` adds that word. It is long.
- `new-foreign` names the act. It does not say what is wrong with it.
- `foreign-construction` names the act too, and says no more.
- `foreign-not-bound` says no binding made it. `Missing::Unbound` already means a missing service in `statement/module.rs`.

Recommended: `foreign-outside-module`, the sibling of `foreign-in-static`. Runner-up: `foreign-created-outside-module`.

`wrapped-class` candidates, read at "`new DateTime()` is `wrapped-class`: write `clock.now()`":

- `wrapped-class` pairs with `wrapped-function`, which refuses a function the standard library wraps, as line 3759 says "the same way".
- `replaced-by-library` pairs with `replaced-by-syntax`. It names no kind.
- `replaced-class` uses the spec's word "replaces".
- `built-in-class` names what it is. Most built-in classes are allowed.
- `library-class` reads as a class of the library.

Recommended: `wrapped-class`. Runner-up: `replaced-class`.

# Files

```text
sharp/mago/
├── crates/analyzer/src/
│   ├── effects/mod.rs                  <- NAMED_EFFECTS deleted; solve carries a declared call's effects to the
│   │                                      class it returns (decision 3)
│   ├── effects/summary.rs              <- prelude_stub_call keeps only its change of the receiver (decision 6);
│   │                                      instantiate names the class `new` names
│   ├── effects/check.rs                <- the undeclared-call help names the class and the declaration's effects
│   ├── expression/instantiation.rs     <- foreign-outside-module (decision 5); wrapped-class at `new` (decision 4)
│   ├── statement/module.rs             <- marks a binding's expression, where a foreign object may be created
│   ├── expression/call/static_method_call.rs  <- wrapped-class at a static factory (decision 4)
│   ├── expression/call/function_call.rs  <- REPLACEMENTS rows for the nine classes; member_form writes an
│   │                                        instance member on the constructor's object; WRAPPED_CLASSES
│   ├── code.rs                         <- ForeignOutsideModule, WrappedClass
│   └── tests/sharp.rs                  <- one test per rule below
├── crates/codex/src/scanner/function_like.rs  <- TAKES_ON_EFFECTS on the collection stubs' \Closure parameters
├── crates/semantics/src/internal/checker/sharp.rs  <- report_superglobal names Environment for _ENV and argv
├── crates/semantics/tests/sharp.rs     <- the superglobal rows
└── crates/sharp-lean/src/translate/body.rs  <- decision 9: a List, a lambda, map, filter and count in Lean
sharp/bin/builtins                      <- declares every built-in exception class (decision 10)
sharp/composer/library/Sharp/
├── Builtins.sharp                      <- regenerated: an extern for each built-in exception class
├── Clock.sharp  Random.sharp  Console.sharp  Process.sharp  Files.sharp
├── Http.sharp  Cache.sharp  Mail.sharp  Database.sharp  ProcessResult.sharp   <- new (decision 1)
├── Time/Duration.sharp                 <- new
├── Net/HttpMethod.sharp  Net/HttpRequest.sharp  Net/HttpResponse.sharp
├── Net/MailAddress.sharp  Net/MailMessage.sharp   <- new
└── Module.sharp                        <- binds the seven classes whose constructors take nothing
sharp/bin/build                         <- --with-curl (decision 2)
sharp/docker/Dockerfile                 <- libcurl4-openssl-dev (decision 2)
sharp/docker/runtime/Dockerfile         <- libcurl4-openssl-dev, if it lacks it (decision 2)
sharp/baseline/spec/
├── §29/pure-interface                  <- deleted (#136 deletes §29/function-effects)
└── §8/output-and-exit                  <- deleted
Zend/tests/sharp/                       <- added: Effects.sharp and one effect_<class>.phpt per class
docs/architecture/slices/
├── 29-effects.md                       <- this note
└── 29-effect-classes.md                <- "ship in their own PR" becomes "ship with section 29"
docs/spec.md                            <- lines 3686 and 3997 name the checker's messages, not an editor; the
                                           environment example's run moves to a plain PHP block; line 3744
                                           names the built-in exception classes
```

# The tests

Each rule gets one test, with each form as a row, watched failing before its code.

- **`crates/analyzer/tests/sharp.rs`:**
  - line 3741: a chain, a local, a field, a `static` return, a facade through `__callStatic`, and an `extern` on the returned class that wins
  - line 3759: each replaced function's message, and each `wrapped-class` row, with the standard library exempt
  - line 3871: `new` of a `foreign` class in a method, a property's initial value and a lambda, against a module binding and a test module
  - line 3993: a collection method with an effectful lambda, a method value and a function value, through `passed_functions`
  - the four messages of decision 8
  - line 3741's class returned by two declared calls with different effects, which takes both
  - decision 10: pure code throws `new ValueError("empty")`
- **`crates/semantics/tests/sharp.rs`:** `_ENV`, `_SERVER["argv"]`, `_SERVER["argc"]` and `_SERVER["HTTP_HOST"]`.
- **`crates/sharp-lean` (decision 9):** a law over `map`, `filter` and `count` proves, and one over a collection method with no exact Lean function stays refused by name.
- **`Zend/tests/sharp/`, each in every opcache mode, preloading included:** every member of each effect class, with its failures as `29-effect-classes.md` lists them:
  - `Http` against PHP's own test server, `sapi/cli/tests/php_cli_server.inc`
  - `Mail` with `sendmail_path` writing to a file, as `ext/standard/tests/mail/` does
  - `Database` on SQLite in memory
  - `Process.run` on `php -r`, and `Process.exit` skipping a `finally`

  The tests that touch shared functions pass a scribbled-memory loop, `USE_ZEND_ALLOC=0 MallocScribble=1`.
- **Plain PHP:** `mago analyze` of a plain PHP project prints the same issues before and after.
- **Spec:** `sharp/bin/spec` passes every section 29 example, and `sharp/bin/spec --completes` passes on `Completes: §29`.

# The PR

First, `fix/spec-plain-php-run` changes `sharp/bin/spec` alone, so a plain PHP block of an example may restate a claim a run line restates today. Team-lead merges it ahead of everything else. Then one php-sharp PR on `feat/effects`, after #136 merges. It merges `origin/master` once #136 is in, when team-lead says it is at the head of the landing queue. Its body says `Completes: §29` and closes issue #60. It changes no file in `sharp/harness/`.

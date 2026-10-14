# Slice 15: Events

Spec section 15, finished across the checker, the bridge and the engine in one php-sharp pull request, `feat/events-engine`. team-lead approved decisions 1 to 7 as written and ruled on the questions, under "Rulings". "What the branch holds" says what is built.

## The answer first

**The checker and the bridge already do section 15. The engine does none of it.** mago-sharp #162 and #170, now in `sharp/mago/`, parse, check and lower `event`, `extern event`, `emit` and `on`, and write `.sharp/listeners.php`. php-sharp #121 checks that each listener's class can be built from its namespace's modules. But `emit` lowers to `\Sharp\Internal\emit($e)`, which the engine does not define, so the first `emit` that runs throws:

```text
Error: Call to undefined function Sharp\Internal\emit()
```

**Five of section 15's seven examples pass `sharp/bin/spec` only because none of them claims a value or a throw,** so `sharp/bin/spec` never runs an `emit`. The two that fail are listed in `sharp/baseline/spec/§15/`:

- `listeners`: "`JobFailed` is not an event." The harness has the plain PHP `Illuminate\Queue\Events\JobFailed` but no `extern event JobFailed;`.
- `scoped-listener`: "Comparisons do not chain". `Events.on<RowImported>(…)` needs a call's type arguments, which php-sharp #102 (generics, open) adds, and `Sharp.Events` has no class.

**The work is mostly engine and standard library, and it reuses three finished pieces:** the emit engine on php-sharp `feat/slice-events` (`6970c355a8`, never opened as a pull request), #121's `sharp_container_build`, and the standard library's own `Module.sharp` binding.

**Section 15 cannot complete on master alone.** Two of its rules sit on open pull requests:

- `Events.on<RowImported>(…)` needs #102's type arguments on a call and `typeof` of a method's type parameter.
- "Code without a body allows it with `uses Events`" needs #118's `uses` on an interface or abstract method. Today `void complete(int orderId) uses Events;` in an interface is a parse error.

## Decisions team-lead approves

1. **Sequencing.** Build every part that needs neither #102 nor #118 now, on `feat/events-engine`. Merge `origin/master` once both land, then build `Events.on<T>`, and open the one pull request with `Completes: §15`. #118 has merged. Decision 6's correction adds #124, under "Listening for a while". Rejected: merging `feat/slice-generics` and `feat/foreign-classes` into this branch now, because the pull request would carry both diffs until they merge and every conflict would be resolved twice.
2. **The engine creates each listener's object through #121's container,** `sharp_container_build(ce, &object)`, once per listener per event. That is the `transient` lifetime the spec and `docs/architecture/modules.md` state, with services from the modules of the listener's namespace, an environment module or a running test's module first.
3. **A missing `.sharp/listeners.php` means no listeners.** #170's `mago compile` wrote the table only while a listener existed and deleted it after. `6970c355a8` threw `CompileError` for a missing table, which would break every project without a listener. A damaged table still throws that `CompileError`. Ruling 5 makes `mago compile` write the table in every project with a PHP# file, so a missing one now means the project has none.
4. **The dispatcher is a standard library service, `Sharp.Dispatcher`, which a module binds,** and the engine hands it every `emit`. The standard library binds `ImmediateDispatcher`. Corrected by team-lead after the review: `dispatch(Any emitted, List<Function<void(Any)>> listeners)` calls each `listener(emitted)`, so one closure kind serves `emit` and PSR-14. The API is drawn under "The dispatcher".
5. **The dispatcher is one service for the whole app,** as the spec says: "An app replaces the dispatcher once at startup". Corrected by team-lead after the review, which found that resolving it from the emitting class's namespace missed every event a PHP# package in `vendor/` or plain PHP emits. `emit` resolves `Sharp.Dispatcher` from the app's top module that binds it, whoever emits, and a running test's module still replaces it. Drawn with how the top module is found under "The dispatcher".
6. **`Events.on<T>` lowers to one engine call, and its handle is an engine object.** `Sharp\Internal\listen(string $event, \Closure $listener): \Sharp\Listening`, and `Sharp\Listening` stops the listener in its destructor. The standard library may call a `Sharp\Internal` function, and no other PHP# code may. Corrected by team-lead: `Sharp.Events` is `public static class Events`, with no modifier for effects. The spec says "`Events` is the effect of `emit`. It is the one standard effect that is not a `foreign` class: a method has it when its body emits, and no object carries it", so #118's handling of `Events` as an effect with no class stays. `on` gets the effect `Events` by inference from its body, because `listen` is declared `extern Internal.listen uses Events;`, the way every plain PHP function's effects are declared.
7. **The harness gains two spec files.** `sharp/harness/app/Stubs/Laravel.sharp` declares `extern event JobFailed;`, and `sharp/harness/app/Imports/RowImported.sharp` declares `public event RowImported(int row);`. Each sits at the path its own spec example writes, so `extern-event` and `standalone-event` replace them with the same text.

## Rulings

team-lead ruled on the five questions the first version of this note asked, each with its spec line:

1. **`Events.on<T>` with a type that isn't an event is refused with the messages the `on` clause gives** (`check_listed_events` in `crates/analyzer/src/statement/class_like/listener.rs`). Spec: "`Events.on<RowImported>(e => …)` starts a listener and returns a handle."
2. **A new object for every event, whatever lifetime a module gives the listener's class,** as Jordan ruled. Spec: "PHP# creates a new object for every event and passes its constructor's services from the module of its namespace."
3. **`Dispatcher.dispatch` may reach any effect, because the listeners it runs may.** Spec: "`emit` has the effect `Events`" and "An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries".
4. **A plain PHP library's own events reach PHP# listeners through PSR-14,** as Jordan ruled. PHP# offers its listeners as a PSR-14 listener provider, and builds no adapter for any framework. Drawn under "Plain PHP events through PSR-14", and section 15 of the spec now states the rule.
5. **The table records its inputs, and the engine checks them only when `sharp.compile_command` is set.** Spec: "Nothing is wired by hand." Corrected after the review, which found a listener in a new folder and an `on` added to a class nothing loads both unheard: the inputs are every PHP# file and the listing of its folder and of each folder above it, and a listing names a folder's namespace folders too. So any new or changed listener makes the next `emit` compile, and a deploy's `mago compile` is exact.

## What the branch holds

Built and tested. #118, #124 and #102 merged into master and then into this branch:

- `emit` hands the event and one function per listener to the app's `Sharp.Dispatcher`, whoever emits (decision 5). Each function takes the event and creates its listener's object through the container when it runs (ruling 2). `ImmediateDispatcher` is the standard library's binding.
- `Sharp\Internal\listen` and `Sharp\Listening`, the engine half of `Events.on<T>`, tested from plain PHP.
- The table's inputs and the development check (ruling 5). `mago compile` writes the table in every project with a PHP# file.
- The container runs a module file in a symbol table of its own, which ended the tracing-JIT leak the first version of this note named as a risk.
- The harness's `extern event JobFailed;` and `RowImported`, so `listeners` left `sharp/baseline/spec/`.
- Ruling 3: no implementation of `Sharp\Dispatcher::dispatch` is held to its declaration, and `dispatch` declares `uses Events`, so a call of it has the effect `emit` has.
- "Code without a body allows it with `uses Events`", which #118 built, now has a section 15 test.
- Ruling 4: `Sharp\Psr\ListenerProvider` gives a PSR-14 dispatcher every listener of an event, and `sharp/composer` requires `psr/event-dispatcher`.

- `sharp/composer/library/Sharp/Events.sharp` and its `on<TEvent>`, in the form "Listening for a while" draws. #102 parses `on<TEvent>` and carries `TEvent` to `typeof(TEvent)`, and #124 lets `extern Internal.listen uses Events;` name a namespaced function and lowers `Internal.listen(…)` by its full name.
- What only `Events.sharp` uses: the prelude's `Sharp\Internal\listen` and `Sharp\Listening`, and the refusal of `import Sharp.Internal;` outside the standard library, so PHP# code listens one way.
- Ruling 1's refusal of `Events.on<T>` when `T` is not an event or an interface an event implements.
- `scoped-listener` left `sharp/baseline/spec/`, and every section 15 example passes.

The review's fixes, each with a test seen failing first:

- The app's dispatcher serves every `emit` (decision 5), and `Dispatcher.dispatch` takes `List<Function<void(Any)>>` (decision 4).
- Every PHP# file is an input of the table, and a listing names its namespace folders (ruling 5).
- With `sharp.compile_command` set, the table is read past opcache.
- `Sharp\Listening` owns its listener's function and reports it to the cycle collector, so a handle kept in a property of the object its listener uses is collected with it, and the listener stops.
- `namespace Sharp` and every namespace under it are refused outside the standard library, with `reserved-name-outside-library`.
- A dropped `Events.on` handle is reported in PHP# words. A table naming a class or method the code lacks says it is out of date. `Events.on(e => { })` gives one error. The prelude declares `listenersFor`, and `sharp/composer/mago.toml` analyzes `src/Psr/ListenerProvider.php`.

Known follow-up, approved as its own pull request after this one: `write_project` in `sharp/bin/spec` copies all of `sharp/composer` into an example's project, as a Composer install does, and the harness's `Sharp/Psr/ListenerProvider.php` copy is deleted.

# Existing work, and how this Slice reuses it

| Work | What it holds | Reuse |
|---|---|---|
| mago-sharp #162, `feat/slice-events`, merged | `event` and `extern event` at file level, the nested-type refusal, get-only interface properties | In `sharp/mago/` on master. Nothing to do. |
| mago-sharp #170, `feat/events-emit`, merged | `emit`, `Effect::Emit`, `on`, `check_listener`, `.sharp/listeners.php`, `emit` lowered to `\Sharp\Internal\emit($e)` | In `sharp/mago/` on master. The table's row format is the engine's input. |
| mago-sharp #172, `feat/events-on`, closed | the `on` half of #170 | Folded into #170 before it merged. Nothing left. |
| php-sharp #51, `docs/events`, merged | the spec text of section 15 | The source of truth. |
| php-sharp #121, `feat/modules`, merged | the container, `check_listener_services` | `sharp_container_build` creates every listener's object. |
| php-sharp `feat/slice-events`, `6970c355a8`, slot 3, no pull request | the old Slice note, `Sharp\Internal\emit` in `ext/sharp/sharp.c`, its fixtures and four engine tests | Phase 2 merges it into this branch, keeping its history. The table read, the per-event filter and the fixtures stay. `new` becomes the container, a missing table becomes no listeners, and the listeners go to the dispatcher. Its note is replaced by this one. Slot 3's worktree is clean (`git status` shows nothing past the branch). |

The old note on `feat/slice-events` was approved, and its decisions on table order, exceptions, nested `emit` and the snapshot stay. This note replaces its sequencing, because #162, #170 and #121 landed since, and its "PR C", because the container exists now.

# How the states were observed

- **mago:** a release `mago` built from master `76d220c199` in this worktree, with `CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --release --locked -j 2`, at `sharp/build/darwin-arm64/mago/release/mago`.
- **engine:** `sharp/bin/build` from the same commit, PHP 8.5.11 NTS DEBUG at `sharp/build/darwin-arm64/sapi/cli/php`.
- **examples:** `TEST_MAGO_EXECUTABLE=<that mago> <that php> sharp/bin/spec` printed "Checked 180 examples, ignored 1, and 82 fail." Its section 15 lines are quoted below.
- **single rules:** a probe project at `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events-engine/probe`, laid out as `sharp/bin/spec` lays out an example: the harness, the standard library at `vendor/heyjordanparker/php-sharp-composer/`, and `app/Probe/` with an interface, two events, an `extern event JobFailed;`, a listener class with a `Mailer` service and four listeners, a `Module.sharp` binding `Mailer`, an emitting `Register`, and one file per refusal. `mago compile` ran on it, and `run.php` ran its compiled `Register.pay` on the engine.
- **the handle:** `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events-engine/handle.php`, a destructor in a local nobody reads, on the engine without opcache, with opcache's optimizer, and with the function and tracing JIT.

# Every line of section 15

The states are **works**, **refused** (the spec's code gets an error), **compiles, never runs** (the checker accepts it, and the engine cannot run it) and **missing**.

| Spec line | State on master `76d220c199`, before this branch | Mechanism | Reuses |
|---|---|---|---|
| "An event says that something happened … Nothing is wired by hand." | Compiles, never runs | engine `emit`, the table | #170's table |
| `event-declarations` (five files) | Compiles, never runs. Passes `sharp/bin/spec`. The probe's `Register.pay`, the same `emit`, threw `Call to undefined function Sharp\Internal\emit()` | engine `emit` | #162, #170 |
| **Declaring:** an event is a class whose parameters are get-only properties | Works | none new | #162's `Lowering::event`, a `final` class of `readonly` promoted parameters |
| **No type nests**, `standalone-event` | Works. Passes. A nested event gave "A class can't declare a type inside it: declare `Lost` in its own file." | none new | #162 |
| **Emitting:** "Any code may raise any event type", `emit-forms` | Compiles, never runs. Passes | engine `emit` | #170 |
| "`new Order.Paid(…)` always calls `Order`'s named constructor `Paid`" | Works. `new Register.Paid(7)` gave "`Register` has no constructor `Paid`." | none new | named constructors |
| **Plain PHP events:** `extern event`, `extern-event` | Works in the checker. Passes. Plain PHP's raise is missing | engine `emit`, which plain PHP calls | #162 |
| **`extern event` states the class's effects after `uses`**, `extern-event-effects` | Works. Passes | none new | #162, `extern_of` |
| **Listening:** the `on` clause, zero parameters or one every listed event shares | Works in the checker. `listeners` fails: "`JobFailed` is not an event." | harness `app/Stubs/Laravel.sharp` | #170's `check_listener` |
| "PHP# creates a new object for every event and passes its constructor's services from the module of its namespace. A listener's class needs no binding." | Checker works: #121's `check_listener_services`. Engine missing | `sharp_container_build` per listener per event | #121 |
| "A listener on an interface hears every event that implements it." | Checker works. Engine missing | `instanceof` against each listed type | `6970c355a8`'s `sharp_listeners_of` |
| "Every `on` is checked before the code runs", `listener-errors` | Works. Passes | none new | #170 |
| **Listening for a while:** `Events.on<T>` and its handle, `scoped-listener` | Refused: "Comparisons do not chain" | #102's call type arguments, `Sharp.Events`, `Sharp\Internal\listen`, `Sharp\Listening` | #102, decision 040's inlining |
| **Effects:** a method that emits shows `Events` | Works. #170's `emit_has_the_effect_events` | none new | #170 |
| **Effects:** "code without a body allows it with `uses Events`" | Refused: an interface's `void complete(int orderId) uses Events;` is "Parse error encountered during parsing" | #118 | #118 |
| **Timing:** the default dispatcher runs listeners before `emit` returns | Missing | `ImmediateDispatcher`, bound by the standard library | the standard library's `Module.sharp` |
| **Timing:** "An app replaces the dispatcher once at startup … or not at all in tests" | Missing | a module's `Dispatcher` binding, a test module's binding | #121's module order |

The probe compiled `OrderNotices`, whose `alert(JobFailed e) on JobFailed` names the plain PHP class that `app/Probe/Laravel.sharp` marks with `extern event JobFailed;`. That is the harness change `listeners` needs. The probe's `.sharp/listeners.php`:

```php
return [
    ['App\Probe\OrderNotices', 'send', ['App\Probe\OrderPaid', 'App\Probe\OrderRefunded'], true],
    ['App\Probe\OrderNotices', 'refresh', ['App\Probe\OrderPaid'], false],
    ['App\Probe\OrderNotices', 'audit', ['App\Probe\OrderEvent'], true],
    ['App\Probe\OrderNotices', 'alert', ['Illuminate\Queue\Events\JobFailed'], true],
];
```

# How listeners are found and registered

**Found at build time, every `on` in the project.** `mago compile` writes one row per listener of every accepted file, ordered by class name and then by the method's place in its class. The project includes `vendor/`: `mago compile` compiles each vendor PHP# file into the project's own `.sharp/` folder, and refuses a package that holds a `.sharp/` folder of its own ("the engine would read it instead of the project's .sharp folder"). So one table per project lists every listener, across namespaces and packages. A listed type is a full class name, so an `extern event` such as `Illuminate\Queue\Events\JobFailed` is a row like any other.

**Registered at run time, with no scan.** `emit` finds the folder that holds `.sharp/` above the file that declares the event's class, as the engine finds it for a source file. An `extern event` in `vendor/` finds the project's table, because no package may hold its own. It reads the table once per request through `zend_compile_file`, so opcache keeps it in shared memory, and takes the constant array the file returns without running the file. For each event class it keeps, for the request, the rows whose listed types the event is an `instanceof`, in table order. The table read and the per-event filter are `6970c355a8`'s `sharp_listener_table` and `sharp_listeners_of`.

The table, as `mago compile` writes it:

```php
<?php

return [
    'inputs' => [
        ['*.sharp', 0, 0, '99aa06d3014798d86001c324468d497f'],
        ['app/*.sharp', 93, 0, 'a18a24ea6fd2f5b3f064ed26f8c4e443'],
        ['app/AfterCommitDispatcher.sharp', 173, 1792001535371212248, 'b74c2fb0b4a36383e0fb6693b9be7f97'],
        ['app/Module.sharp', 103, 1792001534314190208, 'e6fca0ed7f19ade42991cb3a0c8099e6'],
        ['app/OrderEvent.sharp', 73, 1792001532747311544, '402cc7bc63a1c719f9195120bacbc534'],
        ['app/OrderNotices.sharp', 153, 1792001532051738871, '297e2f94dfdf895b91918498677ba11a'],
        ['app/OrderPaid.sharp', 66, 1792001533503708796, '7af9e91f1e56d4c22149f01cad7fbaf0'],
        ['vendor/*.sharp', 0, 0, '99aa06d3014798d86001c324468d497f'],
        ['vendor/heyjordanparker/*.sharp', 0, 0, '99aa06d3014798d86001c324468d497f'],
        ['vendor/heyjordanparker/php-sharp-composer/*.sharp', 0, 0, '99aa06d3014798d86001c324468d497f'],
        ['vendor/heyjordanparker/php-sharp-composer/library/*.sharp', 7, 0, 'dfe8f5ef6d07b54f90316c52f021483a'],
        ['vendor/heyjordanparker/php-sharp-composer/library/Sharp/*.sharp', 17, 0, '39542a6e047403498ea08c43cf0dfe54'],
        ['vendor/heyjordanparker/php-sharp-composer/library/Sharp/Dispatcher.sharp', 131, 1792001537678659931, 'afac426e86412dd0e5d96728a5736f45'],
    ],
    'listeners' => [
        ['App\OrderNotices', 'send', ['App\OrderPaid'], true],
        ['App\OrderNotices', 'refresh', ['App\OrderPaid', 'App\OrderEvent'], false],
    ],
    'dispatcher' => ['app/Module.sharp', 'app'],
];
```

That is the table of a project with five files in `app/` and the library's `Dispatcher.sharp`, where `app/Module.sharp` binds `singleton Dispatcher = AfterCommitDispatcher;`. `library/*.sharp` is 7 bytes, `Sharp/` and its newline, the namespace folder below it.

- **Each input is a workspace-relative path, its size, its modification time in nanoseconds and its xxh3-128 hash,** stamped by the bridge's `unit::stamp`, the stamp a `.sharpc` file's inputs carry. A path ending in `*.sharp` is that folder's listing, as `unit::SOURCE_LISTING` defines it: its `.sharp` files and its namespace folders, the folders whose name starts with an uppercase letter, each written with a `/`. The inputs are every PHP# file and the listing of its folder and of each folder above it, so a new listener anywhere, in a new folder or in a class that had none, changes an input. The engine compares them with `sharp_stamp_current`, the one comparison a `.sharpc` file's inputs use too.
- **`dispatcher` is the app's module whose binding of `Sharp.Dispatcher` serves every `emit`,** as its path and its namespace, or `null` when no app module binds it. "The dispatcher" says how `mago compile` finds it.
- **Only with `sharp.compile_command` set,** the table is read past opcache, since this request's compile or an earlier one may have rewritten it, and the first `emit` of a request compares its inputs, and on a change runs the command once, as an out-of-date compiled file does, then reads the table it wrote. Without the setting, the engine reads the table through opcache and nothing else.
- **A row naming a class or method the code no longer has** throws "`.sharp/listeners.php` is out of date: it lists `App.Demo.Gone`, which no longer exists. Run vendor/bin/mago compile.", as an out-of-date compiled file does.
- Precedent: Laravel's `php artisan event:cache` writes `bootstrap/cache/events.php` the same way, and PSR-14's `ListenerProviderInterface::getListenersForEvent` matches by class and interface.
- **The table is not run, and neither is anything that would give the caller a symbol table.** `6970c355a8` measured why: code run from inside `emit` gives the calling frame a symbol table that a tracing-JIT frame never frees. `emit` reaches the container's `sharp_container_read`, which ran a module file with `zend_execute`. `events_scoped_jit_tracing.phpt` with `sharp/bin/test --repeat` leaked a 56-byte `HashTable` and its 320 bytes. `sharp_container_execute` now runs a module file in a symbol table of its own, for `emit` and `Container::get` alike.

# How a listener's object is created

A listener's function creates the object as the container creates a bound class, with no binding row. `6970c355a8` called `object_init_with_constructor(&listener, ce, 0, NULL, NULL)`. `Sharp\Internal\Listener::hear` calls #121's `sharp_container_build` when the dispatcher calls the function:

```diff
-		if (object_init_with_constructor(&listener, ce, 0, NULL, NULL) == FAILURE) {
+	if (!sharp_container_build(ce, &object)) {
 		RETURN_THROWS();
 	}
```

`sharp_container_build` reads the modules of the listener's folder and each folder above it, fills each constructor parameter from the modules of the class's namespace, `for` bindings first, a running test's module and then an environment module before the base module, and throws `Sharp\ContainerError` for a service no module binds. A constructor's own exception passes through. A `singleton` service is the same object across events, and the listener is new each time. The checker already proved every service bound (`a_listener_class_takes_services_its_namespace_modules_bind`), so a `ContainerError` here means a module file changed after the compile.

# The dispatcher

php-sharp #56 states the Capability: "a replacement dispatcher installed at startup receives every event". decision 066 makes its API the standard library's.

```csharp
// sharp/composer/library/Sharp/Dispatcher.sharp
namespace Sharp;

public interface Dispatcher
{
    void dispatch(Any emitted, List<Function<void(Any)>> listeners) uses Events;
}
```

```csharp
// sharp/composer/library/Sharp/ImmediateDispatcher.sharp
namespace Sharp;

public class ImmediateDispatcher : Dispatcher
{
    public void dispatch(Any emitted, List<Function<void(Any)>> listeners)
    {
        for (const listener of listeners)
        {
            listener(emitted);
        }
    }
}
```

The parameter is `emitted`, because `event` is a PHP# keyword: "`event` is a PHP# keyword, so it can't name a parameter."

```diff
 // sharp/composer/library/Sharp/Module.sharp
 module
 {
     singleton Environment = Environment;
+    singleton Dispatcher = ImmediateDispatcher;
 }
```

- **`dispatch` runs once per `emit`, with the event and one function per listener,** even when no listener matches. So a recording dispatcher in a test sees every event, which #56 asks for.
- **Each function runs one listener on the event it is given:** `listener(emitted)` creates the object through the container at that moment and calls the method, with the event when its row says it takes one. A dispatcher can call it later in the request, call it again to retry, or never call it. It cannot send one to a queue in another process, and the spec asks for none.
  - Each function is a `\Closure` over the private `hear(object $event)` method of an engine object, `Sharp\Internal\Listener`, which holds the listener's table row or scoped number. `Function<void(Any)>` lowers to `\Closure`, and C makes a closure only over a function and an object. A PSR-14 dispatcher calls the same closures, so one kind serves both.
- **An implementation of `dispatch` may reach any effect** (ruling 3): `runs_listeners` in `crates/analyzer/src/effects/mod.rs` keeps `unfit_implementations` from holding it to its declaration. `dispatch` declares `uses Events`, so code that calls it directly has the effect `emit` has.
- **The dispatcher is one service for the whole app (decision 5),** so every `emit` reaches it, from an app class, a PHP# package in `vendor/` or plain PHP. `sharp_dispatcher_namespace` in `ext/sharp/sharp.c` picks the namespace whose binding serves:
  1. While a test runs, its test module's binding of `Sharp.Dispatcher`, whatever namespace the module's header names: `module : App.Shop { singleton Dispatcher = RecordingDispatcher; }` is "not at all in tests".
  2. Else the app's top module that binds it, which `mago compile` writes in the table as `'dispatcher' => ['app/Module.sharp', 'app']`. The engine reads that module and the modules above it, and resolves `Sharp.Dispatcher` from its namespace. "Once at startup" is `singleton Dispatcher = AfterCommitDispatcher;` in `app/Module.sharp`.
  3. Else the root namespace, which the standard library's module serves with `ImmediateDispatcher`.
- **`mago compile` finds the app's top module** with `IncrementalAnalysisService::dispatcher_module` in `crates/orchestrator/src/service/incremental_analysis/compile.rs`: of the base and environment modules outside `vendor/` that bind `Sharp.Dispatcher`, the one whose namespace has the fewest parts, then the first by file name. A package's module in `vendor/` never replaces the app's dispatcher. The engine reads every module of that module's folder and of each folder above it, so an environment module there binds for its environment, as the container reads any service.
  - Rejected: the emitting class's namespace, which decision 5 first chose. An event a PHP# package in `vendor/` emits resolved from the package's namespace, which no app module serves, so the app's dispatcher missed it.
  - Rejected: the event's namespace. An `extern event` lives in its vendor's namespace, such as `Illuminate\Queue\Events`, which no app module serves.
  - Rejected: each listener's namespace. A dispatcher would see an event once per listener and never one without listeners.
  - Rejected: reading every module of the project at the first `emit` to find the top one. The engine reads modules folder by folder, from a class's file upward, and finding the top one means a scan of the project on every request. `mago compile` already knows every module.
- **A scoped listener goes to the dispatcher too,** after the table's listeners, in the order the listeners started. Its function does nothing once its handle is gone, so a listener stops when its handle goes out of scope even under a dispatcher that runs it later.
  - `Sharp\Listening` owns the listener's function and reports it from its `get_gc` handler, and the engine's list of scoped listeners points at each live handle with no reference. So a handle kept in a property of an object the listener uses forms a cycle the collector reclaims, and the listener stops with it. Before, the list held the function, the function held the object and the object held the handle, so the listener ran until the request ended.
- **These stay as the approved note set them:** an exception from a listener leaves `emit` and the listeners after it don't run (PSR-14), an `emit` inside a listener runs its listeners first, and each `emit` runs the listeners that existed when it started.
- Precedent: MediatR registers its `INotificationPublisher` in dependency injection, and Laravel binds `Illuminate\Contracts\Events\Dispatcher` in its container, which `Event::fake()` swaps.

The default path resolves one `singleton` and calls one PHP# method per `emit`. Measured on the debug engine without opcache, with `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events-engine/bench.php` over 20,000 runs: an `emit` of the fixtures' `OrderPaid`, which five listeners hear, took 21.7 µs, and the same five listeners called directly on new objects took 4.3 µs, so `emit` adds 3.5 µs per listener. Nothing was added to avoid it, and no profile says which step costs most.

# Plain PHP events through PSR-14

Jordan ruled ruling 4: a plain PHP library's own events reach PHP# listeners through PSR-14. PHP# offers its listeners as a PSR-14 listener provider, so a library whose dispatcher follows PSR-14 reaches `on` methods with nothing written by hand. A library with its own dispatcher needs one adapter package, and PHP# builds none for any framework.

```php
// sharp/composer/src/Psr/ListenerProvider.php
namespace Sharp\Psr;

use Psr\EventDispatcher\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable
    {
        return \Sharp\Internal\listenersFor($event);   // one function per listener, each taking the event
    }
}
```

A PHP# app wires it with one module binding, as section 15's `psr-listeners` example writes it:

```csharp
// app/Module.sharp
namespace App;

import Psr.EventDispatcher.EventDispatcherInterface;
import Sharp.Psr.ListenerProvider;
import Vendor.Events.EventDispatcher;

module
{
    singleton EventDispatcherInterface = new EventDispatcher(new ListenerProvider());   // the library's dispatcher, given every PHP# listener
}
```

A plain PHP bootstrap writes the same, `new EventDispatcher(new \Sharp\Psr\ListenerProvider())`, or adds the provider to an aggregate provider.

- **`sharp/bin/spec` checks the example against harness stand-ins:** psr/event-dispatcher 1.0's two interfaces in `sharp/harness/Psr/EventDispatcher/`, `Vendor\Events\EventDispatcher`, a PSR-14 dispatcher in `sharp/harness/Vendor/Events/`, and a copy of `ListenerProvider.php` in `sharp/harness/Sharp/Psr/`. The copy is there because `write_project` in `sharp/bin/spec` copies only `sharp/composer/library/` into an example's project, and `sharp/bin/spec` is guarded, so this pull request can't change it. Without the copy the example fails with "Class `ListenerProvider` not found.", and with a dispatcher that takes `Countable` it fails with "expected `Countable`, but possibly received `ListenerProvider`".

- **The class is `Sharp\Psr\ListenerProvider`, a plain PHP adapter beside `Sharp\Psr\Container`,** for the reason that one gives: the engine can't implement an interface Composer loads after the engine starts. It has no constructor parameter, so a framework's container builds it with no configuration.
- **`sharp/composer/composer.json` requires `psr/event-dispatcher` `^1.0`,** beside `psr/container` `^2.0`. `sharp/phpunit/composer.lock` locks `psr/event-dispatcher` 1.0.0, its only release.
- **`Sharp\Internal\listenersFor(object $event): array` returns the listeners `emit` would hand the dispatcher,** in the same order: the table's, then the scoped ones in the order they started. `sharp_listeners_for` in `ext/sharp/sharp.c` builds both lists, so `emit` and the provider find listeners one way.
- **Each function takes the event as its one parameter, as PSR-14 calls a listener.** It is the closure `emit` hands its dispatcher, over the private `Sharp\Internal\Listener::hear(object $event)`, so a table listener still runs on a new object of its class from the container, with its namespace's services, for every event.
- **The library's dispatcher decides when listeners run, and stops them when its event says so.** A module's `Sharp.Dispatcher` serves only `emit`. A scoped listener whose handle is gone runs nothing, as under any dispatcher.
- **Plain PHP that dispatches a PHP# event through PSR-14 reaches its listeners too,** since the provider finds listeners by the event's class, whoever declared it.
- Precedent: the PSR-14 dispatchers of Yii (`yiisoft/event-dispatcher`) and Crell's Tukio take any `ListenerProviderInterface` in their constructor, and Tukio's `AggregateProvider` combines several. Laravel's dispatcher does not follow PSR-14, so it is the kind of library an adapter package covers.

# Listening for a while: `Events.on<T>`

```csharp
// sharp/composer/library/Sharp/Events.sharp
namespace Sharp;

import NoDiscard;                          // PHP's attribute for a return value the caller must use
import Sharp.Internal;                     // the namespace of the engine's plain PHP functions

extern Internal.listen uses Events;        // starting a listener changes what every later emit runs

public static class Events
{
    [NoDiscard]
    public static Listening on<TEvent>(Function<void(TEvent)> listener) => Internal.listen(typeof(TEvent), listener);
}
```

- **`Events` is a plain static class, and `on` has the effect `Events` by inference from its body.** The spec makes `Events` the one standard effect with no `foreign` class behind it, so #118's handling of `Events` stays. #118 lets a method with a body name only its function parameters in `uses`, and `listen`'s `extern` gives the call its effect, as every plain PHP function's effects are declared.
- **`extern Internal.listen` comes from #124,** which builds spec section 23's calls of a plain PHP library's function through its namespace, `Pdf.render(html)` after `import Vendor.Pdf;`, and `extern Pdf.render uses Files;` for its effect, each lowered to the function's full name. Before it, an `extern` target that the file imported always bound as a class. So `Events.sharp` adds nothing to `extern` and nothing to the bridge. The prelude declares `Sharp\Internal\listen` with `@template T of object`, `class-string<T>` and `\Closure(T): void`, so `Internal.listen(typeof(TEvent), listener)` checks `listener` against `TEvent`.
- **Not chosen: a native body, `private static extern Listening listen(…) uses Events;` in `Events`,** as `Environment.commandLine` is. `sharp/bin/native` generates the C glue for bodies whose types are `string`, `int`, `float`, `bool` and a returned `List<string>`, so a closure parameter and a returned engine object would extend the generator for one caller.
- **Its body is one call of the engine's `Sharp\Internal\listen` with `typeof(TEvent)`,** which #102 reads from the type argument the call runs with. `Events.on<Paid>(…)` and `Events.on<OrderEvent>(…)` in `events_scoped.inc` each hear the events of their type.
- **`Sharp\Listening` is an engine class with no methods,** whose destructor stops the listener. The spec names no `stop()`. On the engine built from master, a destructor in a local that nothing reads ran when its function returned and when an exception left it, without opcache, with opcache's optimizer, and under the function and tracing JIT:

  ```text
  work in function
  stopped function
  after function
  work in method
  stopped method
  after method
  stopped throw
  caught
  ```

- **`[NoDiscard]`,** because `Events.on<RowImported>(…);` as a statement drops its handle at once and listens to nothing. `detect_unused_statement_expressions` reports it in PHP# words in a `.sharp` file: "The value `Events.on` returns must be used.", with the note "`[NoDiscard]` on `Events.on` says the value it returns matters." It reports a call written `Class::m()`, so `get_static_functionlike_id_from_call` in `crates/analyzer/src/utils/expression/mod.rs` now also reads PHP#'s `Class.m()`, through `static_call_class`, and every `[NoDiscard]` or `@must-use` static method a `.sharp` file calls is held to it. Precedent: Combine's `AnyCancellable` and Rust's `#[must_use]` guards.
- **Only the standard library imports `Sharp.Internal`,** so PHP# code listens one way and emits only with `emit`. A full name in code is already refused (section 23), so the import is the one way in. `check_internal_import` in `crates/analyzer/src/statement/use_statement.rs` refuses it outside `is_standard_library` files with `internal-outside-library`: "`Sharp.Internal` holds the engine's functions, and only the standard library imports it.", with the help "Listen with `Events.on`, and raise an event with `emit`." The code is named as `native-body-outside-library`, `silence-outside-library` and `reserved-name-outside-library` are.
- **Only the standard library declares code in `namespace Sharp` or a namespace under it.** A project file there would reach `Sharp.Internal` and the library's own classes with no import. `report_standard_library_namespace` in `crates/analyzer/src/statement/mod.rs` refuses the namespace statement with `reserved-name-outside-library`: "`Sharp.Mine` is a namespace of the standard library, and only the standard library declares code in it." It replaces the refusal of a project class named `Sharp\Int`, `Sharp\Float` or `Sharp\Bool`, which only that namespace can declare. A namespace that only starts with the letters, `Sharpen`, is the app's.
- **`Events.on(e => { })` gives one error,** `missing-template-parameter`. A type argument neither written nor inferred is not checked as an event.
- **Ruling 1:** `check_events_on` in `crates/analyzer/src/statement/class_like/listener.rs` reads the type argument a call of `Sharp\Events::on` runs with, written or inferred, and refuses a class that is not an event, an interface no event implements, and a built-in type, with the `on` clause's messages through `unheard_class`, which both share. A type parameter is the caller's to bound, so it passes, and a class that doesn't exist is reported once, where it is written.

# Public names this Slice adds

The note's choices, with the names each was picked over. team-lead approves them.

- `Sharp\Internal\emit(object $event): void`: the keyword it lowers from, as #170 already writes it. Also considered: `dispatch`, `raise`, `publish`.
- `Sharp\Internal\listen(string $event, \Closure $listener): \Sharp\Listening`: what the spec says the call starts, "starts a listener". Also considered: `subscribe`, `startListening`.
- `Sharp.Listening`: the spec's own local, `const listening = …`. Also considered: `Subscription` (Rx), `Cancellable` (Combine), `ListenerHandle`.
- `Sharp\Internal\Listener`, private constructor: the listener one function runs, engine machinery that plain PHP sees and PHP# code never names. Also considered: `Delivery`, `PendingListener`, `ListenerCall`.
  - Its private `hear(object $event)`: the listener hears the event the dispatcher passes, `emit`'s or a PSR-14 one. Also considered: `run`, `handle`, which says nothing, and `__invoke`, which would make every `Listener` callable.
- `Sharp\Psr\ListenerProvider`: PSR-14's own `ListenerProviderInterface` without `Interface`, as `Sharp\Psr\Container` is PSR-11's `ContainerInterface`. Also considered: `Listeners`, `Provider`, `EventListeners`.
- `Sharp\Internal\listenersFor(object $event): array`: PSR-14's `getListenersForEvent`, read as "the listeners for this event". Also considered: `listeners`, `listenersOf`, as the engine's `sharp_listeners_of` names the table rows.
- `Sharp.Events.on<TEvent>`: the spec's.
- `internal-outside-library`, the issue code for `import Sharp.Internal;` outside the standard library: its siblings' form, `<what>-outside-library`. Also considered: `internal-import`, `engine-namespace-import`.
- `Sharp.Dispatcher` and `dispatch(Any emitted, List<Function<void(Any)>> listeners) uses Events`: the spec's word, "the default dispatcher". `emitted`, because `event` is a keyword. Also considered: `EventDispatcher` (PSR-14), `Publisher` (MediatR), `run` for the method, and `raised` or `e` for the parameter.
- `Sharp.ImmediateDispatcher`: the spec's "runs listeners immediately". Also considered: `SyncDispatcher`, `DefaultDispatcher`, which says nothing about what it does.
- `'dispatcher'`, the table's key for the app's module that binds the dispatcher: the service it names. Also considered: `dispatcherModule`, `topModule`.
  - In mago, `DispatcherModule { file, namespace }` and `dispatcher_module()` on `IncrementalAnalysisService` and `Server`, beside `Listener` and `listeners()`.

# What changes

Built on `feat/events-engine`:

```text
php-sharp (feat/events-engine)
├── ext/sharp/
│   ├── sharp.stub.php          <- Sharp\Listening, Sharp\Internal\Listener, Sharp\Internal\emit, Sharp\Internal\listen,
│   │                              Sharp\Internal\listenersFor
│   ├── sharp_arginfo.h         <- regenerated
│   └── sharp.c                 <- from 6970c355a8: the table read, the per-event filter, emit;
│                                  new: the table's inputs and the development check, read past opcache while
│                                  developing, sharp_stamp_current, a listing's namespace folders,
│                                  emit through the app's dispatcher (sharp_dispatcher_namespace),
│                                  Listener::hear building through the container, listenersFor, listen and
│                                  Sharp\Listening, which owns its function and reports it to the cycle collector,
│                                  sharp_container_execute
├── sharp/composer/
│   ├── composer.json           <- requires psr/event-dispatcher
│   ├── mago.toml               <- analyzes src/Psr/ListenerProvider.php, with the harness's PSR-14 interfaces
│   ├── src/Psr/ListenerProvider.php   <- new: Sharp\Psr\ListenerProvider, PSR-14 over the engine's listeners
│   └── library/Sharp/
│       ├── Dispatcher.sharp    <- new: interface Dispatcher, dispatch uses Events
│       ├── Events.sharp        <- new: extern Internal.listen uses Events, and Events.on<TEvent>
│       ├── ImmediateDispatcher.sharp   <- new: the default
│       └── Module.sharp        <- binds Dispatcher to ImmediateDispatcher
├── sharp/phpunit/composer.lock <- locks psr/event-dispatcher 1.0.0
├── sharp/mago/
│   ├── src/commands/compile.rs <- the table's inputs and its dispatcher, written in every PHP# project; the
│   │                              compiled count counts accepted files, not the table
│   ├── tests/compile.rs        <- the table's text, quoted paths and dispatcher module
│   ├── tests/sharp.rs          <- a project file under Sharp is refused for its namespace
│   ├── scripts/regen-analyzer-issue-codes.php   <- internal-outside-library
│   └── crates/
│       ├── prelude/assets/extensions/sharp.php  <- Sharp\Listening, Sharp\Internal\listen and listenersFor
│       ├── sharp-bridge/src/unit.rs, tests/unit.rs   <- a listing names its namespace folders
│       ├── orchestrator/src/service/incremental_analysis/compile.rs   <- DispatcherModule, dispatcher_module
│       ├── server/src/server.rs      <- dispatcher_module
│       └── analyzer/
│           ├── src/code.rs     <- regenerated: InternalOutsideLibrary
│           ├── src/effects/mod.rs   <- runs_listeners: a dispatcher may reach any effect
│           ├── src/statement/use_statement.rs, statement/mod.rs
│           │                   <- check_internal_import: only the standard library imports Sharp.Internal;
│           │                      report_standard_library_namespace; a dropped [NoDiscard] value in PHP# words
│           ├── src/statement/class_like/mod.rs   <- report_sharp_type_class_outside_library deleted
│           ├── src/statement/class_like/listener.rs   <- check_events_on (ruling 1), and unheard_class, which
│           │                                             it shares with the on clause
│           ├── src/expression/call/static_method_call.rs   <- runs check_events_on on a call of Events.on
│           ├── src/utils/expression/mod.rs   <- a PHP# Class.m() call names its method, so [NoDiscard] holds
│           └── tests/sharp.rs  <- the dispatcher's effects, code without a body that emits, Events.on's
│                                  type, effect and dropped handle, the Sharp.Internal import, the Sharp namespace
├── sharp/harness/
│   ├── app/Stubs/Laravel.sharp <- new: extern event JobFailed;
│   ├── app/Imports/RowImported.sharp   <- new: public event RowImported(int row);
│   ├── Psr/EventDispatcher/EventDispatcherInterface.php, ListenerProviderInterface.php
│   │                           <- new: psr/event-dispatcher 1.0's interfaces
│   ├── Vendor/Events/EventDispatcher.php   <- new: a plain PHP library's PSR-14 dispatcher
│   └── Sharp/Psr/ListenerProvider.php      <- new: a copy of sharp/composer/src/Psr/ListenerProvider.php
├── sharp/baseline/spec/§15/    <- deleted: listeners and scoped-listener pass
├── Zend/tests/sharp/
│   ├── OrderEvent.sharp … Shipping.sharp, harness/JobFailed.inc, events.inc,
│   │   events.phpt, events_jit_function.phpt, events_jit_tracing.phpt
│   │                           <- from 6970c355a8; events.inc reads the library's module, and
│   │                              Shipping keeps each object it ships from
│   ├── events_table.phpt       <- a missing table runs nothing; a damaged table or input throws; an
│   │                              out-of-date one says so
│   ├── events_psr.phpt         <- new
│   ├── events_container.phpt   <- new
│   ├── events_dispatcher.phpt  <- new
│   ├── events_fresh.phpt       <- new
│   ├── events_opcache.phpt     <- new
│   ├── events_scoped.inc, events_scoped.phpt, events_scoped_jit_function.phpt,
│   │   events_scoped_jit_tracing.phpt   <- new, with PHP#'s Events.on
│   ├── extension_inputs.phpt   <- its Sharp.Text extension sits in the vendored standard library
│   └── Container.inc, library_autoload.inc   <- copy the dispatcher's classes, and Container.inc Events
└── docs/
    ├── spec.md                 <- section 15: a plain PHP library's own events reach PHP# listeners through PSR-14,
    │                              with the example psr-listeners
    └── architecture/
        ├── slices/15-events.md <- this note
        └── modules.md          <- the container builds each listener emit runs, its own symbol table;
                                   composer.json requires psr/event-dispatcher
```

Not added: no crate, no `.sharpc` format change, no bridge change for `emit`, no new node kind, and no adapter for any framework's dispatcher.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. Each new one was seen failing before its code, except `code_without_a_body_allows_emit_with_uses_events` and `events_on_has_the_effect_events`, whose code #118 and #124 had already built.

**mago:**

- `tests/compile.rs`, `compile_writes_every_listener_of_an_accepted_file_in_order`: the table's text with its inputs, every PHP# file and every listing above it, and its rows, a refused file's rows gone, the table kept once no listener is left, and "Compiled 4 PHP# files" for four files. `compile_quotes_each_input_path_of_the_listener_table`: a folder named `it's\here`. Four tests that list `.sharp/` now find `listeners.php` in it.
- `tests/compile.rs`, `compile_names_the_app_module_that_binds_the_dispatcher`: of `App` and `App.Shop` modules that bind it, `app/Module.sharp`; without it, `app/Shop/Module.sharp`; without either, `null`. With `dispatcher_module` returning none it failed.
- `crates/sharp-bridge/tests/unit.rs`, `a_source_listing_stamps_the_names_of_the_sharp_files_and_namespace_folders_of_its_folder`: a new namespace folder changes the listing to `Billing/` and the files, and its removal restores it. A lowercase folder named like a source does not change it. With folders left out it failed.
- `crates/analyzer/tests/sharp.rs`, `a_namespace_of_the_standard_library_in_a_project_file_is_a_reserved_name`: `namespace Sharp;` declaring `Bool` or `Int`, `namespace Sharp;` calling `Internal.listen` with no import, and `namespace Sharp.Internal;` are refused, and `namespace Sharpen;` is not. Before `report_standard_library_namespace`, the `Internal.listen` file and `Sharp.Internal` gave no issue. `an_extern_method_in_a_project_file_is_an_error_in_any_namespace` and mago's `tests/sharp.rs` now expect the namespace's error before an `extern` or `@` under `Sharp.Mine`.
- `events_on_with_no_type_is_reported_once`: `Events.on(e => { })` gives only `missing-template-parameter`. It also gave `invalid-event` before.
- `crates/analyzer/tests/sharp.rs`, `events_on_takes_an_event_or_an_interface_an_event_implements`: `Events.sharp` analyzes clean, `Events.on<OrderPaid>` and `Events.on<OrderEvent>` compile, and `Money`, `int` and an interface no event implements are refused. A missing class gives only "Cannot find type", and `Events.on<TEvent>` inside a generic method passes. Before `check_events_on` each refused row gave no issue.
- `events_on_has_the_effect_events`: a getter that calls `Events.on` is refused: "reaches `Events.on`, which calls `Internal.listen` with the effect `Events`".
- `a_dropped_events_on_handle_is_reported`: `Events.on<OrderPaid>(e => { });` as a statement gives "The value `Events.on` returns must be used." with "`[NoDiscard]` on `Events.on` says the value it returns matters." Before the `Class.m()` case in `get_static_functionlike_id_from_call` it gave no issue, and before the PHP# wording it gave PHP's "The return value of 'on' must be used." `a_dropped_must_use_value_is_reported_in_php_sharp_words`: a plain PHP method marked `@must-use` and dropped in a `.sharp` file names `@must-use`.
- `only_the_standard_library_imports_sharp_internal`: an app file's `import Sharp.Internal;` is refused, and the library's `Events.sharp` is not. Before `check_internal_import` the app file gave no issue.
- `crates/analyzer/tests/sharp.rs`, `a_dispatcher_may_reach_any_effect_and_a_call_of_dispatch_has_the_effect_events`: an implementation of the library's `Dispatcher` calls `global.fetch`, which has `Http`, and compiles. The same method of another pure interface is refused. A getter that calls `dispatch` is refused for `Events`.
- `crates/analyzer/tests/sharp.rs`, `code_without_a_body_allows_emit_with_uses_events`: an implementation that emits fits an interface or abstract method with `uses Events`, and is refused by a pure one and by `uses Mail`.
- Plain PHP: the analyzer's tests pass unchanged, its 2,569 PHP tests among them.

**php-sharp, without opcache, with the opcache file cache, twice in one process, under each of the 16 optimizer passes alone, and under the function and tracing JIT:**

- `events.phpt` and its JIT variants, from `6970c355a8`: every listener runs before `emit` returns, in table order, each once, through the standard library's dispatcher. An interface listener hears every event that implements it. A listener without a parameter, and a `private` one, run. An exception stops the rest. A nested `emit` runs first. Plain PHP's `\Sharp\Internal\emit` and an `extern event` reach PHP# listeners. A non-object is a `TypeError`.
- `events_table.phpt`: a missing table runs nothing. A table that is not a list, one without its listeners or its dispatcher, a dispatcher that is not a path and a namespace, each damaged row and each damaged input throw "`.sharp/listeners.php` is damaged. Run vendor/bin/mago compile." A class or method the table names but the code lacks throws "`.sharp/listeners.php` is out of date: it lists `App.Demo.Gone`, which no longer exists. Run vendor/bin/mago compile." Before, it threw PHP's `Class "App\Demo\Gone" not found`.
- `events_container.phpt`: a listener's object is new for every event, while its `singleton` service is the same object and its `transient` one is new. A class a module binds as a `singleton` is still new for every event. A test module's binding fills the service while its test runs. A listener's constructor exception leaves `emit`.
- `events_dispatcher.phpt`: the app's top `app/Module.sharp` binds a recorder, which receives every event, one without listeners included, and runs nothing, whether an app class, plain PHP or a PHP# package in `vendor/` emits it. A test module's dispatcher that runs the functions after `emit` returns, and one of another namespace that retries a listener that threw, while each test runs, and the app's again after. With the table's module ignored, every row but the tests' ran the listeners at once.
- `events_scoped.phpt` and its JIT variants: a scoped listener hears events until its function returns, and stops when an exception leaves it. A handle never stored stops at once. Scoped listeners run after the table's, in the order they started, one through an interface. One started during an `emit` hears only the next. One whose handle is gone runs nothing under a test's dispatcher that runs it later. A type no class has hears nothing. PHP#'s `Events.on<Paid>` and `Events.on<OrderEvent>` hear the events of their type until their method returns. A handle kept in a property of the object its listener uses stops when the collector collects them: "table 14, kept 14, keeper destroyed, table 15". With the function hidden from the collector, the listener kept running and the two leaked. Without `Events.sharp` in the project, the compile fails with "Method `Events.on` does not exist."
- `events_psr.phpt`: a plain PHP library's PSR-14 dispatcher, given `Sharp\Psr\ListenerProvider`, dispatches the `extern event` `Lib\JobFailed` and reaches `OrderNotices.alert`, and dispatches `OrderPaid` and reaches its five listeners in table order. `Shipping` is a new object for every event. A scoped listener runs after the table's, and not once its handle is gone. An event no listener hears gets no listeners. On the engine before the change it failed with "Call to undefined function Sharp\Internal\listenersFor()".
- `events_fresh.phpt`: with `sharp.compile_command` set, a new listener file, a listener added to a listener's file, a deleted listener file, a listener in a new namespace folder and an `on` added to a class that had none and that nothing loads each make the next `emit` compile once, and nothing changed compiles nothing. Without the setting, a new listener file stays unheard. With only listener files as inputs and no folders in a listing, the last two stayed unheard.
- `events_opcache.phpt`: in one process with opcache on, opcache holds the table, a listener file and a new class appear, and loading the class runs the compile before the first `emit`. The `emit` runs the new listener. Reading the table through opcache, it missed it.
- `psr-listeners` in section 15 compiles in `sharp/bin/spec`. It failed before the harness held the provider, and fails again when the harness dispatcher takes another type.
- `sharp/bin/spec` matches the shorter list, and `sharp/bin/spec --completes` on a body holding `Completes: §15` gives "§15: all 9 checked examples pass."

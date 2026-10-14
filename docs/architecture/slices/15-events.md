# Slice 15: Events

Spec section 15, finished across the checker, the bridge and the engine in one php-sharp pull request, `feat/events-engine`. team-lead approved decisions 1 to 7 as written and ruled on the questions, under "Rulings". "What the branch holds" says what is built and what waits on #102.

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
4. **The dispatcher is a standard library service, `Sharp.Dispatcher`, which a module binds,** and the engine hands it every `emit`. The standard library binds `ImmediateDispatcher`. The API is drawn under "The dispatcher".
5. **`emit` resolves the dispatcher for the code that emits:** the class whose method called `emit`, through the container, as that class resolves any service. Drawn with its rejected options under "The dispatcher".
6. **`Events.on<T>` lowers to one engine call, and its handle is an engine object.** `Sharp\Internal\listen(string $event, \Closure $listener): \Sharp\Listening`, and `Sharp\Listening` stops the listener in its destructor. The standard library may call a `Sharp\Internal` function, and no other PHP# code may. Corrected by team-lead: `Sharp.Events` is `public static class Events`, with no modifier for effects. The spec says "`Events` is the effect of `emit`. It is the one standard effect that is not a `foreign` class: a method has it when its body emits, and no object carries it", so #118's handling of `Events` as an effect with no class stays. `on` gets the effect `Events` by inference from its body, because `listen` is declared `extern Internal.listen uses Events;`, the way every plain PHP function's effects are declared.
7. **The harness gains two spec files.** `sharp/harness/app/Stubs/Laravel.sharp` declares `extern event JobFailed;`, and `sharp/harness/app/Imports/RowImported.sharp` declares `public event RowImported(int row);`. Each sits at the path its own spec example writes, so `extern-event` and `standalone-event` replace them with the same text.

## Rulings

team-lead ruled on the five questions the first version of this note asked, each with its spec line:

1. **`Events.on<T>` with a type that isn't an event is refused with the messages the `on` clause gives** (`check_listed_events` in `crates/analyzer/src/statement/class_like/listener.rs`). Spec: "`Events.on<RowImported>(e => …)` starts a listener and returns a handle."
2. **A new object for every event, whatever lifetime a module gives the listener's class,** as Jordan ruled. Spec: "PHP# creates a new object for every event and passes its constructor's services from the module of its namespace."
3. **`Dispatcher.dispatch` may reach any effect, because the listeners it runs may.** Spec: "`emit` has the effect `Events`" and "An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries".
4. **A plain PHP library's own events reach PHP# listeners through PSR-14,** as Jordan ruled. PHP# offers its listeners as a PSR-14 listener provider, and builds no adapter for any framework. Drawn under "Plain PHP events through PSR-14", and section 15 of the spec now states the rule.
5. **The table records its inputs, and the engine checks them only when `sharp.compile_command` is set.** Spec: "Nothing is wired by hand." The inputs are each source folder's listing, which a PHP# file joining or leaving the folder changes, and each file that declares a listener. An `on` added to a class that had none waits until that file loads, and a deploy's `mago compile` is exact.

## What the branch holds

Built and tested. #118 merged into master and then into this branch:

- `emit` hands the event and one function per listener to the `Sharp.Dispatcher` a module binds for the emitting class. Each function creates its listener's object through the container when it runs (ruling 2). `ImmediateDispatcher` is the standard library's binding.
- `Sharp\Internal\listen` and `Sharp\Listening`, the engine half of `Events.on<T>`, tested from plain PHP.
- The table's inputs and the development check (ruling 5). `mago compile` writes the table in every project with a PHP# file.
- The container runs a module file in a symbol table of its own, which ended the tracing-JIT leak the first version of this note named as a risk.
- The harness's `extern event JobFailed;` and `RowImported`, so `listeners` left `sharp/baseline/spec/`.
- Ruling 3: no implementation of `Sharp\Dispatcher::dispatch` is held to its declaration, and `dispatch` declares `uses Events`, so a call of it has the effect `emit` has.
- "Code without a body allows it with `uses Events`", which #118 built, now has a section 15 test.
- Ruling 4: `Sharp\Psr\ListenerProvider` gives a PSR-14 dispatcher every listener of an event, and `sharp/composer` requires `psr/event-dispatcher`.

Waits on #102, built when it lands. #124 merged into master at `660ad5a094`:

- `sharp/composer/library/Sharp/Events.sharp` and its `on<TEvent>`, in the form "Listening for a while" draws. #102 parses `on<TEvent>`, and #124 lets `extern Internal.listen uses Events;` name a namespaced function and lowers `Internal.listen(…)` by its full name.
- What only `Events.sharp` uses: the prelude's `Sharp\Internal\listen` and `Sharp\Listening`, and the refusal of `Sharp.Internal` outside the standard library, so PHP# code listens one way.
- Ruling 1's refusal, which needs `Events.on<Money>` to parse.
- `scoped-listener` leaving `sharp/baseline/spec/`, and `Completes: §15`.

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
        ['app/*.sharp', 67, 0, '5c73e5948a8589d496ffa4ca4f4a51a6'],
        ['app/OrderNotices.sharp', 153, 1791940268341788484, '297e2f94dfdf895b91918498677ba11a'],
    ],
    'listeners' => [
        ['App\OrderNotices', 'send', ['App\OrderPaid'], true],
        ['App\OrderNotices', 'refresh', ['App\OrderPaid', 'App\OrderEvent'], false],
    ],
];
```

- **Each input is a workspace-relative path, its size, its modification time in nanoseconds and its xxh3-128 hash,** stamped by the bridge's `unit::stamp`, the stamp a `.sharpc` file's inputs carry. A path ending in `*.sharp` is that folder's listing, as `unit::SOURCE_LISTING` defines it. The engine compares them with `sharp_stamp_current`, the one comparison a `.sharpc` file's inputs use too.
- **Only with `sharp.compile_command` set,** the first `emit` of a request that reads the table compares its inputs, and on a change runs the command once, as an out-of-date compiled file does, then reads the table it wrote past opcache, which may keep the old one. Without the setting, the engine reads the table and nothing else.
- Precedent: Laravel's `php artisan event:cache` writes `bootstrap/cache/events.php` the same way, and PSR-14's `ListenerProviderInterface::getListenersForEvent` matches by class and interface.
- **The table is not run, and neither is anything that would give the caller a symbol table.** `6970c355a8` measured why: code run from inside `emit` gives the calling frame a symbol table that a tracing-JIT frame never frees. `emit` reaches the container's `sharp_container_read`, which ran a module file with `zend_execute`. `events_scoped_jit_tracing.phpt` with `sharp/bin/test --repeat` leaked a 56-byte `HashTable` and its 320 bytes. `sharp_container_execute` now runs a module file in a symbol table of its own, for `emit` and `Container::get` alike.

# How a listener's object is created

A listener's function creates the object as the container creates a bound class, with no binding row. `6970c355a8` called `object_init_with_constructor(&listener, ce, 0, NULL, NULL)`. `Sharp\Internal\Listener::run` calls #121's `sharp_container_build` when the dispatcher calls the function:

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
    void dispatch(Any emitted, List<Function<void()>> listeners) uses Events;
}
```

```csharp
// sharp/composer/library/Sharp/ImmediateDispatcher.sharp
namespace Sharp;

public class ImmediateDispatcher : Dispatcher
{
    public void dispatch(Any emitted, List<Function<void()>> listeners)
    {
        for (const listener of listeners)
        {
            listener();
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
- **Each function runs one listener when called:** it creates the object through the container at that moment and calls the method, with the event when its row says it takes one. A dispatcher can call it later in the request, call it again to retry, or never call it. It cannot send one to a queue in another process, and the spec asks for none.
  - Each function is a `\Closure` over the private `run` method of an engine object, `Sharp\Internal\Listener`, which holds the event and the listener's table row or scoped number. `Function<void()>` lowers to `\Closure`, and C makes a closure only over a function and an object.
- **An implementation of `dispatch` may reach any effect** (ruling 3): `runs_listeners` in `crates/analyzer/src/effects/mod.rs` keeps `unfit_implementations` from holding it to its declaration. `dispatch` declares `uses Events`, so code that calls it directly has the effect `emit` has.
- **The engine resolves the dispatcher for the class whose method called `emit`,** with `zend_get_executed_scope()`, as that class resolves any service: its namespace's modules first, a `for` binding first. Plain PHP outside a class resolves from the root namespace, which the standard library's binding serves.
  - "Once at startup" is a binding in the app's top `Module.sharp`, which every namespace under it reaches: `singleton Dispatcher = AfterCommitDispatcher;`.
  - "Not at all in tests" is a test module: `module : App.Shop { singleton Dispatcher = RecordingDispatcher; }`, which applies while a test of that file's classes runs.
  - Rejected: the event's namespace. An `extern event` lives in its vendor's namespace, such as `Illuminate\Queue\Events`, which no app module serves, so the app's dispatcher would never receive it.
  - Rejected: each listener's namespace. A dispatcher would see an event once per listener and never one without listeners.
- **A scoped listener goes to the dispatcher too,** after the table's listeners, in the order the listeners started. Its function does nothing once its handle is gone, so a listener stops when its handle goes out of scope even under a dispatcher that runs it later.
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

```php
// an app's plain PHP bootstrap, with any PSR-14 dispatcher
$dispatcher = new Dispatcher(new \Sharp\Psr\ListenerProvider());   // or added to an aggregate provider
$dispatcher->dispatch(new JobFailed('mail'));                        // runs OrderNotices.alert on a new OrderNotices
```

- **The class is `Sharp\Psr\ListenerProvider`, a plain PHP adapter beside `Sharp\Psr\Container`,** for the reason that one gives: the engine can't implement an interface Composer loads after the engine starts. It has no constructor parameter, so a framework's container builds it with no configuration.
- **`sharp/composer/composer.json` requires `psr/event-dispatcher` `^1.0`,** beside `psr/container` `^2.0`. `sharp/phpunit/composer.lock` locks `psr/event-dispatcher` 1.0.0, its only release.
- **`Sharp\Internal\listenersFor(object $event): array` returns the listeners `emit` would hand the dispatcher,** in the same order: the table's, then the scoped ones in the order they started. `sharp_listeners_for` in `ext/sharp/sharp.c` builds both lists, so `emit` and the provider find listeners one way.
- **Each function takes the event as its one parameter, as PSR-14 calls a listener.** It is a `\Closure` over the private `Sharp\Internal\Listener::hear(object $event)`, where `emit`'s are over `run()`, bound to the event. Both call `sharp_listener_call`, so a table listener still runs on a new object of its class from the container, with its namespace's services, for every event.
- **The library's dispatcher decides when listeners run, and stops them when its event says so.** A module's `Sharp.Dispatcher` serves only `emit`. A scoped listener whose handle is gone runs nothing, as under any dispatcher.
- **Plain PHP that dispatches a PHP# event through PSR-14 reaches its listeners too,** since the provider finds listeners by the event's class, whoever declared it.
- Precedent: the PSR-14 dispatchers of Yii (`yiisoft/event-dispatcher`) and Crell's Tukio take any `ListenerProviderInterface` in their constructor, and Tukio's `AggregateProvider` combines several. Laravel's dispatcher does not follow PSR-14, so it is the kind of library an adapter package covers.

# Listening for a while: `Events.on<T>`

```csharp
// sharp/composer/library/Sharp/Events.sharp
namespace Sharp;

import Sharp.Internal;                     // the namespace of the engine's plain PHP functions

extern Internal.listen uses Events;        // starting a listener changes what every later emit runs

public static class Events
{
    [NoDiscard]
    public static Listening on<TEvent>(Function<void(TEvent)> listener) => Internal.listen(typeof(TEvent), listener);
}
```

- **`Events` is a plain static class, and `on` has the effect `Events` by inference from its body.** The spec makes `Events` the one standard effect with no `foreign` class behind it, so #118's handling of `Events` stays. #118 lets a method with a body name only its function parameters in `uses`, and `listen`'s `extern` gives the call its effect, as every plain PHP function's effects are declared.
- **`extern Internal.listen` needs #124** (`feat/plain-php-calls`, open). #124 builds spec section 23's calls of a plain PHP library's function through its namespace, `Pdf.render(html)` after `import Vendor.Pdf;`, and `extern Pdf.render uses Files;` for its effect, each lowered to the function's full name. On master, an `extern` target that the file imports always binds as a class (`walk_in_extern` in `crates/names/src/internal/walker.rs`), so `extern` cannot name a namespaced function, and `library-functions` is listed in `sharp/baseline/spec/§23/`. #124 is that general fix, so `Events.sharp` adds nothing to `extern` and nothing to the bridge.
- **Not chosen: a native body, `private static extern Listening listen(…) uses Events;` in `Events`,** as `Environment.commandLine` is. `sharp/bin/native` generates the C glue for bodies whose types are `string`, `int`, `float`, `bool` and a returned `List<string>`, so a closure parameter and a returned engine object would extend the generator for one caller.
- **Its body is one call of the engine's `Sharp\Internal\listen` with `typeof(TEvent)`,** so decision 040's inlining runs it at the call site. `const listening = Events.on<RowImported>(e => this.progress.advance());` lowers to `$listening = \Sharp\Internal\listen('App\Imports\RowImported', fn ($e) => $this->progress->advance());`. It needs #102: type arguments on a call, and `typeof` of a method's type parameter.
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

- **`[NoDiscard]`,** because `Events.on<RowImported>(…);` as a statement drops its handle at once and listens to nothing. The analyzer already reports it (`detect_unused_statement_expressions`). Precedent: Combine's `AnyCancellable` and Rust's `#[must_use]` guards.
- **Only the standard library may reach `Sharp.Internal`,** so PHP# code listens one way and emits only with `emit`. Outside `is_standard_library` files, an `import Sharp.Internal;` and an `extern` of a `Sharp.Internal` function are refused. It lands with `Events.sharp`, the first standard library file that imports `Sharp.Internal`.

# Public names this Slice adds

The note's choices, with the names each was picked over. team-lead approves them.

- `Sharp\Internal\emit(object $event): void`: the keyword it lowers from, as #170 already writes it. Also considered: `dispatch`, `raise`, `publish`.
- `Sharp\Internal\listen(string $event, \Closure $listener): \Sharp\Listening`: what the spec says the call starts, "starts a listener". Also considered: `subscribe`, `startListening`.
- `Sharp.Listening`: the spec's own local, `const listening = …`. Also considered: `Subscription` (Rx), `Cancellable` (Combine), `ListenerHandle`.
- `Sharp\Internal\Listener`, private constructor, private `run()`: the listener one function runs, engine machinery that plain PHP sees and PHP# code never names. Also considered: `Delivery`, `PendingListener`, `ListenerCall`.
  - Its private `hear(object $event)`: the listener hears the event the PSR-14 dispatcher passes, where `run()` runs it on the one `emit` bound. Also considered: `runOn`, `handle`, which says nothing, and `__invoke`, which would make every `Listener` callable with or without an event.
- `Sharp\Psr\ListenerProvider`: PSR-14's own `ListenerProviderInterface` without `Interface`, as `Sharp\Psr\Container` is PSR-11's `ContainerInterface`. Also considered: `Listeners`, `Provider`, `EventListeners`.
- `Sharp\Internal\listenersFor(object $event): array`: PSR-14's `getListenersForEvent`, read as "the listeners for this event". Also considered: `listeners`, `listenersOf`, as the engine's `sharp_listeners_of` names the table rows.
- `Sharp.Events.on<TEvent>`: the spec's.
- `Sharp.Dispatcher` and `dispatch(Any emitted, List<Function<void()>> listeners) uses Events`: the spec's word, "the default dispatcher". `emitted`, because `event` is a keyword. Also considered: `EventDispatcher` (PSR-14), `Publisher` (MediatR), `run` for the method, and `raised` or `e` for the parameter.
- `Sharp.ImmediateDispatcher`: the spec's "runs listeners immediately". Also considered: `SyncDispatcher`, `DefaultDispatcher`, which says nothing about what it does.

# What changes

Built on `feat/events-engine`:

```text
php-sharp (feat/events-engine)
├── ext/sharp/
│   ├── sharp.stub.php          <- Sharp\Listening, Sharp\Internal\Listener, Sharp\Internal\emit, Sharp\Internal\listen,
│   │                              Sharp\Internal\listenersFor
│   ├── sharp_arginfo.h         <- regenerated
│   └── sharp.c                 <- from 6970c355a8: the table read, the per-event filter, emit;
│                                  new: the table's inputs and the development check, sharp_stamp_current,
│                                  emit through the dispatcher, Listener::run and ::hear building through the container,
│                                  listenersFor, listen and Sharp\Listening's destructor, sharp_container_execute
├── sharp/composer/
│   ├── composer.json           <- requires psr/event-dispatcher
│   ├── src/Psr/ListenerProvider.php   <- new: Sharp\Psr\ListenerProvider, PSR-14 over the engine's listeners
│   └── library/Sharp/
│       ├── Dispatcher.sharp    <- new: interface Dispatcher, dispatch uses Events
│       ├── ImmediateDispatcher.sharp   <- new: the default
│       └── Module.sharp        <- binds Dispatcher to ImmediateDispatcher
├── sharp/phpunit/composer.lock <- locks psr/event-dispatcher 1.0.0
├── sharp/mago/
│   ├── src/commands/compile.rs <- the table's inputs, written in every PHP# project; the compiled count
│   │                              counts accepted files, not the table
│   ├── tests/compile.rs        <- the table's text and quoted paths
│   └── crates/analyzer/
│       ├── src/effects/mod.rs  <- runs_listeners: a dispatcher may reach any effect
│       └── tests/sharp.rs      <- the dispatcher's effects, code without a body that emits
├── sharp/harness/app/
│   ├── Stubs/Laravel.sharp     <- new: extern event JobFailed;
│   └── Imports/RowImported.sharp   <- new: public event RowImported(int row);
├── sharp/baseline/spec/§15/listeners   <- deleted
├── Zend/tests/sharp/
│   ├── OrderEvent.sharp … Shipping.sharp, harness/JobFailed.inc, events.inc,
│   │   events.phpt, events_jit_function.phpt, events_jit_tracing.phpt
│   │                           <- from 6970c355a8; events.inc reads the library's module, and
│   │                              Shipping keeps each object it ships from
│   ├── events_table.phpt       <- a missing table runs nothing; a damaged table or input throws
│   ├── events_psr.phpt         <- new
│   ├── events_container.phpt   <- new
│   ├── events_dispatcher.phpt  <- new
│   ├── events_fresh.phpt       <- new
│   ├── events_scoped.inc, events_scoped.phpt, events_scoped_jit_function.phpt,
│   │   events_scoped_jit_tracing.phpt   <- new
│   └── Container.inc, library_autoload.inc   <- copy the dispatcher's classes
└── docs/
    ├── spec.md                 <- section 15: a plain PHP library's own events reach PHP# listeners through PSR-14
    └── architecture/
        ├── slices/15-events.md <- this note
        └── modules.md          <- the container builds each listener emit runs, its own symbol table;
                                   composer.json requires psr/event-dispatcher
```

To build once #102 lands:

```text
├── sharp/composer/library/Sharp/Events.sharp   <- new: extern Internal.listen uses Events, and Events.on<TEvent>
├── sharp/mago/crates/
│   ├── prelude/assets/extensions/sharp.php      <- Sharp\Internal\listen and Sharp\Listening, for the checker
│   ├── analyzer/                                <- Sharp.Internal reached only from the standard library;
│   │                                               Events.on's type argument names an event (ruling 1)
│   └── analyzer/tests/sharp.rs                  <- one test per rule
└── sharp/baseline/spec/§15/scoped-listener      <- deleted, and the folder with it
```

Not added: no crate, no `.sharpc` format change, no bridge change for `emit`, no new node kind, and no adapter for any framework's dispatcher.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. Each new one was seen failing before its code, except `code_without_a_body_allows_emit_with_uses_events`, whose code #118 had already built.

**mago:**

- `tests/compile.rs`, `compile_writes_every_listener_of_an_accepted_file_in_order`: the table's text with its inputs and its rows, a refused file's rows gone, the table kept once no listener is left, and "Compiled 4 PHP# files" for four files. `compile_quotes_each_input_path_of_the_listener_table`: a folder named `it's\here`. Four tests that list `.sharp/` now find `listeners.php` in it.
- `crates/analyzer/tests/sharp.rs`, `a_dispatcher_may_reach_any_effect_and_a_call_of_dispatch_has_the_effect_events`: an implementation of the library's `Dispatcher` calls `fetch`, which has `Http`, and compiles. The same method of another pure interface is refused. A getter that calls `dispatch` is refused for `Events`.
- `crates/analyzer/tests/sharp.rs`, `code_without_a_body_allows_emit_with_uses_events`: an implementation that emits fits an interface or abstract method with `uses Events`, and is refused by a pure one and by `uses Mail`.
- Plain PHP: the analyzer's tests pass unchanged, its 2,569 PHP tests among them.

**php-sharp, without opcache, with the opcache file cache, twice in one process, under each of the 16 optimizer passes alone, and under the function and tracing JIT:**

- `events.phpt` and its JIT variants, from `6970c355a8`: every listener runs before `emit` returns, in table order, each once, through the standard library's dispatcher. An interface listener hears every event that implements it. A listener without a parameter, and a `private` one, run. An exception stops the rest. A nested `emit` runs first. Plain PHP's `\Sharp\Internal\emit` and an `extern event` reach PHP# listeners. A non-object is a `TypeError`.
- `events_table.phpt`: a missing table runs nothing. A table that is not a list, one without its listeners, each damaged row and each damaged input throw "`.sharp/listeners.php` is damaged. Run vendor/bin/mago compile." A class or method the table names but the code lacks throws PHP's own error.
- `events_container.phpt`: a listener's object is new for every event, while its `singleton` service is the same object and its `transient` one is new. A class a module binds as a `singleton` is still new for every event. A test module's binding fills the service while its test runs. A listener's constructor exception leaves `emit`.
- `events_dispatcher.phpt`: plain PHP gets the standard library's dispatcher. An app module's dispatcher receives every event, one without listeners included, and runs nothing. One that runs the functions after `emit` returns, and one that retries a listener that threw. A test module's dispatcher while its test runs, and the app's again after.
- `events_scoped.phpt` and its JIT variants: a scoped listener hears events until its function returns, and stops when an exception leaves it. A handle never stored stops at once. Scoped listeners run after the table's, in the order they started, one through an interface. One started during an `emit` hears only the next. One whose handle is gone runs nothing under a dispatcher that runs it later. A type no class has hears nothing.
- `events_psr.phpt`: a plain PHP library's PSR-14 dispatcher, given `Sharp\Psr\ListenerProvider`, dispatches the `extern event` `Lib\JobFailed` and reaches `OrderNotices.alert`, and dispatches `OrderPaid` and reaches its five listeners in table order. `Shipping` is a new object for every event. A scoped listener runs after the table's, and not once its handle is gone. An event no listener hears gets no listeners. On the engine before the change it failed with "Call to undefined function Sharp\Internal\listenersFor()".
- `events_fresh.phpt`: with `sharp.compile_command` set, a new listener file, a listener added to a listener's file and a deleted listener file each make the next `emit` compile once, and nothing changed compiles nothing. Without the setting, a new listener file stays unheard.
- `sharp/bin/spec` matches the shorter list. `sharp/bin/spec --completes` on a body holding `Completes: §15` passes only after `scoped-listener` leaves the list.

# Slice 32: Hosts

A PHP# app runs under php-fpm, the CLI, FrankenPHP and Laravel Octane. A long-running host keeps one booted app in memory and serves request after request, or job after job, from it. This slice makes each lifetime mean the same thing under every host: a `scoped` service lives for one unit of work and never reaches the next one, a `singleton` and a static live as long as the app runs.

Jordan's rulings this slice builds on:

- **A static lives as long as the app runs.** Its initial value runs once per app. Under Octane every request sees what the last one left, as in C#, TypeScript and Rust. Under php-fpm nothing changes, because there the app lives for one request.
- **The host starts and ends each scope through one standard-library call, `Scope.run(body)`,** and PHP# ships no framework code. PHP's own request is the default scope.

Paths under `crates/` are in `sharp/mago/crates/`. "The engine" is `ext/sharp/sharp.c`.

## What was observed before any change

Each claim below ran on master `02c05cb5f1` in slot 3, or was read from the source named.

- **The engine already keeps a nested scope.** `Sharp\Container::scope(\Closure $run)` swaps `SHARP_G(container_scope)` to a table on the C stack, calls `$run`, restores the outer pointer and destroys the table. `Zend/tests/sharp/container_lifetimes.phpt` passes: two `get` calls in one request share the `scoped` `Visit`, and `Container::scope` gives a new one. A scratch probe also passed: a body that throws drops its scope and the request's scope comes back, a nested scope is its own, two scopes in a row differ, and a `singleton` is shared across scopes.
- **That scope crashes the process when a `Fiber` suspends inside it.** A fiber calls `Container::scope`, suspends inside it, and the main code then calls `Container::scope` and resumes the fiber. The main code's scoped services come from the fiber's scope, and after both scopes end the next `get` reads the destroyed table: exit code 139, a segmentation fault (`scope_fiber_probe.php`). The pointer is one global restored in call order, and fibers end scopes out of that order.
- **No PHP# code can call the scope today.** The checker's engine stub, `crates/prelude/assets/extensions/sharp.php`, declares no `Sharp\Container`. Only plain PHP reaches `Container::scope`, and only `container_lifetimes.phpt` and `docs/architecture/modules.md` name it.
- **FrankenPHP's worker mode runs ext/sharp's RINIT once per worker thread, not once per HTTP request.** FrankenPHP v1.13.0's `frankenphp_worker_request_startup` and `frankenphp_worker_request_shutdown` rerun the request hooks of `filter` and `session` only (`MODULES_TO_RELOAD`). So under a worker every `scoped` service is built once and reaches every later HTTP request. This was read from FrankenPHP's source. Running it needs the FrankenPHP image this slice adds, so Phase 2 runs the fixture against master's engine first and records the leak before any engine change.
- **Statics already live as long as the PHP request.** A static is PHP's own static member (`CE_STATIC_MEMBERS`), and a static initial value runs in the class's hidden `#statics` method when the request first builds the class's statics. `static lazy` is kept in `container_singletons` (`Sharp_Internal_keep`). Under a worker one PHP request serves many HTTP requests, so the engine already does what Jordan ruled. Only the wording says "request".
- **A PHP# method that takes a function refuses every argument that has an effect.** A probe project declares `static T plain<T>(Function<T()> body) => body();` and `static T carried<T>(Function<T()> body) uses body => body();`, and calls each with `() => this.environment.require("HOME")`. Both are refused: "This lambda calls `Environment.require`, which has the effect `Environment`, so a `Function<string()>` cannot hold it." `uses body` parses but does not let the lambda through. `sharp/baseline/spec/§29/function-effects` lists the spec example of this rule as failing. A `Scope.run` written as a library method would refuse every real request handler until section 29's `uses f` holds.
- **The thread-safe build compiles and passes.** `configure --enable-zts --enable-debug` with `sharp/bin/build`'s flags builds with no warning from ext/sharp. `Zend/tests/sharp` on it: 227 of 278 pass without opcache, and 227 pass with opcache shared memory, each test run twice in one process. The 51 that fail are every `*_jit_*` test, which prints `opcache_get_status()['jit']['on']` and expects `on`. PHP has no JIT for ZTS on Apple Silicon ("JIT not supported on Apple Silicon with ZTS" from configure), so these run on Linux. The same 278 pass on the NTS build.
- **ext/sharp keeps no unguarded process-wide mutable state.** Every module global is in `ZEND_BEGIN_MODULE_GLOBALS(sharp)` and read through `SHARP_G`, with `GINIT` and `GSHUTDOWN`. The process-wide variables are class entries, handlers and interned keys set once in `MINIT`, and three intern tables, `sharp_types`, `sharp_type_lists` and `sharp_class_values`, which every read and write reaches only under `sharp_types_lock`, a `tsrm_mutex` under ZTS. The Rust library holds no state. PHP#'s changes to `Zend/`, `main/` and `ext/opcache/` against upstream `PHP-8.5.11` add no top-level variable, only `zend_compiled_revision`, a function pointer set once in `MINIT`. `sharp_run_compile_command`'s child calls only `open`, `chdir`, `dup2`, `execl` and `_exit`, which are safe after `fork` in a threaded process. No run so far had several threads in one process. The FrankenPHP fixture runs with several worker threads for that.

## Jordan's answers

1. **A scope follows the fiber that runs in it,** as C#'s `AsyncLocal`, Node's `AsyncLocalStorage.run` and tokio's task-local `scope` do. A fiber started inside `Scope.run` belongs to that scope, and a fiber's own `Scope.run` is its own. Resolving a `scoped` service after its scope ended throws `Sharp\ContainerError`, as C# throws when a disposed scope is used. Section 32 states the rule with an example.
2. **"A host that runs several workers runs one app in each."** FrankenPHP runs one PHP request per worker thread, and Octane on Swoole runs one per worker process, so each worker has its own statics and singletons.
3. **A new decision, "Hosts", holds the rulings,** and decision 56 starts with "Superseded in part by decision N", as decisions 34, 40, 43 and 63 do for decision 76. Decision 56 names a `php-sharp/laravel` adapter, which PHP# no longer ships.

## Decisions team-lead approved

1. **`Scope.run` is a library method, and this PR builds section 29's `uses f`.** `sharp/composer/library/Sharp/Scope.sharp` declares `public static T run<T>(Function<T()> body) uses body => Internal.runScoped(body);` with `extern Internal.runScoped;`, as `Events.sharp` wraps `Internal.listen`. The whole section 29 rule holds: a declaration with or without a body lets the function parameter it names hold any effect, and each call has the effects of the function passed as that parameter. The `function-effects` example passes, and its baseline entry leaves. The prelude path issue #60 deletes gains nothing.
2. **`Sharp\Container::scope` is deleted, and `Sharp\Internal\runScoped` replaces it,** so one call starts a scope. Its test and `modules.md` move to `Scope.run`.
3. **A scope is a refcounted table with an ended mark, and each fiber holds its current scope** in a slot of its `zend_fiber_context`, through the fiber observers (section "How ext/sharp keeps the scope stack").
4. **CI gains a thread-safe Linux job.** `sharp/bin/build --zts` and `sharp/bin/test --zts` build and test in `sharp/build/<os>-<arch>-zts`, beside the NTS build. A `LINUX_ZTS` job in `.github/workflows/sharp.yml` runs `sharp/bin/build --linux --zts` and `sharp/bin/test --linux --zts --opcache --repeat --passes`: every mode. Linux has a thread-safe JIT, so every `*_jit_*` test passes there. Only Apple Silicon has none, so MACOS stays NTS. `CI_PASSED` needs `LINUX_ZTS`.
5. **The FrankenPHP image** is docker-library's `php:8.5-zts-trixie` recipe at the pin the fpm image keeps, `79320e28b93f`, built from this repository's source, then FrankenPHP's own `Dockerfile` stages on top, as FrankenPHP builds on `php:8.5-zts-trixie`:
   - File: `sharp/docker/runtime/frankenphp.Dockerfile`, beside `sharp/docker/runtime/Dockerfile`, so both copy the same `docker-php-*` scripts.
   - Pins: FrankenPHP `v1.13.0`, fetched as a git source at its tag, Go `1.27`, which FrankenPHP's `docker-bake.hcl` names, and `e-dant/watcher` at a fixed tag where FrankenPHP takes `latest`.
   - Tag: `ghcr.io/heyjordanparker/php-sharp:<version>-frankenphp`, one image name with a suffix as docker-library names `8.5-fpm` and `8.5-zts`.
   - The `IMAGE` job becomes a matrix over the two files and two suffixes, with the same `docker/github-builder` workflow, platforms and `latest=false`.
6. **A `HOSTS` job runs the fixture on every code pull request,** and builds FrankenPHP against the engine CI already built, never a fresh engine build. The fixture lives in `sharp/hosts/`, and `sharp/bin/hosts` serves it from each host and compares what the requests print with `sharp/hosts/expected/`. `CI_PASSED` needs `HOSTS`. The pull request body reports its wall time. If it adds more than 10 minutes to the slowest job, it runs on tags and nightly instead.
7. **The static's count is the one line that differs between the hosts.** Each request prints whether its `scoped` service is new, whether its two `singleton` reads are one object, and the static's count, which reads `1`, `2` under the worker and `1`, `1` under php-fpm.
8. **The three process-wide intern tables stay, behind `sharp_types_lock`,** as the one process-wide mutable state, by design. Per-thread tables would intern each code type once per thread and lose the pointer equality `sharp_type_equals` takes for two persistent types.
9. **A long-running host keeps its size, in this PR.** The engine interns a type first read from input, such as a type text `unserialize` reads, in the request's own tables, which `ZEND_MODULE_POST_ZEND_DEACTIVATE_D(sharp)` frees "so input never grows a worker" (`type_arguments_requests.phpt`). Under a worker the PHP request is the whole worker, so each new type text from input would stay until the worker exits. Each request type gets a refcount, and it is freed when nothing uses it. A row serves many requests with new type texts under the worker fixture, and memory stays flat.
10. **The leak runs against master's engine first,** under the new image, before any fix, and the report holds its output.

# Every rule, its state and its mechanism

## Section 6: statics

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 251 | A static lives as long as the app runs, and its initial value runs once, when the app first uses its class's statics | The engine already does it: statics are PHP's static members and live for the PHP request, which is the app under every host. The spec says "when a request first uses its class's statics" | Spec only. The line becomes: "**A static lives as long as the app runs, and its initial value runs once, when the app first uses its class's statics,** so it may be any expression, as C#'s static initializer runs before the first use of a static. Under php-fpm the app runs for one request. Under a long-running host, such as Octane or FrankenPHP's worker mode, each request sees what the last one left, as in C#, TypeScript and Rust. A host that runs several workers runs one app in each. A get-only static is set only by its initial value." Rows: an engine test that runs two `Scope.run` bodies in one request and reads a static the first one wrote; the fixture's count | `#statics`, `zend_class_init_statics` |
| 261 | `private static int started = global.time();` | The comment says "runs once, when a request first uses the class's statics" | The comment becomes "runs once, when the app first uses the class's statics" | |
| 400 | `static lazy` computes once for the host's app lifetime, the lifetime of a `singleton` | True and unchanged | None. A row in the same engine test reads a `static lazy` in two scopes and computes it once | `Sharp_Internal_keep` |

## Section 32: lifetimes and scopes

| Line | Rule | Today | Mechanism | Reuses |
|---|---|---|---|---|
| 4016 | A `singleton` lives as long as the host runs the app | True under every host: `container_singletons` lives for the PHP request | None. Rows: two scopes share a `singleton`; the fixture's two requests share one | |
| 4016 | A `scoped` binding lives for one scope, which the host starts and ends | The host has no PHP# call to start one. Under a worker every HTTP request shares one scope (FrankenPHP's source; Phase 2 runs it first) | The new rule below | |
| new, after 4016 | **A host starts and ends each scope with `Scope.run(body)`.** While `body` runs, each `scoped` service it builds lives until `body` returns or throws, and is then dropped. `Scope.run` returns what `body` returns, and has `body`'s effects (section 29). PHP's own request is the default scope, so php-fpm, the CLI and FrankenPHP outside worker mode need no call. A long-running host wraps each unit of work in it: an Octane request, a FrankenPHP worker request, a queued job. Plain PHP calls it as `\Sharp\Scope::run(fn () => …)`. ASP.NET Core's `CreateScope()`, Node's `AsyncLocalStorage.run` and tokio's task-local `scope` start a scope the same way | `Sharp\Container::scope` does it for plain PHP, with the fiber crash. PHP# code has no call | Library: `Sharp/Scope.sharp`, decision 1. Engine: `Sharp\Internal\runScoped`, the scope stack below. Checker: `uses f` (decision 1), and `runScoped` in the engine stub. Rows below | `Container::scope`'s body, `Events.sharp`'s shape |
| new | A `scoped` service a body builds is dropped when the body returns | Works in `Container::scope` | Kept. Row: a service built in a scope is not the one the next scope builds | |
| new | … or throws | Works (scratch probe) | Kept. Row: after a body throws, the request scope's service comes back, and the next scope builds a new one | |
| new | `Scope.run` returns what `body` returns | Works for plain PHP | Kept. Rows: a PHP# caller gets `T` with no cast (checker), and the value back (engine) | |
| new | `Scope.run` has `body`'s effects | A lambda with an effect is refused | `uses f`, decision 1. Rows: a pure body keeps the caller pure, a body that calls `Environment.require` gives the caller `Environment`, and a pure interface method refuses it | `effects/summary.rs` |
| new | Nested `Scope.run` starts its own scope | Works | Kept. Row | |
| new | PHP's own request is the default scope | Works: RINIT starts it | Kept. Row: two `get` calls outside any `Scope.run` share a `scoped` service | |
| new, answer 1 | **A scope follows the fiber that runs in it.** A fiber started inside `Scope.run` belongs to that scope, and its own `Scope.run` is its own. A `scoped` service resolved after its scope ended throws `Sharp\ContainerError` | Segmentation fault | Fiber handlers, below. Rows: the fiber's services come from its scope, main's from main's, a resume after the fiber's scope ended throws, and nested scopes inside fibers. Spec example `scope-fibers` |  | `zend_observer_fiber_*` |
| new | Plain PHP calls `\Sharp\Scope::run(fn () => …)` | `\Sharp\Container::scope` | The library class compiles to `Sharp\Scope`, which Composer autoloads. Row: a plain PHP caller in an engine test | |
| new example `host-scope` | A PHP# worker wraps each job | No such example | Spec example, below | |
| 4035 | `// runs once per request, job or command` | True: each is one scope | None | |
| 4121 | The container implements PSR-11, and the language never depends on a framework | True | None. PHP# adds no framework code | |

The example, as section 32 will show it. Its harness classes come in Phase 2.

```csharp
// app/Jobs/Worker.sharp
namespace App.Jobs;

import Sharp.Scope;

public class Worker
{
    public Worker(private JobQueue queue) { }

    public void work()
    {
        for (const job of this.queue.pending())
        {
            Scope.run(() => job.handle());     // each job gets its own scoped services
        }
    }
}
```

## The lines that say "request" for a lifetime

| File | Line | Today | Becomes |
|---|---|---|---|
| `docs/architecture/modules.md` | 105 | "It keeps each `singleton` for the request and each `scoped` service for the current scope." | "It keeps each `singleton` for as long as the app runs and each `scoped` service for the current scope." |
| `docs/architecture/modules.md` | 108 | "The request is the scope, as each CLI run is. A long-running host runs each unit of work in `Sharp\Container::scope(fn)`." | "The request is the default scope, as each CLI run is. A long-running host runs each unit of work in `Scope.run`." |
| `docs/architecture/modules.md` | 110–116 | `Container` lists `scope` | `scope` goes, and `Scope` and `Sharp\Internal\runScoped` are listed |
| `docs/architecture/modules.md` | 165 | "`container_lifetimes.phpt` singleton, scoped, scope(), transient" | "… singleton, scoped, transient", and a line for `scope_run.phpt` |
| `docs/architecture/slices/06-properties.md` | 127 | "it runs once each request, when the request first builds the class's statics" | "it runs once per app, when the app first builds the class's statics" |
| `docs/architecture/slices/06-properties.md` | 228 | "a `static lazy` runs once per request" | "a `static lazy` runs once per app" |
| `ext/sharp/sharp.c` | 4924 | "A singleton is kept for the request and a scoped service for the scope." | "A singleton is kept for as long as the app runs, and a scoped service for the scope." |
| `ext/sharp/sharp.c` | 6838 | "Spec section 32: the request is the host's scope, as each CLI run is." | "Spec section 32: the request is the default scope, as each CLI run is." |
| `Zend/tests/sharp/container_lifetimes.phpt` | 2 | "… a scoped service for its scope, which is the request until Container::scope starts another …" | Its `scope` rows move to `scope_run.phpt` |
| `docs/decisions/056-modules.md` | 1 | | "Superseded in part by decision N", answer 3 |

`modules.md` line 99 and 101, "once per request", say how often the container reads a module file. That is once per PHP request, which is once per app, and they stay. Every other "request" in `docs/`, `sharp/` and `ext/sharp/` names an HTTP request or a pull request.

## Thread safety

| Requirement | Today | Mechanism |
|---|---|---|
| Module globals through `SHARP_G` | True for every global | None new. The new fiber slot is a resource handle set in `MINIT`, as the class entries are |
| No process-wide mutable state | The three intern tables are process-wide and mutable by design, behind `sharp_types_lock` | Kept. Per-thread tables would intern each code type once per thread and lose the pointer equality `sharp_type_equals` takes for two persistent types. They stay as the one exception, by design (decision 8) |
| A thread-safe build of PHP#'s tests in CI | None | Decision 4 |
| Correct with several threads in one process | Never run | The FrankenPHP fixture runs with `num_threads 4` and the worker's own threads. Phase 2 also runs the engine tests on the Linux ZTS build in every mode |

## The FrankenPHP image and the fixture

| Requirement | Today | Mechanism |
|---|---|---|
| A FrankenPHP image built from this repository | None. `sharp/docker/runtime/Dockerfile` is php-fpm, NTS, with no embed SAPI | Decision 5. The PHP stage keeps the fpm image's source stage and docker-library's ZTS flags, `--enable-zts --enable-embed --disable-zend-signals`, and adds the smoke test `php --ri sharp` |
| Published by the same tag workflow | The `IMAGE` job publishes the fpm image | Decision 5's matrix |
| A neutral fixture under worker mode and php-fpm | None | `sharp/hosts/`: a module binding a `scoped` `Visit`, a `singleton` `Counter` and a class with a static, `worker.php` for FrankenPHP's worker mode, which calls `frankenphp_handle_request(fn () => \Sharp\Scope::run(fn () => …))`, and `public/index.php` for php-fpm. Decisions 6 and 7 |
| Octane | No fixture: Octane is Laravel, and the fixture stays neutral | An Octane app wraps each request in a global middleware that calls `\Sharp\Scope::run(fn () => $next($request))`, and each queued job in a job middleware. `docs/v1.md` names Octane among the hosts v1 runs under. Octane's `terminate` callbacks run after the middleware returns, outside the scope |

## `docs/v1.md`

A new file with the Goal as Jordan set it: v1 is feature-complete PHP#, the first usable version, measured by the validation app. The validation app compiles and its tests pass on the PHP# engine, then its files move to PHP# one at a time, coordinated with Jordan, and keep working. Feature-complete means every spec example and rule holds, no "not supported yet" is left, PHP# runs under php-fpm, the CLI, FrankenPHP and Octane, and it is released with both runtime images. The file names the validation app by no name and no path.

# The `Scope` API

```csharp
// sharp/composer/library/Sharp/Scope.sharp
namespace Sharp;

import Sharp.Internal;

extern Internal.runScoped;

public static class Scope
{
    public static T run<T>(Function<T()> body) uses body => Internal.runScoped(body);
}
```

```php
// ext/sharp/sharp.stub.php, and the checker's crates/prelude/assets/extensions/sharp.php
namespace Sharp\Internal;

/**
 * @template T
 * @param \Closure(): T $body
 * @return T
 */
function runScoped(\Closure $body): mixed {}
```

`extern Internal.runScoped;` declares it pure, because starting a scope reaches nothing outside the container. Its effects are the body's, through `uses body`.

The name of the engine function, at its one callsite, `Internal.runScoped(body)`. `Scope` and `run` are Jordan's and fixed:

- `Internal.runScoped(body)` — what happens to the body; "run body scoped"; close to `run`, which is the point.
- `Internal.withScope(body)` — the state while it runs; "with a scope, body"; `with` already makes a copy in PHP#.
- `Internal.enterScope(body)` — the trigger; "enter a scope (and run) body"; readers add "and run".
- `Internal.runInNewScope(body)` — the object that changes; "run body in a new scope"; long.
- `Internal.isolate(body)` — the effect on services; "isolate body"; a metaphor that hides what is isolated.
- `Internal.scope(body)` — today's method name; "scope body"; a noun read as a verb.

Recommended: `runScoped`, since it says what the call does to the body and mirrors `Scope.run`. Runner-up: `runInNewScope`, the most literal.

`Scope` also names section 3, a variable's scope. Section 32 says "scope" for a service lifetime already, and `Sharp.Scope` is a type, so the two never meet in code.

# How ext/sharp keeps the scope stack

Today a scope is a table on the C stack, and `SHARP_G(container_scope)` points at it:

```c
HashTable *outer = SHARP_G(container_scope);
HashTable scope;
zend_hash_init(&scope, 8, NULL, ZVAL_PTR_DTOR, 0);
SHARP_G(container_scope) = &scope;
fci.retval = return_value;
zend_call_function(&fci, &fcc);
SHARP_G(container_scope) = outer;
zend_hash_destroy(&scope);
```

The stack is the C call stack. It holds while scopes end in the order they started. A fiber has its own C stack, so a fiber that suspends inside a scope leaves the global pointing into it.

The engine will keep each scope on the heap with a refcount and an ended mark, and each fiber will hold the scope it runs in:

```diff
 ZEND_BEGIN_MODULE_GLOBALS(sharp)
 	HashTable container_singletons;
-	HashTable container_request_scope;
-	HashTable *container_scope;
+	/* The scope the running fiber builds scoped services in: the request's until Sharp\Internal\runScoped
+	 * starts another. */
+	sharp_scope *container_scope;
 	HashTable container_building;
```

- **RINIT** creates the request's scope, and **RSHUTDOWN** ends it and releases it.
- **`Sharp\Internal\runScoped`** creates a scope, keeps the running fiber's scope in a C local, makes the new one current, calls the body, makes the kept one current again, then ends the new one: it empties its services, marks it ended and releases it. A throw takes the same path.
- **`sharp_container_bound`** keeps a `scoped` service in the current scope. In an ended scope it throws `Sharp\ContainerError`, "The scope that built this service ended. Resolve it inside `Scope.run`."
- **Fibers** (answer 1): `MINIT` takes a slot of each `zend_fiber_context`'s `reserved` array with `zend_get_resource_handle("sharp")` and registers three observers. `zend_observer_fiber_init_register`: a new fiber holds the scope current where it starts, with one more reference. `zend_observer_fiber_switch_register`: the scope moves from the global into the slot of the fiber that suspends, and from the slot of the fiber that resumes into the global. The main context's slot holds the main code's scope. `zend_observer_fiber_destroy_register`: the fiber's reference is released. A scope ended while a fiber holds it stays allocated, empty and ended, until that fiber ends.
- **`sharp_container_forget`**, which runs around each test, empties the current scope as it empties `container_request_scope` today.

# Files

```text
docs/
├── spec.md                                   section 6 lines 251 and 261; section 32's Scope.run rule and host-scope example
├── v1.md                                     new: the v1 Goal
├── decisions/
│   ├── 056-modules.md                        "Superseded in part by decision N" (answer 3)
│   └── 0NN-hosts.md                          new (answer 3)
└── architecture/
    ├── modules.md                            the lines above, Scope and runScoped in the API, the scope stack
    └── slices/
        ├── 06-properties.md                  lines 127 and 228
        └── 32-hosts.md                       this note
ext/sharp/
├── sharp.c                                   runScoped replaces Container::scope; sharp_scope; the fiber observers;
│                                             RINIT and RSHUTDOWN; sharp_container_bound refuses an ended scope
├── sharp.stub.php                            runScoped in Sharp\Internal; Container loses scope
└── sharp_arginfo.h                           generated from sharp.stub.php
sharp/
├── composer/library/Sharp/Scope.sharp        new: Scope.run
├── mago/crates/
│   ├── prelude/assets/extensions/sharp.php   runScoped, with its @template
│   ├── analyzer/src/effects/                 uses f, if decision 1 keeps it here
│   └── analyzer/tests/sharp.rs               uses f rows; Scope.run's effects and type
├── bin/build, bin/test                       --zts and the <os>-<arch>-zts build folder
├── docker/runtime/frankenphp.Dockerfile      new: the FrankenPHP image (decision 5)
├── hosts/                                    new: the fixture app, worker.php, public/index.php, Caddyfile, expected/
├── bin/hosts                                 new: builds both images and runs the fixture (decision 6)
├── baseline/spec/§29/function-effects        deleted when uses f holds
└── README.md                                 --zts, the FrankenPHP image, the HOSTS and LINUX_ZTS jobs
Zend/tests/sharp/
├── container_lifetimes.phpt                  its scope rows move out
├── scope_run.phpt                            new: Scope.run from plain PHP: returns, throws, nests, a static and a
│                                             static lazy across scopes, the default scope
├── scope_fibers.phpt                         new: answer 1's rows
└── ScopeRun.sharp                            new: a PHP# caller of Scope.run
.github/workflows/sharp.yml                   LINUX_ZTS, HOSTS, CI_PASSED needs both, IMAGE's matrix
README.md                                     Runtime image names both images
```

# Verification

- **Each rule above has its rows,** each seen failing on master before its code: the engine rows in `scope_run.phpt` and `scope_fibers.phpt`, the checker rows in `crates/analyzer/tests/sharp.rs`, and the spec examples through `sharp/bin/spec`.
- **The engine tests this slice touches pass in every mode, NTS and ZTS:** `sharp/bin/test --opcache --repeat --passes` on macOS NTS, and `sharp/bin/test --linux --zts --opcache --repeat --passes` on Linux.
- **The fixture runs under FrankenPHP's worker mode from the new image,** first against master's engine, which shows the leak, then against this branch: two HTTP requests, each in `Scope.run`, each with its own `scoped` `Visit`, one `singleton` `Counter`, and a static that keeps its count from the first request. The same fixture runs under php-fpm from the fpm image, with the outputs decision 7 names.
- **A plain PHP project's analysis is unchanged:** `mago.yml`'s plain-php job, and `mago analyze` of a plain PHP project before and after the change.

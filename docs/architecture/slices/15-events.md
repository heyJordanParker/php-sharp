# Slice 13: Events

Spec section 15, with the parts of the sections it leans on: section 29 (the effect `Events`), section 32 (the container that creates each listener's object), sections 11 and 14 (the type argument and the lambda of `Events.on<T>`), and section 23 (a class's full name, and `.` as member access). v1 plan Slice 13. This note is the design only. team-lead approves it before anyone builds.

## Jordan's ruling: nothing nests

**Every type, events and interfaces included, is declared at file level in a namespace, in its own file** (specimen ruling 3, round 3, 2026-10-12). `app/Store/OrderPaid.sharp` declares `public event OrderPaid(int orderId, Money amount) : OrderEvent;`, and `app/Store/OrderEvent.sharp` declares the interface its family shares. A type declared inside a class, an interface, an enum, a trait or a module is one parse error that names its owner: "A class can't declare a type inside it: declare `Paid` in its own file."

Spec section 23 already says a class can't share its full name with a namespace, and `.` in code is always member access. So `new Order.Paid(…)` can only call `Order`'s named constructor `Paid`, which is mago-sharp #161's work, and a dotted name in a type position is not PHP#.

The ruling changes the scope of these PRs:

- **mago-sharp PR 1 (#162) shrinks.** It keeps `event` at file level, `extern event` with or without `uses`, get-only interface properties and their checks, and the nested-type refusal. It drops the nested event and interface, the binder's nested-first lookup, dotted types, the dotted `new X.Y` resolution and the nested lowering.
- **mago-sharp PR 3 (`on`) shrinks.** An `on` clause lists plain type names, and an unknown name is the existing "Cannot find type" error. The "`Order` has no event `Payed`" message goes.
- **php-sharp PR A shrinks.** `sharp/composer/autoload.php` needs no nested-type branch, because each event is its own class in its own file.
- **The spec-files PR changes shape.** The harness declares `OrderEvent`, `OrderPaid` and `OrderRefunded` in three files of their own, and the `emit-forms` template marker is no longer needed.
- **mago-sharp PR 2 (`emit`), PR 4 (`Events.on<T>`) and php-sharp PRs B and C keep their scope.** Only their example names change.

## The answer first

**No part of section 15 exists.** Every form is a parse error or a misleading error on mago-sharp `master` at `b5827b3a9`, and none compiles silently:

- `public event Paid(int orderId, Money amount);` at file level is a parse error. Inside a class it parses as an abstract method named `Paid` that returns a type named `event`: "Non-Abstract method `Parcel.Lost` must have a concrete body."
- An `interface` inside a class, a dotted type such as `Order.Paid e`, `emit`, the `on` clause and `extern event` are all parse errors.
- `new Order.Paid(…)` reads as a named constructor: "A named constructor is not supported yet in PHP#."
- `Events.on<RowImported>(…)` reads as two comparisons, and `Events.on(…)` is "Method `Events.on` does not exist."
- `Function<void() uses Events>` compiles, and is correct: `Events` is already a standard effect name.

**The Slice builds it in 4 mago-sharp PRs and 3 php-sharp PRs.** Four of them need no generics and land now: mago-sharp PRs 1 to 3 and php-sharp PR A. mago-sharp PR 4 and php-sharp PR B wait for generics (mago-sharp #152, php-sharp #102, then R3c's `typeof(T)`). php-sharp PR C waits for section 32's container.

**Each construct lowers to PHP the engine already runs, plus one engine function:**

- An event is a `final` PHP class whose constructor promotes `public readonly` properties. `app/Store/OrderPaid.sharp` is the PHP class `App\Store\OrderPaid`, as every class in its own file is.
- `emit e;` is a call of `\Sharp\Internal\emit($e)`, the one function the engine adds. Plain PHP calls it too.
- An `on` clause adds nothing to its class. `mago compile` writes every listener into one table, `.sharp/listeners.php`, and `Sharp\Internal\emit` reads it.
- `Events.on<T>(f)` returns a `Sharp\Listening` handle whose destructor stops the listener.

**Guard 1.** Of section 15's 7 entries in `sharp/baseline/spec`, php-sharp PR A removes `standalone-event` and `extern-event` outright. It removes `listeners`, `listener-errors`, `emit-forms` and `event-declarations` once the spec-files changes in "Rulings needed outside this Slice" land. PR B removes `scoped-listener` after its errata. Section 29's `emit-effect` leaves with PR A once section 29's `uses` on a method declaration, task #468's default `public` and the harness's `OrderEvent` and `OrderPaid` files land. No PR of this Slice can write `Completes: §15` before all 7 are gone.

## Decisions team-lead approves

Decisions 1 (nested-type naming) and 6 (autoload through the outer class) of the approved note are gone, because each event is its own class in its own file. The rest keep their text and take new numbers.

1. **`emit e;` lowers to `\Sharp\Internal\emit($e);`.** Architecture's rule puts runtime machinery that plain PHP can see, and PHP# code never writes, under `Sharp\Internal`. PHP# raises only with `emit`, so `Sharp.Events` gets no `emit` method and there is one spelling.
2. **`mago compile` writes one listener table, `.sharp/listeners.php`.** It is a PHP file returning every listener in a fixed order, each with the types its `on` clause lists and whether it takes the event:

   ```php
   <?php

   return [
       ['App\OrderNotices', 'alert', ['Illuminate\Queue\Events\JobFailed'], true],
       ['App\OrderNotices', 'audit', ['App\OrderEvent'], true],
       ['App\OrderNotices', 'refresh', ['App\OrderPaid'], false],
       ['App\OrderNotices', 'send', ['App\OrderPaid', 'App\OrderRefunded'], true],
       ['App\Receipts', 'deliver', ['App\OrderPaid'], true],
   ];
   ```

   opcache keeps it in shared memory, so a request reads no file. Laravel's `php artisan event:cache` writes `bootstrap/cache/events.php` the same way, and Symfony compiles its listeners into its container. `mago compile` already deletes every other file in `.sharp/`, and it keeps this one.
   - Rejected: a table inside each event's `.sharpc` file. An interface listener and an `extern event` have no event file to hold it, and a new listener file would have to make every event's file stale.
   - Rejected: listeners that register when their class loads. A listener class is autoloaded only when something names it, so it would never register, which is why Laravel needs explicit registration or its discovery cache.
3. **`Sharp\Internal\emit` is engine code in `ext/sharp/sharp.c`,** as `Sharp\Collection` and `Sharp\Environment` are. It finds `.sharp/` as the engine finds it for a source file (spec section 27), walking up from the file that declares the event's class, so an `extern event` in `vendor/` finds the project's table. It reads the table once per request through `zend_compile_file`, so opcache serves it, as the constant array the file returns, without running the file: code run from inside `emit` would give the caller a symbol table that a tracing-JIT frame never frees, a leak measured on `events_jit_tracing.phpt` with `--repeat 2`. For each event class it keeps, for the request, the listeners whose listed types the event is an `instanceof`, in table order. That is the one lookup path, the way a PSR-14 `ListenerProviderInterface::getListenersForEvent` matches by class and interface. A class with no `.sharp/` folder above its file has no listeners. A missing or damaged table throws `CompileError`, ".sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.", as the engine refuses a damaged compiled file, and a listener class or method the table names but the code no longer has throws PHP's own `Class "…" not found` or `Call to undefined method`.
4. **Until section 32's container lands, the engine creates a listener's object with `new`,** and the checker refuses a listener class whose main constructor takes parameters as "not supported yet". Section 32 says the container builds every class through its main constructor, `on` listeners included. php-sharp PR C swaps `new` for the container with no change to any accepted program.
5. **`extern event JobFailed;` is that class's one `extern` declaration.** `ExternMetadata` gains `event: bool`, and a second `extern` naming `JobFailed` is the existing "at most one `extern` declaration" error. Without `uses` it declares the class pure, as every `extern` without `uses` does. With `uses`, as `extern event JobFailed uses Database;`, it gives the class those effects, as `extern JobFailed uses Database;` does (Jordan's ruling on question 2). Creating a `JobFailed` then has the effect `Database`, through the existing `extern_of` in `crates/analyzer/src/effects/summary.rs`.
6. **`emit` records a new `Effect::Emit` in its body's `EffectSummary`,** which `is_outside` matches against `Sharp\Events`. It carries no payload, because the effect's cause already holds the event's class. `Impurity::describe` gains the arms "emits `OrderPaid`, which has the effect `Events`" and "reaches `StoreCheckout.complete`, which emits `OrderPaid` with the effect `Events`". The effect is `Events` whatever the listeners do: decision 049 chose `Events` to stand for the listeners, so their own effects never reach the code that emits.

## Standard-library choices the spec leaves open

decision 066 makes the dispatcher's API team-lead's (php-sharp #56). Each item is a proposal with its precedent, and team-lead rules. The note writes the proposal everywhere below.

1. **Listeners run in table order: by the listener class's full name, then by the method's place in its class, then the scoped listeners in the order they started.** The order is the same on every build, and nothing changes it. Precedent: a C# event's invocation list and Laravel's dispatcher run in registration order. PHP# has no registration, so a stable sort stands in for it. Rejected: Symfony's integer priorities, which couple listeners that never name each other.
2. **An exception from a listener leaves `emit` at once, and the remaining listeners do not run.** Precedent: PSR-14 requires it ("An Exception or Error thrown by a Listener MUST block the execution of any further Listeners" and "MUST be allowed to propagate back up to the Emitter"), and C#'s multicast delegates, Laravel and Symfony all behave this way. Spec section 31 makes an exception a bug that fails loudly. Rejected: MediatR's `TaskWhenAllPublisher`, which runs every handler and throws an `AggregateException`.
3. **An `emit` inside a listener runs its own listeners at once, before the outer `emit` continues,** as every synchronous dispatcher does.
4. **Each `emit` runs the listeners that existed when it started.** A scoped listener started or stopped during an `emit` takes effect at the next one. Precedent: C# delegates are immutable, so `+=` inside a handler never changes the running invocation list.
5. **A listener runs once per `emit`,** even when several of its listed types match, as `send(…) on OrderPaid, OrderEvent` would. The table holds each listener once, so this needs no extra rule.
6. **A listener's object is created for each call** (decision 4), as Laravel's dispatcher resolves a listener class from its container on every dispatch. Section 32's Slice decides the container's lifetime for a class no module binds, and must keep this one or refuse a listener that keeps state between events.
7. **`Events.on<TEvent>` and its handle:**

   ```csharp
   // sharp/composer/library/Sharp/Events.sharp
   namespace Sharp;

   public static class Events
   {
       [NoDiscard]
       public static Listening on<TEvent>(Function<void(TEvent)> listener) uses Events, listener => listen(typeof(TEvent), listener);
   }
   ```

   - `listen` is the engine function `Sharp\Internal\listen(string $class, \Closure $listener): Sharp\Listening`. The checker's prelude declares it, and the checker refuses a call of a `Sharp\Internal` function outside the standard library, as it already refuses a native body outside it: "`listen` belongs to PHP#'s engine; call `Events.on`." So PHP# code listens one way.
   - `uses listener` lets the lambda have any effect, and gives the call its effects. That is exact here: the listener runs only while the handle lives, which spec section 15's example bounds by the method that started it.
   - `uses Events`, because starting a listener changes what every later `emit` runs.
   - `[NoDiscard]`, because a handle dropped at once stops its listener at once: `Events.on<RowImported>(…);` as a statement listens to nothing. Precedent: Swift's Combine warns "Result of call to 'sink' is unused" for an `AnyCancellable` it cancels on release, and Rust marks guards `#[must_use]`. The analyzer already reports `[NoDiscard]` (`detect_unused_statement_expressions`).
   - `Sharp\Listening` is an engine class with no methods and a destructor that stops the listener, measured with PHP 8.5.1: a local's destructor runs when its function returns, and when an exception leaves it. Precedent: decision 049's tokio receiver and C#'s `using var`. The spec names no `stop()`, so it has none.
8. **The replaceable dispatcher is a service a module binds:**

   ```csharp
   namespace Sharp;

   public interface Dispatcher
   {
       void dispatch(Any event, List<Function<void()>> listeners) uses Events;
   }
   ```

   The standard library binds `Dispatcher` to `ImmediateDispatcher`, which calls each function in order. An app overrides the binding in its `Module.sharp`, and a test module binds a fake, so "replaces the dispatcher once at startup" and "not at all in tests" are section 32's own overrides with no new API. Precedent: MediatR registers its `INotificationPublisher` in dependency injection, and Laravel binds `Illuminate\Contracts\Events\Dispatcher` in its container, which `Event::fake()` swaps. Each function runs one listener on the event, so a dispatcher can run it later in the request, retry it, or skip it. It cannot serialize one to a queue, and the spec asks for none.
9. **Plain PHP raises with `\Sharp\Internal\emit($event)`** and listens through a dispatcher an adapter binds (after php-sharp PR C). It writes no `on`.

## Language rules the spec leaves open

The checker enforces each one with the message shown. team-lead rules, and brings any it rejects to Jordan.

1. **`emit` takes a value whose type is an event,** declared with `event` or `extern event`: "`emit` takes an event, and `Money` is not one." An interface type such as `OrderEvent` is refused the same way, so the checker never accepts a value plain PHP could fill with a non-event.
2. **An `on` clause names events, or interfaces that at least one event implements.** "`Money` is not an event." and "No event implements `Countable`, so `refresh` never runs." Decision 049 rejected startup registration because "a forgotten line silently never runs", and a listener that nothing can reach is the same failure.
3. **A listener is an instance method that returns `void`, in a class that is neither abstract nor generic:** "A listener runs on an object, so `refresh` cannot be `static`.", "A listener returns nothing, and `refresh` returns `int`.", "`Notices` is abstract, so the container cannot create it to run `refresh`." A generic class is refused because nothing would fix its type arguments.
4. **A listener may be `private`.** The engine calls it, and the analyzer counts an `on` method as used. Precedent: VB.NET's `Handles`, decision 049's chosen precedent, is idiomatic on a `Private Sub`.
5. **An event takes only `public`, and declares no body.** Its header lists interfaces only, and the class it lowers to is `final`. A family of events shares an interface, as spec section 15 shows, so no event extends another. Precedent: Kotlin's data classes are final.
6. **Nothing nests.** A type declared inside a class, an interface, an enum, a trait or a module is one parse error over the whole type, which names its owner and the type: "A class can't declare a type inside it: declare `Paid` in its own file." The owner parses on without it. This is Jordan's ruling 3, not a rule the Slice chose.

## Jordan's rulings on the spec syntax questions

1. **`new Order.Paid(…)` against a named constructor.** Jordan first picked the nested type, then ruled that nothing nests. Spec section 23 says a class can't share its full name with a namespace, and `.` in code is always member access, so `new Order.Paid(…)` can only call `Order`'s named constructor `Paid`. That is mago-sharp #161's work, and this Slice adds no `new X.Y` resolution and no "both a type and a named constructor" error.
2. **`extern event` carries `uses`.** "sure", following section 29's own `extern … uses`. `extern event JobFailed uses Database;` gives the plain PHP class `JobFailed` the effect `Database` (decision 5).
3. **Nothing nests.** Every type, events and interfaces included, is declared at file level in a namespace, in its own file. Jordan: "We care about the final usage of the language, its capabilities & CONSUMERS basically, not which code is easier to implement." His CS0542 ruling, a nested type named like its class, no longer applies.

## Rulings needed outside this Slice

1. **`emit-forms` needs no template marker.** `sharp/bin/spec` matches the setup line `public event Paid(int orderId, Money amount);` against `TYPE_DECLARATION` and moves it to file level in its own generated file, which is now exactly where an event belongs. The marker the approved note proposed only kept a nested event nested, so it is withdrawn.
2. **The harness needs four changes** (`sharp/harness/`, a spec file):
   - `app/OrderEvent.sharp` declares `public interface OrderEvent { public int orderId { get; } }`. `app/OrderPaid.sharp` declares `public event OrderPaid(int orderId, int amount) : OrderEvent;`, and `app/OrderRefunded.sharp` declares `public event OrderRefunded(int orderId, int amount) : OrderEvent;`. Each type is in its own file. The amount is `int` because the harness `Order.total` is `int`, and section 29's `emit-effect` passes `order.total`. Making `Order.total` a `Money` everywhere would break the sections that use it as a number, such as section 21's `order.total + 1`.
   - `app/Stubs/Laravel.sharp` declares `extern event JobFailed;`, so `listeners`'s `alert(JobFailed e) on JobFailed` names an event.
   - `app/Logger.sharp` declares `public class Logger { }`. `listener-errors` writes `Logger logger`, and a missing class never satisfies a `compile error` line.
   - `app/Imports/RowImported.sharp` declares `public event RowImported(int row);`, for `scoped-listener`.
3. **Errata for Jordan,** beside the batch team-lead already holds for `scoped-listener`'s undeclared `progress` and `importer`:
   - `event-declarations` declares its own `Order`, which replaces the harness's, and reads `this.id` and `this.total` that it never declares. It needs `public int id { get; }` and `public Money total { get; }` in the example's `Order`.
   - `scoped-listener` names `RowImported` in namespace `App` with no import. It needs `import App.Imports.RowImported;`.
4. **Cross-slice order:**
   - An interface property is "This class member is not supported yet in PHP#." on `master`, and an interface member without `public` is "Interface virtual property `Tracked.shipmentId` must be declared public." (task #468). A spec example that writes `interface OrderEvent { int orderId { get; } }` needs both. PR 1 adds get-only interface properties, which PHP 8.4 runs as `public int $orderId { get; }`, and #468 lands its default-public rule before php-sharp PR A. The harness writes `public`, so it needs only PR 1.
   - Section 29's `emit-effect` writes `void complete(Order order) uses Events;` in an interface, which is a parse error on `master` ("Expected one of `LeftBrace`, found `Identifier`", the same as `effectful-declarations`). It leaves the baseline when section 29's Slice parses `uses` on a method declaration, and when #468 makes that interface method `public` by default.
   - Section 32's container comes before php-sharp PR C.

## How the states were observed

Every state below comes from the `mago` built from mago-sharp `master` at `b5827b3a9` in slot 3, `/Users/jordan/Developer/sharp-worktrees/3/mago-sharp/target/debug/mago`, built at 03:14 on 2026-10-12 (`stat -f "%Sm"`) with `git status --short` empty.

**The spec's examples ran through Guard 1's own checker.** `git archive` exported `README.md`, `docs/spec.md`, `sharp/bin/spec`, `sharp/baseline/spec`, `sharp/harness/` and `sharp/composer/` from php-sharp `ci/spec-examples` at `20e7f96b7` into `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events/spec-tree`, and `sharp/bin/spec` ran there with `TEST_MAGO_EXECUTABLE` set to the `mago` above. `sharp/composer/` is the same at php-sharp `master` (`git diff --stat origin/master origin/ci/spec-examples -- sharp/composer` is empty). The branch moved to `fbbef736f` during the work, and that commit changes only section 6.2's probe examples and section 24 (`git diff --stat` shows `docs/spec.md` alone). Each "the checker reports" line below is that run's output, which carries mago's message and its first note. The engine runs nothing for section 15's examples, because none states a value or a `throws`.

**Single rules ran on one probe project,** `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events/ws`, a copy of one example's generated project with the example removed: the harness, the standard library under `vendor/heyjordanparker/php-sharp-composer/`, and the probe files under `app/Events/`.

```sh
MAGO=/Users/jordan/Developer/sharp-worktrees/3/mago-sharp/target/debug/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-events
compile() { env -u FORCE_COLOR XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=emacs MAGO_LOG=error MAGO_THREADS=1 \
  $MAGO --no-version-check --colors never --workspace $P/ws compile; }
```

`compile` runs printed every probe line quoted below. `Tracked.sharp` ran once without `public` and once with it. A run with `MAGO_REPORTING_FORMAT=json`, read by `$P/issues.php`, gave each parse error's note. The last run ended with `Compiled 85 PHP# files into .sharp/. 10 PHP# files were refused`, and `Hooks.sharp` and `Unused.sharp` were among the compiled.

**PHP's own behavior ran** on Homebrew PHP 8.5.1, `$P/php-behavior.php`: the PHP the bridge would emit for an event and its interface, under the nested names the approved note proposed, and a destructor handle. Its output is quoted where a rule uses it, and the engine run below repeats it under file-level names.

**The engine part of each state is read from code,** except the run below. No PHP# engine is built in slot 3.

**mago-sharp #162 after Jordan's ruling ran on a second probe project,** `$P/ws-r3`, with the `mago` built from `feat/slice-events` at `41982e195` in `/Users/jordan/Developer/sharp-worktrees/3/mago-sharp-pr1`. It holds `app/Shop/OrderEvent.sharp`, `app/Shop/OrderPaid.sharp`, `app/Shop/Order.sharp`, `app/Shop/Failure.sharp`, `app/Stubs/Queue.sharp` (`extern event JobFailed uses Database;`) and the plain PHP `src/JobFailed.php`, plus `app/Shop/Nested.sharp` for the refusal. Its compiled files ran on the PHP# engine built in slot 7, `/Users/jordan/Developer/sharp-worktrees/7/php-sharp/sharp/build/darwin-arm64/sapi/cli/php`, through `$P/ws-r3/run.php`.

# Every rule of section 15, its state and its mechanism

The states are **parse error**, **misread** (it parses as something else and fails for that reason), **missing** (it parses, and the name does not exist) and **works**.

## An event type, declared at file level in its own file

An event is a class whose parameters become get-only properties, declared at file level in its own file (Jordan's ruling 3). `app/Store/OrderPaid.sharp` declares `public event OrderPaid(int orderId, Money amount) : OrderEvent;`.

On `master`, misread inside a class and a parse error at file level. `app/Events/Parcel.sharp`:

```csharp
namespace App.Events;

public class Parcel
{
    public event Lost(int parcelId);
}
```

printed

```text
app/Events/Parcel.sharp:5:36:error - semantics: Non-Abstract method `Parcel.Lost` must have a concrete body.
app/Events/Parcel.sharp:3:14:error - unimplemented-abstract-method: Class `Parcel` does not implement the abstract method `lost`.
```

With a header, as `event-declarations` writes `public event Paid(int orderId, Money amount) : Event;`, it is a parse error, and the example's whole `Order` fails:

```text
docs/spec.md:1228 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Question`, `LeftParenthesis`, `Array`, `Callable`, `Null`, `True`, `False`, `Static`, `Self_`, `Parent`, `Enum`, `From`, `Identifier`, `QualifiedIdentifier`, `FullyQualifiedIdentifier`, found `Interface`
docs/spec.md:1229 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1230 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1232 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1233 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `LeftBrace`
docs/spec.md:1235 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `New`
docs/spec.md:1237 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `RightBrace`
docs/spec.md:1239 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `Public`
and problems on 2 more lines
```

`standalone-event`, an event on its own in a file, printed

```text
docs/spec.md:1252 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
```

On #162, `$P/ws-r3` compiles `OrderPaid.sharp` and `Order.sharp`, which creates `new OrderPaid(orderId, amount: 2)` and reads `paid.amount + paid.orderId`.

- PHP it lowers to, under the file's `namespace App\Store`:

  ```php
  final class OrderPaid implements \App\Store\OrderEvent
  {
      public function __construct(public readonly int $orderId, public readonly \App\Money $amount) {}
  }
  ```

- The engine's behavior, run through `$P/ws-r3/run.php`: `get_class` printed `App\Shop\OrderPaid`, `instanceof App\Shop\OrderEvent` and `ReflectionClass::isFinal()` were both `true`, `amountOf` printed `9`, and `$paid->orderId = 1` threw `Cannot modify readonly property App\Shop\OrderPaid::$orderId`. A get-only auto-property already lowers to `readonly` (`is_readonly` in `crates/sharp-bridge/src/lower.rs`).
- Mechanism:
  - Syntax: a CST `Event` (keyword, name, parameter list, optional `Inheritance`, terminator) and a `Statement::Event`. The parser reads `event` as a keyword only in `.sharp` files, where it starts a declaration. `composer/src/Sdk/Syntax/NodeKind.php` regenerates with `scripts/regen-sdk-node-kinds.php`.
  - Semantics: `check_slice` allows `Event` at file level. It refuses a modifier other than `public`, a body, and a header naming a class.
  - Codex: the scanner records an event as a final class-like with a promoted constructor and an `event` flag on its `ClassLikeMetadata`, so every other check reads it as the class it lowers to. `Plugin::declaredClass` already matches `app/Store/OrderPaid.sharp` to `App\Store\OrderPaid`, so the class map needs no change.
  - Bridge: `Lowering::event` emits the class.

## An interface its events share, in its own file

A family of events shares an interface, declared at file level in its own file, as `app/Store/OrderEvent.sharp` declares `OrderEvent`.

On `master`, a parse error inside a class: `app/Events/Shipment.sharp`, `public class Shipment { public interface Event { int shipmentId { get; } } }`, printed `Expected one of …, found `Interface`` (8 parse errors in all). At file level the interface parses, and its property is refused:

```text
app/Events/Tracked.sharp:3:28:error - semantics: Interface virtual property `Tracked.shipmentId` must be declared public.
app/Events/Tracked.sharp:3:28:error - semantics: This class member is not supported yet in PHP#.
```

and with `public` written, only "This class member is not supported yet in PHP#." remains.

- PHP it lowers to: `interface OrderEvent { public int $orderId { get; } }`. The engine's behavior, run above: `OrderPaid`'s `public readonly int $orderId` implements it.
- Mechanism: a get-only interface property is cross-slice item 4. The analyzer refuses a class or an event whose property does not fit the interface's, as the engine refuses to load it: not `public`, `static`, or of another type, where a get-only property may take a narrower type.

## A type declared inside another is refused

Nothing nests (language rule 6). On #162, `$P/ws-r3`'s `app/Shop/Nested.sharp` declares `public event Refunded(int orderId) : OrderEvent;` and `public interface Placed { }` inside `class Nested`, and printed

```text
app/Shop/Nested.sharp:5:5:error - parse: A class can't declare a type inside it: declare `Refunded` in its own file.
app/Shop/Nested.sharp:7:5:error - parse: A class can't declare a type inside it: declare `Placed` in its own file.
```

- Mechanism: `parse_sharp_member_with_attributes_and_modifiers` parses an `event`, `interface`, `class`, `enum` or `trait` inside a class-like whole, so the owner parses on, and returns `ParseError::NestedTypeInSharp` over it. The parser's `State::members_of` names the owner, `A class`, `An interface`, `An enum`, `A trait` or `A module`, so the message is true inside each.
- A dotted name is no type. `new Order.Paid(…)` calls `Order`'s named constructor `Paid`, which is "A named constructor is not supported yet in PHP#." until mago-sharp #161, as on `master`. `app/Events/Maker.sharp`, `const lost = new Parcel.Lost(7);`, printed that on `master`:

  ```text
  app/Events/Maker.sharp:7:26:error - parse: A named constructor is not supported yet in PHP#.
  ```

## `emit` raises an event, and any code may raise any event type

"`emit` raises an event. Any code may raise any event type", "the default dispatcher runs listeners immediately, before `emit` returns".

Parse error. `emit-forms`, as `sharp/bin/spec` builds it:

```text
line 2 of the generated app/Paid.sharp, from the marker's setup or wrapper: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1264 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `New`
docs/spec.md:1265 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `New`
```

Its one "not supported yet" in the baseline is the spec's `new Order.Paid(…)` read as a named constructor (`app/Paid.sharp:11:10:error - parse: A named constructor is not supported yet in PHP#.`), which Jordan's ruling makes `new OrderPaid(…)`. `app/Events/Raiser.sharp`, `emit amount;` with `Money amount`, printed `Expected one of `Semicolon`, `CloseTag`, found `Identifier``.

- PHP it lowers to: `\Sharp\Internal\emit(new \App\OrderPaid($this->id, $this->total));`.
- The engine's `Sharp\Internal\emit(object $event): void` (decision 3) collects the event's listeners and the scoped listeners that match, snapshots them (choice 4), and runs each in order: it creates the listener's object, and calls the method with the event when the table says it takes one. Until php-sharp PR C, it runs them itself. After it, it hands them to the bound `Dispatcher` as functions.
- Mechanism:
  - Syntax: a CST `Emit` statement, `emit <expression>;`, in `.sharp` files.
  - Analyzer: the operand's type must be an event (language rule 1). `Effect::Emit` joins the body's summary (decision 6).
  - Bridge: `Lowering::statement` lowers `Emit` to the call.
  - Lean: a law that reaches an `emit` is refused for the effect `Events` before `crates/sharp-lean` translates it, so the translator needs no arm.

## `extern event` marks a plain PHP class as an event

"`extern event` marks a plain PHP class, such as a Laravel event, as an event, so PHP# code can listen to it."

Parse error. `extern-event` printed

```text
docs/spec.md:1277 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `Identifier`
```

`extern event` parses `event` as the extern's target, then finds `JobFailed`.

On #162, `$P/ws-r3`'s `extern event JobFailed uses Database;` compiles, and gives the plain PHP class its effect. A getter that creates one printed

```text
app/Shop/Failure.sharp:7:30:error - impure-getter: Getter `last` calls `JobFailed`, which has the effect `Database`. Getters must be pure.
```

and a method that creates one compiles, and the engine ran it: `get_class` printed `Queue\JobFailed`.

- PHP it lowers to: nothing, as every `extern` (`Statement::Extern` in `Lowering::file_statement`).
- Mechanism: `Extern` gains `event: Option<Keyword>`, and `ExternMetadata` gains `event: bool` (decision 5). The scanner records `uses` as for any class-level `extern`, and `extern_of` gives every call on the class, `new` included, its effects. An `on` clause and `emit` accept the class.
- What reaches a PHP# listener: an `emit` of it in PHP#, and plain PHP calling `\Sharp\Internal\emit($jobFailed)`. Laravel's own `event(new JobFailed(…))` reaches PHP# listeners only through an adapter that forwards it ("Found outside the Slice").

## Listening with an `on` clause

"a trailing `on` clause after a method's parameters lists the events the method listens to, for as long as the app runs. The method takes zero parameters, or one parameter whose type every listed event shares. It takes no other parameter, and no parameter is implicit."

Parse error. `listeners` printed

```text
docs/spec.md:1288 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Variable`, found `Dot`
docs/spec.md:1289 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1290 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1291 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1292 expects no error, and the checker reports: Parse error encountered during parsing Unexpected token `RightBrace`
```

and `app/Events/Listener.sharp`, `public void refresh() on Money { }`, printed `Expected one of `LeftBrace`, found `Identifier``.

- PHP it lowers to: the method alone, `public function refresh(): void { … }`. The clause becomes one row of `.sharp/listeners.php` (decision 2).
- Mechanism:
  - Syntax: `Method` gains `on: Option<On>`, a keyword and a comma-separated list of type names, after the return type and before the body or `=>`. Each name is a bare or imported type, as every type is, so a dotted name is refused as it is in any other type position.
  - Codex: `FunctionLikeMetadata` gains `listens: Vec<Word>`, the full names the clause lists.
  - Analyzer: `check_listener`, a new file `crates/analyzer/src/statement/class_like/listener.rs` beside `override_attribute.rs`, checks the clause (the rules below).
  - Orchestrator and command: `IncrementalAnalysisService::listeners` returns every method with a non-empty `listens`, sorted (choice 1), and `CompileCommand::execute` writes the table and spares it from the folder's clean-up.

## The container creates the object each listener runs on

"The namespace's `Module.sharp` container creates the object each listener runs on."

Missing: section 32's container does not exist (`module` parses only laws, `ModuleMember::Law`).

- Until php-sharp PR C: `new` on a class whose main constructor takes no parameters (decision 4). A listener class whose constructor takes parameters is "A listener whose class takes constructor parameters is not supported yet in PHP#."
- After it: the container builds the object, as it builds any class.

## A listener on an interface hears every event that implements it

Parse error, as `listeners`'s interface listener, `audit`, shows above.

- The engine matches each listed type with `instanceof`, so an interface needs nothing more (decision 3).
- The checker requires at least one event to implement a listed interface (language rule 2).

## `mago compile` finds every `on` at build time, and refuses a bad one

"A wrong event name, or a parameter type the listed events don't share, is a compile error."

Parse errors where the spec wants three compile errors. `listener-errors` printed

```text
docs/spec.md:1300 expects a compile error, and the checker reports: Parse error encountered during parsing Expected one of `Variable`, found `Dot`
docs/spec.md:1301 expects a compile error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
docs/spec.md:1302 expects a compile error, and the checker reports: Parse error encountered during parsing Expected one of `Class`, found `Identifier`
line 7 of the generated app/Example.sharp, from the marker's setup or wrapper: Parse error encountered during parsing Unexpected token `RightBrace`
```

The errors, each with its code, written with the file-level names Jordan's ruling gives the spec's examples:

- A parameter the listed events don't share: `invalid-event` "`send` takes an `OrderPaid`, and it listens to `OrderRefunded`, which is not one." with the help "Take a type every listed event implements, such as `OrderEvent`, or take no parameter." The help names the most specific interface every listed event implements, and drops "such as …" when there is none. A test row compiles `send(OrderEvent e) on OrderPaid, OrderRefunded`.
- A wrong event name, as `on OrderPayed`: the existing `non-existent-class-like` "Cannot find type `OrderPayed`.", as any unknown type in a `.sharp` file. Nothing nests, so no class lists "its events", and the approved note's "`Order` has no event `Payed`." goes.
- A listener that takes more than its event: `invalid-event` "A listener takes only its event, and `log` also takes `logger`." with the help "A listener gets everything else through its class's constructor." The help quotes no code, because a listener class with constructor parameters waits for section 32.

`invalid-event` is one new issue code for every rule of this Slice: the `on` clause, a listener's shape, an event's declaration and `emit`'s operand, as `invalid-argument` covers every wrong argument. Every message names PHP# types as PHP# writes them, and none cites a section.

## Listening for a while: `Events.on<T>`

"`Events.on<RowImported>(e => …)` starts a listener and returns a handle. The listener stops when the handle goes out of scope."

Parse error, then missing. `scoped-listener` printed

```text
docs/spec.md:1313 expects no error, and the checker reports: Comparisons do not chain: write `(Events.on<RowImported)>(e => this.progress.advance())` or `Events.on<(RowImported>(e => this.progress.advance()))`.
docs/spec.md:1314 expects no error, and the checker reports: Property `ImportScreen.importer` does not exist.
```

and `app/Events/Scoped.sharp`, `const listening = Events.on((Money m) => { });`, printed `app/Events/Scoped.sharp:9:34:error - non-existent-method: Method `Events.on` does not exist.` A bare `Events` already resolves to `Sharp\Events`, as `resolve_class` resolves every standard effect name. `app/Events/Unused.sharp` showed that an unused `const` compiles, so `const listening = …` needs no change.

- Needs generics: mago-sharp #152 parses, checks and carries `Events.on<RowImported>`'s type argument (`store.get<List<int>>("numbers")` compiles there), and R3c lets the library body read it with `typeof(TEvent)`, which #152 refuses until then.
- PHP it lowers to, through the existing inlining of a one-call standard-library method (decision 040): `$listening = \Sharp\Internal\listen('App\Imports\RowImported', fn ($e) => $this->progress->advance());`, which returns a `Sharp\Listening`.
- PHP's behavior, run: a handle in a local printed `stopped` before `after import`, `stopped on throw` before `caught boom`, and a handle never stored printed `stopped at once` before the next line.
- Mechanism: `sharp/composer/library/Sharp/Events.sharp` (choice 7); engine `Sharp\Internal\listen(string $class, \Closure $listener): Sharp\Listening` and `Sharp\Listening` in `ext/sharp/sharp.stub.php`, `sharp_arginfo.h` (regenerated) and `sharp.c`. The scoped listeners live in the request's globals and end with it.

## `emit` has the effect `Events`

"`emit` has the effect `Events` (section 29), so a method that emits shows `Events` among its effects, and code without a body allows it with `uses Events`."

Partly works. `Function<void() uses Events>` compiles today (`app/Events/Hooks.sharp`). `emit` itself is a parse error, and `uses` on a method declaration is a parse error: `app/Events/Checkout.sharp`, `void complete(Order order) uses Events;` in an interface, printed `Expected one of `LeftBrace`, found `Identifier``. Section 29's `emit-effect` printed

```text
docs/spec.md:2894 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `LeftBrace`, found `Identifier`
docs/spec.md:2899 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `New`
```

- Mechanism: decision 6. A getter, a law or a pure function type that reaches an `emit` is refused through the existing `effects/check.rs`, as one that reaches `file_put_contents` is. `uses Events` on a method declaration is section 29's (cross-slice item 4).

## The default dispatcher, and replacing it

"the default dispatcher runs listeners immediately, before `emit` returns. An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries, or not at all in tests."

Missing: nothing dispatches.

- Immediately: php-sharp PR A's `Sharp\Internal\emit` runs every listener before it returns.
- Replacing: php-sharp PR C, through a module's `Dispatcher` binding (choice 8). Until then no PHP# code can name `Dispatcher`, so nothing compiles that the engine would ignore.

# What a plain PHP caller sees

- **An event is an ordinary PHP class in its own file.** `new \App\Store\OrderPaid(7, $money)` creates one, `$paid->orderId` reads it, a write throws `Error`, and `$paid instanceof \App\Store\OrderEvent` is `true` (run above). Laravel's `Event::listen(\App\Store\OrderPaid::class, …)` names it as it names any class, and the Composer package autoloads it as any class.
- **Plain PHP raises with `\Sharp\Internal\emit($event)`.** It runs the same listeners `emit` runs, before it returns. It throws `TypeError` for a non-object, and runs nothing for an object no listener lists.
- **Plain PHP writes no `on`.** It listens through a dispatcher an adapter binds, after php-sharp PR C.
- **`.sharp/listeners.php` is readable PHP.** An adapter can read it to know which `extern event` classes to forward from Laravel.

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
mago-sharp
├── crates/syntax/src/
│   ├── cst/cst/
│   │   ├── event.rs                     <- new: Event, `event Name(parameters) : Interfaces;`
│   │   ├── emit.rs                      <- new: Emit, `emit <expression>;`
│   │   ├── class_like/method.rs         <- Method gains on: Option<On>; On is the keyword and its listed names
│   │   ├── extern.rs                    <- Extern gains event: Option<Keyword>
│   │   └── statement.rs                 <- Statement gains Event and Emit
│   ├── error.rs                         <- ParseError gains NestedTypeInSharp(owner, name, span)
│   └── parser/mod.rs                    <- State gains members_of, the class-like whose members the parser reads
├── composer/src/Sdk/Syntax/NodeKind.php <- regenerated
├── crates/codex/src/metadata/
│   ├── class_like.rs                    <- an `event` flag
│   ├── function_like.rs                 <- listens: Vec<Word>
│   └── extern.rs                        <- ExternMetadata gains event: bool
├── crates/analyzer/src/
│   ├── statement/class_like/listener.rs <- new: check_listener
│   ├── effects/mod.rs                   <- Effect::Emit; Impurity::describe gains its two arms
│   └── code.rs                          <- IssueCode::InvalidEvent, `invalid-event`
├── crates/orchestrator/src/service/incremental_analysis/compile.rs
│                                        <- IncrementalAnalysisService::listeners
├── src/commands/compile.rs              <- writes .sharp/listeners.php and keeps it in the clean-up
├── crates/sharp-bridge/src/lower.rs     <- Lowering::event; Emit to \Sharp\Internal\emit
└── crates/prelude/assets/extensions/sharp.php
                                         <- Sharp\Listening and Sharp\Internal\listen, for the checker (PR 4);
                                            a Sharp\Internal call outside the standard library is refused

php-sharp
├── ext/sharp/sharp.stub.php, sharp_arginfo.h, sharp.c
│                                        <- function Sharp\Internal\emit(object $event): void (PR A), registered in
│                                           MINIT, since the module's own table is the one sharp/bin/native writes
│                                           function Sharp\Internal\listen(string $class, \Closure $listener): Sharp\Listening (PR B)
│                                           final class Sharp\Listening, no methods, a destructor (PR B)
├── sharp/composer/library/Sharp/
│   ├── Events.sharp                     <- new: static class Events, on<TEvent>(…) (PR B)
│   ├── Dispatcher.sharp                 <- new: interface Dispatcher (PR C)
│   └── ImmediateDispatcher.sharp        <- new: the default binding (PR C)
├── Zend/tests/sharp/OrderEvent.sharp, OrderPaid.sharp, OrderRefunded.sharp, OrderNotices.sharp,
│   Receipts.sharp, Shipping.sharp, Register.sharp, harness/JobFailed.inc,
│   events.inc, events.phpt, events_jit_*.phpt, events_table.phpt
│                                        <- new, the engine's tests, one type per file
└── ext/sharp/sharp_unit.h               <- SHARP_MAGO_COMMIT, the pinned Mago commit, moves

the generated project file
└── .sharp/listeners.php                 <- new: every listener, its listed types and whether it takes the event
```

Not added: no `sharp_kind`, no crate, no `.sharpc` format change, no engine change to the class compiler. `Sharp\Internal\emit` and `listen` are the engine's, and PHP# code outside the standard library calling either is refused: `emit` is `non-existent-function`, since the prelude does not declare it, and `listen` is refused by the standard-library rule of choice 7.

The names, each with the slate it was picked from:

- `Sharp\Internal\emit($event)`: the keyword it lowers from. Also considered: `dispatch` (the dispatcher's own verb, and PSR-14's), `raise` (C#'s word), `fire` (Laravel's old word), `publish` (MediatR's).
- `.sharp/listeners.php`: what it holds. Also considered: `events.php` (Laravel's name, though it lists listeners, not events), `listeners.sharpc` (a `.sharpc` file holds one source's code), `dispatch.php`.
- `Sharp.Listening`: the spec's own local, `const listening = …`. Also considered: `Subscription` (Rx), `Cancellable` (Combine's `AnyCancellable`), `ListenerHandle`, `Listener`.
- `Sharp.Dispatcher` and `Sharp.ImmediateDispatcher`: the spec's word, "the default dispatcher runs listeners immediately". Also considered: `EventDispatcher` (PSR-14 and Symfony), `Publisher` (MediatR's `INotificationPublisher`), `SyncDispatcher`, `DefaultDispatcher` (says nothing about what it does).
- `check_listener`: the spec's word for a method with an `on` clause. Also considered: `check_on_clause` (names syntax, not the rule), `check_event_listener`, `check_handler` (VB.NET's word).
- `invalid-event`: every rule it reports is about an event. Also considered: `invalid-listener` (misses `emit` and the declaration), `invalid-emit`, `invalid-on-clause`, one code per rule (four codes for one feature).
- `Effect::Emit`: the statement that has it. Also considered: `Effect::Events` (names the effect, though `Foreign` names a cause), `Effect::Raise`.

# The PRs, in order

Each PR ends in something a developer runs. mago-sharp PRs 1 to 3 and php-sharp PR A need no generics and land now. PR 4 and PR B wait for generics. PR C waits for section 32.

1. **mago-sharp #162: event types.** `Event` at file level, `extern event` with or without `uses`, get-only interface properties and the checks that a class or an event fits them (cross-slice item 4), language rules 5 and 6, and the bridge's lowering. Jordan's ruling shrank it: no nested event or interface, no dotted type and no `new X.Y` resolution.
   - Run: `compile` compiles `$P/ws-r3`'s `OrderEvent.sharp`, `OrderPaid.sharp`, `Order.sharp`, `Failure.sharp` and `Queue.sharp`, refuses `Nested.sharp`'s two nested types, and refuses a getter that creates a `JobFailed` for the effect `Database`.
2. **mago-sharp: `emit`.** The `Emit` statement, language rule 1, `Effect::Emit`, and its lowering. Follows PR 1. Its scope holds, and its examples name file-level events.
   - Run: `compile` refuses `Raiser.sharp` with "`emit` takes an event, and `Money` is not one.", compiles `emit new OrderPaid(7);`, and refuses a getter that emits with "emits `OrderPaid`, which has the effect `Events`".
3. **mago-sharp: `on` listeners.** The clause, `check_listener` with language rules 2 to 4, the spec's errors and the interim refusal of constructor parameters, `listens`, and `.sharp/listeners.php`. Follows PR 1, and is independent of PR 2. Jordan's ruling shrank it: its names are plain type names, and a wrong one is the existing "Cannot find type" error.
   - Run: `compile` compiles `listeners` and writes its four rows, and refuses each `listener-errors` line with its message.
4. **mago-sharp: `Events.on<T>`.** `Sharp\Listening` in the prelude, and `[NoDiscard]` reported for a dropped handle. Follows #152 and R3c.
   - Run: `compile` compiles `scoped-listener` with its errata, and refuses `Events.on<RowImported>(e => { });` as a statement.

php-sharp:

- **A: the engine runs events.** Pin mago-sharp PRs 1 to 3. `Sharp\Internal\emit`, the table, `new` for listener objects, the engine tests, and `sharp/baseline/spec` losing `standalone-event`, `extern-event`, `extern-event-effects` and `emit-forms`, plus `listeners`, `listener-errors` and `event-declarations` once their spec-files changes and #468's default-public interface members land, through the `cp` command a run prints.
  - Run: `sharp/bin/test Zend/tests/sharp/events.phpt` passes, and `sharp/bin/test --spec` passes with the shorter list.
  - Jordan's ruling shrank it: `sharp/composer/autoload.php` needs no nested-type branch.
- **The spec-files PR** lands the harness's three event files, `Logger` and `RowImported`, and the stub's `extern event JobFailed;`. Jordan's ruling changed its shape: no `emit-forms` template marker, and three files where the approved note nested types in `app/Order.sharp`.
- **B: scoped listeners.** Pin PR 4. `Sharp.Events`, `Sharp\Internal\listen`, `Sharp\Listening`, their tests, and `scoped-listener` leaving the list. Follows php-sharp #102 and R3c.
- **C: the replaceable dispatcher.** `Sharp.Dispatcher`, `Sharp.ImmediateDispatcher`, and listener objects from the container. Follows section 32's container.

Each PR body states the Problem, the Solution and an annotated file tree. The mago-sharp PRs carry no `Completes:` line. `Completes: §15` lands with the last PR that removes a section 15 entry.

# The tests

Each test proves one rule of section 15 and fails on a real bug in it, with its forms as rows. No `.php` twin is written, because no rule here runs code that `.php` also runs. Each help that quotes code has a row that compiles the fix. mago-sharp's `patch-coverage` needs every changed line run, so each refusal arm has a row.

**mago-sharp, `crates/syntax/tests/sharp.rs`:**

- `an_event_declares_its_parameters_and_its_interfaces`: rows for file-level events, one with `: OrderEvent`, one with two interfaces, one with no parameters, and one named with a word PHP reserves.
- `a_type_declared_inside_another_is_one_error_and_its_owner_parses_on`: an event, an interface, a class, an enum and a trait inside a class, and a type inside an interface, an enum, a trait and a module, each naming its owner.
- `emit_raises_the_expression_after_it`: `emit new OrderPaid(…)`, `emit e`, and `emit` as a plain PHP function name in a `.php` file, which stays a call.
- `an_on_clause_lists_events_after_the_return_type`: one event, two events, a block body and an `=>` body.
- `extern_declares_a_class_a_method_or_a_function_and_its_effects` grows rows for `extern event JobFailed;` and `extern event JobFailed uses Database;`.

**mago-sharp, `crates/semantics/tests/sharp.rs`:**

- `an_event_takes_only_public_and_its_parameters_are_its_properties`: `private event`, `static event`, a parameter's modifier, accessors or `...`, and a name PHP reserves are refused.
- `an_extern_event_names_a_class`: `extern event JobFailed;` and `extern event JobQueued uses Database;` are accepted, and `extern event Carbon.now;` is refused.

**mago-sharp, `crates/analyzer/tests/sharp.rs`:**

- `an_event_is_a_final_class_whose_parameters_are_get_only_properties`: a read compiles, a write is refused, `new` with a wrong argument is refused, and a class that extends an event is refused.
- `an_event_header_names_interfaces_and_an_event_is_declared_once`: a header naming a class is refused, and an event that shares its full name with another event, an interface or a class is refused.
- `an_event_or_a_class_gives_every_property_its_interfaces_state`: a missing property, one of the wrong type, a `private` one and a `static` one are refused, and a narrower get-only type compiles.
- `an_extern_event_gives_its_class_the_effects_it_lists`: a getter that creates the class compiles without `uses`, and is refused for `Database` with it.
- `emit_takes_an_event`: a file-level event and an `extern event` compile; `Money` and `OrderEvent` are refused.
- `a_listener_names_events_and_takes_one_parameter_they_share`: the spec's four listeners compile; its errors; the help's `OrderEvent` fix compiles; `static`, a non-`void` return, an abstract class, a generic class, a class that is not an event, an interface no event implements, and a constructor with parameters are each refused; a `private` listener compiles and is not unused.
- `emit_has_the_effect_events`: a getter and a law that emit are refused naming `Events`; a lambda that emits fits `Function<void() uses Events>` and is refused by `Function<void()>`; a getter that reaches an emitting method in another file names the path.

**mago-sharp, `crates/sharp-bridge/tests/lower.rs`:**

- One test per lowering form: an event as a `final` class with `readonly` promoted parameters and an interface with a `get` hook; `emit` as the call; a listener method with no trace of its clause; `extern event` lowering to nothing.

**mago-sharp, `tests/sharp.rs`:**

- `compile_writes_every_listener_in_order`: two files, three listeners, the table's rows in order; a removed listener leaves the table; an empty project writes an empty table.

**php-sharp, on the engine in all four opcache modes,** the fixtures `OrderEvent.sharp` to `Register.sharp`, `events.inc` and `events.phpt` with its two JIT variants:

- `emit` runs every listener before it returns; an interface listener hears every event that implements it; a listener listed twice runs once; listeners run in table order; a listener without a parameter runs; a `private` listener runs.
- An exception from a listener leaves `emit`, and the next listener does not run; an `emit` inside a listener runs its listeners first.
- Plain PHP: `\Sharp\Internal\emit` reaches PHP# listeners, an `extern event` raised from plain PHP reaches them (php-sharp #56's "Done when"), an object no listener lists runs nothing, a non-object is a `TypeError`, and an event's properties are read-only.
- `events_table.phpt`: a missing table, one that is not a list, and three damaged rows each throw the `CompileError`; a listener class or method the table names but the code lacks throws PHP's own error.
- PR B adds: a scoped listener stops when its function returns and when an exception leaves it, and one started during an `emit` hears only the next.

# What the Slice refuses as "not supported yet", and why

- **A listener whose class takes constructor parameters.** Section 32's container creates them, and it does not exist yet.
- **`Events.on<T>` until #152 and R3c,** where it is "Method `Events.on` does not exist." as today, not a new refusal.
- **Replacing the dispatcher until section 32.** `Dispatcher` does not exist until php-sharp PR C, so no program names it.

# Found outside the Slice

- **Laravel's own `event(new JobFailed(…))` does not reach PHP# listeners.** php-sharp #56's "Done when" says an `extern event` raised by plain PHP reaches them. `\Sharp\Internal\emit` does that for plain PHP that calls it. Laravel's dispatcher needs the `php-sharp/laravel` adapter (section 32) to forward each `extern event` class the table lists. That adapter is its own work.
- **A new listener file runs only after the next `mago compile`.** The table lists every listener of the last compile. `sharp.compile_command` runs `mago compile`, which rewrites it, but only when a stale file asks for a compile, and a new listener file makes no other file stale. A `.sharpc` file whose extension read a listing has the same gap today. A deploy runs `mago compile` and is exact.
- **`sharp/agents/architecture.md` says the bridge "never runs the analyzer, because types never change the emitted code."** Typed compilation has landed, and the section 12 note already relies on the checker's types. Its owner updates the sentence.

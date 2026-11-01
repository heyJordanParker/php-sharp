# Changelog

Every PHP# release, newest first. PHP#, its engine and `mago` release together at one version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, a minor release may break 0.x code, so each one opens with what to change when upgrading.

## [Unreleased]

### Fixed

- A file or folder `mago` can't read or write is named in the error with what `mago` was doing, as "Failed to load database: can't open the folder `/app/.sharp`: Operation not permitted (os error 1)" instead of "Failed to load database: I/O error: Operation not permitted (os error 1)". That covers loading the project's files, `mago compile`'s writes into `.sharp`, and the law checker's `.sharp/.lean` package. A `git` that `--staged` can't run is named as "Failed to run `git diff`", and a failed read of the standard input as "Failed to read the standard input", instead of either reading as a failed database load (#238).

## [0.6.1] - 2026-11-01

### Upgrading from 0.6.0

#### Move mago and `heyjordanparker/mago-sharp` to 0.6.1 together

The analyzer protocol is 1.13, so an extension worker built on the 0.6.0 SDK refuses mago 0.6.1's first request, and `mago analyze` stops with this, here for the extension host `acme` in `mago.toml` (#223):

```text
ERROR Orchestrator error: External plugin error: external analyzer protocol error: extension host `acme` refused the handshake of mago 0.6.1, which speaks analyzer protocol 1.13: Unsupported analyzer protocol version 1.13. Its SDK is from a `heyjordanparker/mago-sharp` release before 0.6.1, so require the release of this mago with `composer require --dev "heyjordanparker/mago-sharp:0.6.1"`, or run the mago of the release the project requires with `vendor/bin/mago`.
```

A worker on the 0.6.1 SDK under an older mago refuses its first request too, and names its extension, here `acme/routes`:

```text
ERROR Orchestrator error: External plugin error: external analyzer worker failed: extension worker 0 rejected request 1: The worker of `acme/routes` can't read mago's request: `mago` speaks analyzer protocol 1.12, and the extension SDK, heyjordanparker/mago-sharp 0.6.1, speaks 1.13. `mago` and heyjordanparker/mago-sharp must be the same release: run the `mago` that heyjordanparker/mago-sharp installs, `vendor/bin/mago`, or require the heyjordanparker/mago-sharp release `mago --version` prints.
```

The SDK ships inside `heyjordanparker/mago-sharp`, so a project's extensions run on the SDK of the package the project requires. Upgrade the package and any `mago` installed another way at once:

```sh
composer require --dev "heyjordanparker/mago-sharp:^0.6.1"
```

- `^0.6.1` suits a project that runs `vendor/bin/mago`, which is always the package's own release. A `mago` installed another way needs the exact release, `"heyjordanparker/mago-sharp:0.6.1"`, which is the command mago's error gives.
- A `mago` from `install.sh` updates with `mago self-update --tag 0.6.1`.
- An extension's own code needs no change.

#### Fix the code the 0.6.1 checker refuses

0.6.1 refuses each of these.

An `int` no longer has the `float`-only methods `round`, `floor`, `ceil` and `isFinite`, because no extension whose receiver holds a `float` reaches an `int`, at any depth, as with `List<float>` (#224). Convert with `(float)` first:

```csharp
int count = 3;
count.round(2);              // compile error: Method `round` does not exist on `int`.
((float) count).round(2);    // compiles
```

A call that returns nothing gives no value through `?.`, as it gives none through `.` (#232). `const saved = maybe?.save();` on a `void` method `save` is refused as `const saved = box.save();` is. So is such a call as an argument, an operand or an interpolation, and below a `?.` in a chain, as `holder?.box.save()`. Call it as a statement of its own:

```csharp
const saved = maybe?.save();    // compile error: `maybe?.save()` returns nothing, so it has no value to use: call it as a statement of its own.
maybe?.save();                  // compiles
```

A method, a function, a computed property or a `get` accessor that declares a return other than `void` can't return a call that returns nothing, as `return box.save();` or `=> box.save()` (#232). Neither can a lambda passed as a function type whose result holds no type parameter and isn't `Any?`, as `Function<int()>`, and `int n = Scope.run(() => box.save());` is refused too. Declare the method or the function type `void`:

```csharp
public Any? saved(Box box) => box.save();    // compile error: `box.save()` returns nothing, so it has no value to use: call it as a statement of its own.
public void saved(Box box) => box.save();    // compiles

Function<int()> later = () => box.save();    // compile error: `box.save()` returns nothing, so it has no value to use: call it as a statement of its own.
Function<void()> later = () => box.save();   // compiles
```

A lambda passed where the result is a type parameter or `Any?` still compiles, as `Scope.run(() => job.handle());`.

#### Remove `config-file` from `mago.toml`

mago 0.6.1 refuses a `mago.toml` that holds `config-file` or `config-file-is-explicit`, as it refuses any key it doesn't know (#225). mago 0.6.0 accepted them and then replaced both with the file it loaded, so neither did anything. Delete them, and name another configuration file with `--config`.

### Fixed

- A save just after `mago analyze --watch` starts or restarts on a busy Mac is now analyzed (#221).
- An extension hook that runs past `request-timeout-ms` is now named in the error, as "made no progress for 30000 ms on the after-analysis hooks of plugin `dead-routes`", instead of by a request number (#223). The deadline starts again each time the worker starts one of a request's units: a file's hooks in an after-file batch, one plugin's initialization, before-analysis or after-analysis hooks, a codebase-scan hook, or an extension's worker reducer. So each plugin's project-level hooks now get the whole deadline, as each file's hooks do. The analyzer protocol is 1.13, so a worker built on the 0.6.0 SDK refuses this mago's first request, and mago names the host and the package to require: "extension host `acme` refused the handshake of mago 0.6.1, which speaks analyzer protocol 1.13: Unsupported analyzer protocol version 1.13. Its SDK is from a `heyjordanparker/mago-sharp` release before 0.6.1, so require the release of this mago with `composer require --dev "heyjordanparker/mago-sharp:0.6.1"`, or run the mago of the release the project requires with `vendor/bin/mago`." Any other refusal of that request, such as an extension's own exception, reads as the worker gave it. A worker on the 0.6.1 SDK refuses a request of another analyzer, linter, worker management or frame protocol naming its extension, both protocols and its own release, as "The worker of `acme/routes` can't read mago's request: `mago` speaks analyzer protocol 1.12, and the extension SDK, heyjordanparker/mago-sharp 0.6.1, speaks 1.13. `mago` and heyjordanparker/mago-sharp must be the same release: run the `mago` that heyjordanparker/mago-sharp installs, `vendor/bin/mago`, or require the heyjordanparker/mago-sharp release `mago --version` prints."
- A user's extension on `Iterable<TItem>` reaches a `Map<TKey, TValue>` with `TItem` as `[TKey, TValue]`, and an extension on `List<T>` reaches a value that may be one of several `List`s with `T` fixed by every one of them, as a generic call's parameter takes them. A member that changes that value, as `add`, takes only what every one of those `List`s takes (#224).
- No extension whose receiver holds a `float` reaches an `int`, at any depth, as with `List<float>`, so an `int` no longer has the `float`-only methods `round`, `floor`, `ceil` and `isFinite`. Convert with `(float)` first, as in `((float) count).round(2)` (#224).
- When `mago analyze --watch` restarts on a configuration change, it now applies the edited file as a fresh start does (#225). That covers `php-version`, `threads` and `stack-size`, which now set the analysis's thread pool, `[source] workspace`, `allow-unsupported-php-version` and `no-version-check`. A flag passed on the command line still wins. An edit a fresh start refuses, such as `php-version = "7.4"`, keeps the previous configuration. Before, a restart kept the previous run's values. When `[source] workspace` named another folder, a restart without `--config` searched that folder for its configuration file instead of the current directory, and fell back to the defaults when it found none.
- A save whose event macOS or Linux drops under a flood of changes is now analyzed by `mago analyze --watch`, which reads the watched files again when the OS says it dropped events, and restarts when they could hide a change to its configuration, baseline or Composer files (#228).
- A property read and a method call through `?.` keep each part of their type beside the `null`, as a plain PHP property or method that may give a `List<int>` or a `List<string>` keeps both, so `maybe?.rows.clear()` writes back the property's own type, and `maybe?.rows.add(1)` is refused as `box.rows.add(1)` is, where it took any element and refused the write-back (#230).
- A method a collection lacks is an error whose help lists only the methods the collection can call. A `List` whose elements have no `==`, as a `List<TItem>` of `TItem : Object` or a `List<Cart>` of a class without `operator ==`, no longer lists `contains`, `indexOf` and `remove`, and a `Set<TItem>` of `TItem : Object` no longer lists `add`, `contains` and `remove`. In a project without the standard library, the help names the library to install, where it read "has the methods .", after any extension the project declares itself. The help of a collection, a `string` and a number lists `remove` before `removeLast` and `trim` before `trimEnd` (#229).
- A type parameter bound PHP# refuses, such as `TPart : TItem & Shape`, `T : int` or `<T : TOther, TOther : T>`, gives only the error that refuses it. A call, a body and an unused type parameter check no longer read it, as "`TPart` is bounded by `never`" did (#231).
- `const saved = maybe?.save();` on a `void` method `save` is now refused as `const saved = box.save();` is: a call that returns nothing gives no value through `?.` either, as an argument, an operand, an interpolation, or below a `?.` in a chain, as `holder?.box.save()`. A `?.` call stays a value where a part of its receiver runs a method that returns one. A method, a computed property or a `get` accessor that returns a value now refuses to return such a call too, as `return box.save();` in a method that returns `Any?`, and so does a lambda for a function type whose result holds no type parameter and isn't `Any?`, as `Function<int()>`. `Scope.run(() => box.save());` still compiles. Each such use is refused once, so `maybe?.save() ?? 1` no longer also says its left side is always `null` (#232).

## [0.6.0] - 2026-10-31

### Upgrading from 0.5

#### Move the engine, mago and the standard library to 0.6.0 together

The 0.6.0 engine refuses every `.sharpc` file an older mago wrote, because `SHARP_UNIT_ABI` changed (#201, #218):

```text
Shop.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
```

mago 0.6.0 also needs the 0.6.0 standard library, because every `List`, `Map`, `Set` and `Iterable` member is now one of its extensions. Against the 0.5 library, mago 0.6.0 refuses every `List`, `Map`, `Set` and `Iterable` member call, as "Method `map` does not exist on `List<int>`.", every `Map` index write, as "Method `set`, which `map[key] = value` runs, does not exist on `Map<string, int>`.", and the 0.5 library's own files.

Upgrade all three, then compile again:

```sh
composer require "heyjordanparker/php-sharp-composer:^0.6.0"
composer require --dev "heyjordanparker/mago-sharp:^0.6.0"
vendor/bin/mago compile
```

- The runtime image is `ghcr.io/heyjordanparker/php-sharp:0.6.0`, or `ghcr.io/heyjordanparker/php-sharp:0.6.0-frankenphp`.
- A `mago` from `install.sh` updates with `mago self-update --tag 0.6.0`.
- A `version = "0.5"` or `version = "0.5.0"` pin in `mago.toml` warns on mago 0.6.0, and fails under `version-drift-fail-level = "minor"`. Change it to `version = "0.6"` before you run `mago self-update --to-project-version`, which installs the newest release the pin allows.

#### Rename `\Sharp\Sequence` in plain PHP

The lazy result of an `Iterable`'s `filter`, `map` and `take` is now `\Sharp\Internal\Sequence` (#218). Plain PHP that names `\Sharp\Sequence` in a type, an `instanceof` or a `::class` names `\Sharp\Internal\Sequence` instead. `\Sharp\Collection` is gone, so plain PHP that names it names nothing.

#### Write values of a PHP# collection's type from plain PHP

On 0.5.0, a wrong element plain PHP appended to a PHP# `List` property went unchecked. On 0.6.0, PHP# checks every write plain PHP makes into a PHP# `List`, `Map` or `Set` property (#201):

- A wrong element in a `List` throws when PHP# code next reads the property. After `$order->items[] = "x"` on an `App.Order` whose `List<int> items` holds `[1, 2]`, the read throws `TypeError: Reference held by property App\Order::$items of type List<int> holds array: [2] expected int, got string 'x'`.
- A wrong value or key in a `Map` throws at the write: `TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x'`, and for a key no case backs, `Cannot use string 'zzz' as a key of Map<App.Level, int>: expected App.Level`.
- A wrong value written into a collection a `Map` holds, as `$box->shelves['k'][] = 5` on a `Map<string, List<string>> shelves`, throws at every PHP# read of the `Map`'s entries, and at `json_encode` and `serialize` of it: `TypeError: Map<string, List<string>> holds array under the key string 'k': [1] expected string, got int 5`.

Each message names the property or the collection, the index or the key, and the value. Write a value of the type there, as `$order->items[] = 3`.

#### Fix the code the 0.6.0 checker refuses

Each of these compiled on 0.5.0 and is a compile error on 0.6.0.

`==` on a value of a type parameter needs the bound `Equatable` (#211). The same holds for a `Map` key, a `Set` element, a value pattern, and `contains`, `indexOf` and `remove` on a `List<T>`. Bound the parameter, and give a class you pass for it an `operator ==`:

```csharp
public static bool same<T>(T a, T b) => a == b;              // compile error: `T` has no `==`: bound it with `Equatable`, as in `<T : Equatable>`.
public static bool same<T : Equatable>(T a, T b) => a == b;  // compiles
```

`==`, `!=` and `contains` on a struct need `==` on every field, an accessor-less field included (#207). On 0.5.0 such a field compared by object identity. Make the field a type that has `==`, or give its class an `operator ==`. A struct can also keep a value that has `==`, and build the object from it when it needs it:

```csharp
public struct Zone
{
    private Calc calc;                                    // Lib.Calc, a plain PHP class with no operator ==

    public Zone(public string name { get; })
    {
        this.calc = new Calc();
    }

    public static bool same(Zone a, Zone b) => a == b;    // compile error: `Zone` can't compare by value: `calc` is a `Calc`, which declares no `operator ==`.
}
```

```csharp
public struct Zone
{
    public Zone(public string name { get; }) { }

    public Calc calc() => new Calc();

    public static bool same(Zone a, Zone b) => a == b;    // compiles
}
```

A struct whose property is an `Iterable` has no `==`, so it keys no `Map` and joins no `Set` (#207): "`Streamed` can't key a `Map`: `items` is an `Iterable<int>`, which `==` does not compare." Hold a `List` instead, as `public Streamed(public List<int> items { get; }) { }`.

A value pattern compares as the code's own `==` does (#207). On a union with a class that declares no `operator ==`, test the type first: `extra is 200` on a `Calc|int` is refused with "`==` cannot compare `int|Calc` with `int`: `Calc` declares no `operator ==`.", and `extra is int and 200` compiles. A function has no `==`, so a constant list of functions in an `is` or a `match` arm is refused: "`==` compares `List<Function<int(int)>>` with `List<Function<int(int)>>` element by element, and `Function<int(int)>` declares no `operator ==`." Match a value that has `==` instead, such as an enum case kept beside each function.

A local or parameter that any lambda assigns is never narrowed by an `if`, a `while` or an early return (#200). Bind the tested value to a name:

```csharp
namespace App;

public class Narrowing
{
    public static int size(string? given)
    {
        string? s = given;
        const clear = () => { s = null; };
        clear();
        if (s != null) {
            return s.length();    // compile error: `s` is not narrowed to `string` here: the lambda on line 8 assigns it, so it may change before this read.
        }

        return 0;
    }
}
```

```csharp
namespace App;

public class Narrowing
{
    public static int size(string? given)
    {
        string? s = given;
        const clear = () => { s = null; };
        clear();
        if (s is string t) {
            return t.length();    // compiles
        }

        return 0;
    }
}
```

A class that inherits from itself, as `class Cycle : Loop` and `class Loop : Cycle`, is refused (#200): "`Cycle` inherits from itself: `Cycle` → `Loop` → `Cycle`." PHP refused it when it loaded the classes. Remove one of the two parents.

`Date` and `Instant` no longer have `isBefore` and `isAfter` (#206). Write `due < today` and `due > today`.

PHP# passes an `int` to a plain PHP type parameter bounded by `float`, as `@template T of float`, only as a `float` (#217): "`int` does not satisfy the bound `float` of type parameter `T`." Convert it, as `Sink.keep((float) count)`.

#### Expect these runtime changes

`new TimeZone(name)` takes only a name of the time zone database, in its exact case (#206). `"+03:00"`, `"EEST"` and `"europe/sofia"` throw `ValueError`, and `TimeZone.tryOf` gives null for them, where 0.5.0 gave a zone.

A `TimeZone` that 0.5.0 serialized, alone or inside a `DateTime`, still unserializes, but PHP reports "Creation of dynamic property Sharp\Time\TimeZone::$rules is deprecated", and plain PHP `==` against a new zone is false (#206). Clear every cache, queue and session that holds one when you upgrade.

`Map` and `Set` keys follow C#'s `Dictionary`: an `int` and a `float` key compare exactly, and every NaN is one key (#201).

Plain PHP's `clone` of an object whose PHP# typed property holds a reference something else holds shares that reference, as stock PHP does, so a write through the clone shows in the original (#201).

#### Extension authors: require `heyjordanparker/mago-sharp` `^0.6`

The analyzer protocol is 1.12, so a worker built on the 0.5 SDK refuses mago 0.6.0's first request: "Unsupported analyzer protocol version 1.12." (#212). Require `"heyjordanparker/mago-sharp": "^0.6"` in the extension's `composer.json`.

`mago compile` runs node hooks only in the PHP# files it lowers (#212). `mago analyze` still runs them in every file.

### Added

- A `List` has `removeAt`, `removeFirst` and `removeLast`, and a `List`, a `Map` and a `Set` have `isEmpty` and `isNotEmpty` (#208, #214).
- The `Equatable` bound, as in `<T : Equatable>` (#211).
- `Date`, `Instant`, `Duration` and `DateTime` order with `<`, `<=`, `>` and `>=` (#206).
- `new Clock.fixed(at)`, a clock for tests whose `now()` is always `at` (#206).
- `toInstant()` and `toDateTime()` on any plain PHP `DateTimeInterface`, after `import Sharp.Time.DateTimeInterfaceMembers;`, and `toPhpDateTime()` on `Instant` and `DateTime` (#206).
- `compareTo` on `string`, in the byte order `<` uses (#206).

### Changed

- Every `List`, `Map`, `Set` and `Iterable` member runs from the standard library, and `map[key] = value` runs `MapMembers.set` (#208, #214, #218).
- A one-file edit compiles and analyzes only what it touches (#209).
- Plain PHP's object readers, such as `(array)`, `get_object_vars()` and `foreach`, skip every class with no `Map` or `Set` property (#210).

### Fixed

- The checker refuses programs that failed at runtime, and every error names what the code wrote (#200).
- The engine refuses a malformed `.sharpc` file (#201).
- Two `TimeZone`s of one name, and two equal `DateTime`s, are `==` (#206).
- A plain PHP function's type parameters come from the array a `Map` or a `Set` crosses into, and a typed or `let` local of one `Map` crosses into a plain PHP reference parameter and back (#217).
- A warm `mago compile` and `mago analyze` see every listing an extension rule cached (#212).
- An extension worker runs one hook at a time (#199).
- The analysis server no longer leaks memory on files that fail to parse (#205).

### Security

- `install.sh`, `mago self-update` and the `heyjordanparker/mago-sharp` Composer wrapper verify every `mago` download, and the runtime images pin their bases by digest and are scanned for CVEs (#203). Releases 0.5.0 and earlier publish no sums file, so `install.sh` and `mago self-update` install them only with the GitHub CLI, `gh`, which verifies their attestation.
- The FrankenPHP image builds FrankenPHP v1.13.4, which fixes CVE-2026-116587 (#215).

## [0.5.0] - 2026-10-23

Release pull request: [#198](https://github.com/heyJordanParker/php-sharp/pull/198).

## [0.4.2] - 2026-10-21

Release pull request: [#190](https://github.com/heyJordanParker/php-sharp/pull/190).

## [0.4.1] - 2026-10-21

Release pull request: [#187](https://github.com/heyJordanParker/php-sharp/pull/187).

## [0.4.0] - 2026-10-20

Release pull request: [#180](https://github.com/heyJordanParker/php-sharp/pull/180).

## [0.3.1] - 2026-10-11

Release pull request: [#89](https://github.com/heyJordanParker/php-sharp/pull/89).

## [0.3.0] - 2026-10-09

Release pull request: [#81](https://github.com/heyJordanParker/php-sharp/pull/81).

## [0.2.0] - 2026-10-09

Tagged on the merge of [#52](https://github.com/heyJordanParker/php-sharp/pull/52), with no release pull request.

## [0.1.0] - 2026-10-08

Tagged on the merge of [#47](https://github.com/heyJordanParker/php-sharp/pull/47), with no release pull request.

[Unreleased]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.1...HEAD
[0.6.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/heyJordanParker/php-sharp/releases/tag/v0.1.0

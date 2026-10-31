# Changelog

Every PHP# release, newest first. PHP#, its engine and `mago` release together at one version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, a minor release may break 0.x code, so each one opens with what to change when upgrading.

## [Unreleased]

### Fixed

- An extension hook that runs past `request-timeout-ms` is now named in the error, as "made no progress for 30000 ms on the after-analysis hooks of plugin `dead-routes`", instead of by a request number. Each plugin's project-level hooks now get the whole deadline, as each file's hooks do (#222).

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

The analyzer protocol is 1.12, so mago 0.6.0 refuses a worker built on the 0.5 SDK at its first message: "unsupported analyzer protocol version 1.11" (#212). Require `"heyjordanparker/mago-sharp": "^0.6"` in the extension's `composer.json`.

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

[Unreleased]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/heyJordanParker/php-sharp/releases/tag/v0.1.0

# Changelog

Every PHP# release, newest first. PHP#, its engine and `mago` release together at one version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, a minor release may break 0.x code, so each one opens with what to change when upgrading.

## [0.6.0] - 2026-10-31

### Upgrading from 0.5

#### Move the engine, mago and the standard library to 0.6.0 together

The 0.6.0 engine refuses every `.sharpc` file an older mago wrote, because `SHARP_UNIT_ABI` changed (#201, #218):

```text
Shop.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
```

mago 0.6.0 also needs the 0.6.0 standard library, because every `Map` and `Set` member is now one of its extensions. The 0.5 library has none of them, so mago 0.6.0 refuses every `Map` index write against it with "Method `set`, which `map[key] = value` runs, does not exist on `Map<…>`."

Upgrade all three, then compile again:

```sh
composer require "heyjordanparker/php-sharp-composer:^0.6.0"
composer require --dev "heyjordanparker/mago-sharp:^0.6.0"
vendor/bin/mago compile
```

- The runtime image is `ghcr.io/heyjordanparker/php-sharp:0.6.0`, or `ghcr.io/heyjordanparker/php-sharp:0.6.0-frankenphp`.
- A `mago` from `install.sh` updates with `mago self-update --tag 0.6.0`.
- A `version = "0.5"` pin in `mago.toml` warns on 0.6.0. Pin `version = "0.6"`.

#### Rename `\Sharp\Sequence` in plain PHP

The lazy result of an `Iterable`'s `filter`, `map` and `take` is now `\Sharp\Internal\Sequence` (#218). Plain PHP that names `\Sharp\Sequence` in a type, an `instanceof` or a `::class` names `\Sharp\Internal\Sequence` instead. `\Sharp\Collection` is gone, so plain PHP that names it names nothing.

#### Fix the code the 0.6.0 checker refuses

Each of these compiled on 0.5.0 and is a compile error on 0.6.0.

`==` on a value of a type parameter needs the bound `Equatable` (#211). The same holds for a `Map` key, a `Set` element, a value pattern, and `contains`, `indexOf` and `remove` on a `List<T>`. Bound the parameter, and give a class you pass for it an `operator ==`:

```csharp
public static bool same<T>(T a, T b) => a == b;              // error: `T` has no `==`: bound it with `Equatable`, as in `<T : Equatable>`
public static bool same<T : Equatable>(T a, T b) => a == b;  // compiles
```

`==`, `!=` and `contains` on a struct need `==` on every field, an accessor-less field included (#207). On 0.5.0 such a field compared by object identity. The error names the field: "`Zone` can't compare by value: `rules` is a `DateTimeZone`, which declares no `operator ==`."

A local or parameter that any lambda assigns is never narrowed by an `if`, a `while` or an early return (#200). Bind the tested value to a name instead, as the error says: "`s` is not narrowed to `string` here: the lambda on line 24 assigns it", with the help "as in `s is string t`".

`Date` and `Instant` no longer have `isBefore` and `isAfter` (#206). Write `a < b` and `a > b`.

An extension of `Map<int, T>` no longer applies to a `List<T>` (#214). Declare the extension on `List<T>` too.

A type that inherits from itself, and a plain PHP trait or a class in an interface's header, are refused (#200). Each already failed when PHP loaded it.

A by-reference `array` parameter of a plain PHP function refuses a value that may be one of several collections, such as a `Map<string, int>|Set<string>`, and in PHP# an `int` no longer meets a `float` bound of a plain PHP type parameter (#217).

#### Expect these runtime changes

`new TimeZone(name)` takes only a name of the time zone database, in its exact case (#206). `"+03:00"`, `"EEST"` and `"europe/sofia"` throw `ValueError`. `TimeZone.tryOf` gives null for them instead.

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
- The engine refuses a malformed `.sharpc` file, and PHP# checks every write plain PHP makes into a PHP# collection property (#201).
- Two `TimeZone`s of one name, and two equal `DateTime`s, are `==` (#206).
- A plain PHP function's type parameters come from the array a `Map` or a `Set` crosses into (#217).
- A warm `mago compile` and `mago analyze` see every listing an extension rule cached (#212).
- An extension worker runs one hook at a time (#199).
- The analysis server no longer leaks memory on files that fail to parse (#205).

### Security

- `install.sh`, `mago self-update` and the `heyjordanparker/mago-sharp` Composer wrapper verify every `mago` download, and the runtime images pin their bases by digest and are scanned for CVEs (#203).
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

[0.6.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/heyJordanParker/php-sharp/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/heyJordanParker/php-sharp/releases/tag/v0.3.0

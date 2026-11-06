# Changelog

Every PHP# release, newest first. PHP#, its engine and `mago` release together at one version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, a minor release may break 0.x code, so each one opens with what to change when upgrading.

## [Unreleased]

### Changed

- `mago compile` proves the laws of every `.sharp` file it compiles in one Lean process, where it built the Lean code it generates with Lake and then ran one Lean process per file at once, each importing Lean, the runtime library and that code. Lean and the runtime library now load once per compile, and the generated code is elaborated once, in that process, so a compile of many files with laws uses far less processor time and memory. Mago no longer writes the generated code to `.sharp/.lean/`, and deletes the `Code` folder and the Lake build an earlier `mago` left there. The processor-time limit and the deadline still hold for each file's check.
- `mago compile` builds the law checker's Lean library in Mago's cache folder once for each version of the library's Lean sources and of Lean, where it built one for each `mago` build. A `mago` built from other Rust code with the same Lean sources, as each edit to Mago makes, uses the library that is there, where it built a new one and deleted the other.
- On macOS and Linux, each extension worker runs in a process group of its own, as each Lean the law checker runs already did. A worker that fails, or that runs past `shutdown-timeout-ms`, now ends with every process it started on the host, such as the `docker compose` process of a `docker compose exec` wrapper, where that process kept running. A process inside a container is Docker's to end. Ctrl-C, or a signal that ends `mago`, ends each worker with SIGKILL, where the worker got the signal itself. A SIGKILL sent to `mago`'s process group no longer reaches the workers, since no handler runs on SIGKILL: a healthy worker exits once its stdin closes, and a stuck one keeps running. A worker that reads from the terminal, as an interactive debugger prompt does, is stopped by SIGTTIN, since its group is not the terminal's.
- A law whose proof reaches another file's generated Lean code, where Lean rejects that code or it runs past the checker's limit or deadline, is refused with that code's refusal, where the whole compile failed with "the law checker failed with exit status: 1". The file's other laws are still checked.

### Fixed

- A law whose code names something `sharp_prove`, `sharp_close` or `sharp_condition` is checked. Lean read the name as one of the law checker's proof steps, so the law was refused as a bug in the checker.
- An extension host whose worker dies, as one does at startup when `composer install --no-dev` left out a require-dev package it loads, now stops `mago` with the host's name and what the worker wrote to stderr: "extension host `reports` disconnected (worker 0): worker closed stdout; stderr: PHP Fatal error:  Uncaught Error: Class "Acme\DevTools\ReportPlugin" not found in …". Before, the error was "extension worker 0 disconnected: worker is not running", which named neither. Mago restarts a dead worker before it sends the first request, so the request reached a worker that had died again, and that error dropped the stderr Mago had kept. A worker that dies while Mago writes it a request now fails the same way, where the error was "violated the protocol: … Broken pipe".
- An extension host whose worker stops reading its standard input, as one deadlocked in an extension or held at a debugger breakpoint does, now fails the request at the host's `request-timeout-ms`, as a worker that stops answering does: "extension host `reports` made no progress for 30000 ms (worker 0): raise its `request-timeout-ms` for hooks that run that long". Before, once a request no longer fit in the pipe Mago writes to, which holds 64 KiB on macOS and Linux, Mago waited on the write forever, so `mago analyze` in a CI job or a watch session froze with no message. The worker's other requests now fail with that deadline too, as "extension host `reports` disconnected (worker 0): made no progress for 30000 ms on the hooks of app/User.php", where they said only "request 2 timed out".
- `mago` no longer ends with exit code 141 and no message when an extension worker, the law checker or `git` ends while Mago writes to it. The write ended `mago` with SIGPIPE, so a stuck worker that Mago ended at its deadline, or a worker that died while Mago wrote it a request, gave no error. Each now gives its error.
- `mago convert` holds one proof's analysis and extension hosts at a time. A proof that split its files kept its whole analysis and its hosts' workers running while it proved each part, so a split k levels deep held k analyses and k workers per worker a host has. On a real Laravel app, runs were killed for memory at 7 to 8 GiB, with workers from the first proof still alive hours in. On a project of five clean interfaces and three that split, peak memory falls from 241 MiB to 137 MiB.
- A `mago convert` proof that can't finish, as when an extension host's hook makes no progress within its `request-timeout-ms`, logs its error once, "Proof 4 could not finish, so the files it proves stay plain PHP: … extension host `dent` made no progress for 120000 ms on the after-analysis hooks of plugin `routes` …", and keeps each of those files plain PHP with "src/Shop/Order.php: proof 4 could not finish" as its reason. The run goes on and reports every other file. Before, the run stopped with exit code 2 and reported nothing it had found.
- Once `mago convert` has written its files, a write that fails, a compile of the project for the extension hosts that fails, or a proof by fresh hosts that can't finish now logs its error, says git holds the plain PHP, and ends the run with exit code 1, where each stopped it with exit code 2 and no report. The run names each file it wrote, as `src/Shop/Order.php -> src/Shop/Order.sharp`, where only a dry run named them.
- `mago compile` keeps the `.php` files it writes beside `.sharp` files out of git with a `.gitignore` of its own in each folder that holds them, which lists itself and each file. It listed them in `.git/info/exclude`, which every worktree of a repository shares, so a compile in one worktree hid the same paths in every other: an untracked `app/Tenant/Publishable.php` someone wrote in another worktree left `git status`, and a branch switch could overwrite it without a warning. A folder whose `.gitignore` the compile didn't write keeps it as it is, and the compile warns: "app/Tenant/.gitignore doesn't list /Order.php, so git may show app/Tenant/Order.php as untracked: add /Order.php to it." A compile also removes the blocks an earlier `mago compile` wrote in `.git/info/exclude`, and keeps every other byte. Run this `mago` in every checkout of a repository: a compile by an older `mago` in any worktree writes its block again, which hides its paths in all of them.

## [0.6.3] - 2026-11-05

### Upgrading from 0.6.2

#### Name the test bootstrap in `phpunit.xml`

A test runner includes each `.sharp` test file it lists itself, past the plugin's loader, so on plain PHP it printed the file's whole source as output (#259). Pest then reported "No tests found" and exited 0, so a CI job on plain PHP passed and published the source. 0.6.3's `heyjordanparker/php-sharp-composer` ships a test bootstrap that stops such a run before any test file loads. Upgrade the package:

```sh
composer require "heyjordanparker/php-sharp-composer:^0.6.3"
```

Then name its bootstrap in `phpunit.xml` in place of `vendor/autoload.php`:

```xml
<phpunit bootstrap="vendor/heyjordanparker/php-sharp-composer/require-engine.php">
```

- The bootstrap loads `vendor/autoload.php` when the runner hasn't, so it needs nothing beside it.
- PHPUnit, Pest and ParaTest load it before any test file, in each worker too. On plain PHP every run stops with "This project needs the PHP# engine, and this is plain PHP." and exits with an error, where Pest exited 0.
- On the engine it changes nothing.

#### Build the app's image on the 0.6.3 images

The 0.6.2 images hold apt 3.0.3, which CVE-2026-109518 (HIGH) affects (#256). The 0.6.3 images hold apt 3.0.4, Debian's fix. Change the app's `FROM` line to `ghcr.io/heyjordanparker/php-sharp:0.6.3`, or `ghcr.io/heyjordanparker/php-sharp:0.6.3-frankenphp`, and build it again.

### Added

#### `mago convert`, plain PHP class files into PHP#

`mago convert <paths>` turns each plain PHP class file the paths name into PHP#, in place, so `src/Shop/Order.php` becomes `src/Shop/Order.sharp` (#251). It converts a file whole, or keeps it plain PHP and names each construct that keeps it so, with its place and reason. Run it one folder at a time, with `--dry-run` first, which prints each conversion as a diff and changes nothing:

```sh
mago convert src/Shop --dry-run
mago convert src/Shop
```

It takes a file under a PSR-4 folder of `composer.json`'s `autoload` that declares one class, interface or enum, named like the file. A script, a test under `autoload-dev`, a file of two types and one whose type is named unlike the file stay plain PHP. It never writes a type the analysis inferred: each type comes from a declaration, a PHPDoc tag or an extension host's refinement.

Before it writes a file, it analyzes the converted files with the whole project, as `mago analyze` does, and compiles them in memory, as `mago compile` does. A file stays plain PHP when it has any error or warning, when another file gains an error or a warning the summary doesn't name as expected, or when `mago compile` refuses a file it accepted before. The command exits with status 1 when any file the paths name stays plain PHP.

It refuses each of these, with its reason as `file:line:column: reason`:

- what PHP# removes, as `global`, references, `echo`, `goto` and `compact`, and a trait declaration
- a type PHP# can't write, as the `false` of `int|false`, `array-key`, a bare `array` and an array shape
- a method's return the analysis takes from its body
- a built-in call no standard library member runs with PHP's meaning, as "`trim` has no PHP# form: no standard library member runs `trim` with PHP's meaning (decision 083)"
- a construct whose meaning PHP# would change, as a `switch` case that falls through, a capture by value of a variable written again, `self::m()` outside a `final` class or an enum, and `__LINE__`

`docs/architecture/slices/convert.md` lists every construct it converts and refuses, by spec section.

A file converts only as git's last commit holds it. A file that is untracked, or changed since that commit, staged or not, stays plain PHP: "git holds no commit of it as it is: commit it first, so git holds its plain PHP". For each file it converts, the index records the rename alone, as `git mv` does, and the converted text is an unstaged change on top, as `RM src/Shop/Order.php -> src/Shop/Order.sharp` in `git status --short`. So `git commit` records the rename alone, which `git log --follow` follows, and the conversion is the next commit.

After converting:

- `mago lint`, `mago guard` and `mago fmt` read only plain PHP, so their rules no longer check the converted files.
- A PHPDoc type is now checked while the code runs, so run the project's tests on the PHP# engine and compare them test by test.

#### Guides

- `sharp/README.md`'s "Running tests" says what a test run of PHP# classes needs: `mago compile` first, how PHPUnit lists `Test.sharp` files, how Pest runs PHP# classes and test classes, and what a shared `opcache.file_cache` serves when `opcache.validate_timestamps=0` (#253).
- `sharp/README.md`'s "Runtime images" says the engine ships only as the php-fpm and FrankenPHP images, and that `php --ri sharp` shows whether a `php` is the engine (#253). It now runs a production app on the php-fpm image: the extensions the image ships, the app's own image and pool file, php-fpm, the queue worker and the scheduler as the deploy user, and a deploy that runs `mago compile` and reloads php-fpm (#258).
- `sharp/README.md`'s "Linux dependencies" lists the packages a source build installs on Debian trixie and Ubuntu 24.04 (#253, #255).
- Mago's installation guide says a project with `.sharp` files requires `heyjordanparker/mago-sharp` without `--dev`, since its deploy runs `mago compile` and `composer install --no-dev` installs no `require-dev` package (#258).

### Fixed

- A test run on plain PHP stops with "This project needs the PHP# engine" before the runner includes a `.sharp` test file, once `phpunit.xml` names `heyjordanparker/php-sharp-composer`'s test bootstrap, `<phpunit bootstrap="vendor/heyjordanparker/php-sharp-composer/require-engine.php">`, in place of `vendor/autoload.php`. Before, PHPUnit, Pest and ParaTest included a listed `.sharp` test file themselves, so plain PHP printed its whole source as output, and Pest then reported "No tests found" and exited 0.
- Brackets before a modifier and `(` are a parameter's attributes, so `[Field(label: "Value")] public (int|string)? value { get; set; }` in a constructor parses, as brackets before the class's own name do (#251). Before, the parser read them as a tuple type.
- `mago compile` compiles a file that creates a generic class whose bound the checker refuses, as `new Box<int>(1)` of `Box<T : Any?>`, where it panicked. The class's own file stays refused with its own error (#251).
- The engine builds from source with `--enable-werror` on Ubuntu 24.04, whose gcc 13 stopped `make` with "'heredoc_len' may be used uninitialized" in `ext/readline/readline_cli.c` (#255). The interactive shell behaves as before.

### Security

- The php-fpm and FrankenPHP images upgrade apt and libapt-pkg7.0 to 3.0.4, Debian's fix for CVE-2026-109518 (HIGH), which their pinned `debian:trixie-slim` base still holds at 3.0.3 (#256).

## [0.6.2] - 2026-11-03

### Upgrading from 0.6.1

#### Fix the code the 0.6.2 checker refuses

0.6.2 refuses each of these.

A spread of a list whose length is unknown fills only a `...` parameter, as in Kotlin and TypeScript, into a PHP# method and into plain PHP alike (#227). Into any other parameter, a parameter with a default included, it is refused with the parameters it may not fill. 0.6.1 compiled such a spread into a parameter with a default, and refused one before a required parameter as "Too few arguments". Spread a list literal, whose length is known, start the spread at the `...` parameter, or pass each value as an argument of its own:

```csharp
public static List<T> pair<T>(T a, T b) => [a, b];
public static int tagged(int count, string ...tags) { … }
public static void logSlow(string message, Position caller = Position.current()) { }

Pairs.pair(...numbers);               // compile error: `numbers` may not fill `a` and `b`. A spread of unknown length fills only a ... parameter.
Pairs.pair(...[900, 2900]);           // compiles: a list literal's length is known
Pairs.logSlow("slow", ...positions);  // compile error: `positions` may not fill `caller`. A spread of unknown length fills only a ... parameter.
Pairs.tagged(...tags);                // compile error: `tags` may not fill `count`. A spread of unknown length fills only a ... parameter.
Pairs.tagged(1, ...tags);             // compiles
```

A search given its value by name or by a spread, as `carts.contains(value: cart)` or `carts.contains(...[cart])`, needs `==` on the element type, as `carts.contains(cart)` does (#233). That covers a `List`'s `contains`, `indexOf` and `remove`, and a `Set`'s and a `Map`'s searches given `value:` or `key:`. Declare `operator ==` and `hash()` in the class, or test for the same object with `===`:

```csharp
carts.contains(value: cart);    // compile error: `contains` compares elements with `==`, and `Cart` declares no `operator ==`.
carts.any(c => c === cart);     // compiles
```

An extension of `Iterable<T>` whose parameter is marked `ComparedByEquality` needs `==` on what the `Iterable` gives a loop, as a `List`'s `contains` does (#233). So its call is refused on an `Iterable<Cart>` of a class without `operator ==`, on a `Map<string, Cart>`, whose `[string, Cart]` entries it compares, and on a `Set<Money|Ticket>` whose `Money` has an `operator ==` that can't take a `Ticket`. Declare `operator ==` on what it compares, or test for the same object, as with `rows.toList().any(c => c === cart)`.

A `match` without `default` on a call's result names the enum cases it misses, as a `match` on a parameter does (#247). 0.6.1 compiled it. Add an arm for each case it misses, or add `default => …` as the last arm:

```csharp
public string called() => match (this.stage()) {    // compile error: This `match` misses `Stage.Paid` and `Stage.Closed`.
    Stage.Open => "open",
};
```

A type parameter bounded by a class takes one class as its type argument, in a type, in a call's or a `new`'s type arguments, and in a class header (#247). 0.6.1 compiled a union there, and `TItem.make()` then threw `Error: Class "App.Fancy|App.Plain" not found` while the code ran. Write the one class the error names. A type parameter bounded by an interface, or with no bound, still takes a union:

```csharp
public class Builder<TItem : Maker> { public string build() => TItem.make(); }

new Builder<Fancy|Plain>().build();    // compile error: `TItem`'s bound is a class, so its type argument is one class. Write `Builder<Maker>`.
new Builder<Fancy>().build();          // compiles
```

`new TItem()` and `new (typeof(TItem))()`, where `TItem`'s bound is an interface, are refused, where 0.6.1 gave a warning (#247). Bound `TItem` by a class that implements the interface:

```csharp
public class Tagged<TItem : Labeled> { public TItem create() => new TItem(); }    // compile error: `TItem`'s bound `Labeled` is an interface, so `new` of it creates no object. Bound `TItem` by a class.
```

In plain PHP, a call through `?->` to a method only `__call` runs may give `null`, as one to a declared method does (#237). So `return $bag?->untagged();` in a function declared to return `int` is refused as `nullable-return-statement`. Handle the `null` first, or declare the return type nullable.

### Changed

- A spread of a list of unknown length fills only a `...` parameter, as in Kotlin and TypeScript, into a PHP# method and into plain PHP alike. Anywhere else, a parameter with a default included, it is refused with the parameters it may not fill, as "`numbers` may not fill `a` and `b`. A spread of unknown length fills only a ... parameter.", where it compiled into a parameter with a default and was "Too few arguments" before a required one. Spread a list literal there, whose length is known, or pass each value as an argument of its own (#227).
- The x86_64 macOS and musl `mago` binaries are built without profile-guided optimization (#241).

### Removed

- The release no longer publishes `mago-<version>-wasm.tar.gz`. 0.6.1 was the last release with it (#241).

### Fixed

- A search given its value by name or by a spread, as `carts.contains(value: cart)` or `carts.contains(...[cart])`, now needs `==` on the element type, as `carts.contains(cart)` does (#233). Before, either form skipped the check, so a `List`'s `contains`, `indexOf` or `remove` on a class without `operator ==` compiled and compared by identity at runtime. A `Set`'s and a `Map`'s searches given `value:` or `key:` are now refused beside their declaration, as their positional twins are. An extension's parameter marked `ComparedByOrder`, as `sortedBy`'s is, is now checked when a named argument fills it out of order.
- An extension of `Iterable<T>` whose parameter is marked `ComparedByEquality` now needs `==` on what the `Iterable` gives a loop, as a `List`'s `contains` does, so its call on an `Iterable<Cart>` of a class without `operator ==` is refused, and the help of a method an `Iterable<Cart>` lacks no longer lists it. On a `Map`, it compares the `[TKey, TValue]` entries an `Iterable` reads, so it is refused on a `Map<string, Cart>`, where it compared nothing. On a `Set`, it compares each element, as a loop reads it, so it is refused on a `Set<Money|Ticket>` as on a `List<Money|Ticket>`, where it was taken for the `Set`'s own lookup by `hash()` (#233).
- A search on a value that may be several kinds of collection, as `things.remove(cart)` on a `List<Cart>|Map<Cart, int>`, is refused once, where each kind refused it. A search of `Any?` elements for an `Any?` value is warned of once too, and the warning names the receiver as the call writes it, as "Each element of `loose` is `Any?`", where it named a `List` for every kind. A `sortedBy` whose key has no order, as `statuses.sortedBy(s => s)` on a `List<Status>|Map<string, Status>`, is refused once too. Where the kinds compare different types, each is refused, so `items.sortedBy(x => x)` on a `List<Status>|Map<string, Cart>` refuses its `Status` keys and its `Cart` keys (#233).
- A refused search's help tests for the same object through a member that reaches what the search compares: `counts.keys().any(c => c === cart)` for a `Map`'s keys, where `counts.any(…)` tested its values, and `rows.toList().any(c => c === cart)` for an `Iterable`, which has no `any`. The help reads every part of the receiver as the call writes it, whether or not that part's search is checked, so a search on several kinds, as a `List<Any?>|Map<Money, int>`, on a value that may be a class with its own `contains`, as a `List<Cart>|Box`, or with a spread, offers no such test (#233).
- A type in parentheses that is neither a union nor an intersection before `?` gives one error that names the type to write, as "Write `Shareable` without parentheses" for `T : (Shareable)` and "Write `Line?` without parentheses" for `(Line)?`. That covers a bound, a catch clause and every other place a type is written. Before, PHP's "cannot be parenthesized" error came as well, and a bound got "A bound is `Object`…" too (#237).
- A type parameter bound that names a type no file declares, as `T : Missing`, gives one "Cannot find type" error, where the bound is written. Before, the error came at each use of `T`, and calls, type arguments and other type parameters bounded by `T` added errors such as "Nothing fixes the type argument". `T : Missing & Shareable` no longer reports the existing `Shareable` as missing. Once a file declares `Missing`, `mago analyze --watch` checks the bound again. A `catch (T error)` of a type parameter whose bound is refused or names a missing type no longer adds "`T` is caught, so its bound must be a `Throwable`" (#237).
- `$maybe?->method()` on a method only `__call` runs now has the `null` the `?->` adds (#237).
- A file or folder `mago` can't read or write is named in the error with what `mago` was doing, as "can't open the folder `/app/.sharp`: Operation not permitted (os error 1)" instead of "Failed to load database: I/O error: Operation not permitted (os error 1)". That covers loading the project's files, `mago compile`'s writes into `.sharp`, the law checker's `.sharp/.lean` package and its cache folder, and the baseline file. A `git` that `--staged` can't run is named as "Failed to run `git diff`", and a failed read of the standard input as "Failed to read the standard input", instead of either reading as a failed database load. A `git` that `--staged` runs and that exits with an error now stops the command with git's reason, so `mago fmt --staged` no longer says it re-staged a file another git's `index.lock` kept it from re-staging (#238).
- A spread into a generic method's parameters fixes its type arguments, as each element fixes the parameter it fills, so `Pairs.pair(...[1, 2])` is a `List<int>`, in PHP# and in plain PHP. Each element of a spread is checked against the parameter it lands in, the ones before a `...` parameter included (#227).
- A member an extension gives a union, as `label()` in `extension(Order|string value)`, can be called and read on that union (#64). It runs on each part that declares no member of its name, and a part that declares one runs its own, so `value.label()` on an `Order|string` runs `Order.label()` on an `Order` and the extension on a `string`. A part's own member can come from a trait, or be a property that holds a function, which the call calls. A computed property and a method read as a function value, as `value.label`, choose the same way. Before, the call was refused as a missing method, and its help listed the `label` it said is missing. A member an extension gives only some parts of a union, as `size()` on an `int|string`, is refused once, with help that names those parts and says to test the value with `is` first.
- A constructor with an expression body, as `public Report(private int start) => this.count = start;`, declares the property its promoted parameter names, as one with a block body does. Before, it was refused with "Promoted properties are not allowed outside of constructors."
- A `match` without `default` on a call's result, as `match (ticket.stage()) { Stage.Open => "open" }`, names the enum cases it misses, as one on a parameter does. Before, it compiled. When its arms name no case, as one that handles only `null`, it names the `import` the cases need.
- A refused search's help names its lambda's parameter with a name no local or parameter in scope holds, as `lines.any(element => element === line)` in a method with a parameter `l`. Before, it wrote `lines.any(l => l === line)`, which is refused because `l` is already declared.
- A case written in a class or an interface is refused once, as "A class has no cases: declare `Open` in an enum." Before, mago's "Class `Shop` cannot contain enum cases." came as well.
- `new Self(…)` in a `final` class or a struct compiles without a `required` constructor, since no subclass can lack it. Before, it was refused because `Self` can be any subclass.
- Of two files that declare one full name, the file whose path sorts first keeps it, and the other file's error names it, as "Class `Cart` is already defined in `app/Shop/Cart.sharp`." A case of an enum with data whose class another file declares names that file too. Before, an internal file id decided, so a user could not tell which file would be refused, and the error said "already defined elsewhere".
- After a refused `!entity is HasDesign`, the code reads `entity` as the `entity is not HasDesign` the error asks for, so `if (!entity is HasDesign) { return false; } return entity.ready();` reports the refusal alone. Before, `entity.ready()` also reported a method access on `Any`. A name the refused test binds is in scope where the corrected test binds it.
- `typeof(TAny).make()` and `typeof(TAny).size`, on a type parameter whose bound is no class, are refused as `TAny.make()` is: "`TAny` can stand for a type that is not a class." Before, they reported a method or property access on a non-object `Class<TAny>`.
- A type parameter bounded by a class takes one class, so `new Builder<Fancy|Plain>()` for `Builder<TItem : Maker>` is refused at the type argument: "`TItem`'s bound is a class, so its type argument is one class. Write `Builder<Maker>`." That holds for a type, a call's type arguments and a class header too. Before, it compiled, and `TItem.make()` threw `Error: Class "App.Fancy|App.Plain" not found` while the code ran. A type parameter bounded by an interface, or with no bound, still takes a union.
- `new TItem()` and `new (typeof(TItem))()`, where `TItem`'s bound is an interface, are refused in PHP#'s words: "`TItem`'s bound `Labeled` is an interface, so `new` of it creates no object. Bound `TItem` by a class." Before, mago's warning said a `class-string<Labeled>` "could technically be `Labeled::class` itself". `TItem.make()`, where the interface declares no `make`, is refused as a missing method alone, without mago's second warning about a static call on an interface via a `class-string`.
- A law that reaches a member named by an expression, as `this.payments.{"fee"}`, which is a parse error, no longer adds an impure-law error beside the parse error.

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

[Unreleased]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.3...HEAD
[0.6.3]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.2...v0.6.3
[0.6.2]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.1...v0.6.2
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

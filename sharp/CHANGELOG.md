# Changelog

Every PHP# release, newest first. PHP#, its engine and `mago` release together at one version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, a minor release may break 0.x code, so each one opens with what to change when upgrading.

## [Unreleased]

### Upgrading from 0.6.5

#### Rename a static named `name`, `properties` or `typeArguments`

`Class<T>` has the members `name()`, `properties()` and `typeArguments()`, spec section 25, and `Class`'s own members win over a static of the class a value holds. A class that declares a static of one of these names is a compile error where a class value reaches it: "`type.name` on a class value reaches `Class`'s member `name`, never this static. Rename it."

#### Read `Json.parse`'s value through `.value`

`Json.parse` returns a `JsonValue`, spec section 24, which compares by structure with `==`, where it returned the decoded `Any?`. Code that checks or reads the decoded value reads it through `value`:

```csharp
Any? payload = Json.parse(body).value;
```

### Added

- A class is an `Iterable<T>` when its header names `Iterable<T>` and it declares `Iterable<T> elements()`, spec section 12. A loop, `toList()`, `filter`, `map` and `take` read `elements()`, and the object passes wherever an `Iterable<T>` is taken. Plain PHP receives it as a `Traversable`: `foreach ($bag as $i => $order)` gives the keys 0, 1, 2, and a `Tally : Iterable<[string, int]>` gives `"tea" => 3` in `foreach` and in `iterator_to_array()`. A class that names `Iterable<T>` and declares no `elements()` is a compile error: "`Crate` is an `Iterable<int>` and declares or inherits no `elements()`, the member a loop reads." A PHP# class whose plain PHP base implements PHP's `IteratorAggregate`, as a subclass of Laravel's `Collection`, declares no `elements()` and loops over its base's `getIterator()`.
- A `late` instance field or property with storage may stay unset after the constructor, spec section 6, as a PHPUnit test's field set in `setUp()` and an ORM row filled after `new` are: `public late string number { get; set; }`. A read before its first write throws "Error: App.Order.number is read before its first write.", and plain PHP's `ReflectionProperty::isInitialized()` gives false until that write. A `late` static, a `late` member with an initial value, and a `late` computed property are compile errors. Every read of a PHP# property before its first write now throws this error, where it threw "Typed property App\Order::$number must not be accessed before initialization".
- `nameof` names a method, spec section 6.3: `nameof(OrdersController.index)` gives `"index"`, so a route names its action and a rename breaks the build. A misspelled member and one `nameof` can't reach, as a `private` method of another class, are compile errors. `nameof(Counted)` gives a class's own name, `"Counted"`, and `Class<T>.name()` its full name as PHP# writes it, `"App.Orders.Counted"`, spec section 25.
- `Class<T>.properties()` gives a `List<Property>` of a class's instance properties and fields, its own first and then those it inherits, and `Class<T>.typeArguments()` a type's type arguments, spec section 25. A `Property` has its `name`, its `type` as a `Class<Any?>`, `nullable` and `attributes<TAttribute : Object>()`. For `[Field] List<string> tags`, `type == typeof(List<string>)` and `type.typeArguments() == [typeof(string)]`.
- A `string` reads as its bytes, spec section 18: `"école".byteLength()` is 6, `"école".bytes()` is `[195, 169, 99, 111, 108, 101]` and `"école".byteSubstring(0, 2)` is `"é"`. `trim`, `trimStart` and `trimEnd` take the characters to remove, as `"--sku--".trim("-")` gives `"sku"`.
- `Json.encode` takes `escapeSlashes`, `escapeUnicode` and `keepZeroFraction`, spec section 24, which default to its output without them, so a payload matches the bytes another system signs: all three switched give PHP's `json_encode` default, and `keepZeroFraction: false` alone JavaScript's `JSON.stringify`.
- `Json.parse` returns a `JsonValue`, which compares by structure at every depth: `Json.parse("{\"a\":[1,2]}") == Json.parse("{ \"a\": [1, 2] }")` is true, spec section 24.
- A plain PHP class's `__toString()`, its own or inherited, is its `toString()` in PHP# code, spec section 18: `` `To: ${email}` `` and `email.toString()` compile for a plain PHP `Email` that declares `__toString(): string`, and run it. A value that holds an enum beside such a class is a compile error: "`Email|Status` holds an enum and a plain PHP class whose `toString()` is its `__toString()`, and no one call shows both."
- `Sql.identifier(name, allowed)` gives `name` as a `literal-string` only when it is one of `allowed`, and throws a `ValueError` for any other, spec section 24, so a sort column chosen while the code runs reaches Laravel's `orderByRaw` and `selectRaw`.
- `[Import("levenshtein")]` from `Sharp.Interop` on a static method without a body makes each call run the PHP function it names, spec section 23: `[Import("levenshtein")] public static int distance(string from, string to);`. Each argument and the result cross as any plain PHP value does and are checked there, so `str_repeat` declared to return `int` throws "TypeError: str_repeat(): Return value must be of type int, string returned". It replaces the plain PHP class a project wrote for a built-in the standard library has no member for. `[Import]` on an instance method or a method with a body, and a second `[Import]` of one function in the project, are compile errors.
- `extern namespace WordPress for WP_Post;` gives the folder of the namespace-less plain PHP class `WP_Post` the PHP# namespace `WordPress`, spec section 23: its classes and functions are `import WordPress.WP_Post;` and `WordPress.add_action(…)`, and run PHP's `\WP_Post` and `\add_action`, and `extern WordPress.get_post uses Database;` declares a function's effect. A named class that has a namespace or is PHP#'s, two lines that name one folder, one namespace given to two folders, and a namespace a class already has are compile errors.
- `[Export("auth_redirect")]` from `Sharp.Interop` on a static method makes PHP see the global function `auth_redirect()`, which calls the method with its arguments, named ones included, and returns its result, spec section 23. The Composer plugin writes `vendor/composer/autoload_sharp_exports.php` at each dump, and PHP#'s `autoload.php` declares its functions, so a library that declares its own only when none exists, as WordPress's pluggable functions do, skips it. `[Export]` on an instance method, and a second export of one name in the project, are compile errors.
- The converter writes a built-in call that leaves out an argument as the standard library member whose default for it is PHP's own: `round($total)` becomes `total.round()` and `mb_trim($name)` becomes `name.trim()`, where both kept the file plain PHP.
- `mago convert` converts more of plain PHP with its meaning kept, where it kept the file plain PHP:
  - a local or a `catch` variable named outside camel case, as `$line_total`, which takes its camel case name, `lineTotal`, wherever its method names it
  - `__METHOD__`, `__FUNCTION__`, `__NAMESPACE__` and `__TRAIT__`, as the text PHP compiles in their place, and `__CLASS__` as `self::class` converts
  - a keyed loop over a `string`-keyed array whose body reads the key only as text or as a key, where PHP's `int` key `42` and PHP#'s `"42"` give the same
  - `(string)` of a value only PHPDoc types `string`, as a template, which converts what the value holds at runtime as `(string)` does, and of a `?string` as `` `${note ?? ""}` ``
  - a loop over the values of an object whose class implements `Traversable`, as a framework's collection
  - a condition of several parts and `?:` on a property its class declares with no hook and no `__get`, which a second read gives again, and `?:` on a variable
  - `match (true)` with `bool` conditions and a `default` arm, as a chain of ternaries, or as `if … else if … else` where it stands as a statement
  - `self::m()` in a class a subclass can extend, where `m` is a static method of the file that names neither `static` nor `parent`, nor calls through `self` one that does
  - an intersection type, as `Item&Shareable`, and `class-string`, as `Class<Object>`
  - `===` between unions of scalars that share a type, as `string|false` and `false`, as `==`
  - a lambda that takes fewer parameters than the function type it is passed as, which takes each one it lacks, named `unused` where the function type names none
  - a `string` that may be null in `.` or an interpolated string, as `` `${title ?? ""}` ``, since PHP shows `null` as nothing
  - `(bool)`, as the comparison a condition of the value's type gives, as `(count != 0)`
  - `array-key`, as `int|string`, and so a plain `array`, as `Map<int|string, Any?>`, where each key the body reads or writes by is an `int` or a string literal PHP keeps a string
  - `isset($list[$i])` and `$list[$i] ?? $x`, through `list.getOrNull(i)`
  - a `catch` of several classes, as `catch (Timeout|Refused e)`
  - `.=` of an `int`, a `float` or a `string` that may be null onto a string, as `` text += `${count}` ``
  - `===` between a `mixed` and a string, a `bool` or an enum, as `==`
  - an `if` whose condition assigns a local, as the assignment on its own line and the local's test
  - `instanceof self`, as `is` the class's name
  - `===` between a string and `Order::class`, as `==` with the text of the class's name
  - `(string)`, `.` and interpolation of a plain PHP object whose class declares `__toString`, as `email.toString()` and `` `${email}` ``
  - an override of a plain PHP method declared `array` that returns a `Map`, which keeps the plain PHP method's `array`
  - `===` between two class values, as `==`, where neither may be the class of an object of a PHP# generic class, which carries its type arguments, or one is `Order::class` of a class that takes none, and between an `array-key` and a string, as `==`
  - `=` and `unset` at an index of an object whose class implements `ArrayAccess`, as the index write and `bag.offsetUnset(key)`
  - an `if` whose condition first assigns a local that a comparison reads, as `if (($at = $this->find($id)) !== null)`, as the assignment on its own line
  - `new static(…)`, as `new Self(…)`, in a `final` class, and in any other class that declares a constructor, which becomes `required`
  - PHP's own constant whose value a standard library constant is, as `PHP_INT_MAX` as `Int.MAX`, which the codex now records for each standard library constant

### Changed

- `mago convert` exits 0 when its run completes, whatever files stay plain PHP, in a dry run or a real one, so a CI step can run it. It exited 1 whenever a file stayed plain PHP, which on a real project is always, so a refusal looked like a broken run. The summary line still counts both, "Would convert 201 files to PHP#, and 739 files stay plain PHP.", and the report names why each file stays. It exits 1 only when the run itself fails: a path it names reaches no plain PHP file, a proof can't finish, the written files' proof with the extension hosts doesn't hold, a write fails, or a lowering panics.
- `mago convert` names why PHP# can't keep a refused construct, where it said "not converted yet": a write or `unset` at a `List` index, a local first named outside an assignment of its own, an `else if` that assigns, a loop's variable read after the loop, and `===` of a string and a class value or of a type PHP# has none of.
- `mago convert` refuses `['id' => $id] = $row;`, which it converted to one `row.get("id")` per key: PHP warns on a missing key, and a PHP# `Map` read gives `null` and goes on, and a converted file never succeeds where PHP warned. A key read converts behind `??` or `isset`.

### Fixed

- A `List` literal inside a literal, where its value type is a `Map`, as `["x": ['a']]` in a `Map<string, Map<int, string>>`, is the compile error spec section 12 names, and so is a `Map` literal where it is a `List`: the checker read only the outer literal.
- A lowering that panics refuses its file with an error that holds the panic's text, code `lowering-panic`, where the compile dropped the text, or crashed when the checker had accepted the file. `mago convert`'s proof keeps such a file plain PHP and prints the panic's text. A panic in the plain project's proof, of a file every proof refuses, is an error of the run, and one in a standard library file fails the run with its text, where the run went on without that file's forms.
- `mago convert` refuses a condition, `(bool)` or `!` of a `bool`, an `int`, a `float` or a string that only PHPDoc types, as a framework model's `@property int $count`, which may hold `"0"`, where it wrote PHP#'s test of the type, as `count != 0`, which reads `"0"` otherwise than PHP's truthiness. A value a native declaration, a literal, an operator or a parameter makes that type converts.
- `mago convert` writes `['a', 'b']` as `[0: 'a', 1: 'b']` where a `Map` is declared, at any depth.
- `mago convert` refuses a PHP# generic class with a type argument other than its bound, as `Page<Order>` under `Page<TItem : Item>`, in a parameter's or a property's type and in the result of a plain PHP call. Plain PHP creates such an object without type arguments, so it holds its bounds, and the converted file threw `TypeError` where PHP went on: "Return value must be of type Page<Order>, Page<Item> returned".
- `mago convert` names the compile error that keeps a converted file plain, as "mago compile would refuse it once converted: Declaration of … must be compatible with …", where it named none.
- `mago convert` in a git worktree whose main repository a container doesn't mount says what to do: "/app/.git names the git folder /Users/me/app/.git/worktrees/design, which doesn't exist here, as in a container that mounts a git worktree alone. mago convert converts only files git holds, so mount the main repository's .git folder at the path it has where the worktree was made, so that folder exists, or run mago convert in a plain clone." It said "`git rev-parse` failed (exit status: 128): fatal: not a git repository: /Users/me/app/.git/worktrees/design". `mago compile` and `mago analyze` read no git, so they run there as they are.

- A plain PHP generic whose key type no argument fixes, as `Arr::from` given an object leaves it, no longer stops `mago compile` with "the analyzer refuses a type PHP# can't write, not `array-key`". Plain PHP's `array-key` enters PHP# as `int|string` wherever it reaches PHP# code: a call's type argument, a return value and a property read. A type argument of a call into a project's plain PHP method that PHP# can't write, as `scalar`, is now refused by the checker as it is for a PHP# method, where the compile stopped on it.
- A plain PHP method whose PHPDoc says `@return $this` returns the object it is called on inside a backed enum's method, where the checker took it for the enum and refused the method's `return` with "expected `Query`, but found `Status`".
- A lambda's parameter has the type it declares, `Any?` included, where the checker gave it the narrower type the expected `Function` passes, and reported its body's null checks and casts as redundant. The `Function` type only checks that the lambda takes every parameter it passes, spec section 14.1.
- An untyped plain PHP property with no default, instance or static, reads as `T?` for its `@var T`, since PHP starts it at `null`, where PHP# code's null checks of it were reported as redundant. A typed property, and one with a default, still reads as `T`.
- `too-wide-member` no longer reports a constant a PHP# class inherits from a plain PHP parent in another file. It reports a member only on the class that declares it.
- A composed string passed to a plain PHP `literal-string` parameter is refused with "Argument #1 of method `Query.selectRaw` takes a `literal-string`, a string literal or a constant, and this string is composed while the code runs.", and the help to pass each value as a query's binding, where it said "expected `string`, but possibly received `string`".
- `mago analyze --scan-only` reports each file's parse error as an error, in every reporting format, and exits non-zero, where it only logged a warning and exited 0, so a build that scans the codebase no longer passes over a file that doesn't parse.
- `mago analyze`, `mago lint` and `mago guard --substitute ORIG=TEMP` check `TEMP` exactly as `ORIG` would be at `ORIG`'s path. It takes `ORIG`'s package, folder namespace and exclusions, wherever `TEMP` lies, and keeps its own dialect, so a PHP# draft kept outside the project gets the folder-namespace error it gets in place, and one in an excluded folder is checked, where it was skipped without a message. Reported issues and baseline entries name `ORIG`'s path with `TEMP`'s extension, where they named `TEMP`.
- A plain PHP write into a PHP# property already holds the property's type, from a file without `strict_types` too, spec section 12. In 0.6.5, `ReflectionProperty::setValue($order, 5)`, `$order->number = 5` and a write through a reference stored `"5"` in a `string number` property, as PHP's weak mode converts. Each now throws "TypeError: Cannot assign int to property App\Order::$number of type string". An `int` written into a `float` property still becomes a `float`.
- A loop over an object whose class is no `Iterable`, as `for (const item of this)` in a plain class, is a compile error that names the type, as a loop over any other value that is no `Iterable` is: "The expression provided to a loop is not iterable. It resolved to type `Basket`, which is not iterable." In 0.6.5 it compiled with a warning, and PHP looped over the object's public properties. A loop over an `Object` and over an enum value is the same error.
- A value of a type parameter has every extension its bound has, spec sections 11 and 26, as C# and Kotlin resolve them. `int f<THeap : Heap<int>>(THeap heap) => heap.size();` runs the extension `size()` of `Heap<TItem>`, where 0.6.5 refused it with "Method `Heap.size` does not exist.", and so do an extension of `Bag<T>` through `TBag : Bag<Order>` and one of `Iterable<T>` through a bound that is a plain PHP `IteratorAggregate`. A generic method's argument fixes a type argument through its type parameter's bound the same way: `Picks.first(bag)` for `TItem first<TItem>(Bag<TItem> bag)` gives an `Order`, where 0.6.5 refused the argument with "expected `Bag<TItem>`, but found `TBag`". A type parameter with no bound, or with a bound the extension's receiver doesn't fit, still has none of its extensions.
- A law whose body splits on an enum's cases proves, spec section 28. Each case of a body reads the code the claim's calls reach, as a law without a body does, so `law fromLevelUndoesLevel(Role role) => Role.fromLevel(role.level()) == role` with an `if (role == Role.Owner) { return; }` for each case proves when `level()` calls another method, where 0.6.5 refused every case with "when role == Role.Owner, still to show: Role.fromLevel(role.level()) == role". The same holds for a `match` arm, for the case on either side of `==`, and for `!=` in the `else` branch.
- A law reaches a private method and every method of an enum or a final class, which no subclass can override. `public bool meets(Level @required) => this.rank() >= @required.rank();` with `private int rank()` on an enum proves, where 0.6.5 refused it with "Law meetsItself reaches a call to the overridable method Level.rank in Level.meets. A law cannot know which implementation runs." A `virtual` method of a class a subclass can extend, an interface method and a call through `Self` are still refused.
- A law reaches an enum's `cases()`, the list of its cases in the order the enum declares them, where 0.6.5 reported "the checker left out Level.cases, which the law reaches. This is a bug in the PHP# checker".
- A law reads a `List` by index, spec section 12. The read gives the element, or throws `OutOfRangeException` at an index outside the list, as the engine does, and a throw doesn't break a law. So `law backoffNeverShrinks(Retry r, int i) => i < 0 || i + 1 >= r.backoff().count() || r.backoff()[i] <= r.backoff()[i + 1]` proves through its own guards, where 0.6.5 refused it with "reaches a collection", and a law without the guards proves where the read is in range. A `Map` read by its key is still refused.

## [0.6.5] - 2026-11-11

### Upgrading from 0.6.4

#### Declare a list plain PHP gives as a list

`is`, `as` and `match` test plain PHP's array as the type it arrives as, spec section 12, and a bare `array` arrives as a `Map<int|string, Any?>`, whatever keys it holds. In 0.6.4, `box.records as List<Map<string, string?>>` tested the raw array and gave the list, while `const records = box.records;` held the `Map` and gave null, so the same value answered two ways. A `Map` is never a `List`, so a `List` test of it is now a compile error: "This pattern never matches the value it tests, a `Map<int|string, Any?>?`: a `Map` is never a `List`." Read the `Map`'s values as a `List` in PHP#:

```csharp
List<Any?>? rows = box.records?.values();
```

Or declare the list in plain PHP, and PHP# reads it as a `List` both ways:

```php
/** @var list<array<string, ?string>>|null */
public ?array $records = null;
```

### Fixed

- On a busy Mac, `mago analyze --watch` checks only the folders FSEvents has not started on, where a watcher FSEvents didn't start in time checked every watched file, `vendor/` included, every half second for the rest of the session. A folder goes back to FSEvents once FSEvents reports a change there, and the watch prints "The file watcher reported a change in …, so this run stops checking the files there." The warning now names those folders: "The file watcher reported no change in … within 30s, so this run checks the files there every 500ms until it does." The checks of the project's files skip the folders the watch's changes skip, as `vendor/` when it is an `includes` root and `node_modules/`, and read only the files of the configured extensions. The checks of `mago.toml`, `composer.json` and `composer.lock` still read every file beside them.
- On macOS, `mago` no longer hangs when a process of an extension worker's or the law checker's process group never ends, as one the kernel holds on a stalled disk. Mago sent the group SIGKILL until no process of it ran, so it waited forever, and a worker's restart and the end of every run waited too. It now stops after 10 seconds, leaves that process running, and goes on: the worker's error says "process group 4321 still ran 10000 ms after SIGKILL", and the law checker refuses the law as one that "did not finish within … seconds".
- A plain PHP method that overrides a PHP# class's method, and the test double PHPUnit's `createMock()` or Mockery generates, gives PHP# its PHP array as the `Map` or `Set` the method declares. In 0.6.4, `const status = source.status(); return status.get("status");`, and the same read without the local, threw "ValueError: Sharp\Internal\get(): Argument #1 ($collection) must be a list" when `source` was such a class, since only a PHP# interface's member was read as a value plain PHP may give.
- A call whose method may be plain PHP's reads its result as its declared type wherever the call is written: an argument, a constructor's argument, a collection literal's element and a spread too. This covers a plain PHP function or method, a PHP# interface's member, and a PHP# class's method an override may replace, a static method `Self.` or a class value reaches included. In 0.6.4, `Map<int|string, Any?> payload = [...source.payload()];` on a plain PHP interface `Source { public function payload(): array; }` threw "Error: Call to a member function keys() on array", and so did `[...request.query.all(), ...payload]`. The engine looks up the method that ran, so a call that runs a PHP# method gives its result as it is, whatever the result holds.
- A PHP# property that overrides a plain PHP parent's array property, as `protected override Map<string, string> casts { get; set; } = ["paid": "bool"];` overrides Eloquent's `$casts`, keeps the parent's storage, so the parent's own code reads the PHP array. In 0.6.4, `array_merge(['id' => 'int'], $this->casts)` in the parent threw "TypeError: array_merge(): Argument #2 must be of type array, Sharp\Map given". PHP# code still reads the property as its `Map`.
- A PHP# method that overrides a plain PHP method declared `array` with a `Map` or `Set` result, as `public override Map<string, List<string>> errors() => [:];` under `public function errors(): array`, keeps the plain PHP method's `array`, so its class loads. In 0.6.4, `mago compile` accepted it and loading the class failed: "PHP Fatal error: Declaration of Child::errors(): Sharp\Map|array must be compatible with Base::errors(): array". The method returns the PHP array to every caller, and PHP# code reads it as the `Map`.
- An extension host that refines a PHP# member's type to a type PHP# can't write, such as an `array{key: string, reason: string}` shape on a `Map<string, string>?` property, no longer stops `mago analyze`. 0.6.4 panicked in every analysis of the project, whatever file it was scoped to: "internal error: entered unreachable code: PHP# writes no array shape". The member keeps the type it declares, and the analysis goes on with an error at the member: "The host `typing` refines `Notice.fields` to `array{'key': string, 'reason': string}`, which PHP# can't write, so it keeps its declared type `Map<string, string>?`." A refinement PHP# writes that doesn't fit the declared type, as `Other` on a `Spec?` member, is an error at the member too, in the same words, where it was a "Docblock property type" mismatch. A refinement that fits, as `Special` on `Spec?`, still applies where PHP# alone gives the member its values: a result, and a property whose `set` is private. On a property plain PHP may write, or a parameter of a method plain PHP may call, the engine checks each value against the declared type alone, so a narrower type is an error at the member too: "The host `typing` refines `Notice.opened` to `Special?`, which narrows its declared type `Spec?`, and plain PHP may give it any `Spec?`." The same holds for a parameter, a result, a type parameter's bound, a parent's type argument and a lambda's `this`, and for an abstract or interface method, whose refinement applied unchecked before.
- `mago analyze` reads plain PHP's `str_getcsv()` as returning `list<string|null>`, which PHP returns, where it read the bare `array` its signature declares. Plain PHP code that reads the result by a string key now gets the report a list gives, and PHP# reads the result as a `List<string?>`, where it read a `Map<int|string, Any?>`.
- An interface method or an abstract method with a parameter default, as `int read(Map<string, int> limits = [:]);`, compiles: the analysis types and checks the default, and `mago compile` panicked on it before.

## [0.6.4] - 2026-11-07

### Upgrading from 0.6.3

#### Build the app's image on the 0.6.4 images

The 0.6.0 to 0.6.3 FrankenPHP images build FrankenPHP v1.13.4, which holds CVE-2026-78661 (HIGH) and the 17 FrankenPHP advisories that v1.13.5 fixes (#277). The 0.6.4 FrankenPHP image builds v1.13.5, as Security says. Change the app's `FROM` line to `ghcr.io/heyjordanparker/php-sharp:0.6.4`, or `ghcr.io/heyjordanparker/php-sharp:0.6.4-frankenphp`, and build it again.

#### Clear four values after each request in FrankenPHP's worker mode

The 0.6.0 to 0.6.3 FrankenPHP images reset `error_get_last()`, the libxml errors, `http_get_last_response_headers()` and `strtok()`'s string between the requests a worker handles. v1.13.5 no longer resets them ([php/frankenphp#2790](https://github.com/php/frankenphp/pull/2790)), so each can carry another client's data into the next request, as [FrankenPHP's worker guide](https://github.com/php/frankenphp/blob/v1.13.5/docs/worker.md#state-persistence) says. A worker loop clears the first three after each `frankenphp_handle_request()` call:

```php
while (frankenphp_handle_request($handler)) {
    error_clear_last();
    libxml_clear_errors();
    http_clear_last_response_headers();
}
```

Each request passes its own string to its first `strtok()` call, where a call with only the separators continues the string an earlier request gave it.

#### Expect these answers from FrankenPHP and Caddy

FrankenPHP v1.13.5 builds on Caddy 2.11.9, and in both modes:

- A request for a script that doesn't exist gets `404 No input file specified.`, and one for a script FrankenPHP can't read gets `403 Access denied.`, where each got a 200 with a fatal error ([php/frankenphp#2791](https://github.com/php/frankenphp/pull/2791)).
- A request whose body is over `request_body`'s `max_size` gets 413, and PHP code that reads such a body gets a `FrankenPHP\RequestBodyTooLargeException`, where it read the body cut short ([php/frankenphp#2785](https://github.com/php/frankenphp/pull/2785)).
- `$_SERVER['SERVER_ADDR']` holds the address that accepted the connection ([php/frankenphp#2809](https://github.com/php/frankenphp/pull/2809)).
- Over HTTP/2, `$_SERVER['HTTP_HOST']` is the `:authority` Caddy routed the request on, and a value set with `env` wins over the client's header of the same name.
- Dot segments after a script, as `/admin/tool.php/../../missing.php`, resolve before FrankenPHP picks the script, so it runs the script Caddy's `path` rules saw. A request whose escaped and decoded paths pick different scripts, as `/public/tool.php%2f..%2f..%2fadmin%2ftool.php`, gets 400.
- The image's `encode` compresses a response even when the request sends `Cache-Control: no-transform`, and adds `Vary: Accept-Encoding` to each response it could compress.

In worker mode only:

- A worker that restarts, or reaches `max_requests`, closes its persistent resources, as `PDO::ATTR_PERSISTENT` connections, where each restart left them open until the database refused new connections ([php/frankenphp#2812](https://github.com/php/frankenphp/pull/2812)).
- `frankenphp_handle_request()` empties the superglobals and runs the garbage collector before it waits for the next request, so the destructor of an object left in `$_SESSION` or another superglobal runs inside that call, and an exception it throws comes out of it.

### Changed

- `mago compile` proves the laws of every `.sharp` file it compiles in one Lean process, where it built the Lean code it generates with Lake and then ran one Lean process per file at once, each importing Lean, the runtime library and that code. Lean and the runtime library now load once per compile, and the generated code is elaborated once, in that process, so a compile of many files with laws uses far less processor time and memory. Mago no longer writes the generated code to `.sharp/.lean/`, and deletes the `Code` folder and the Lake build an earlier `mago` left there. The processor-time limit and the deadline still hold for each file's check.
- `mago compile` builds the law checker's Lean library in Mago's cache folder once for each version of the library's Lean sources and of Lean, where it built one for each `mago` build. A `mago` built from other Rust code with the same Lean sources, as each edit to Mago makes, uses the library that is there, where it built a new one and deleted the other.
- On macOS and Linux, each extension worker runs in a process group of its own, as each Lean the law checker runs already did. A worker that fails, or that runs past `shutdown-timeout-ms`, now ends with every process it started on the host, such as the `docker compose` process of a `docker compose exec` wrapper, where that process kept running. A process inside a container is Docker's to end. Ctrl-C, or a signal that ends `mago`, ends each worker with SIGKILL, where the worker got the signal itself. A SIGKILL sent to `mago`'s process group no longer reaches the workers, since no handler runs on SIGKILL: a healthy worker exits once its stdin closes, and a stuck one keeps running. A worker that reads from the terminal, as an interactive debugger prompt does, is stopped by SIGTTIN, since its group is not the terminal's.
- A law whose proof reaches another file's generated Lean code, where Lean rejects that code or it runs past the checker's limit or deadline, is refused with that code's refusal, where the whole compile failed with "the law checker failed with exit status: 1". The file's other laws are still checked.
- `mago compile` with nothing to do returns at once, where it analyzed the whole workspace every time. A compile that succeeds keeps `.sharp/inputs.json`, a record of every file and setting it read and every file it wrote, and the next compile checks it before any analysis. When nothing in it changed, the compile prints the same summary and stops. A compile that is refused, fails or warns keeps no record, so the next one prints the same errors and warnings. A project with an extension host enabled still compiles every time, because a host doesn't say what it reads.
- The compile of the project for the extension hosts, which `mago analyze` and `mago lint` run before they start the hosts, checks the same record before it loads the project, where it loaded the whole workspace and checked each compiled file's inputs, an input once per file that names it. It runs again after an edit in `vendor/` or a change to `mago.toml` or a `composer.json`, where the hosts kept loading code compiled before it.
- `mago convert` converts more of a project, with the same guarantee: a converted file runs as its PHP did, and the converter writes no type the analysis only inferred (#266). On a Laravel application of 1,124 class files, a dry run of the whole app converts 323 files, where it converted 115. Most of the gain is one refusal: a class named `Module` stays plain PHP up front, since spec section 32 keeps `Module.sharp` for its folder's module. Converted, such a class made the application's extension host misread every module, and about 200 other files stayed plain PHP for the errors that caused. A local that starts as `null` or `[]` now converts, to `T? name = null`, a `List<V>` the body only appends to, or a `Map<K, V>` it only writes by key, when the values written to it have declared types. Each construct the proof refused in a converted file now converts, or keeps its file plain PHP up front with the spec section that rules it out, as a static method of an interface (section 22) or a `get` hook that writes `$this` (section 6.1). A member whose PHPDoc tag narrows the type PHP# writes, as `@return non-empty-string` narrows `string`, keeps its file plain PHP and names the tag, where the narrowing was dropped. `docs/architecture/slices/convert.md` lists each construct by spec section.

### Fixed

- A compile that `sharp.compile_command` runs never starts another. With the setting in a `php.ini` that `php` on the command line reads, an extension host's `php` that loaded a PHP# file the checker refused ran the command itself, and that compile started the hosts again, with no end. In a container capped at 300 processes, one `mago compile` nested 18 compiles in 34 s and stopped only when `fork` failed. The engine now runs the command with `SHARP_COMPILING=1`, `mago` starts every process with it, and a process that has it set never runs the command, so it gets the refusal. The dev line for a Composer install is in sharp/README.md's Development section and spec section 27:

  ```ini
  sharp.compile_command = "vendor/bin/mago --no-version-check --no-extensions compile"
  ```

  `--no-extensions` keeps the extension hosts out of the compile a request waits for, and their rules run in `mago analyze` and CI. Production leaves the setting empty, so a stale deploy is refused.
- A refusal after a compile command that failed says how it failed, when the checker reported no error for the file: its exit status and its last three lines of stderr. Through `vendor/bin/mago`, a compile that a signal killed reports exit 128 plus the signal's number, such as exit 137. The engine sent the command's stderr to `/dev/null`, so a command that couldn't run, or a user that can't write the project, gave the plain "is out of date" with no reason. Now:

  ```text
  app/Orders/Order.sharp is out of date (app/Shared/Database/BaseModel.php changed). Run vendor/bin/mago compile. `vendor/bin/no-such-mago compile` failed with exit 127: /bin/sh: 1: vendor/bin/no-such-mago: not found
  ```
- A law whose code names something `sharp_prove`, `sharp_close` or `sharp_condition` is checked. Lean read the name as one of the law checker's proof steps, so the law was refused as a bug in the checker.
- An extension host whose worker dies, as one does at startup when `composer install --no-dev` left out a require-dev package it loads, now stops `mago` with the host's name and what the worker wrote to stderr: "extension host `reports` disconnected (worker 0): worker closed stdout; stderr: PHP Fatal error:  Uncaught Error: Class "Acme\DevTools\ReportPlugin" not found in …". Before, the error was "extension worker 0 disconnected: worker is not running", which named neither. Mago restarts a dead worker before it sends the first request, so the request reached a worker that had died again, and that error dropped the stderr Mago had kept. A worker that dies while Mago writes it a request now fails the same way, where the error was "violated the protocol: … Broken pipe".
- An extension host whose worker stops reading its standard input, as one deadlocked in an extension or held at a debugger breakpoint does, now fails the request at the host's `request-timeout-ms`, as a worker that stops answering does: "extension host `reports` made no progress for 30000 ms (worker 0): raise its `request-timeout-ms` for hooks that run that long". Before, once a request no longer fit in the pipe Mago writes to, which holds 64 KiB on macOS and Linux, Mago waited on the write forever, so `mago analyze` in a CI job or a watch session froze with no message. The worker's other requests now fail with that deadline too, as "extension host `reports` disconnected (worker 0): made no progress for 30000 ms on the hooks of app/User.php", where they said only "request 2 timed out".
- `mago` no longer ends with exit code 141 and no message when an extension worker, the law checker or `git` ends while Mago writes to it. The write ended `mago` with SIGPIPE, so a stuck worker that Mago ended at its deadline, or a worker that died while Mago wrote it a request, gave no error. Each now gives its error.
- On macOS, an extension worker that another worker or a Lean process started beside fails its requests as it should. That process could inherit the worker's pipes and hold them open for as long as it ran, so a worker that closed its stdin still took Mago's request, and a worker that died never closed its stdout. Its request then failed only at the host's `request-timeout-ms`, with "extension host `reports` made no progress for 30000 ms (worker 0): …" in place of the "PHP Fatal error: …" the worker wrote. A pool starts its workers at once as it grows, so this hit them at random, most often on a busy machine. Mago now starts every process one at a time, `git`, Lake and elan included.
- On macOS, `mago` no longer hangs when it ends an extension worker that was starting a process at that moment, as a worker that runs a shell command does. The new process missed the SIGKILL that ended the worker's group, kept the worker's stderr open, and ran on, so `mago` waited for that stderr to end forever. Mago now ends the group again until no process of it runs.
- `mago analyze --watch` no longer restarts as it starts for a `composer.lock` or `mago.toml` written just before it ran. A busy Mac's FSEvents reported such a write after the watch started, as a change, so the watch analyzed the project twice and started the extension hosts' workers again.
- On macOS, `mago analyze --watch` starts its file watcher's stream once for all the folders it watches, and stops it once, where it stopped and started the stream again for each folder. Each stop can wait forever when the stream's run loop misses the stop, a race in the `notify` library's FSEvents watcher that its 9.0 release candidates fix, so a watch hung at start or restart more often than it does now.
- On a busy Mac, `mago analyze --watch` waits at most 30 seconds for each of its two file watchers, so about 60 seconds on a cold start. A watcher that FSEvents doesn't start in time checks the watched files every half second for the rest of the session instead, which reads every watched file, `vendor/` included, at each check. FSEvents took up to four minutes to start while fseventsd was behind, and the watch waited for it with no message, where it now prints "The file watcher reported no change in … within 30s, so this run checks the watched files every 500ms instead." A watch also restarts without waiting for its old file watcher to stop, which took as long, or forever when the `notify` race above hit.
- An extension host reads the arguments of a PHP# attribute as it reads the PHP that means it. `[Type(of: Type.String)]` reached a codebase scan hook as an `Unsupported` constant expression, so `ConstantExpression::evaluate()` threw, where `#[Type(of: Type::String)]` gave the class constant. Each PHP# argument now arrives as its PHP twin does:
  - `typeof(Order)` is the `ClassName` `Order::class` is, with the same type.
  - A class constant or an enum case read through its class, as `Type.String` and `Plan.Starter`, is a `ClassConstant`, as is a read through a class no file declares, which the analyzer reports.
  - A library's constant read through its namespace, as `Pdf.DEFAULT_DPI` after `import Vendor.Pdf;`, is the `Constant` `Vendor\Pdf\DEFAULT_DPI`, and one a library declares in PHP's root namespace beside its classes, as `Foundation.APP_LIMIT` after `import Illuminate.Foundation;`, is the `Constant` `APP_LIMIT`.
  - What no constant expression holds is `Unsupported`, as in PHP: a function read through its namespace, as `Pdf.render`, a case of an enum whose cases carry data, as `Tier.Free` and `Lookup<int>.Missing`, which runs as an object, and a backed enum's case keying a map, as `[Status.Paid: "Paid"]`, which runs as its value. A read that names no class member has no type, as a constant or a call has none in PHP.

  Only the whole codebase tells these apart, so a file whose attributes hold such a read is sent to the codebase scan hooks once every file is scanned, and sent again when a change to another file changes what it reads.
- `ConstantExpression::evaluate()` refuses an attribute argument Mago doesn't evaluate with "The expression is not a constant expression Mago evaluates.", where it named a byte offset, as "The expression at 141 in app/Shop/NotFoundException.sharp …", that no one could find. The host's issue carries the argument's location, which Mago prints as `file:line`.
- `mago convert` holds one proof's analysis and extension hosts at a time. A proof that split its files kept its whole analysis and its hosts' workers running while it proved each part, so a split k levels deep held k analyses and k workers per worker a host has. On a real Laravel app, runs were killed for memory at 7 to 8 GiB, with workers from the first proof still alive hours in. On a project of five clean interfaces and three that split, peak memory falls from 241 MiB to 137 MiB.
- A `mago convert` proof that can't finish, as when an extension host's hook makes no progress within its `request-timeout-ms`, logs its error once, "Proof 4 could not finish, so the files it proves stay plain PHP: … extension host `dent` made no progress for 120000 ms on the after-analysis hooks of plugin `routes` …", and keeps each of those files plain PHP with "src/Shop/Order.php: proof 4 could not finish" as its reason. The run goes on and reports every other file. Before, the run stopped with exit code 2 and reported nothing it had found.
- Once `mago convert` has written its files, a write that fails, a compile of the project for the extension hosts that fails, or a proof by fresh hosts that can't finish now logs its error, says git holds the plain PHP, and ends the run with exit code 1, where each stopped it with exit code 2 and no report. The run names each file it wrote, as `src/Shop/Order.php -> src/Shop/Order.sharp`, where only a dry run named them.
- `mago compile` keeps the `.php` files it writes beside `.sharp` files out of git with a `.gitignore` of its own in each folder that holds them, which lists itself and each file. It listed them in `.git/info/exclude`, which every worktree of a repository shares, so a compile in one worktree hid the same paths in every other: an untracked `app/Tenant/Publishable.php` someone wrote in another worktree left `git status`, and a branch switch could overwrite it without a warning. A folder whose `.gitignore` the compile didn't write keeps it as it is, and the compile warns: "app/Tenant/.gitignore doesn't list /Order.php, so git may show app/Tenant/Order.php as untracked: add /Order.php to it." A compile also removes the blocks an earlier `mago compile` wrote in `.git/info/exclude`, and keeps every other byte. Run this `mago` in every checkout of a repository: a compile by an older `mago` in any worktree writes its block again, which hides its paths in all of them.
- `mago convert` says why it can't read git, where it kept every file plain PHP with "commit it first" (#268). In a Docker container on a git worktree, whose git folder sits outside the mount, git can't read the repository, and the run now stops with exit code 2 and git's own reason, which Mago's FAQ explains:

  ```text
  ERROR `git rev-parse` failed (exit status: 128): fatal: not a git repository: /missing/.git/worktrees/design
  ```

  With no `git` on `PATH`, the run stops with "Failed to run `git rev-parse`: git isn't installed or isn't on PATH". A file in a git submodule stays plain PHP with "it is in the git submodule `src/Library`: run mago convert there", and one that changes after git is read stays plain PHP with "it changed since mago convert read git: commit it first". An issue in a converted file names the file the user has: a dry run names the plain file, the member, its line and the line of the PHP# its diff shows, as `src/Contracts/Box.php:7, in Box::$item, at line 5 of its PHP#: …`, and a run that writes names the `.sharp` file. A named path that reaches no file says why, as "src/Generated/Made.php is excluded from the project's analysis.", and a run with nothing to prove starts no extension host.
- The `.php` file `mago compile` writes beside each `.sharp` file passes Laravel Pint's `laravel`, `per` and `psr12` presets, PHP-CS-Fixer's `@PER-CS2x0`, PHPCS's `PSR12` and `mago fmt` as written (#274). Pint refused every one, so a project's lint failed the day it adopted PHP#, and excluding the files didn't help, since Pint reads no `.gitignore`. Spec section 27 says how a project on PHP-CS-Fixer's `@Symfony` set or Pint's `symfony` preset skips them. On plain PHP the file still throws "This project needs the PHP# engine" while its `.sharp` source exists.
- Mago parses and walks a file nested many levels deep, as a PHP sum of 100,000 terms, and a union or intersection of many types, with no call frame per level (#281). Its memory grew with the depth: Mago's dev build took 2.58 GB for that sum, and takes 0.16 GB now.

### Security

- The FrankenPHP image builds FrankenPHP v1.13.5, whose golang.org/x/net v0.62.0 fixes CVE-2026-78661 (HIGH), a denial of service through crafted HTML (#277). Upgrading from 0.6.3 says what changes in worker mode.
- FrankenPHP v1.13.5 fixes 17 FrankenPHP advisories in v1.13.4, which every earlier 0.6.x FrankenPHP image builds (#277). The image's default configuration runs `php_server`. Among them:
  - [GHSA-j99q-3wr8-xj43](https://github.com/php/frankenphp/security/advisories/GHSA-j99q-3wr8-xj43) (High): `php_server` ran an uploaded file as PHP, as `/uploads/photos.php.php/avatar.jpg`.
  - [GHSA-jvmv-5538-pqpg](https://github.com/php/frankenphp/security/advisories/GHSA-jvmv-5538-pqpg): `php_server`'s trailing-slash redirect for a folder could send a client to another host.
  - [GHSA-2725-rh2w-9j9w](https://github.com/php/frankenphp/security/advisories/GHSA-2725-rh2w-9j9w): over HTTP/2, a `host` field beside `:authority` set `$_SERVER['HTTP_HOST']` to a host Caddy didn't route the request on.
  - [GHSA-j45x-h238-h4xx](https://github.com/php/frankenphp/security/advisories/GHSA-j45x-h238-h4xx): slow request bodies held every PHP thread, one slow connection per thread.
  - [GHSA-xh92-4p94-ch7x](https://github.com/php/frankenphp/security/advisories/GHSA-xh92-4p94-ch7x): `php_server` served a Caddyfile kept in the document root.
  - [GHSA-8qcf-v46r-g2mc](https://github.com/php/frankenphp/security/advisories/GHSA-8qcf-v46r-g2mc): in worker mode, destructors of one request's objects sent output and cookies to the next client.
  - [GHSA-2x4j-r638-wxff](https://github.com/php/frankenphp/security/advisories/GHSA-2x4j-r638-wxff): dot segments after a script, as `/admin/tool.php/../../missing.php`, ran a script a Caddy `path` rule denied.

  [The v1.13.5 release notes](https://github.com/php/frankenphp/releases/tag/v1.13.5) list the other ten.

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

[Unreleased]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.5...HEAD
[0.6.5]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.4...v0.6.5
[0.6.4]: https://github.com/heyJordanParker/php-sharp/compare/v0.6.3...v0.6.4
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

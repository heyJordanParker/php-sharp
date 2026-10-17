# Slice: PHP# discovery and the first compile

Two fixes found by moving part of a real Laravel app to PHP#. Spec section 27, and section 32 where the standard library's module is read. This note is the design only. team-lead approves it before anyone builds.

Every state below was observed on master 754ab7ce08, in the `php-sharp:754ab7ce08` image, with the `mago` built from that commit's `sharp/mago/`, in neutral fixture projects. The fixtures, commands and outputs are under [Evidence](#evidence).

## The answer first

- **On a fresh tree, every PHP process on the engine that loads `vendor/autoload.php` dies, `vendor/bin/mago compile` included.** The plugin's `autoload.php` reads the standard library's compiled `Module.sharp` the moment it loads, since 4567fd69. Nothing is compiled yet, so it throws `Module.sharp isn't compiled. Run vendor/bin/mago compile.` The extension host is one such process. `vendor/bin/mago` itself is another, because its PHP wrapper loads `vendor/autoload.php` before it starts the binary. So the command the refusal names fails with that same refusal, and a project can only compile with a `mago` binary called directly.
- **Fix for the first compile:** the container reads the library's module the first time it reads modules, as it already reads every app module. `vendor/autoload.php` then needs nothing compiled again, as before 4567fd69.
- **Compiled output is a binary unit in `.sharp/`, which no PHP scanner looks at.** Scanners list `*.php` files in a source folder and turn each path into a class name by dropping `.php`. A `.sharp` class has no such file, so all three listing styles in the scanner fixture miss it, while the same class loads by name.
- **Fix for discovery:** `mago compile` writes a `<Name>.php` file beside each project `.sharp` file that declares a type. The file only loads the `.sharp` file through the engine, and on plain PHP it throws the plugin's "needs the PHP# engine" error, so no source leaks. A prototype of that file made every listing style find the class and its attribute, with no engine change.

## Where compiled output lives today

**`mago compile` writes one binary unit per accepted PHP# file into one `.sharp/` folder at the workspace root.** The unit sits at the source's path relative to the root, with `.sharp` replaced by `.sharpc`. Vendor packages mirror into the same folder. `.sharp/listeners.php` holds the listener table. `sharp/mago/src/commands/compile.rs` deletes every other file in the folder on each run.

```text
fixture/
├── app/Greeting.sharp
├── vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp
└── .sharp/
    ├── app/Greeting.sharpc                                                 <- starts with the bytes "SHARPC", no PHP
    ├── vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharpc
    └── listeners.php
```

**The engine finds the unit from the source, never the other way.** `sharp_compile_file` in `ext/sharp/sharp.c` takes over any file whose name ends in `.sharp`. `sharp_find_root` walks up from the file's real path to the nearest folder that holds a `.sharp/` folder, and `sharp_read_compiled` reads `<root>/.sharp/<relative path>c`. `sharp_check` refuses a unit that is missing, from another engine, or older than its source or any input. With `sharp.compile_command` set, the engine runs it once and checks again. The image leaves it empty.

**The Composer plugin never reads `.sharp/`.** It only finds `.sharp` sources, and the engine does the rest.

- `Plugin::dumpClassMap` writes `vendor/composer/autoload_sharp.php`, but fills it only on `composer dump-autoload --optimize`. After a plain `composer install` it is `return [];`.
- `autoload.php`'s loader runs after Composer's, which registers itself first. It tries the class map, then each PSR-4 prefix and fallback folder with the `.sharp` extension, then the enum's file for a case that carries data.
- It includes the `.sharp` file through `$includeFile`, which first runs `$requireEngine`. On plain PHP that throws "This project needs the PHP# engine", so no source reaches the output.
- Outside Composer's own process, `autoload.php` runs `$requireEngine` eagerly when it loads. That checks the engine's native fingerprint, then calls `Sharp\Internal\readModule` on the library's `Module.sharp`. The second call needs `Module.sharpc`, and is the first-compile failure.

**How the scanners name a class.** All three read in source:

- Spatie's route attributes list `*.php` with Symfony Finder and build the name with `Str::replaceLast('.php', '', …)` on `getRealPath()`.
- Laravel's console kernel lists `*.php` with Finder and replaces `.php` on `getRealPath()`.
- Laravel's event discovery lists every file, drops `.php` with `Str::replaceLast`, and skips a name `ReflectionClass` rejects.

Each one uses the real path, so a symbolic link resolves to the `.sharp` file and gives `App\…\Name.sharp`.

## Discovery: the Solutions

Kotlin's classes reach Java's scanners because both compile to `.class` files and the scanners read the output folders on the classpath. PHP's scanners read source folders, where the class name comes from the path. So the general answer puts a real `<Name>.php` file where the source is, or makes PHP believe one is there.

### A. `mago compile` writes `<Name>.php` beside each `.sharp` file (recommended)

The `tsc` model: the compiler emits a file beside each source, where every tool already looks. Today's compiled output is not PHP, so the file loads the source through the engine instead of holding compiled PHP:

```php
<?php

// mago compile writes this file from SharpController.sharp, so PHP code that lists *.php files finds its class.

function_exists('Sharp\Internal\requireNative') || throw new Error('This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.');

require_once __DIR__ . '/SharpController.sharp';
```

Composer's own loader tries `.php` first, so it loads this file, which loads the `.sharp` file, which the engine compiles from `.sharp/`. Stack traces, `Position` and opcache still name the `.sharp` file.

- **Folders:** one `<Name>.php` beside each project `.sharp` file that declares a type. A `Module.sharp`, an environment module and an `extern` file get none. Nothing is written into `vendor/`, so decision 29's "nothing is written into `vendor/`" holds. `.sharp/` does not change.
- **Version control:** the files are generated, so the project should not commit them. Git cannot ignore "a `.php` beside a `.sharp`" with a pattern, and `*.php` would ignore the user's own PHP. So `mago compile` has to keep the ignore list itself. That is a question below.
- **Tooling:** every listing sees a real file: Finder, `glob()`, `scandir()`, `find`, and Composer's PSR-4. Editors show the files unless the PHP# editor setup hides them, as VS Code's `files.exclude` with `"when": "$(basename).sharp"` and PhpStorm's file nesting hide `tsc`'s `.js` files.
- **Analysis:** the file declares no class, so PHPStan, Psalm and PhpStorm see no second declaration. `mago` loads it as plain PHP from `source.paths`. The prototype compiled clean with it in place. A deploy already runs `mago compile` (section 27), so the files exist wherever the code runs.
- **Cost:** `compile.rs` writes, refreshes and deletes the files, and refuses a hand-written `<Name>.php` beside a `<Name>.sharp`. One extra small include per PHP# class. No engine change.

The literal `tsc` variant, the unit itself written as `<Name>.php` inside a PHP envelope, is rejected. The engine would have to recognize a compiled `.php` file and check it against its `.sharp` source. Vendor units would stay in `.sharp/` or move into `vendor/`, so compiled output would live in two places or reverse decision 29. It gains no Capability over A.

### B. The engine shows PHP a `<Name>.php` that is not on disk

The engine's plain-files stream wrapper would list `<Name>.php` for each `<Name>.sharp` and answer `stat`, `realpath` and `include` for it.

- **Folders and version control:** nothing changes. This is the only Solution where a user never sees a PHP file.
- **Tooling:** `glob()` reads the disk directly: `php_glob` in `main/php_glob.c` calls the C library's `opendir` and `readdir`, not the stream wrapper. So `glob('*.php')` still misses every PHP# class. B covers some PHP scanners and not others, which fails the "any PHP code that scans `*.php`" requirement.
- **Analysis:** PHP sees files that git, editors, PHPStan's own file finder and deploy tools do not.
- **Cost:** changes in `main/streams/plain_wrapper.c` and the realpath cache, engine files every upstream merge meets.

### Rejected outright

- **A symbolic link `<Name>.php` to `<Name>.sharp`.** Observed: every scanner resolves it to `Name.sharp`, and Composer loads the link as PHP, so the engine prints the PHP# source as output.
- **PHP# files named `.php`.** It reverses section 27, where the engine, Composer, editors and the checker choose the grammar from the extension.
- **Teaching each library about `.sharp`,** as a Spatie or Laravel patch. Jordan rejects it.
- **Pointing scanners at `.sharp/`.** Every library would need its own setting, and the class name derived from `.sharp/app/…` is wrong.

## The first compile: the options and their cost

The failure reaches more than the extension host. Observed on a fresh tree: `vendor/bin/mago compile` and `php -r 'require "vendor/autoload.php";'` both die with the refusal. Laravel's skeleton runs `@php artisan package:discover` in `post-autoload-dump` (read in `laravel/laravel`'s `composer.json` on the 12.x branch), and `artisan` requires `vendor/autoload.php`. So `composer install` of a stock Laravel app on the engine meets it too. That last step was read, not run.

1. **The standard library ships precompiled.**
   - Fixes every case above.
   - Costs the most. The engine roots a file at the nearest `.sharp/`, and `compile.rs` refuses a `.sharp/` folder inside a package, so both rules need an exception for the library.
   - Each unit lists its inputs with the build machine's modification times. `sharp_stamp_current` hashes an input whose time differs, and keeps stamps for one request only, so after an install the engine would hash every input of every library unit on each request (read, not measured).
   - The split repository needs a CI step that compiles the library for each release, and the units are tied to the engine's unit format.
2. **`mago compile` compiles the library before it starts any extension host.**
   - Fixes only `mago compile` run as a binary.
   - `vendor/bin/mago` dies before the binary starts (observed).
   - `mago analyze` with a host fails the same way (observed). `mago lint` starts hosts too, in `initialize_external_linter` (read, not run).
   - `composer install` scripts that run PHP still fail.
3. **An autoloader that needs nothing compiled (recommended).**
   - `autoload.php`'s eager boot checks the engine only, as before 4567fd69.
   - The library's module is read when PHP# code first needs it, so a missing compile surfaces where PHP# code runs, with the same refusal.
   - Two ways to place the read:
     - **3a. In the plugin's `$includeFile`, before the first `.sharp` file it includes.** Costs one line in `autoload.php`. Any load that bypasses the plugin's loader skips the read: Solution A's `<Name>.php`, a direct `require` of a `.sharp` file, and opcache preloading. So 3a and Solution A contradict each other.
     - **3b. In the engine: `readModule` records the path, and the container reads it before it reads any other module or looks up any binding (recommended).** App modules are already read lazily, in `sharp_container_read_folders`, which `Container::get`, `Container::has`, building a class and `emit`'s dispatcher call. `sharp_container_binding` is the only reader of the bindings table. The library's module is read at the start of both, so it is still read before every app module and binding order is unchanged. The second place covers a lookup that reads no folder, as `Container::get` of a `list<…>` id, which skips `sharp_container_read_folders`. Costs a change to `ext/sharp/sharp.c`, which has a critical complexity rank, and a change to what a PHP-visible engine function does.

```diff
 ZEND_FUNCTION(Sharp_Internal_readModule)
 {
 	…
-	sharp_container_read(ZSTR_VAL(path), &SHARP_G(container_bindings));
+	SHARP_G(library_module) = zend_string_copy(path);
 }

 static void sharp_container_read_folders(const zend_string *file)
 {
+	sharp_container_read_library();
 	size_t root;
 …
 static zval *sharp_container_binding(const zend_string *id, const zend_string *namespace, const zend_class_entry *requester)
 {
+	sharp_container_read_library();
```

An extension host that boots the project's own PHP# code still cannot run before the project compiles. The library was the reported case, and the project's code is a question below.

## What changes

```text
sharp/mago/
├── src/commands/compile.rs              writes <Name>.php beside each accepted project .sharp file that declares a type,
│                                        rewrites only a file that starts with its own comment, deletes such a file
│                                        whose .sharp is gone, and refuses a hand-written <Name>.php beside a .sharp
├── crates/sharp-bridge/src/unit.rs      the beside path, next to compiled_path
└── tests/compile.rs                     rows: written, kept current, deleted with its source, refused beside a hand-
                                         written file, never written into vendor/, every unit still current after it
sharp/composer/
└── autoload.php                         the eager boot checks the engine and records the library's module, reads nothing
ext/sharp/
├── sharp.c                              the library_module global; readModule records the path, and
│                                        sharp_container_read_folders and sharp_container_binding read it first
└── sharp.stub.php, sharp_arginfo.h      only if decision 3 renames readModule
Zend/tests/sharp/
├── library_autoload_uncompiled.phpt     new: vendor/autoload.php loads with nothing compiled, then PHP# code resolves
│                                        Environment through the library's module
└── discovery.phpt                       new: a *.php scanner over a folder holding a .sharp class finds and loads it
docs/
├── spec.md                              section 27: the <Name>.php rule, and the refusal's fix that now works
├── decisions/0NN-discovery.md           new: Solution A, with B and the rejected Solutions
└── architecture/modules.md              the Engine list: the library's module is read with the other modules
```

The extension-host probe's worker exits with status 1 after it loads `vendor/autoload.php`, so `mago` reports "worker closed stdout" even after the fix (observed with the library compiled). Phase 2's fixture runs `Mago\Sdk\Worker` with no extension instead, so the compile can pass.

## Decisions team-lead approves

1. **Solution A for discovery,** with the file's content as shown, for project files only.
2. **Option 3b for the first compile.** It removes the one module the engine reads eagerly, so every module is read the same way.
3. **`Sharp\Internal\readModule` no longer reads.** Its callsite in `autoload.php` is `\Sharp\Internal\readModule(__DIR__ . '/library/Sharp/Module.sharp');`. The word that changes is the verb:
   - `readModule`: keep. Reads "read the module (now)", which stops being true.
   - `addModule`: what the container gains. Reads "add the module at this path to the container's modules".
   - `useModule`: what the caller wants. Reads "use the module at this path". Close to `addModule`.
   - `deferModule`: when it happens. Says how the code works, not what the caller gets.
   - `libraryModule`: names the module itself. A noun for a call that changes state.

   Recommended: `addModule`, because the sentence stays true whenever the read happens. Runner-up: keep `readModule`, since only `autoload.php` and two test helpers call it.
4. **A new decision file** records Solution A, because it puts generated files in a user's source folders, which is hard to reverse.

## Questions for Jordan

Each one decides what appears in a user's project. The note recommends and decides none.

1. **Is a generated `<Name>.php` beside each `.sharp` file acceptable in a user's folders?** It is the only Solution that every `*.php` scanner sees, and the user sees it too. Recommended: yes, hidden by the PHP# editor setup, as `tsc`'s `.js` files are. The alternative is B, which hides it from users but misses every scanner that uses `glob()`.
2. **How does version control skip the generated files?** A pattern can't. Recommended: `mago compile` keeps their paths in `.git/info/exclude`, which belongs to the clone, is never committed, and is rebuilt by each clone's first compile. The alternatives are a block `mago compile` rewrites in the project's `.gitignore`, which commits a list that changes with every new class, or committing the files.
3. **Should a package's PHP# classes get the file too?** A package that discovers its own classes, as one that loads its console commands from a folder, needs it. Recommended: not now, because it writes into `vendor/`, which decision 29 rules out, and no case has come up.
4. **Should `mago compile` work when an extension host boots the project's own PHP# code?** After the fix, the library loads, but a host that runs project PHP# code still fails until a first compile without it. Recommended: leave it, because a host's facts can change what compiles. The alternative is a first pass without hosts.

## Found, not fixed

1. **Composer's class map never lists a PHP# class.** After `composer dump-autoload --optimize`, `$loader->getClassMap()` holds `App\Http\Controllers\PlainController` but not `App\Http\Controllers\SharpController`, which only the plugin's `autoload_sharp.php` lists. Discovery that reads the class map instead of the folders misses PHP# classes. Solution A does not change this, because the file it writes declares no class. Probe: [the class map](#the-class-map).
2. **A `.php` link to a `.sharp` file prints the PHP# source.** Composer's PSR-4 finds the link, the engine compiles it as PHP because of its name, and the source reaches the output. A user who tries a link to fix discovery leaks code. Probe: [the link](#the-link).

## Evidence

Each fixture is a Composer project that requires `heyjordanparker/php-sharp-composer` from `sharp/composer/` at 754ab7ce08, installed as a copy, with `"App\\": "app/"` as its PSR-4 root. Each command ran as `docker run --rm -v <fixture>:/p -v <mago>:/usr/local/bin/mago:ro -w /p php-sharp:754ab7ce08 sh -c '<command>'`.

### The first compile

`app/Greeting.sharp` holds one class. `mago.toml`:

```toml
php-version = "8.5"

[source]
paths = ["app"]
includes = ["vendor"]

[extension-hosts.local]
workers = 1
command = ["php", "tools/worker.php"]
```

`tools/worker.php` requires `vendor/autoload.php`, writes what happened to `worker-result.txt`, and exits with status 1.

```text
$ rm -rf .sharp; mago --no-version-check --colors never compile
ERROR Orchestrator error: External plugin error: external analyzer worker failed: extension worker 0 disconnected: worker closed stdout
compile exit: 2
worker saw: CompileError: /p/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp isn't compiled. Run vendor/bin/mago compile.
ls .sharp: No such file or directory
```

A second `compile`, and `mago analyze`, fail the same way, with the same `worker saw:` line. Without the host, the same project compiles, and then the host loads:

```text
$ mago --config mago.nohost.toml compile
 INFO Compiled 51 PHP# files into .sharp/.
compile without host exit: 0
$ mago compile
compile with host, library compiled: 2
worker saw: autoload loaded
```

Any PHP script, and the documented command, on a fresh tree:

```text
$ rm -rf .sharp; php -r 'require "vendor/autoload.php"; echo "loaded\n";'
Fatal error: /p/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp isn't compiled. Run vendor/bin/mago compile. in /p/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp on line 1
exit: 255

$ rm -rf .sharp; vendor/bin/mago compile        # heyjordanparker/mago-sharp installed from sharp/mago/
Fatal error: /p/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp isn't compiled. Run vendor/bin/mago compile. in /p/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Module.sharp on line 1
vendor/bin/mago compile exit: 255
```

`autoload.php` at 4567fd69's parent runs `requireNative` alone in its eager boot, with no `readModule`.

### The scanners

```text
app/
├── Route.php                               #[\Attribute(\Attribute::TARGET_CLASS)] final class Route
└── Http/Controllers/
    ├── PlainController.php                 #[Route('/plain')] final class PlainController
    └── SharpController.sharp               [Route("/sharp")] public class SharpController
```

`scan.php` lists `app/Http/Controllers` three ways: a `RecursiveDirectoryIterator` filtered to `*.php`, `glob('*.php')`, and every file with `.php` dropped. Each path becomes `App\` plus its real path under `app/`, with `.php` dropped. For each class that exists, it prints the `Route` attribute's path.

```text
$ rm -rf .sharp; mago compile; php scan.php
 INFO Compiled 51 PHP# files into .sharp/.
directory iterator, *.php:
  App\Http\Controllers\PlainController /plain
glob, *.php:
  App\Http\Controllers\PlainController /plain
every file, .php stripped:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController.sharp (no class)
loaded by name: yes
```

The same fixture with Solution A's `SharpController.php`, written by hand as shown above:

```text
$ rm -rf .sharp; mago compile; php scan.php
 INFO Compiled 51 PHP# files into .sharp/.
directory iterator, *.php:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
glob, *.php:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
every file, .php stripped:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
  App\Http\Controllers\SharpController.sharp (no class)
loaded by name: yes

$ php -r 'require "app/Http/Controllers/SharpController.php";'      # plain PHP 8.5.1, outside the image
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
```

The `.sharp` line in the last listing is the source itself, which event discovery already skips, as it skips any file that is no class.

### The class map

```text
$ composer dump-autoload --optimize
$ php -r '$map = (require "vendor/autoload.php")->getClassMap(); …'
App\Http\Controllers\PlainController in Composer class map: yes
App\Http\Controllers\SharpController in Composer class map: no
in autoload_sharp.php: yes
```

### The link

```text
$ ln -s SharpController.sharp app/Http/Controllers/SharpController.php; php scan.php
directory iterator, *.php:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController.sharp (no class)
…
loaded by name: namespace App.Http.Controllers;

import App.Route;

[Route("/sharp")]
public class SharpController
{
}
no
```

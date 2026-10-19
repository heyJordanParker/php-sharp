# 81. PHP code that lists or reads `*.php` files finds PHP# classes

## Decision

`mago compile` writes a `.php` file beside each of the project's `.sharp` files that declares a type, as `StoreService.php` beside `StoreService.sharp`. The file loads its `.sharp` file through the engine, and on plain PHP it throws the "needs the PHP# engine" error, so no source reaches the output. Its last statement states the type's declaration and never runs, so PHP code that reads `*.php` source finds the type in it with no change of its own, as Composer's class map generator and Symfony's attribute routing do. The engine declares the type once, from the `.sharp` file, and mago reads the `.sharp` file and never the `.php` file:

```php
require_once __DIR__ . '/StoreService.sharp';

// The engine declares StoreService from StoreService.sharp, and this declaration never runs.
if (false) {
    class StoreService
    {
    }
}
```

The declaration names the type and the kind PHP's reflection gives it: `interface`, `enum` for an enum that runs as a PHP enum, and `class` for a class, a struct, an event and an enum whose cases carry data.

A type named after a word PHP reserves, compared ignoring case, as `Case`, `Match`, `Print`, `Function`, `Echo`, `Include` or `Global`, gets the file without the declaration. Spec section 1 makes a capitalized keyword an ordinary PHP# name, but PHP refuses a reserved word as a type's name even in a declaration that never runs, so the file would not parse. No PHP source can declare such a type, so no source scanner finds it: Symfony's attribute routing reads the word after `class` as the keyword's token, not as a name, and Composer's class map lists only what PHP source declares. The file still parses and loads the type, through Composer's PSR-4 rule and through PHP code that names a class by its path. A project whose class map is authoritative, built with `--classmap-authoritative`, never reaches PSR-4, so it loads such a type only once it is named otherwise.

`mago compile` writes the installed standard library's native fingerprint into each compiled file, and the engine refuses a compiled file whose fingerprint is not its own, whatever loads it: the `.php` file, a `require`, the plugin's loader, opcache or preloading. Composer's own process runs on any engine, so `composer install` runs there and prints the refusal as an error line. PHP code that lists a folder's `*.php` files and names each class by its path, as Spatie's route attributes, Laravel's console kernel and Laravel's event discovery do, finds and loads the PHP# class with no change of its own.

Nobody edits the file. A `.php` file someone wrote beside a `.sharp` file, under its name in any case, is a compile error, because PHP names a class whatever its case. The next compile rewrites a changed file and deletes the file of a removed source, which until then declares nothing. In a git repository, `mago compile` lists the files no `.gitignore` already ignores in a block of `.git/info/exclude`, one per checkout. Editors hide them, as they hide `tsc`'s `.js` files: VS Code through `"files.exclude": {"**/*.php": {"when": "$(basename).sharp"}}`, PhpStorm through file nesting.

A `.sharp` file in an `autoload-dev` folder gets no file, so a test class keeps one file: PHPUnit loads a test class only from the file it lists, and a project lists its PHP# tests with `suffix="Test.sharp"`. A package in `vendor/` gets none, because nothing is written into `vendor/` (decision 29).

Decision 29's compiled output still lives in one folder, `.sharp/`. A deploy's `mago compile` writes the `.php` files beside the sources too.

## Options

### Chosen: a `.php` file beside each `.sharp` file

```text
app/Http/Controllers/
├── OrderController.sharp
└── OrderController.php        <- written by mago compile: loads OrderController.sharp
```

Every listing sees a real file: Symfony's Finder, `glob()`, `scandir()` and `find`. The class name a scanner builds from the path is right, and Composer's PSR-4 rule loads the `.php` file first. Compiled output stays in `.sharp/`.

### Chosen: the file states the type's name and kind in a declaration that never runs

Composer's class map generator matches `class Order` in the code that `php_strip_whitespace` leaves, and Symfony's attribute routing reads the name after the `class` token. Both find the type and load it through Composer, and the engine declares it from the `.sharp` file.

### Rejected: the declaration in a comment

`php_strip_whitespace` and every tokenizer drop comments, so no scanner would see it.

### Rejected: a reserved name's declaration after `__halt_compiler();`

PHP never reads past `__halt_compiler();`, so `class Case {}` there parses, and Composer's class map, which strips strings and comments but not what follows the halt, would list it. Symfony's tokens read it as inline text, so attribute routing still finds nothing, and the class map would depend on Composer's cleaner not knowing a part of PHP's grammar.

### Rejected: a declaration that runs

PHP binds a top-level class when it compiles the file, before any statement runs, so the file would declare a second `StoreService` beside the one the engine declares from the `.sharp` file.

### Rejected: the declaration with its members

A scanner reads the type's name and kind from source, and reads the rest through reflection, which gets the engine's class. Members would only repeat the `.sharp` file.

### Rejected: the engine shows PHP a `.php` file that is not on disk

The engine's plain-files stream wrapper would list `OrderController.php` and answer `stat`, `realpath` and `include` for it. `glob()` reads the disk directly, not through the stream wrapper, so `glob('*.php')` still misses every PHP# class. It would also change engine files every upstream merge meets.

### Rejected: a symbolic link `OrderController.php` to `OrderController.sharp`

Every scanner resolves the link with `getRealPath()` and names the class `OrderController.sharp`, and Composer loads the link as PHP, so the engine prints the PHP# source as output.

### Rejected: PHP# files named `.php`

The engine, Composer, editors and the checker choose the grammar from the extension (spec section 27).

### Rejected: the compiled file itself as `OrderController.php`

The engine would have to recognize a compiled `.php` file and check it against its `.sharp` source, and a package's compiled files would stay in `.sharp/` or move into `vendor/`.

### Rejected: teaching each library about `.sharp`

Every library would need its own change, and a user would choose libraries by whether they know PHP#.

## Precedent

- **Chosen:** TypeScript's `tsc`, which writes `.js` beside `.ts` where every JavaScript tool already looks, and Kotlin, whose `.class` files Java's classpath scanners find because they sit where those scanners look.
- **Chosen, the declaration:** TypeScript's `.d.ts` file, a generated declaration that tools read and that emits no code, which `tsc` never reads in place of the `.ts` file beside it, and Kotlin's light classes, the Java declarations the IDE derives from Kotlin source for Java tools.
- **Chosen, hiding the files:** VS Code's `files.exclude` sibling condition and JetBrains' file nesting, made for `tsc`'s output.

## Spec

- [Section 27, PHP# files](../spec.md#27-php-files)

# 81. PHP code that lists `*.php` files finds PHP# classes

## Decision

`mago compile` writes a `.php` file beside each of the project's `.sharp` files that declares a type, as `StoreService.php` beside `StoreService.sharp`. The file only loads its `.sharp` file through the engine, and on plain PHP it throws the "needs the PHP# engine" error, so no source reaches the output. PHP code that lists a folder's `*.php` files and names each class by its path, as Spatie's route attributes, Laravel's console kernel and Laravel's event discovery do, finds and loads the PHP# class with no change of its own.

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
- **Chosen, hiding the files:** VS Code's `files.exclude` sibling condition and JetBrains' file nesting, made for `tsc`'s output.

## Spec

- [Section 27, PHP# files](../spec.md#27-php-files)

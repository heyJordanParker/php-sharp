# 81. PHP# classes load by name, and no folder scan finds them

## Decision

`mago compile` writes nothing beside a `.sharp` file, so a PHP# project's tree holds only what its authors wrote. Its compiled output lives in `.sharp/` (decision 29).

A PHP# class loads by its name. The standard library's autoloader finds `<Class>.sharp` by Composer's PSR-4 prefixes, and the Composer plugin lists every PHP# class in `vendor/composer/autoload_sharp.php` on an optimized dump.

PHP code that lists a folder's `*.php` files and names each class by its path finds no PHP# class. Laravel's console kernel, Laravel's event discovery and Spatie's route attributes find classes this way. An app names each PHP# class through the framework's own explicit API instead:

```php
->withCommands([SendReport::class])                   // a console command, in bootstrap/app.php
Event::listen(Login::class, RecordLogin::class);      // a listener
```

A controller is named in a route file, or with `RouteRegistrar::registerClass`.

A `.php` file and a `.sharp` file that declare one class are two declarations of it, which `mago analyze` refuses with `duplicate-definition`.

## Options

### Chosen: the app names each PHP# class

```text
app/Http/Controllers/
└── OrderController.sharp      <- the only file; a route file names OrderController
```

A framework's explicit registration names the class, and the autoloader loads it by that name. The source tree holds one language, and `mago compile` carries no code for files of another.

### Rejected: a `.php` file beside each `.sharp` file

```text
app/Http/Controllers/
├── OrderController.sharp
└── OrderController.php        <- not PHP#: a PHP file mago compile wrote, which loads OrderController.sharp
```

Every listing saw a real file, so a folder scan found the class. The source tree filled with files in another language that nobody wrote, and each needed more: a `.gitignore` in each folder to hide them, a compile error for a `.php` file someone wrote under the same name, editor and formatter settings to hide or skip them, and code that deleted the files an earlier compile wrote. `mago compile` wrote these files from 0.4.0 until this decision.

### Rejected: the engine shows PHP a `.php` file that is not on disk

The engine's plain-files stream wrapper would list `OrderController.php` and answer `stat`, `realpath` and `include` for it. `glob()` reads the disk directly, not through the stream wrapper, so `glob('*.php')` still misses every PHP# class. It would also change engine files every upstream merge meets.

### Rejected: a symbolic link `OrderController.php` to `OrderController.sharp`

Every scanner resolves the link with `getRealPath()` and names the class `OrderController.sharp`, and Composer loads the link as PHP, so the engine prints the PHP# source as output.

### Rejected: PHP# files named `.php`

The engine, Composer, editors and the checker choose the grammar from the extension (spec section 27).

## Precedent

- **Earlier decisions:** this reverses this decision's earlier choice of a `.php` file beside each `.sharp` file, and keeps decision 29's single `.sharp/` output folder.

## Spec

- [Section 27, PHP# files](../spec.md#27-php-files)

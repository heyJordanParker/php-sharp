<?php

/**
 * Loads each type of the PHP# fixture folder `$name` the first time code names it, from the `.sharp` file named like
 * the type in the namespace it declares, as the Composer plugin's classmap maps a project's files. A `.sharp` file
 * declares one type, spec section 27, so a fixture of several types is a folder, and its files load in whatever order
 * the code reaches them. A class a type declares inside its name, as an interface's `HasDesign\Defaults` trait or an
 * enum case's `Verdict\Ruled`, spec section 22, loads from its type's file. With `$extension` `inc`, it loads each
 * type's plain PHP twin beside its file instead.
 */
function fixture(string $name, string $extension = 'sharp'): void
{
    $folder = dirname(__DIR__) . "/$name";
    if (!is_dir($folder)) {
        throw new LogicException("No fixture folder $folder.");
    }

    $files = [];
    foreach (glob("$folder/*.sharp") as $file) {
        $namespace = preg_match('{^namespace\s+([\w.]+)\s*;}m', file_get_contents($file), $match) === 1
            ? strtr($match[1], '.', '\\') . '\\'
            : '';
        $files[strtolower($namespace . basename($file, '.sharp'))] = substr($file, 0, -strlen('sharp')) . $extension;
    }
    fixture_files($files);
    // A named function, unlike a closure, takes no object handle, so the objects a test prints keep their numbers.
    spl_autoload_register('fixture_load');
}

/** The file of each class the loaded fixtures declare, by its lowercase name, with `$files` added. */
function fixture_files(array $files = []): array
{
    static $known = [];

    return $known += $files;
}

function fixture_load(string $class): void
{
    $files = fixture_files();
    $owner = substr($class, 0, (int) strrpos($class, '\\'));
    $file = $files[strtolower($class)] ?? $files[strtolower($owner)] ?? null;
    if ($file !== null) {
        require_once $file;
    }
}

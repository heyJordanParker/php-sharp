--TEST--
Spec section 27: PHP code that lists a folder's *.php files and names each class by its path, as route-attribute and event discovery do, finds and loads the PHP# class of that folder through the PHP file mago compile writes beside its source
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('discovery', [
    'mago.toml' => "[source]\npaths = [\"app\"]\n",
    'app/Route.php' => <<<'PHP'
<?php

namespace App;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Route
{
    public function __construct(public string $path)
    {
    }
}
PHP,
    'app/Http/Controllers/PlainController.php' => <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Route;

#[Route('/plain')]
final class PlainController
{
}
PHP,
    'app/Http/Controllers/SharpController.sharp' => <<<'SHARP'
namespace App.Http.Controllers;

import App.Route;

[Route("/sharp")]
public class SharpController
{
}
SHARP,
]);

// Composer's PSR-4 rule for App\, which loads a class's .php file.
spl_autoload_register(static function (string $class) use ($root): void {
    $file = "$root/app/" . str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
    if (str_starts_with($class, 'App\\') && is_file($file)) {
        require $file;
    }
});

$folder = "$root/app/Http/Controllers";
$listings = [
    'a directory iterator' => array_map(
        static fn (SplFileInfo $file): string => $file->getRealPath(),
        iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)), '/\.php$/'), false),
    ),
    'glob' => glob("$folder/*.php"),
];
foreach ($listings as $listing => $paths) {
    $found = [];
    foreach ($paths as $path) {
        $class = 'App\\' . str_replace('/', '\\', substr($path, strlen("$root/app/"), -strlen('.php')));
        $routes = array_map(static fn (ReflectionAttribute $route): string => $route->newInstance()->path, (new ReflectionClass($class))->getAttributes(App\Route::class));
        $found[] = "$class " . implode(', ', $routes);
    }
    sort($found);
    echo "$listing:\n  ", implode("\n  ", $found), "\n";
}

remove_project($root);
?>
--EXPECT--
a directory iterator:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
glob:
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp

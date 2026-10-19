--TEST--
Spec section 27: PHP code that lists a folder's *.php files and names each class by its path, as route-attribute and event discovery do, finds and loads the PHP# class of that folder through the PHP file mago compile writes beside its source, and finds no class through the file of a source deleted since
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
    'app/Http/Controllers/GoneController.sharp' => <<<'SHARP'
namespace App.Http.Controllers;

public class GoneController
{
}
SHARP,
    'app/Kinds/Shape.sharp' => "namespace App.Kinds;\n\npublic interface Shape\n{\n}\n",
    'app/Kinds/Size.sharp' => "namespace App.Kinds;\n\npublic enum Size\n{\n    case Small;\n    case Large;\n}\n",
    'app/Kinds/Outcome.sharp' => "namespace App.Kinds;\n\npublic enum Outcome\n{\n    case Paid(string id);\n    case Declined;\n}\n",
    'app/Kinds/Spot.sharp' => "namespace App.Kinds;\n\npublic struct Spot\n{\n    public Spot(public int x { get; }) { }\n}\n",
    'app/Kinds/Placed.sharp' => "namespace App.Kinds;\n\npublic event Placed(int orderId);\n",
]);
// A source deleted since the last compile leaves its PHP file, which declares nothing until the next compile deletes it.
unlink("$root/app/Http/Controllers/GoneController.sharp");

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
        if (!class_exists($class)) {
            $found[] = "$class (no class)";
            continue;
        }
        $routes = array_map(static fn (ReflectionAttribute $route): string => $route->newInstance()->path, (new ReflectionClass($class))->getAttributes(App\Route::class));
        $found[] = "$class " . implode(', ', $routes);
    }
    sort($found);
    echo "$listing:\n  ", implode("\n  ", $found), "\n";
}

// A tool that reads PHP source and finds the type its tokens declare, as Composer's class map and Symfony's attribute
// routing do, finds each PHP# type in its PHP file, of the kind reflection gives it, and the engine declares it once.
$read = static function (string $path): string {
    $namespace = '';
    $tokens = PhpToken::tokenize(file_get_contents($path));
    foreach ($tokens as $index => $token) {
        if ($token->is(T_NAMESPACE)) {
            $namespace = $tokens[$index + 2]->text . '\\';
        }
        if ($token->is([T_CLASS, T_INTERFACE, T_ENUM])) {
            return "$token->text $namespace{$tokens[$index + 2]->text}";
        }
    }

    return 'no type';
};
$kind = static function (string $type): string {
    $reflection = new ReflectionClass($type);

    return $reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : 'class');
};
$read_files = glob("$root/app/{Http/Controllers,Kinds}/*.php", GLOB_BRACE);
sort($read_files);
foreach ($read_files as $path) {
    $stated = $read($path);
    $type = explode(' ', $stated)[1] ?? null;
    $loaded = $type !== null && (class_exists($type) || interface_exists($type)) ? 'reflection: ' . $kind($type) : 'no type loads';
    echo substr($path, strlen("$root/")), ": $stated, $loaded\n";
}

remove_project($root);
?>
--EXPECT--
a directory iterator:
  App\Http\Controllers\GoneController (no class)
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
glob:
  App\Http\Controllers\GoneController (no class)
  App\Http\Controllers\PlainController /plain
  App\Http\Controllers\SharpController /sharp
app/Http/Controllers/GoneController.php: class App\Http\Controllers\GoneController, no type loads
app/Http/Controllers/PlainController.php: class App\Http\Controllers\PlainController, reflection: class
app/Http/Controllers/SharpController.php: class App\Http\Controllers\SharpController, reflection: class
app/Kinds/Outcome.php: class App\Kinds\Outcome, reflection: class
app/Kinds/Placed.php: class App\Kinds\Placed, reflection: class
app/Kinds/Shape.php: interface App\Kinds\Shape, reflection: interface
app/Kinds/Size.php: enum App\Kinds\Size, reflection: enum
app/Kinds/Spot.php: class App\Kinds\Spot, reflection: class

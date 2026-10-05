--TEST--
PHP# runs the deepest nesting it allows and raises ParseError past it, on the main stack and inside a Fiber
--FILE--
<?php

// A method returning a sum of 509 terms nests its innermost term 512 levels deep, the most PHP# allows. A sum of
// 100,000 terms and a return type of 100,000 members nest far deeper.
function write(string $name, string $code): string {
    $path = __DIR__ . "/nesting_$name.sharp";
    file_put_contents($path, "namespace App;\n\nclass Deep$name\n{\n$code}\n");

    return $path;
}

function sum(string $name, int $terms): string {
    return write($name, "    public int run(int extra)\n    {\n        return " . implode(' + ', array_fill(0, $terms, 'extra')) . ";\n    }\n");
}

$union = write('Union', '    public ' . implode('|', array_map(fn ($index) => "A$index", range(0, 99_999))) . " run()\n    {\n        return null;\n    }\n");
$deepest = ['Main' => sum('Main', 509), 'Fiber' => sum('Fiber', 509)];
$tooDeep = sum('Sum', 100_000);

function run(string $where, string $deepest, string ...$tooDeep): void {
    require $deepest;
    $class = "App\\Deep$where";
    echo "$where: ", (new $class())->run(1), "\n";

    foreach ($tooDeep as $path) {
        try {
            require $path;
        } catch (ParseError $e) {
            echo "$where: ", get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
        }
    }
}

run('Main', $deepest['Main'], $tooDeep, $union);
(new Fiber(fn () => run('Fiber', $deepest['Fiber'], $tooDeep, $union)))->start();
?>
--CLEAN--
<?php
foreach (['Main', 'Fiber', 'Sum', 'Union'] as $name) {
    @unlink(__DIR__ . "/nesting_$name.sharp");
}
?>
--EXPECT--
Main: 509
Main: ParseError: PHP# nests statements, expressions and types at most 512 levels deep. in nesting_Sum.sharp on line 7
Main: ParseError: PHP# nests statements, expressions and types at most 512 levels deep. in nesting_Union.sharp on line 5
Fiber: 509
Fiber: ParseError: PHP# nests statements, expressions and types at most 512 levels deep. in nesting_Sum.sharp on line 7
Fiber: ParseError: PHP# nests statements, expressions and types at most 512 levels deep. in nesting_Union.sharp on line 5

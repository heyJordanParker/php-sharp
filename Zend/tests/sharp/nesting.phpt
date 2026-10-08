--TEST--
PHP# runs the deepest nesting it allows, on the main stack and inside a Fiber
--FILE--
<?php

require __DIR__ . '/project.inc';

// A method returning a sum of 509 terms nests its innermost term 512 levels deep, the most PHP# allows. mago compile
// refuses anything deeper.
$sum = implode(' + ', array_fill(0, 509, 'extra'));
$root = compile_project('nesting', [
    'DeepMain.sharp' => "namespace App;\n\nclass DeepMain\n{\n    public int run(int extra)\n    {\n        return $sum;\n    }\n}\n",
    'DeepFiber.sharp' => "namespace App;\n\nclass DeepFiber\n{\n    public int run(int extra)\n    {\n        return $sum;\n    }\n}\n",
]);

function run(string $root, string $where): void {
    require "$root/Deep$where.sharp";
    $class = "App\\Deep$where";
    echo "$where: ", (new $class())->run(1), "\n";
}

run($root, 'Main');
(new Fiber(fn () => run($root, 'Fiber')))->start();
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-nesting');
?>
--EXPECT--
Main: 509
Fiber: 509

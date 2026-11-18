--TEST--
A Map or a Set field's initial value is a new object for each object, and plain PHP still writes no new there
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    $first = new Demo\Tallies();
    $second = new Demo\Tallies();
    $first->count('tea');
    $first->see('tea');
    echo 'each object its own Map: ', json_encode($first->count('tea')), ' ', json_encode($second->count('jam')), "\n";
    echo 'each object its own Set: ', json_encode($first->see('pie')), ' ', json_encode($second->see('bun')), "\n";
    echo 'a static Map starts empty: ', json_encode(Demo\Tallies::started()), ' ', json_encode(Demo\Tallies::without('tea')), "\n";
    $output = ob_get_clean();
}
echo $output;

// Plain PHP still writes no new in a property's initial value: only a PHP# Map or Set field starts as an object.
$plain = sys_get_temp_dir() . '/sharp-plain-default-' . getmypid() . '.php';
file_put_contents($plain, <<<'PHP'
<?php

class PlainDefault
{
    public Sharp\Map $prices = new Sharp\Map();
}
PHP);
register_shutdown_function(static fn () => unlink($plain));
require $plain;
?>
--EXPECTF--
each object its own Map: {"tea":2} {"jam":1}
each object its own Set: ["start","tea","pie"] ["start","bun"]
a static Map starts empty: [] []

Fatal error: New expressions are not supported in this context in %ssharp-plain-default-%d.php on line 5

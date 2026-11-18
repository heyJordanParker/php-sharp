--TEST--
PHP# reads a plain PHP collection's values by the type its docblock states, and compiles with strict_types=1, so a value of another type plain PHP put there fails PHP's own check where PHP# uses it: as an argument, as a result, and as a list's index
--FILE--
<?php

namespace Demo;

use Illuminate\Support\Collection;

require __DIR__ . '/vendor/autoload.php';

foreach ([
    fn () => Strict::run(new Collection(['5'])),
    fn () => Strict::must(new Collection([4])),
    fn () => Strict::must(new Collection([null])),
    fn () => Strict::at([3], new Collection([0])),
    fn () => Strict::at([3], new Collection(['closed'])),
    fn () => Strict::at([3], new Collection([new \stdClass()])),
] as $call) {
    try {
        var_dump($call());
    } catch (\Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), ' on line ', $e->getLine(), "\n";
    }
}
?>
--EXPECTF--
TypeError: Demo\Helper::take(): Argument #1 ($value) must be of type int, string given, called in %sStrict.sharp on line 11 on line 5
int(4)
TypeError: Demo\Strict::must(): Return value must be of type int, null returned on line 16
int(3)
OutOfRangeException: Undefined array key "closed" on line 21
TypeError: Cannot access offset of type stdClass on array on line 21

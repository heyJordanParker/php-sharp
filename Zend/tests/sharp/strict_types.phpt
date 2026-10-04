--TEST--
PHP# compiles a .sharp file with strict_types=1
--FILE--
<?php

namespace Demo;

class Helper
{
    public static function take(int $value): int
    {
        return $value;
    }
}

require __DIR__ . '/Strict.sharp';

try {
    var_dump(Strict::run());
} catch (\TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
Demo\Helper::take(): Argument #1 ($value) must be of type int, string given, called in %sStrict.sharp on line 7

--TEST--
PHP# a Map keyed by a backed enum reaches a plain PHP array parameter with each case's backing value as its key, passed, spread and nullable
--FILE--
<?php
require __DIR__ . '/harness/Standing.inc';
require __DIR__ . '/harness/Tally.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('BackedKeys');

$roll = new BackedKeys\Roll();
echo $roll->described(), "\n";
echo $roll->sized(), "\n";
echo $roll->spread(), "\n";
echo $roll->maybe(null), "\n";
?>
--EXPECT--
{"active":2,"closed":1}
{"1":"s","2":"l"}
{"active":1};{"closed":2}
none

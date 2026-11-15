--TEST--
PHP# a class value keys a Map and joins a Set by its whole type, so two type arguments of one class are two keys
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ClassKeys');

echo ClassKeys\Registry::handlers(), "\n";
echo ClassKeys\Registry::kinds(), "\n";
var_dump(ClassKeys\Registry::found());
?>
--EXPECT--
2 numbers strings
3
bool(true)

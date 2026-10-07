--TEST--
PHP# typeof(X) is the full name of the public class X
--FILE--
<?php

require __DIR__ . '/Kinds.sharp';

var_dump(Demo\Kinds::own());
var_dump(class_exists(Demo\Kinds::own()));
?>
--EXPECT--
string(10) "Demo\Kinds"
bool(true)

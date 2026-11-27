--TEST--
PHP# an interface's default body reaches the internal members of the namespace it is written in, when a class of another namespace implements the interface and runs it
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(InterfaceReachOutside\Run::opened());
var_dump(InterfaceReachOutside\Run::seal());
?>
--EXPECT--
int(1)
int(7)

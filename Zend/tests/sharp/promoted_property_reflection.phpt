--TEST--
A constructor-promoted property of a class open to subclasses is closed, as a property in the class body is: reflection reads it without `virtual`, and fields() gives its storage
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", Promoted\Report::facts()), "\n";
?>
--EXPECT--
property key
property message
field key notice
field message saved

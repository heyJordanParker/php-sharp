--TEST--
An extern line's List or Map parameter defaults to [], so a PHP# call that leaves it out passes plain PHP an empty array, the line's default and not plain PHP's own, and a call that passes a list passes it
--FILE--
<?php
require __DIR__ . '/harness/Database.inc';
require __DIR__ . '/vendor/autoload.php';

use ExternListDefaults\Reports;

echo Reports::bound(), "\n";
echo Reports::configured(), "\n";
echo Reports::given(), "\n";
?>
--EXPECT--
0
0
2

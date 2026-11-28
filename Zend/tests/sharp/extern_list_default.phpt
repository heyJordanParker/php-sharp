--TEST--
An extern line's List parameter defaults to [] and its Map parameter to [:], so a PHP# call that leaves one out passes plain PHP an empty array, the line's default and not plain PHP's own, and a call that passes a list passes it
--FILE--
<?php
require __DIR__ . '/harness/Database.inc';
require __DIR__ . '/vendor/autoload.php';

use ExternListDefaults\Reports;

echo Reports::bound(), "\n";
echo Reports::recent(), "\n";
echo Reports::given(), "\n";
var_dump(Reports::headers());
?>
--EXPECT--
0
0
2
array(0) {
}

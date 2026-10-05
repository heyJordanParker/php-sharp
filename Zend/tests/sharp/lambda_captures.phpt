--TEST--
PHP# lambdas capture the variables themselves, give each loop pass its own let, and pass to plain PHP functions
--FILE--
<?php
require __DIR__ . '/lambda_captures.inc';
?>
--EXPECT--
17
2,4,6
0,102,101
18
17
2,4,6
0,102,101
18

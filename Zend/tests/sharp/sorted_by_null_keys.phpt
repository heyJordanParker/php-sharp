--TEST--
PHP# sortedBy puts a null key before every other key, whatever order the keys come in
--FILE--
<?php
require __DIR__ . '/sorted_by_null_keys.inc';
?>
--EXPECT--
NULL -2 0 1 3
NULL -2 0 1 3
NULL -2 0 1 3
NULL '' 'B' 'a' 'b'
NULL '' 'B' 'a' 'b'
NULL -2 0 1 3
NULL -2 0 1 3
7 5 -2 0 1 3
5 7 -2 0 1 3

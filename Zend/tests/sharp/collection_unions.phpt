--TEST--
PHP# List, Map and Set share a union as every other type does, and is tells them apart
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Unions');

echo json_encode(Unions\Counts::kinds()), "\n";
?>
--EXPECT--
["list","map","set"]

--TEST--
PHP# a default reads one field of the caller's position, as int line = Position.current().line, which a direct call fills with its own and a call through a function value with the declaration's
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('CallerLines');

echo json_encode(CallerLines\Log::run()), "\n";
?>
--EXPECT--
["12","40","CallerLines.Log.run","5"]

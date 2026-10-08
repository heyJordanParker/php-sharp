--TEST--
json_encode never writes a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

echo json_encode(App\Queue::orders()), "\n";
echo json_encode(App\Queue::pair()), "\n";
?>
--EXPECT--
{"items":[]}
{"key":{"id":2},"value":3}

--TEST--
`?.` reads a nullable named tuple's element by its name when the tuple is present, as `.` does, spec sections 14.4 and 30: from a parameter, a field, a method's result, a local after `??=` and a nested `a?.b?.c`, and it calls the function an element holds, and gives null for an absent tuple
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\NullableTuples;

echo 'repro: ', NullableTuples::repro(), "\n";
echo 'parameter: ', NullableTuples::parameter([8, 'close']), "\n";
echo 'absent: ', NullableTuples::parameter(null), "\n";
echo 'field: ', (new NullableTuples())->field(), "\n";
echo 'returned: ', NullableTuples::returned(), "\n";
echo 'assigned: ', NullableTuples::assigned(), "\n";
echo 'nested: ', NullableTuples::nested(), "\n";
echo 'narrowed: ', NullableTuples::narrowed([9, 'close']), "\n";
echo 'called: ', NullableTuples::called([1, fn (int $n): int => $n * 2]), "\n";
echo 'called absent: ', NullableTuples::called(null), "\n";
?>
--EXPECT--
repro: open close
parameter: close
absent: null
field: close
returned: close
assigned: close
nested: close
narrowed: close
called: 4
called absent: -1

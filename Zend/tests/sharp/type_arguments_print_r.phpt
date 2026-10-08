--TEST--
print_r never shows a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

print_r(App\Queue::orders());
?>
--EXPECT--
App\PaginatedList Object
(
    [items] => Array
        (
        )

)

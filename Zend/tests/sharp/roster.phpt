--TEST--
A PHP# Map keyed by a backed enum reads and deletes by case, and filters, maps and sorts with lambdas
--FILE--
<?php
require __DIR__ . '/harness/Standing.inc';
require __DIR__ . '/Roster.sharp';

// Each report runs twice, so the JIT variants compile the method and its lambdas before the second run.
$members = ['active' => 1, 'closed' => 4];
for ($run = 0; $run < 2; $run++) {
    echo implode(" | ", Demo\Roster::report($members)), "\n";
}
var_dump($members);
?>
--EXPECT--
4,1 | 10,40 | 1,4 | 0 | 4 | 1 | 0
4,1 | 10,40 | 1,4 | 0 | 4 | 1 | 0
array(2) {
  ["active"]=>
  int(1)
  ["closed"]=>
  int(4)
}

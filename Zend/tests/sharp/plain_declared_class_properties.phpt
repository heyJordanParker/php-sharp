--TEST--
Class<T>.properties() reads `self` in a plain PHP trait's property as the class that uses the trait
--FILE--
<?php
require __DIR__ . '/harness/Linked.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Linking');

foreach (App\Linking\Report::describeProperties() as $line) {
    echo $line, "\n";
}
?>
--EXPECT--
next: Lib.Linked?

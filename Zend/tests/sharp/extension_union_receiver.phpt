--TEST--
PHP# runs an extension member on the parts of a union receiver that declare no member of its name: label() on an Order|string runs Order's own label() for an Order and the extension for a string, as the caption property and label read as a method's value do, badge(), which Order lacks, runs the extension for both, and a value shown as a string or an Order runs the extension or the method alone
--FILE--
<?php
require __DIR__ . '/UnionLabels/Order.sharp';
require __DIR__ . '/UnionLabels/Labels.sharp';
require __DIR__ . '/UnionLabels/Shelf.sharp';

use Demo\Labels\Order;
use Demo\Labels\Shelf;

// Each run repeats, so the JIT variants compile the calls before the second run.
for ($run = 0; $run < 2; $run++) {
    echo Shelf::label(new Order()), ' | ', Shelf::label('plain'), "\n";
    echo Shelf::badge(new Order()), ' | ', Shelf::badge('plain'), "\n";
    echo Shelf::text('plain'), ' | ', Shelf::order(new Order()), "\n";
    echo Shelf::caption(new Order()), ' | ', Shelf::caption('plain'), "\n";
    echo Shelf::labeled(new Order()), ' | ', Shelf::labeled('plain'), "\n";
}
?>
--EXPECT--
order | text plain
badge order | badge plain
text plain | order
order caption | caption plain
order | text plain
order | text plain
badge order | badge plain
text plain | order
order caption | caption plain
order | text plain

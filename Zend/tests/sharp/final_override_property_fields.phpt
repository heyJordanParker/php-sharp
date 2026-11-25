--TEST--
An override in an open class stays open until `final override` closes it, so fields() gives the storage of a final override only
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", Fields\Overrides::listed()), "\n";
?>
--EXPECT--
OpenLeaf: [content]
OpenBranch: []
Shelf: [items]

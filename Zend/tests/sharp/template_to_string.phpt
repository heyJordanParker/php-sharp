--TEST--
PHP# a template shows what toString() returns, and plain PHP's string conversion of the object gives the same text
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Templates');

$sku = new Templates\Sku('A-12');
echo (new Templates\Label())->show($sku, Templates\Grade::Gold), "\n";
echo (string) $sku, "\n";
echo "plain: {$sku}\n";
var_dump($sku instanceof Stringable);
?>
--EXPECT--
Sku: A-12, grade: gold
A-12
plain: A-12
bool(true)

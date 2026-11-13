--TEST--
A constant written as a template or a + join of literals and constants holds the text it joins, spec section 24, as a template of constants written in a body does
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('FixedStrings');

echo implode("\n", FixedStrings\Weights::texts()), "\n";
?>
--EXPECT--
1.0 / 3 as weight
0.5 / COUNT(*) OVER (PARTITION BY order_id)
1.0 / COUNT(*) OVER (PARTITION BY order_id)
3 rows

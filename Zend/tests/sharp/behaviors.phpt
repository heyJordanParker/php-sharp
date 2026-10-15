--TEST--
PHP# behaviors run set in the order they are written and get the other way, the initial value's write included, with one instance of each per object
--FILE--
<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/Behavior.sharp';
require __DIR__ . '/Behaviors.sharp';

// The initial values went through each `set`, so each `Counted` counted one write.
$first = new Behaviors\Profile();
echo $first->name, '|', $first->code, "\n";

// `name` trims, then counts. `code` counts, then upper-cases, and its `get` runs `Upper` first, then `Counted`.
$first->name = '  ada  ';
$first->code = 'bo';
echo $first->name, '|', $first->code, "\n";

// A second object has its own behaviors, so its count starts again.
$second = new Behaviors\Profile();
$second->name = ' cy ';
echo $second->name, ' ', $first->name, "\n";
?>
--EXPECT--
#1|X#1
ada#2|BO#2
cy#2 ada#2

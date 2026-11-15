--TEST--
PHP# behaviors run set in the order they are written and get the other way, the initial value's write included, which a behavior trims, with one instance of each per object, and one per class for a static
--FILE--
<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/Behavior.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('Behaviors');

// The initial values went through each `set`, so `Trimmed` trimmed one and each `Counted` counted one write.
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

// A copy has its own behaviors, made with `with` or with plain PHP's `clone`, so its writes count apart.
$copy = $first->renamed(' dee ');
echo $copy->name, ' ', $first->name, "\n";
$cloned = clone $first;
$cloned->name = 'eve';
echo $cloned->name, ' ', $first->name, "\n";

// A generic behavior is created with its type argument, and runs as any behavior does.
$first->level = 4;
echo $first->level, "\n";

// A static's behaviors belong to its class, one instance of each, so each write counts on. Its initial value filled
// the storage without `set`, so `Counted` counted no write before them.
echo Behaviors\Profile::tagged(), ' ', Behaviors\Profile::retag('  ann  '), ' ', Behaviors\Profile::retag(' bo '), "\n";
?>
--EXPECT--
start#1|X#1
ada#2|BO#2
cy#2 ada#2
dee#1 ada#2
eve#1 ada#2
4
base#0 ann#1 bo#2

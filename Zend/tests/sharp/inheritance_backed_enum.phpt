--TEST--
A PHP# string-backed and int-backed enum implement a plain PHP interface that extends BackedEnum, and from and tryFrom run through the interface
--FILE--
<?php

require __DIR__ . '/harness/Traits.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Traits');

// The interface's own static methods, `from` and `tryFrom` that `BackedEnum` declares, run on any enum that implements it.
function pick(Lib\Aligned $aligned, int|string $value, int|string $missing): array
{
    return [$aligned::from($value)->label(), $aligned::tryFrom($value)?->name, $aligned::tryFrom($missing)];
}

var_dump(pick(Demo\Align::Left, 'right', 'up'), pick(Demo\Tier::Low, 2, 9));
var_dump(Demo\Align::Right instanceof Lib\Aligned, Demo\Tier::High instanceof BackedEnum);
try {
    Demo\Align::from('up');
} catch (ValueError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(5) "align"
  [1]=>
  string(5) "Right"
  [2]=>
  NULL
}
array(3) {
  [0]=>
  string(5) "level"
  [1]=>
  string(4) "High"
  [2]=>
  NULL
}
bool(true)
bool(true)
"up" is not a valid backing value for enum Demo\Align

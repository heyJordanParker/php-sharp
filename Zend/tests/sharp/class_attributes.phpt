--TEST--
PHP# type.attributes<TAttribute>() gives the attributes a class value's class declares itself, subclasses of TAttribute included
--FILE--
<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/ClassMembers.sharp';
require __DIR__ . '/harness/Factory.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ClassValues');

var_dump(Classes\ClassValues::topics(Classes\Badge::class));
var_dump(Classes\ClassValues::topics(Classes\GoldBadge::class));
var_dump(Classes\ClassValues::loud());
?>
--EXPECT--
array(2) {
  [0]=>
  string(6) "badges"
  [1]=>
  string(5) "shiny"
}
array(0) {
}
int(1)

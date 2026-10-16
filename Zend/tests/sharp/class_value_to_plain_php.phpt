--TEST--
A PHP# class value reaches plain PHP that takes a class name as that name, with no conversion written
--FILE--
<?php

require __DIR__ . '/harness/Factory.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ClassValues');

var_dump(get_class(Classes\ClassValues::made()));
var_dump(get_class(Classes\ClassValues::remade(new Classes\GoldBadge())));
var_dump(get_class(Classes\ClassValues::remade(new Classes\Badge())));
var_dump(get_class(Classes\ClassValues::fromValue(Classes\GoldBadge::class)));
?>
--EXPECT--
string(17) "Classes\GoldBadge"
string(17) "Classes\GoldBadge"
string(13) "Classes\Badge"
string(17) "Classes\GoldBadge"

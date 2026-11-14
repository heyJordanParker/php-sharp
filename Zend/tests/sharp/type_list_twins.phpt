--TEST--
A .sharp file names a class as written, spec section 24, so its type `Parcel.Box | Parcel.BOX` keeps both twins and takes a `BOX` value, while plain PHP's `Foo|FOO` stays a duplicate type
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
fixture('TypeTwins');

var_dump(Twins\Parcel::label(new Twins\Parcel\Box(1)), Twins\Parcel::label(new Twins\Parcel\BOX(9)));
var_dump((string) (new ReflectionMethod(Twins\Parcel::class, 'label'))->getParameters()[0]->getType());

eval('function plain(Foo|FOO $value): void {}');
?>
--EXPECTF--
string(5) "small"
string(5) "large"
string(33) "Twins\Parcel\Box|Twins\Parcel\BOX"

Fatal error: Duplicate type FOO is redundant in %s on line %d

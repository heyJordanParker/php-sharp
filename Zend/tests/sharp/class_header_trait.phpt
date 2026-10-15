--TEST--
A PHP# class or enum header lists a plain PHP trait, alone or beside its base class, and the class and its subclass get the trait's field, method and constant, as PHP's use gives them
--FILE--
<?php

require __DIR__ . '/harness/Traits.inc';
require __DIR__ . '/Traits.sharp';

$essay = new Demo\Essay();
var_dump($essay->link(), $essay->name(), $essay->key(), get_parent_class($essay), Demo\Essay::ROOT);
var_dump(class_uses($essay), (new ReflectionClass($essay))->getTraitNames(), class_implements($essay));

$guide = new Demo\Guide();
var_dump($guide->link(), $guide->topic(), $guide->path(), get_parent_class($guide));
var_dump(class_uses($guide), (new ReflectionClass($guide))->getTraitNames());

var_dump((new Demo\Draft())->copy(), Demo\Shade::Light->copy(), class_uses(Demo\Shade::class));

// A header trait's method replaces the base class's, as Laravel's SoftDeletes replaces Model's.
$posting = new Demo\Posting();
var_dump($posting->forceDelete(), $posting->delete(), $posting->getKeyType());
?>
--EXPECT--
string(15) "/articles/intro"
string(5) "intro"
int(7)
string(12) "Lib\Document"
string(10) "/articles/"
array(1) {
  ["Lib\HasSlug"]=>
  string(11) "Lib\HasSlug"
}
array(1) {
  [0]=>
  string(11) "Lib\HasSlug"
}
array(1) {
  ["Demo\Linked"]=>
  string(11) "Demo\Linked"
}
string(15) "/articles/setup"
string(5) "setup"
string(15) "/articles/setup"
string(10) "Demo\Essay"
array(0) {
}
array(0) {
}
string(17) "copied Demo\Draft"
string(17) "copied Demo\Shade"
array(1) {
  ["Lib\Copies"]=>
  string(10) "Lib\Copies"
}
string(10) "soft force"
string(11) "soft delete"
string(6) "string"

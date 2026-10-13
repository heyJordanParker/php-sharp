--TEST--
A PHP# class header lists a plain PHP trait beside its base class, and the class and its subclass get the trait's field, method and constant
--FILE--
<?php

require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/Traits.sharp';

$article = new Demo\Article();
var_dump($article->link(), $article->name(), $article->key(), get_parent_class($article), Demo\Article::ROOT);
var_dump(class_uses($article), (new ReflectionClass($article))->getTraitNames(), class_implements($article));

$guide = new Demo\Guide();
var_dump($guide->link(), $guide->topic(), $guide->path(), get_parent_class($guide));
var_dump(class_uses($guide), (new ReflectionClass($guide))->getTraitNames());
?>
--EXPECT--
string(15) "/articles/intro"
string(5) "intro"
int(7)
string(9) "Lib\Model"
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
string(12) "Demo\Article"
array(0) {
}
array(0) {
}

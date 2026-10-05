--TEST--
A PHP# class header names its parent class and its interfaces in any order
--FILE--
<?php

require __DIR__ . '/Header.inc';
require __DIR__ . '/Header.sharp';

function inheritance(string $class): array
{
    $interfaces = class_implements($class);
    ksort($interfaces);

    return [get_parent_class($class), array_keys($interfaces)];
}

$page = new Demo\Page();
var_dump($page->link(), $page->name(), $page->number(), $page instanceof Lib\Entity);
var_dump(inheritance(Demo\Page::class), inheritance(Demo\Post::class), inheritance(Demo\Tag::class));
var_dump(class_implements(Demo\Linkable::class));

$size = new ReflectionMethod(Demo\Thumbnail::class, 'size');
var_dump((new Demo\Thumbnail())->size(), count($size->getAttributes(Override::class)), $size->isFinal());
?>
--EXPECT--
string(5) "/page"
string(4) "page"
int(7)
bool(true)
array(2) {
  [0]=>
  string(10) "Lib\Entity"
  [1]=>
  array(2) {
    [0]=>
    string(13) "Demo\Linkable"
    [1]=>
    string(9) "Lib\Named"
  }
}
array(2) {
  [0]=>
  string(10) "Lib\Entity"
  [1]=>
  array(0) {
  }
}
array(2) {
  [0]=>
  bool(false)
  [1]=>
  array(1) {
    [0]=>
    string(9) "Lib\Named"
  }
}
array(1) {
  ["Lib\Named"]=>
  string(9) "Lib\Named"
}
int(11)
int(1)
bool(false)

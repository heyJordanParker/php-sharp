--TEST--
A PHP# class header names its parent class and its interfaces in any order
--FILE--
<?php

require __DIR__ . '/harness/Header.inc';
require __DIR__ . '/Header.sharp';

function inheritance(string $class): array
{
    $interfaces = class_implements($class);
    ksort($interfaces);

    return [get_parent_class($class), array_keys($interfaces)];
}

$article = new Demo\Article();
var_dump($article->link(), $article->name(), $article->number(), $article instanceof Lib\Record);
var_dump(inheritance(Demo\Article::class), inheritance(Demo\Post::class), inheritance(Demo\Tag::class));
var_dump(class_implements(Demo\Linkable::class));
var_dump(inheritance(Demo\Card::class), (new Demo\Card())->copy()->link());

$size = new ReflectionMethod(Demo\Thumbnail::class, 'size');
var_dump((new Demo\Thumbnail())->size(), count($size->getAttributes(Override::class)), $size->isFinal());
?>
--EXPECT--
string(8) "/article"
string(7) "article"
int(7)
bool(true)
array(2) {
  [0]=>
  string(10) "Lib\Record"
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
  string(10) "Lib\Record"
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
array(2) {
  [0]=>
  string(9) "Lib\Shelf"
  [1]=>
  array(2) {
    [0]=>
    string(13) "Demo\Linkable"
    [1]=>
    string(9) "Lib\Named"
  }
}
string(5) "/card"
int(11)
int(1)
bool(false)

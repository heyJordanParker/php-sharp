--TEST--
An override auto-property reads and writes its own storage, and fields() gives it, under a parent whose override has an accessor body
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$leaf = new Fields\BodyLeaf();
echo $leaf->content, "\n";
$leaf->content = 'y';
echo $leaf->content, "\n";
echo implode(', ', Fields\BodyLeaf::stored()), "\n";
$property = new ReflectionProperty(Fields\BodyLeaf::class, 'content');
echo count($property->getHooks()), "\n";
echo (new Fields\BodyMid())->content, "\n";
?>
--EXPECT--
x
y
content
0
mid

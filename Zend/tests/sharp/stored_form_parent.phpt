--TEST--
A PHP# class whose plain PHP parent serializes its objects itself, as an Eloquent model does, keeps the parent's serialization, and unserialize builds it through the parent's __unserialize
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/harness/Archived.inc';

$stored = serialize(new Queued\Note('kept'));
echo $stored, "\n";
$back = unserialize($stored);
echo get_class($back), ' ', $back->kept(), "\n";
?>
--EXPECT--
O:11:"Queued\Note":1:{i:0;s:4:"kept";}
Queued\Note kept

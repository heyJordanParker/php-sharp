--TEST--
An empty plain PHP array passes both as Map<K, V> and as List<T>, and is agrees, so a request's empty query array reads as an empty Map, while a non-empty map passes only as a Map; and Json.parse gives an empty Map for {}, which is no List, and an empty List for [], which encode writes back as {} and []
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$_GET = [];
echo json_encode(EmptyArrays\Reads::of($_GET)), "\n";
echo json_encode(EmptyArrays\Reads::of(['page' => 2])), "\n";
echo json_encode(EmptyArrays\Reads::parsed()), "\n";
?>
--EXPECT--
[true,0,true,0,true,true]
[true,1,false,null,true,false]
[true,false,true,true,true,true]

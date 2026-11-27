--TEST--
An empty plain PHP array is a list, as PHP's array_is_list([]) is, so as and is take it as a List<T> and never as a Map<string, V>, a request's empty query array included, while a non-empty map passes only as a Map; and Json.parse gives an empty Map for {}, which is no List, and an empty List for [], which is no Map, and encode writes them back as {} and []
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$_GET = [];
echo json_encode(EmptyArrays\Reads::of($_GET)), "\n";
echo json_encode(EmptyArrays\Reads::of(['page' => 2])), "\n";
echo json_encode(EmptyArrays\Reads::parsed()), "\n";
?>
--EXPECT--
[false,null,true,0,false,true]
[true,1,false,null,true,false]
[true,false,true,false,true,true]

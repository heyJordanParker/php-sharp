--TEST--
A plain PHP call on a side of ??, in a branch of ? : or in a match arm, bound for a Map, keeps the keys plain PHP gave it, as the call bound there alone does
--FILE--
<?php
require __DIR__ . '/harness/Numbered.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\Numbering;

echo json_encode(Numbering::direct()), "\n";
echo json_encode(Numbering::coalesced(null)), "\n";
echo json_encode(Numbering::branched(false)), "\n";
echo json_encode(Numbering::matched(1)), "\n";
?>
--EXPECT--
{"5":"a","9":"b"}
{"5":"a","9":"b"}
{"5":"a","9":"b"}
{"5":"a","9":"b"}

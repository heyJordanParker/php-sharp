--TEST--
A type test binds the value as the tested type holds it, whether a type parameter or the type itself spells it, and `as` gives such a copy: a PHP array from plain PHP becomes the Map or the Set the type names, while the tested value stays the plain array a later test reads
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use TypeTests\Decoded;

echo 'direct: ', Decoded::direct('{"a":1,"b":2}'), "\n";
echo 'generic: ', Decoded::generic('{"a":1,"b":2}'), "\n";
// `as` gives the converted copy, and leaves its subject the plain array a later test reads.
echo 'as: ', Decoded::cast('{"a":1,"b":2}'), ', empty:', Decoded::cast('{}'), "\n";
var_dump(Decoded::directSet('[1,2,2]'), Decoded::genericSet('[1,2,2]'), Decoded::genericSetCount('[1,2,2]'));
// The optimizer knows the list a local holds, and the test that binds it still runs.
var_dump(Decoded::constantList());
?>
--EXPECT--
direct: a,b
generic: a,b
as: a,b, empty: and a list
bool(true)
bool(true)
int(2)
int(2)

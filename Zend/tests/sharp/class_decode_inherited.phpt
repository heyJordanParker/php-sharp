--TEST--
Class<T>.decode(value), Json.encode and Class<T>.encode, spec sections 10 and 25, read and write a stored property a class inherits from its PHP# parent as they do one it declares, after its main constructor's parameters
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use InheritedFields\Reads;

echo Reads::decoded(), "\n";
echo Reads::encoded(), "\n";
echo Reads::roundTripped(), "\n";
?>
--EXPECT--
label=b key=a
{"label":"b","key":"a"}
label=b key=a

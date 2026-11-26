--TEST--
Field.set writes an `init` property's storage that holds no value, as the object's creation would, and refuses a second write and a value of another type
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

foreach (Fields\Restore::notice() as $line) {
    echo $line, "\n";
}
?>
--EXPECT--
before: false
x
Error: `set` writes the storage of Fields\Notice::$key, which `init` lets the object's creation write once, and it holds a value
TypeError: Cannot assign int to property Fields\Notice::$key of type string

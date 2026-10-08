--TEST--
A PHP# class calls every method of Sharp.Data.Base64, and Base64.decode throws ValueError on text that is not base64
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Base64.sharp';
require __DIR__ . '/Base64Calls.sharp';

use Demo\Base64Calls;

var_dump(Base64Calls::encode('Hello'), Base64Calls::decode('SGVsbG8='));
var_dump(Base64Calls::encode(''), Base64Calls::decode(''));

try {
    Base64Calls::decode('@@');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(8) "SGVsbG8="
string(5) "Hello"
string(0) ""
string(0) ""
ValueError: Base64.decode: the text is not valid base64

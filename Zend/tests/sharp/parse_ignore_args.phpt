--TEST--
Sharp\Int::parse and Sharp\Float::parse leave the string out of their message when zend.exception_ignore_args is on
--INI--
zend.exception_ignore_args=1
--FILE--
<?php

try {
    Sharp\Int::parse('secret-token');
} catch (ValueError $e) {
    echo $e->getMessage(), "\n";
}
try {
    Sharp\Float::parse('1e999');
} catch (ArithmeticError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Sharp\Int::parse(): Argument #1 ($value) must hold an int, string given
Sharp\Float::parse(): Argument #1 ($value) must hold a float from -PHP_FLOAT_MAX to PHP_FLOAT_MAX, string given

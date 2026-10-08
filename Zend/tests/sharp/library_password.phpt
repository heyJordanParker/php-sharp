--TEST--
A PHP# class calls every method of Sharp.Data.Password, and Password.hash throws ValueError on a password with a NUL byte
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Password.sharp';
require __DIR__ . '/PasswordCalls.sharp';

use Demo\PasswordCalls;

$hash = PasswordCalls::hash('secret');
var_dump(str_starts_with($hash, '$2y$'), strlen($hash));
var_dump(PasswordCalls::verify('secret', $hash), PasswordCalls::verify('wrong', $hash), PasswordCalls::verify('secret', 'nope'));

try {
    PasswordCalls::hash("a\0b");
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
bool(true)
int(60)
bool(true)
bool(false)
bool(false)
ValueError: Bcrypt password must not contain null character

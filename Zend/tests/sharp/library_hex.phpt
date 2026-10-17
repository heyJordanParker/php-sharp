--TEST--
A PHP# class calls every method of Sharp.Data.Hex, and Hex.decode throws ValueError on text that is not hexadecimal, with no warning
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Hex.sharp';
require __DIR__ . '/HexCalls.sharp';

use Demo\HexCalls;

var_dump(HexCalls::encode('abc'), HexCalls::decode('616263'));

try {
    HexCalls::decode('zz');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// An error handler that turns the warnings PHP reports into exceptions, as Laravel's does, still gets the library's
// ValueError.
set_error_handler(function (int $level, string $message): bool {
    if (!(error_reporting() & $level)) {
        return false;
    }
    throw new ErrorException($message, 0, $level);
});
try {
    HexCalls::decode('zz');
} catch (ValueError|ErrorException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(6) "616263"
string(3) "abc"
ValueError: Hex.decode: the text is not hexadecimal with an even length
ValueError: Hex.decode: the text is not hexadecimal with an even length

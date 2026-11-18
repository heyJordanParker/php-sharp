--TEST--
Under opcache, Json.decode<T> throws its InvalidFields for a bad field from the first request, whose shared memory holds no copy of the class's name yet, and the engine releases the name it looked the class up by. The file names no class the throw builds, so nothing it compiles puts that name in shared memory first
--EXTENSIONS--
opcache
--INI--
opcache.enable_cli=1
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\JsonCalls;

foreach (['{"x":"four"}', '{"other":1}'] as $json) {
    try {
        JsonCalls::point($json);
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
Sharp\InvalidFields: x: expected int, got string 'four'
Sharp\InvalidFields: x: missing

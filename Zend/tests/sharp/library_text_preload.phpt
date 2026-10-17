--TEST--
Preloading keeps the string members of Sharp.Text.Text: contains, lowercase, uppercase, startsWith, endsWith, replace and split, which throws ValueError for an empty delimiter
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/library_text.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php

// Preloading declared the classes before this request, which never ran the files that declare them.
var_dump(class_exists(Demo\TextCalls::class, false), class_exists(Sharp\Text\Text::class, false));

use Demo\TextCalls;

var_dump(TextCalls::contains('hello', 'ell'), TextCalls::contains('hello', 'xyz'));
var_dump(TextCalls::lowercase('ÉCOLE'), TextCalls::uppercase('école'));
var_dump(TextCalls::startsWith('hello', 'he'), TextCalls::endsWith('hello', 'he'));
var_dump(TextCalls::replace('banana', 'a', 'o'));
echo json_encode(TextCalls::split('a,b,', ',')), "\n";
try {
    TextCalls::split('abc', '');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(false)
string(6) "école"
string(6) "ÉCOLE"
bool(true)
bool(false)
string(6) "bonono"
["a","b",""]
ValueError: Text.split: the delimiter is empty

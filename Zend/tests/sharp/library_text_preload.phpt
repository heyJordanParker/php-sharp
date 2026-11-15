--TEST--
Preloading keeps the string members of Sharp.Text: contains, lowercase, uppercase, startsWith, endsWith, replace, which puts empty text to replace before each character and after the last, and split, which takes a limit and splits an empty delimiter before each character and after the last
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
var_dump(class_exists(Demo\TextCalls::class, false), class_exists(Sharp\Text\StringMembers::class, false));

use Demo\TextCalls;

var_dump(TextCalls::contains('hello', 'ell'), TextCalls::contains('hello', 'xyz'));
var_dump(TextCalls::lowercase('ÉCOLE'), TextCalls::uppercase('école'));
var_dump(TextCalls::startsWith('hello', 'he'), TextCalls::endsWith('hello', 'he'));
var_dump(TextCalls::replace('banana', 'a', 'o'));
echo json_encode(TextCalls::split('a,b,', ',')), "\n";
echo json_encode(TextCalls::splitAtMost('a:b:c', ':', 2)), "\n";
echo json_encode(TextCalls::split('abc', '')), "\n";
var_dump(TextCalls::replace('abc', '', '-'));
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
["a","b:c"]
["","a","b","c",""]
string(7) "-a-b-c-"

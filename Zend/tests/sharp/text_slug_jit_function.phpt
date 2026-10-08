--TEST--
A PHP# class turns titles into slugs through the native body of Text.slug under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/text_slug.inc';
?>
--EXPECT--
jit on
string(20) "unicode-strasse-2024"
string(10) "privet-mir"
string(0) ""
string(3) "a-b"
string(21) "bei-jing-huan-ying-ni"
string(11) "caf-au-lait"
string(20) "unicode-strasse-2024"
string(10) "privet-mir"
string(0) ""
string(3) "a-b"
string(21) "bei-jing-huan-ying-ni"
string(11) "caf-au-lait"

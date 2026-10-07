--TEST--
PHP# receives Any? from plain PHP, keeps it and passes it back unchanged under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/any.inc';
?>
--EXPECT--
jit on
plan: string "pro"
seats: int 5
tags: array ["a","b"]
trial: null null
missing: null null
int(5)
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
NULL
bool(true)
float(1.5)
bool(true)
bool(false)
saved plan: string "pro"
saved tags: array ["a","b"]
saved trial: null null
saved missing: null null
bool(true)
NULL
plans 100, last 5

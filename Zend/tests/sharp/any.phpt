--TEST--
PHP# receives Any? from plain PHP, keeps it and passes it back to plain PHP unchanged
--FILE--
<?php
require __DIR__ . '/any.inc';
?>
--EXPECT--
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

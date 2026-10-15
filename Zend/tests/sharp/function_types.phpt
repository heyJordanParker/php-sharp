--TEST--
PHP# `is`, `as` and `match` test a closure's function type, its effects included, as assignment compares it
--FILE--
<?php
require __DIR__ . '/function_types.inc';
?>
--EXPECT--
0: true
1: false
2: true
3: true
4: false
5: true
6: false
7: true
8: true
9: false
10: false
11: true
12: true
13: true
14: false
15: true
16: false
17: true
18: true
19: true
20: false
21: false
22: true
23: true
24: true
25: false
26: true
27: false

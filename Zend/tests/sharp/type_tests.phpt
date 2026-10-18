--TEST--
PHP# `is`, `as` and `match` test a tuple's count and element types, a class value's class and an object, alone or in a union
--FILE--
<?php
require __DIR__ . '/type_tests.inc';
?>
--EXPECT--
0: true
1: false
2: false
3: true
4: true
5: true
6: true
7: true
8: true
9: true
10: false
11: true
12: false
13: false
14: false
15: true
16: true
17: true
18: true
19: true
20: true
21: true
22: false
23: true
24: false
25: false
26: false
27: true
28: true
29: true
30: true
31: true
32: true
33: true
34: false
35: true
36: true
37: false

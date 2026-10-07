--TEST--
Sharp\Sequence reads its source only as far as toList or a loop asks, numbers toList from 0, and gives a Sequence back from from
--FILE--
<?php
require __DIR__ . '/sequence.inc';
?>
--EXPECT--
built, 0 rows read
100 numbers from 100 of 10000 rows, n1 to n100
built, 0 rows read
100 numbers from 200 of 10000 rows, n2 to n200
built a chain over a failing source
RuntimeException: The source failed after 5 rows in sequence.inc on line 39
1
2
3
4
5
RuntimeException: The source failed after 5 rows in sequence.inc on line 39
string(26) "[0,1,2,3,4] => [1,2,3,4,5]"
built
yield 1
filter 1
yield 2
filter 2
map 2
yield 3
filter 3
yield 4
filter 4
map 4
yield 5
filter 5
yield 6
filter 6
map 6
string(21) "[0,1,2] => [20,40,60]"
string(22) "[0,1,2,3] => [1,2,3,4]"
string(14) "[0,1] => [4,6]"
string(22) "[0,1,2,3] => [1,2,3,4]"
string(14) "[0,1] => [8,9]"
string(14) "[0,1] => [1,2]"
string(14) "[0,1] => [1,2]"
yield 1
0 => 2
yield 2
1 => 4
yield 3
2 => 6
10: 10,20,30
20: 10,20,30
30: 10,20,30
yield 1
0 => 1
yield 2
1 => 2
yield 3
2 => 3
string(14) "[0,1] => [1,2]"
string(16) "InternalIterator"
bool(true)
string(16) "[0,1] => [20,40]"
string(8) "[] => []"
ValueError: Sharp\Sequence::take(): Argument #1 ($count) must be greater than or equal to 0 in sequence.inc on line 142
LogicException: filter 1 in sequence.inc on line 145
LogicException: map 1 in sequence.inc on line 148
yield 1
yield 2
string(14) "[0,1] => [1,2]"
Exception: Cannot traverse an already closed generator in sequence.inc on line 153
Error: Call to private Sharp\Sequence::__construct() from global scope in sequence.inc on line 155
ReflectionException: Class Sharp\Sequence is an internal class marked as final that cannot be instantiated without invoking its constructor in sequence.inc on line 156
Error: Trying to clone an uncloneable object of class Sharp\Sequence in sequence.inc on line 157
Exception: Serialization of 'Sharp\Sequence' is not allowed in sequence.inc on line 158
Exception: Unserialization of 'Sharp\Sequence' is not allowed in sequence.inc on line 159
Error: Cannot create dynamic property Sharp\Sequence::$extra in sequence.inc on line 161
TypeError: Sharp\Sequence::filter(): Argument #1 ($predicate) must be of type Closure, string given in sequence.inc on line 163
bool(true)
NULL
bool(true)
NULL

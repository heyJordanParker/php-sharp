--TEST--
PHP# compares an unchecked Any? from plain PHP by value with a string, a number, a bool, a collection, an enum case and a struct, and an object only with ===
--FILE--
<?php
require __DIR__ . '/any_equality.inc';
?>
--EXPECT--
string pro: pro, not ticket
int 1: one, not ticket, not pro
true: true, not ticket, not pro
list [1, 2]: numbers, not ticket, not pro
list [2, 1]: not ticket, not pro
map a: 1: counts, not ticket, not pro
set 2, 1: seen, not ticket, not pro
Hearts: hearts, not ticket, not pro
Spades: not ticket, not pro
Point(1): point, not ticket, not pro
Point(2): not ticket, not pro
the ticket: ticket, not pro
another ticket: not ticket, not pro
null: not ticket, null, not pro

--TEST--
A PHP# enum runs from PHP# and plain PHP, and a PHP# class runs a plain PHP enum, as their PHP twins run
--FILE--
<?php
require __DIR__ . '/enums.inc';
?>
--EXPECTF--
string(10) "Active (a)"
bool(true)
array(3) {
  [0]=>
  enum(Demo\Status::Active)
  [1]=>
  enum(Demo\Status::Paused)
  [2]=>
  enum(Demo\Status::Closed)
}
enum(Demo\Status::Paused)
enum(Demo\Status::Active)
string(68) "ValueError: "nope" is not a valid backing value for enum Demo\Status"
string(17) "Paused by support"
string(1) "a"
string(6) "Active"
string(10) "Active (a)"
bool(true)
enum(Demo\Status::Closed)
bool(true)
NULL
int(3)
string(%d) "TypeError: Demo\Shipment::__construct(): Argument #1 ($status) must be of type Demo\Status, Demo\Suit given, called in %s on line %d"
string(6) "hearts"
int(2)
bool(true)
enum(Demo\Rank::Low)
NULL
array(3) {
  [0]=>
  int(-1)
  [1]=>
  int(0)
  [2]=>
  int(2)
}
bool(true)
bool(false)
enum(Demo\Priority::High)
int(2)
enum(Demo\Priority::Low)
NULL

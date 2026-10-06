--TEST--
A PHP# enum runs from PHP# and plain PHP under the tracing JIT
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
require __DIR__ . '/enums.inc';
?>
--EXPECTF--
jit on
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
enum(Demo\Status::Active)
bool(true)
array(3) {
  [0]=>
  string(13) "Demo\HasLabel"
  [1]=>
  string(8) "UnitEnum"
  [2]=>
  string(10) "BackedEnum"
}
bool(true)
bool(false)
string(68) "ValueError: "nope" is not a valid backing value for enum Demo\Status"
string(17) "Paused by support"
string(1) "a"
string(6) "Active"
string(10) "Active (a)"
bool(true)
enum(Demo\Status::Closed)
string(6) "Active"
enum(Demo\Status::Paused)
string(10) "Active (a)"
enum(Demo\Status::Active)
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

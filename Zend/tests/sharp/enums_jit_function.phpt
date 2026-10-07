--TEST--
A PHP# enum runs from PHP# and plain PHP under the function JIT
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
require __DIR__ . '/enums.inc';
?>
--EXPECTF--
jit on
string(10) "Active (a)"
bool(true)
array(3) {
  [0]=>
  enum(Demo\Stage::Active)
  [1]=>
  enum(Demo\Stage::Paused)
  [2]=>
  enum(Demo\Stage::Closed)
}
enum(Demo\Stage::Paused)
enum(Demo\Stage::Active)
enum(Demo\Stage::Active)
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
string(67) "ValueError: "nope" is not a valid backing value for enum Demo\Stage"
string(17) "Paused by support"
string(1) "a"
string(6) "Active"
string(10) "Active (a)"
bool(true)
enum(Demo\Stage::Closed)
string(6) "Active"
enum(Demo\Stage::Paused)
string(10) "Active (a)"
enum(Demo\Stage::Active)
bool(true)
NULL
int(3)
string(%d) "TypeError: Demo\Shipment::__construct(): Argument #1 ($status) must be of type Demo\Stage, Demo\Suit given, called in %s on line %d"
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

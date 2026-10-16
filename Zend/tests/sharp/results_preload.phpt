--TEST--
A preloaded Result passes a failure up through try, and a preloaded generic enum keeps one plain case object for each list of type arguments
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/results_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/results.inc';

var_dump(...results());
?>
--EXPECT--
string(47) "Sharp\Result\Ok<Demo.Receipt, Demo.ChargeError>"
string(48) "Sharp\Result\Err<Demo.Receipt, Demo.ChargeError>"
int(5)
string(38) "Sharp\Result\Ok<int, Demo.ChargeError>"
int(10)
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
string(20) "declined: empty cart"
int(7)
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
int(8)
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
string(53) "Sharp\Result\Ok<List<Demo.Receipt>, Demo.ChargeError>"
int(3)
string(54) "Sharp\Result\Err<List<Demo.Receipt>, Demo.ChargeError>"
string(38) "Sharp\Result\Ok<int, Demo.ChargeError>"
string(6) "paid 9"
string(20) "declined: empty cart"
bool(true)
bool(false)
string(12) "card expired"
string(18) "declined: no funds"
bool(true)
bool(true)
bool(true)
bool(false)
string(24) "Demo\Lookup\Missing<int>"
string(27) "Demo\Lookup\Missing<string>"
bool(true)
bool(false)
string(22) "Demo\Lookup\Found<int>"
string(34) "Sharp\Result\Ok<Any?, Sharp.Error>"
string(25) "Demo\Lookup\Missing<Any?>"
string(16) "Demo\ChargeError"
bool(true)
string(38) "Sharp\Result\Ok<int, Demo.ChargeError>"
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
string(22) "Demo\Lookup\Found<int>"
string(24) "Demo\Lookup\Missing<int>"
int(4)
int(0)
int(-1)
array(2) {
  [0]=>
  int(6)
  [1]=>
  string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
}
string(7) "total 3"
string(42) "Sharp\Result\Err<string, Demo.ChargeError>"
int(4)
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
int(5)
string(39) "Sharp\Result\Err<int, Demo.ChargeError>"
string(19) "charge calm first 1"
string(34) "label calm, label big, label boxed"
string(9) "label big"

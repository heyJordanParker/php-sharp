--TEST--
A method returns an expected failure as a Result, try passes it up to the caller, and a plain case of a generic enum is one object for each list of type arguments
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Error.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Result.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('Results');
require __DIR__ . '/results.inc';

// A hot function's second call runs the code the tracing JIT compiled on its first.
for ($round = 0; $round < 100; $round++) {
    $results = results();
}

var_dump(...$results);
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

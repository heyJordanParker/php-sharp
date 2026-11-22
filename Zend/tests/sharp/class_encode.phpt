--TEST--
Class<T>.encode(value), spec section 10, gives the stored form Class<T>.decode reads back: a storage layer outside a class's namespace reads its internal property into a Map, a struct holding a machine, a Map keyed by ints and a List reads back equal, and for each value library_json.phpt encodes, json_encode of its stored form with Json.encode's flags writes what Json.encode writes
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\StoredCalls;

$recorded = StoredCalls::recorded();
var_dump(StoredCalls::recordedFormIsMap($recorded), StoredCalls::recordedForm($recorded));
var_dump(StoredCalls::recordedReadsBack($recorded));
var_dump(StoredCalls::parcelReadsBack(StoredCalls::parcel()));
foreach (StoredCalls::written() as $json => $alike) {
    echo $json, ' ', var_export($alike, true), "\n";
}
?>
--EXPECT--
bool(true)
array(2) {
  ["name"]=>
  string(7) "shipped"
  ["sequence"]=>
  int(7)
}
bool(true)
bool(true)
{} true
[] true
{"counts":{},"lines":[]} true
{"0":"a","1":"b"} true
{"#red":1} true
{"id":7,"tags":["new","vip"],"at":{"x":2}} true
{"shelves":{"7":"top","8":"floor"},"labels":["fragile","dry"],"status":"draft"} true
1.0 true
"a/é \u003Cb\u003E" true

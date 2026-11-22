--TEST--
Property.isExtern holds for each extern property properties() lists, and for no property the class declares or its plain PHP parent declares
--FILE--
<?php
require __DIR__ . '/harness/Columns.inc';
require __DIR__ . '/vendor/autoload.php';

var_dump(ExternProperties\Invoice::externs(), ExternProperties\Invoice::others());
?>
--EXPECT--
array(2) {
  [0]=>
  string(10) "totalCents"
  [1]=>
  string(4) "note"
}
array(2) {
  [0]=>
  string(8) "currency"
  [1]=>
  string(10) "attributes"
}

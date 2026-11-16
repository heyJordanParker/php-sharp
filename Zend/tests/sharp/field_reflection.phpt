--TEST--
Class<T>.fields() lists each field and each closed accessor-less auto-property's storage, and no other property, properties() lists no field, and a Field reads and writes the storage as a direct use from the calling code would, refuses an `init` storage that holds a value, and refuses a lazy object that has not loaded rather than load it
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Fields');

var_dump(Fields\Report::listed());
var_dump(Fields\Report::facts());
var_dump(Fields\Report::own());
var_dump(Fields\Report::autoProperty());
var_dump(Fields\Report::refused());
var_dump(Fields\Report::lazy());
?>
--EXPECT--
array(2) {
  [0]=>
  array(6) {
    [0]=>
    string(5) "total"
    [1]=>
    string(4) "made"
    [2]=>
    string(5) "loads"
    [3]=>
    string(4) "name"
    [4]=>
    string(4) "code"
    [5]=>
    string(5) "depth"
  }
  [1]=>
  array(7) {
    [0]=>
    string(5) "loads"
    [1]=>
    string(4) "name"
    [2]=>
    string(4) "code"
    [3]=>
    string(5) "level"
    [4]=>
    string(4) "note"
    [5]=>
    string(7) "doubled"
    [6]=>
    string(3) "sum"
  }
}
array(11) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(false)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  bool(true)
  [7]=>
  bool(true)
  [8]=>
  int(3)
  [9]=>
  bool(true)
  [10]=>
  bool(true)
}
array(2) {
  [0]=>
  int(8)
  [1]=>
  int(8)
}
array(5) {
  [0]=>
  string(3) "ada"
  [1]=>
  string(3) "ada"
  [2]=>
  int(4)
  [3]=>
  int(4)
  [4]=>
  string(5) "plain"
}
array(3) {
  [0]=>
  string(56) "Error: Cannot access private property Fields\Row::$total"
  [1]=>
  string(126) "Error: `set` writes the storage of Fields\Row::$code, which `init` lets the object's creation write once, and it holds a value"
  [2]=>
  string(73) "TypeError: Cannot assign int to property Fields\Row::$name of type string"
}
array(8) {
  [0]=>
  string(112) "Error: `get` reads the storage of Fields\Row::$name, and the lazy object has not loaded, which `get` never loads"
  [1]=>
  string(113) "Error: `set` writes the storage of Fields\Row::$name, and the lazy object has not loaded, which `set` never loads"
  [2]=>
  int(0)
  [3]=>
  bool(false)
  [4]=>
  int(0)
  [5]=>
  string(3) "row"
  [6]=>
  int(1)
  [7]=>
  string(3) "row"
}

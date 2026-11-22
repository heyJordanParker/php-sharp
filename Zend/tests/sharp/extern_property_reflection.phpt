--TEST--
An extern property is a property of its class that Class<T>.properties() lists with its type, its access and its [Key], whose get and set call the plain PHP parent's __get and __set with its key, as each PHP# read and write of it does, so a member named like the key never takes the write, and plain PHP reaches the parent as before
--FILE--
<?php
require __DIR__ . '/harness/Entries.inc';
require __DIR__ . '/vendor/autoload.php';

$entry = new ExternProperties\Entry();
var_dump($entry->store('written'));
var_dump($entry->charge(250));
var_dump($entry->noted('first'));
var_dump($entry->held());
var_dump($entry->reflected());
var_dump($entry->attributes());
$entry->note = new DateTimeImmutable('2026-01-01');
var_dump(isset($entry->note), get_class($entry->note));
unset($entry->note);
var_dump(isset($entry->note), property_exists($entry, 'stored'), property_exists($entry, 'note'));
?>
--EXPECT--
__set a
__get a
string(7) "written"
__set total_cents
__get total_cents
__set total_cents
__get total_cents
__set total_cents
__get total_cents
__set total_cents
__get total_cents
__set total_cents
__get total_cents
array(4) {
  [0]=>
  int(250)
  [1]=>
  int(252)
  [2]=>
  int(252)
  [3]=>
  int(251)
}
__get note
__set note
__get note
array(2) {
  [0]=>
  string(5) "first"
  [1]=>
  string(5) "first"
}
__get total_cents
__set total_cents
__get total_cents
__set total_cents
__get total_cents
__set total_cents
__get note
int(4)
__set a
__get a
__get a
array(16) {
  [0]=>
  array(5) {
    [0]=>
    string(1) "a"
    [1]=>
    string(10) "attributes"
    [2]=>
    string(6) "stored"
    [3]=>
    string(10) "totalCents"
    [4]=>
    string(4) "note"
  }
  [1]=>
  array(2) {
    [0]=>
    string(5) "calls"
    [1]=>
    string(10) "attributes"
  }
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
  [5]=>
  array(1) {
    [0]=>
    string(1) "a"
  }
  [6]=>
  bool(true)
  [7]=>
  bool(true)
  [8]=>
  bool(true)
  [9]=>
  array(1) {
    [0]=>
    string(11) "total_cents"
  }
  [10]=>
  int(0)
  [11]=>
  NULL
  [12]=>
  NULL
  [13]=>
  bool(true)
  [14]=>
  string(17) "set by reflection"
  [15]=>
  string(17) "set by reflection"
}
array(3) {
  ["a"]=>
  string(17) "set by reflection"
  ["total_cents"]=>
  int(258)
  ["note"]=>
  string(5) "first"
}
__set note
__isset note
__get note
bool(true)
string(17) "DateTimeImmutable"
__unset note
__isset note
bool(false)
bool(false)
bool(false)

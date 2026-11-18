--TEST--
Reflection reads and writes a static through its Property and invokes a static method with no receiver, creates an object from a class value and refuses arguments its constructor does not take, reads whether a late property holds a value, gives a computed and an observed property's accessors, which invoke as a read and a write, and none for a stored one, and creates a lazy object that loads at its first read, beside a property written before the load, and each reflection member value keys a Map and joins a Set as the member it reflects, whenever reflection read it, a static that runs accessors is listed, read and written through them as a direct use reaches it, a non-constant initial value is a default each read evaluates anew, and a Field's write before a lazy object loads reaches the storage as a direct write would and stays once it loads
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(ReflectedMembers\Report::statics());
var_dump(ReflectedMembers\Report::created());
var_dump(ReflectedMembers\Report::initialized());
var_dump(ReflectedMembers\Report::accessors());
var_dump(ReflectedMembers\Report::lazy());
var_dump(ReflectedMembers\Report::keys());
var_dump(ReflectedMembers\Report::staticAccessors());
var_dump(ReflectedMembers\Report::defaults());
?>
--EXPECT--
array(4) {
  [0]=>
  bool(true)
  [1]=>
  int(4)
  [2]=>
  int(4)
  [3]=>
  string(7) "account"
}
array(4) {
  [0]=>
  string(3) "ada"
  [1]=>
  int(10)
  [2]=>
  string(107) "`createInstance` cannot create ReflectedMembers\Account: its constructor takes 1 argument, and was given 0."
  [3]=>
  string(111) "`createInstance` cannot create ReflectedMembers\Account: its parameter `owner` takes string, and was given int."
}
array(3) {
  [0]=>
  bool(false)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
}
array(10) {
  [0]=>
  string(13) "$doubled::get"
  [1]=>
  bool(true)
  [2]=>
  int(20)
  [3]=>
  NULL
  [4]=>
  string(10) "$note::set"
  [5]=>
  bool(true)
  [6]=>
  array(1) {
    [0]=>
    bool(true)
  }
  [7]=>
  string(4) "seen"
  [8]=>
  NULL
  [9]=>
  NULL
}
array(7) {
  [0]=>
  int(99)
  [1]=>
  array(1) {
    [0]=>
    string(101) "Cannot modify private(set) property ReflectedMembers\Account::$pin from scope ReflectedMembers\Report"
  }
  [2]=>
  array(2) {
    [0]=>
    bool(true)
    [1]=>
    bool(false)
  }
  [3]=>
  int(0)
  [4]=>
  string(5) "grace"
  [5]=>
  int(1)
  [6]=>
  int(99)
}
array(10) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  array(1) {
    [0]=>
    string(6) "ledger"
  }
  [3]=>
  bool(false)
  [4]=>
  int(1)
  [5]=>
  bool(false)
  [6]=>
  int(2)
  [7]=>
  bool(true)
  [8]=>
  bool(true)
  [9]=>
  bool(false)
}
array(16) {
  [0]=>
  array(5) {
    [0]=>
    string(5) "count"
    [1]=>
    string(5) "limit"
    [2]=>
    string(6) "hidden"
    [3]=>
    string(6) "serial"
    [4]=>
    string(5) "label"
  }
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  string(7) "counter"
  [5]=>
  NULL
  [6]=>
  NULL
  [7]=>
  int(5)
  [8]=>
  int(1)
  [9]=>
  string(11) "$limit::get"
  [10]=>
  int(5)
  [11]=>
  bool(true)
  [12]=>
  int(2)
  [13]=>
  string(103) "Cannot modify private(set) property ReflectedMembers\Counter::$count from scope ReflectedMembers\Report"
  [14]=>
  string(64) "Cannot access private property ReflectedMembers\Counter::$hidden"
  [15]=>
  string(54) "Property ReflectedMembers\Counter::$label is read-only"
}
array(13) {
  [0]=>
  bool(true)
  [1]=>
  string(7) "COUNTER"
  [2]=>
  int(2)
  [3]=>
  int(3)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  int(4)
  [7]=>
  bool(false)
  [8]=>
  int(0)
  [9]=>
  bool(true)
  [10]=>
  bool(false)
  [11]=>
  bool(true)
  [12]=>
  int(2)
}
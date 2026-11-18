--TEST--
A class value reads every fact a class declaration states: its applied base and interfaces, whether it is abstract, an interface, a struct or an enum, an enum's backing type and cases with their attributes, a generic type made from its definition, the constructor apart from the methods, each property's writer, a void and a never method's result, its use as a Map key, and a static method invoked through reflection on the class value it was read from
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

var_dump(ReflectedClasses\Facts::shape());
var_dump(ReflectedClasses\Facts::cases());
var_dump(ReflectedClasses\Facts::made());
var_dump(ReflectedClasses\Facts::constructed());
foreach (ReflectedClasses\Facts::writers() as $line) {
    echo $line, "\n";
}
var_dump(ReflectedClasses\Facts::results());
var_dump(ReflectedClasses\Facts::keys());
var_dump(ReflectedClasses\Facts::statics());
?>
--EXPECT--
array(7) {
  [0]=>
  string(26) "ReflectedClasses.ValueList"
  [1]=>
  array(1) {
    [0]=>
    string(24) "ReflectedClasses.Labeled"
  }
  [2]=>
  NULL
  [3]=>
  array(4) {
    [0]=>
    bool(true)
    [1]=>
    bool(false)
    [2]=>
    bool(false)
    [3]=>
    bool(false)
  }
  [4]=>
  array(2) {
    [0]=>
    bool(true)
    [1]=>
    bool(true)
  }
  [5]=>
  array(2) {
    [0]=>
    bool(true)
    [1]=>
    bool(false)
  }
  [6]=>
  array(2) {
    [0]=>
    bool(true)
    [1]=>
    bool(false)
  }
}
array(6) {
  [0]=>
  bool(true)
  [1]=>
  NULL
  [2]=>
  array(2) {
    [0]=>
    string(3) "Low"
    [1]=>
    string(4) "High"
  }
  [3]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
  [4]=>
  bool(true)
  [5]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(0)
  }
}
array(4) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  string(26) "ReflectedClasses.ValueList"
  [3]=>
  string(57) "List takes 1 type argument, and makeGenericType gave it 2"
}
array(6) {
  [0]=>
  bool(true)
  [1]=>
  array(2) {
    [0]=>
    string(2) "id"
    [1]=>
    string(4) "note"
  }
  [2]=>
  array(2) {
    [0]=>
    bool(false)
    [1]=>
    bool(true)
  }
  [3]=>
  int(1)
  [4]=>
  bool(false)
  [5]=>
  NULL
}
name: internal init=no
code: private init=no
key: public init=yes
id: none init=no
array(4) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(3)
}
array(4) {
  [0]=>
  bool(true)
  [1]=>
  string(27) "ReflectedClasses.Dictionary"
  [2]=>
  string(26) "ReflectedClasses.ValueList"
  [3]=>
  string(105) "`invoke` calls the instance method ReflectedClasses\ValueList::label() on a receiver, and was given null."
}
--TEST--
A PHP# struct keys a Map and joins a Set by its whole value, stored as its key text and read back as the struct
--FILE--
<?php
require __DIR__ . '/struct_keys.inc';
?>
--EXPECTF--
array(2) {
  ["Demo\Spot{"x":1,"y":2}"]=>
  int(2)
  ["Demo\Spot{"x":2,"y":1}"]=>
  int(3)
}
array(2) {
  [0]=>
  int(212)
  [1]=>
  int(321)
}
array(1) {
  ["Demo\Spot{"x":1,"y":2}"]=>
  int(2)
}
array(2) {
  ["Demo\Spot{"x":1,"y":2}"]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(1)
    ["y"]=>
    int(2)
  }
  ["Demo\Spot{"x":3,"y":4}"]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(3)
    ["y"]=>
    int(4)
  }
}
array(1) {
  [0]=>
  string(180) "Demo\Route{"stops":[Demo\Spot{"x":1,"y":2}],"standing":Lib\Standing::Active,"tone":Demo\Tone::Warm,"length":0.0,"tolls":{"7":3,"a":1,"b":2},"note":"a \"quoted\"\u000a","open":true}"
}
array(1) {
  [0]=>
  object(Demo\Route)#%d (7) {
    ["stops"]=>
    array(1) {
      [0]=>
      object(Demo\Spot)#%d (2) {
        ["x"]=>
        int(1)
        ["y"]=>
        int(2)
      }
    }
    ["standing"]=>
    enum(Lib\Standing::Active)
    ["tone"]=>
    enum(Demo\Tone::Warm)
    ["length"]=>
    float(0)
    ["tolls"]=>
    array(3) {
      [7]=>
      int(3)
      ["a"]=>
      int(1)
      ["b"]=>
      int(2)
    }
    ["note"]=>
    string(11) "a "quoted"
"
    ["open"]=>
    bool(true)
  }
}
array(3) {
  [0]=>
  string(130) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":0.1,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(0.1)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(130) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":1.0,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(1)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(134) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":1.0E+25,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(1.0E+25)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(130) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":0.0,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(0)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(130) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":INF,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(INF)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(131) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":-INF,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(-INF)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(130) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":NAN,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(NAN)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(140) "Demo\Route{"stops":[],"standing":Lib\Standing::Closed,"tone":Demo\Tone::Cool,"length":123456789.125,"tolls":[],"note":"\u0001","open":false}"
  [1]=>
  float(123456789.125)
  [2]=>
  bool(true)
}
array(1) {
  ["Demo\Spot{"x":1,"y":2}"]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(1)
    ["y"]=>
    int(2)
  }
}
ValueError: Sharp\Map::readKey(): Argument #1 ($text) names stdClass, which is not a struct in struct_keys.inc on line 50
ValueError: Sharp\Map::readKey(): Argument #1 ($text) names Demo\Missing, which does not exist in struct_keys.inc on line 51
TypeError: Cannot assign string to property Demo\Spot::$x of type int in struct_keys.inc on line 52
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 53
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 54
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 55
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 56
ValueError: Sharp\Map::readKey(): Argument #1 ($text) nests deeper than 512 in struct_keys.inc on line 57
ValueError: Sharp\Map::readKey(): Argument #1 ($text) names stdClass, which is not an enum in struct_keys.inc on line 58
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 59
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 60
ValueError: Sharp\Map::readKey(): Argument #1 ($text) is not a key text in struct_keys.inc on line 61
TypeError: Sharp\Map::key(): Argument #1 ($struct) must be of type Sharp\Struct, stdClass given in struct_keys.inc on line 62
TypeError: Sharp\Set::from(): Argument #1 ($values) must hold only int, string, backed enum or struct values, stdClass given in struct_keys.inc on line 63
TypeError: A struct key holds only values, stdClass given in struct_keys.inc on line 66
ValueError: A key text nests deeper than 512 in struct_keys.inc on line 67
Error: Demo\Spot::$x must be set before its struct is a key in struct_keys.inc on line 68

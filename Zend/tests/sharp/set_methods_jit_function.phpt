--TEST--
A PHP# Set holds each element once in its first order, under its key, wherever it lives, under the function JIT
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
require __DIR__ . '/set_methods.inc';
?>
--EXPECT--
jit on
array(9) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  bool(false)
  [4]=>
  bool(true)
  [5]=>
  bool(false)
  [6]=>
  int(2)
  [7]=>
  array(2) {
    [0]=>
    string(1) "5"
    [1]=>
    string(3) "vip"
  }
  [8]=>
  string(6) "5;vip;"
}
array(6) {
  [0]=>
  array(2) {
    [0]=>
    string(1) "5"
    [1]=>
    string(3) "tea"
  }
  [1]=>
  array(3) {
    [0]=>
    int(3)
    [1]=>
    int(1)
    [2]=>
    int(3)
  }
  [2]=>
  bool(true)
  [3]=>
  string(3) "vip"
  [4]=>
  array(3) {
    [0]=>
    string(1) "5"
    [1]=>
    string(3) "tea"
    [2]=>
    string(3) "vip"
  }
  [5]=>
  string(9) "vip,5,tea"
}
OutOfRangeException: No element matches the predicate in Sets.sharp on line 43
array(7) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(false)
  [3]=>
  bool(true)
  [4]=>
  array(3) {
    [0]=>
    int(5)
    [1]=>
    int(2)
    [2]=>
    int(4)
  }
  [5]=>
  array(2) {
    [0]=>
    int(3)
    [1]=>
    int(1)
  }
  [6]=>
  int(11)
}
array(7) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  array(1) {
    [0]=>
    enum(Lib\Standing::Closed)
  }
  [5]=>
  array(2) {
    [0]=>
    enum(Lib\Size::Large)
    [1]=>
    enum(Lib\Size::Small)
  }
  [6]=>
  bool(true)
}
TypeError: Sharp\Set::from(): Argument #1 ($values) must hold only int, string or backed enum values, float given in Sets.sharp on line 18
array(2) {
  [3]=>
  int(3)
  [1]=>
  int(1)
}
array(13) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  array(1) {
    [0]=>
    string(3) "vip"
  }
  [4]=>
  bool(true)
  [5]=>
  bool(false)
  [6]=>
  bool(true)
  [7]=>
  bool(false)
  [8]=>
  int(2)
  [9]=>
  bool(true)
  [10]=>
  bool(true)
  [11]=>
  bool(true)
  [12]=>
  array(3) {
    [0]=>
    int(3)
    [1]=>
    int(1)
    [2]=>
    int(9)
  }
}
array(1) {
  ["vip"]=>
  string(3) "vip"
}
array(9) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  int(1)
  [4]=>
  array(2) {
    [0]=>
    string(3) "vip"
    [1]=>
    string(2) "n1"
  }
  [5]=>
  array(0) {
  }
  [6]=>
  bool(false)
  [7]=>
  bool(true)
  [8]=>
  int(1)
}
array(9) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  int(2)
  [4]=>
  array(1) {
    [0]=>
    string(2) "n2"
  }
  [5]=>
  array(0) {
  }
  [6]=>
  NULL
  [7]=>
  NULL
  [8]=>
  NULL
}
array(2) {
  ["b"]=>
  string(1) "b"
  ["a"]=>
  string(1) "a"
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  int(2)
}
array(2) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  NULL
  [2]=>
  bool(true)
}
array(3) {
  ["b"]=>
  string(1) "b"
  ["a"]=>
  string(1) "a"
  [7]=>
  string(1) "7"
}
array(2) {
  [0]=>
  array(2) {
    [3]=>
    int(3)
    [1]=>
    int(1)
  }
  [1]=>
  bool(true)
}
array(2) {
  ["closed"]=>
  enum(Lib\Standing::Closed)
  ["active"]=>
  enum(Lib\Standing::Active)
}
array(2) {
  [2]=>
  enum(Lib\Size::Large)
  [5]=>
  enum(Lib\Code::Five)
}
array(1) {
  ["x"]=>
  string(1) "x"
}
TypeError: Sharp\Set::from(): Argument #1 ($values) must hold only int, string or backed enum values, null given in set_methods.inc on line 43
TypeError: Sharp\Set::from(): Argument #1 ($values) must hold only int, string or backed enum values, stdClass given in set_methods.inc on line 44

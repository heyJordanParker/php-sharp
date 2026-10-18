--TEST--
A PHP# Set holds each element once in its first order, under its key, wherever it lives, under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/set_methods.inc';
?>
--EXPECTF--
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
OutOfRangeException: No element matches the predicate in Tagged.sharp on line 43
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
TypeError: Demo\Tagged::strings(): Argument #1 ($tags) must be of type Set<string>, array given: [0] expected string, got float 1.5, called in %s on line %d in Tagged.sharp on line 18
array(2) {
  [0]=>
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
  [0]=>
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
  [0]=>
  string(1) "b"
  [1]=>
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
object(Sharp\Set)#19 (4) {
  [0]=>
  string(1) "b"
  [1]=>
  string(1) "a"
  [2]=>
  string(1) "7"
  [3]=>
  int(7)
}
object(Sharp\Set)#19 (2) {
  [0]=>
  int(3)
  [1]=>
  int(1)
}
object(Sharp\Set)#19 (2) {
  [0]=>
  enum(Lib\Standing::Closed)
  [1]=>
  enum(Lib\Standing::Active)
}
object(Sharp\Set)#19 (2) {
  [0]=>
  enum(Lib\Size::Large)
  [1]=>
  enum(Lib\Code::Five)
}
object(Sharp\Set)#19 (1) {
  [0]=>
  string(1) "x"
}
object(Sharp\Set)#19 (2) {
  [0]=>
  string(1) "x"
  [1]=>
  NULL
}
object(Sharp\Set)#19 (1) {
  [0]=>
  object(stdClass)#13 (0) {
  }
}

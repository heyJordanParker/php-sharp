--TEST--
PHP# collection methods change a List or Map where it lives under the function JIT
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
require __DIR__ . '/collection_methods.inc';
?>
--EXPECT--
jit on
array(3) {
  [0]=>
  int(9)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
object(Sharp\Map)#3 (1) {
  [0]=>
  array(2) {
    [0]=>
    string(1) "5"
    [1]=>
    int(7)
  }
}
int(2)
NULL
NULL
int(7)
NULL
object(Sharp\Map)#3 (2) {
  [0]=>
  array(2) {
    [0]=>
    int(0)
    [1]=>
    int(5)
  }
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(6)
  }
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
object(Sharp\Map)#3 (1) {
  [0]=>
  array(2) {
    [0]=>
    int(0)
    [1]=>
    array(2) {
      [0]=>
      int(1)
      [1]=>
      int(2)
    }
  }
}
OutOfRangeException: Undefined array key 2 in Pantry.sharp on line 54
object(Sharp\Map)#6 (1) {
  [0]=>
  array(2) {
    [0]=>
    string(3) "pie"
    [1]=>
    int(7)
  }
}
object(Sharp\Map)#6 (1) {
  [0]=>
  array(2) {
    [0]=>
    string(3) "pie"
    [1]=>
    int(7)
  }
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(8)
}
array(1) {
  [0]=>
  int(3)
}
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
int(2)
int(7)
int(2)
int(2)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(0)
int(3)
int(2)
int(3)
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
int(3)
NULL
int(4)
NULL
int(4)
NULL
string(6) "2 of 2"
array(3) {
  [0]=>
  int(9)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(9)
}
OutOfRangeException: Undefined array key 3 in Pantry.sharp on line 120
OutOfRangeException: Undefined array key -1 in Pantry.sharp on line 120
bool(true)
bool(false)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(1)
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(1)
}
array(0) {
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(9)
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(3)
    [2]=>
    int(4)
  }
  [1]=>
  int(2)
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(4)
  }
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  bool(false)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(4)
  }
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  int(4)
}
int(5)
OutOfRangeException: The list is empty. in Pantry.sharp on line 166
int(1)
NULL
bool(true)
bool(false)
bool(true)
bool(false)
array(2) {
  [0]=>
  string(1) "5"
  [1]=>
  string(3) "tea"
}
array(3) {
  [0]=>
  int(2)
  [1]=>
  int(7)
  [2]=>
  int(2)
}
object(Sharp\Set)#9 (3) {
  [0]=>
  int(3)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(4) {
  [0]=>
  int(2)
  [1]=>
  string(1) "b"
  [2]=>
  string(4) "none"
  [3]=>
  int(1)
}
object(Sharp\Map)#10 (2) {
  [0]=>
  array(2) {
    [0]=>
    string(3) "tea"
    [1]=>
    int(3)
  }
  [1]=>
  array(2) {
    [0]=>
    string(3) "pie"
    [1]=>
    int(7)
  }
}
bool(true)
bool(false)
bool(true)
array(1) {
  [0]=>
  int(7)
}
NULL
DivisionByZeroError: Modulo by zero in Pantry.sharp on line 78
NULL
Error: Call to private Sharp\Collection::__construct() from global scope in collection_methods.inc on line 110
ReflectionException: Class Sharp\Collection is an internal class marked as final that cannot be instantiated without invoking its constructor in collection_methods.inc on line 111
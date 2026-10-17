--TEST--
A PHP# struct keys a Map and joins a Set by its whole value, and reads back as the struct it was
--FILE--
<?php
require __DIR__ . '/struct_keys.inc';
?>
--EXPECTF--
object(Sharp\Map)#%d (2) {
  [0]=>
  array(2) {
    [0]=>
    object(Demo\Spot)#%d (2) {
      ["x"]=>
      int(1)
      ["y"]=>
      int(2)
    }
    [1]=>
    int(2)
  }
  [1]=>
  array(2) {
    [0]=>
    object(Demo\Spot)#%d (2) {
      ["x"]=>
      int(2)
      ["y"]=>
      int(1)
    }
    [1]=>
    int(3)
  }
}
array(2) {
  [0]=>
  int(212)
  [1]=>
  int(321)
}
object(Sharp\Map)#%d (1) {
  [0]=>
  array(2) {
    [0]=>
    object(Demo\Spot)#%d (2) {
      ["x"]=>
      int(1)
      ["y"]=>
      int(2)
    }
    [1]=>
    int(2)
  }
}
array(2) {
  [0]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(1)
    ["y"]=>
    int(2)
  }
  [1]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(3)
    ["y"]=>
    int(4)
  }
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  array(1) {
    [0]=>
    int(2)
  }
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
    object(Sharp\Map)#%d (3) {
      [0]=>
      array(2) {
        [0]=>
        string(1) "b"
        [1]=>
        int(2)
      }
      [1]=>
      array(2) {
        [0]=>
        string(1) "a"
        [1]=>
        int(1)
      }
      [2]=>
      array(2) {
        [0]=>
        string(1) "7"
        [1]=>
        int(3)
      }
    }
    ["note"]=>
    string(11) "a "quoted"
"
    ["open"]=>
    bool(true)
  }
}
object(Sharp\Set)#%d (1) {
  [0]=>
  object(Demo\Spot)#%d (2) {
    ["x"]=>
    int(1)
    ["y"]=>
    int(2)
  }
}
int(2)

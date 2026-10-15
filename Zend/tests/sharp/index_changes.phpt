--TEST--
A PHP# List index is a place: a change through it changes the element the list stores
--FILE--
<?php
require __DIR__ . '/index_changes.inc';
?>
--EXPECT--
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(5)
  }
  [1]=>
  array(2) {
    [0]=>
    int(8)
    [1]=>
    int(3)
  }
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(1) {
    [0]=>
    int(3)
  }
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
  [1]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(2)
  }
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    array(1) {
      [0]=>
      int(1)
    }
  }
  [1]=>
  array(1) {
    [0]=>
    array(2) {
      [0]=>
      int(2)
      [1]=>
      int(3)
    }
  }
}
array(2) {
  [0]=>
  object(Sharp\Set)#6 (2) {
    [0]=>
    int(1)
    [1]=>
    int(4)
  }
  [1]=>
  object(Sharp\Set)#7 (0) {
  }
}
array(2) {
  [0]=>
  object(Sharp\Map)#6 (1) {
    [0]=>
    array(2) {
      [0]=>
      string(1) "d"
      [1]=>
      int(4)
    }
  }
  [1]=>
  object(Sharp\Map)#9 (2) {
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
      string(1) "c"
      [1]=>
      int(3)
    }
  }
}
array(2) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(9)
  }
  [1]=>
  array(0) {
  }
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    object(Sharp\Map)#6 (1) {
      [0]=>
      array(2) {
        [0]=>
        string(1) "b"
        [1]=>
        int(2)
      }
    }
  }
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  object(Sharp\Map)#9 (3) {
    [0]=>
    array(2) {
      [0]=>
      string(1) "a"
      [1]=>
      int(1)
    }
    [1]=>
    array(2) {
      [0]=>
      string(1) "c"
      [1]=>
      int(3)
    }
    [2]=>
    array(2) {
      [0]=>
      string(1) "d"
      [1]=>
      int(4)
    }
  }
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    array(0) {
    }
    [1]=>
    array(1) {
      [0]=>
      int(3)
    }
  }
  [1]=>
  int(1)
}
array(4) {
  [0]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      int(1)
      [1]=>
      int(5)
    }
    [1]=>
    NULL
  }
  [1]=>
  NULL
  [2]=>
  NULL
  [3]=>
  NULL
}
array(4) {
  [0]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      int(1)
      [1]=>
      int(5)
    }
    [1]=>
    NULL
  }
  [1]=>
  NULL
  [2]=>
  NULL
  [3]=>
  NULL
}
array(2) {
  [0]=>
  array(4) {
    [0]=>
    array(2) {
      [0]=>
      array(2) {
        [0]=>
        int(1)
        [1]=>
        int(5)
      }
      [1]=>
      NULL
    }
    [1]=>
    NULL
    [2]=>
    NULL
    [3]=>
    NULL
  }
  [1]=>
  int(1)
}
array(10) {
  [0]=>
  array(1) {
    [0]=>
    NULL
  }
  [1]=>
  NULL
  [2]=>
  NULL
  [3]=>
  NULL
  [4]=>
  array(1) {
    [0]=>
    NULL
  }
  [5]=>
  NULL
  [6]=>
  NULL
  [7]=>
  array(1) {
    [0]=>
    NULL
  }
  [8]=>
  NULL
  [9]=>
  NULL
}
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 170
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 172
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 174
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 176
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 178
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 180
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 182
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 195
int(0)
array(2) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
}
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 184
array(2) {
  [0]=>
  int(3)
  [1]=>
  NULL
}
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 186
array(2) {
  [0]=>
  string(6) "size 3"
  [1]=>
  string(4) "none"
}
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 188
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(5)
}
OutOfRangeException: Undefined array key 2 in Grid.sharp on line 214
OutOfRangeException: Undefined array key -1 in Grid.sharp on line 214
array(6) {
  [0]=>
  int(12)
  [1]=>
  int(2)
  [2]=>
  int(40)
  [3]=>
  int(13)
  [4]=>
  int(1)
  [5]=>
  int(40)
}
array(3) {
  [0]=>
  array(1) {
    [0]=>
    string(2) "a!"
  }
  [1]=>
  array(1) {
    [0]=>
    float(2.75)
  }
  [2]=>
  float(1.75)
}
OutOfRangeException: Undefined array key 5 in Grid.sharp on line 240
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(9)
  }
  [1]=>
  array(2) {
    [0]=>
    int(5)
    [1]=>
    int(5)
  }
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      int(6)
      [1]=>
      int(2)
    }
    [1]=>
    array(1) {
      [0]=>
      int(7)
    }
  }
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(12)
  }
  [1]=>
  array(2) {
    [0]=>
    int(3)
    [1]=>
    int(4)
  }
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(4)
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    int(1)
  }
  [1]=>
  array(1) {
    [0]=>
    int(3)
  }
}
OutOfRangeException: Undefined array key 5 in Grid.sharp on line 118
OutOfRangeException: Undefined array key 1 in Grid.sharp on line 55
OutOfRangeException: Undefined array key 2 in Grid.sharp on line 124
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 200
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 206
int(8)
OutOfRangeException: Undefined array key 5 in Point.sharp on line 32
array(7) {
  [0]=>
  int(0)
  [1]=>
  NULL
  [2]=>
  NULL
  [3]=>
  int(0)
  [4]=>
  NULL
  [5]=>
  int(0)
  [6]=>
  NULL
}
OutOfRangeException: Undefined array key 5 in Point.sharp on line 20
OutOfRangeException: Undefined array key 5 in Point.sharp on line 25
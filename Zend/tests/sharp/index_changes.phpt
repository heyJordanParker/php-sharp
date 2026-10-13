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
  array(2) {
    [1]=>
    int(1)
    [4]=>
    int(4)
  }
  [1]=>
  array(0) {
  }
}
array(2) {
  [0]=>
  array(1) {
    ["d"]=>
    int(4)
  }
  [1]=>
  array(2) {
    ["b"]=>
    int(2)
    ["c"]=>
    int(3)
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
    array(1) {
      ["b"]=>
      int(2)
    }
  }
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  array(3) {
    ["a"]=>
    int(1)
    ["c"]=>
    int(3)
    ["d"]=>
    int(4)
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
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 163
OutOfRangeException: Undefined array key 9 in Grid.sharp on line 169
int(8)
OutOfRangeException: Undefined array key 5 in Point.sharp on line 9

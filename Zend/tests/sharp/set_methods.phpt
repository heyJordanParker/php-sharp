--TEST--
A PHP# Set holds each element once in its first order, under its key, wherever it lives
--FILE--
<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Probe.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/vendor/autoload.php';

    // Each round runs every method, so the JIT variants compile them before the last round, whose output is printed.
    for ($round = 0; $round < 100; $round++) {
        ob_start();
        // Plain PHP passes a list, which each Set parameter makes a Set: duplicates go, the first order stays.
        attempt(fn () => Demo\Tagged::strings(['new', '5', 'new']));
        attempt(fn () => Demo\Tagged::reads(['vip', '5', 'tea']));
        attempt(fn () => Demo\Tagged::missing(['vip']));
        attempt(fn () => Demo\Tagged::ints([5, 2 => 2, 5]));
        attempt(fn () => Demo\Tagged::cases([Lib\Standing::Active]));
        attempt(fn () => Demo\Tagged::strings([1.5]));

        $tagged = new Demo\Tagged([3, 3, 1], ['b', 'a', 'b']);
        attempt(fn () => $tagged->ids);
        attempt(fn () => $tagged->properties(Lib\Standing::Closed));
        attempt(fn () => $tagged->tags);
        attempt(fn () => $tagged->once(['x']));
        attempt(fn () => $tagged->once(null));
        // A get-only promoted Set is set once, to the Set of the list.
        attempt(fn () => $tagged->labels);
        // A lambda, a variadic and a nullable variadic make each list they take a Set, so contains finds an element.
        attempt(fn () => Demo\Tagged::lambdas(['v', 'v', 'x']));
        attempt(fn () => Demo\Tagged::groups(['a', 'b'], ['c']));
        attempt(fn () => Demo\Tagged::maybe(['a'], null, ['b', 'a']));
        $output = ob_get_clean();
    }

    echo $output;

    // new Sharp\Set keeps each element once, in its first place, whatever key the array holds it under.
    attempt(fn () => new Sharp\Set(['b', 'a', 'b', '7', 7]));
    attempt(fn () => new Sharp\Set([3, 1, 3]));
    attempt(fn () => new Sharp\Set([Lib\Standing::Closed, Lib\Standing::Active, Lib\Standing::Closed]));
    attempt(fn () => new Sharp\Set([Lib\Size::Large, Lib\Code::Five]));
    attempt(fn () => new Sharp\Set([1 => 'x']));
    attempt(fn () => new Sharp\Set(['x', null]));
    attempt(fn () => new Sharp\Set([new stdClass()]));
}
?>
--EXPECTF--
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
OutOfRangeException: `first` finds an element the predicate matches, and none does. in Tagged.sharp on line 45
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
TypeError: Demo\Tagged::strings(): Argument #1 ($tags) must be of type Set<string>, array given: [0] expected string, got float 1.5, called in %s on line %d in Tagged.sharp on line 19
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
object(Sharp\Set)#%d (4) {
  [0]=>
  string(1) "b"
  [1]=>
  string(1) "a"
  [2]=>
  string(1) "7"
  [3]=>
  int(7)
}
object(Sharp\Set)#%d (2) {
  [0]=>
  int(3)
  [1]=>
  int(1)
}
object(Sharp\Set)#%d (2) {
  [0]=>
  enum(Lib\Standing::Closed)
  [1]=>
  enum(Lib\Standing::Active)
}
object(Sharp\Set)#%d (2) {
  [0]=>
  enum(Lib\Size::Large)
  [1]=>
  enum(Lib\Code::Five)
}
object(Sharp\Set)#%d (1) {
  [0]=>
  string(1) "x"
}
object(Sharp\Set)#%d (2) {
  [0]=>
  string(1) "x"
  [1]=>
  NULL
}
object(Sharp\Set)#%d (1) {
  [0]=>
  object(stdClass)#%d (0) {
  }
}

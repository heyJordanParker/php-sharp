--TEST--
PHP# stores a backed enum key in a Map as its value, and plain PHP keeps refusing it
--FILE--
<?php

namespace {
    use Lib\Code;
    use Lib\Size;
    use Lib\Standing;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/vendor/autoload.php';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        $headcount = new Demo\Headcount();
        $headcount->add(Standing::Active);
        attempt(fn () => $headcount->add(Standing::Active));
        attempt(fn () => $headcount->seededCounts());
        attempt(fn () => Demo\Headcount::count(['closed' => 2], Standing::Closed));
        attempt(fn () => Demo\Headcount::count([], Standing::Active));
        attempt(fn () => Demo\Headcount::ensure(['closed' => 2], Standing::Closed));
        attempt(fn () => Demo\Headcount::ensure([], Standing::Active));
        attempt(fn () => Demo\Headcount::sizes(Size::Small, Size::Large));
        attempt(fn () => Demo\Headcount::codes(Code::Five));
        attempt(fn () => Demo\Headcount::lookup(['active' => 3], Standing::Active));
        attempt(fn () => Demo\Headcount::lookup(['active' => 3], Standing::Closed));
        attempt(fn () => Demo\Headcount::without(['active' => 3, 'closed' => 4], Standing::Active));
        attempt(fn () => Demo\Headcount::nest(Standing::Closed, Size::Large));
        attempt(fn () => Demo\Headcount::byName(['active' => 3, 'closed' => 4]));
        // A key that is no case fails the Map's check where plain PHP hands it in.
        attempt(fn () => Demo\Headcount::byName(['missing' => 1]));
        // PHP stores the key "5" as the int 5, and PHP# reads it back as the string "5".
        attempt(fn () => Demo\Headcount::skus(['5' => 1, 'tea' => 2, 'pie' => 0]));
        // groupBy keyed by a backed enum: plain PHP sees the backing values, and a loop reads back the cases.
        attempt(fn () => Demo\Headcount::grouped([1, 2, 3]));
        attempt(fn () => Demo\Headcount::groupNames([1, 2, 3]));
        $output = ob_get_clean();
    }

    echo $output;

    // Plain PHP keeps refusing an enum as a key.
    attempt(function () {
        $counts = [];
        $counts[Standing::Active] = 1;
        return $counts;
    });
    attempt(fn () => ['active' => 1][Standing::Active] ?? 0);
    attempt(fn () => [Size::Small => 'small']);
    attempt(fn () => (new class { public array $seeded = [Standing::Active => 0]; })->seeded);
}
?>
--EXPECTF--
array(1) {
  ["active"]=>
  int(2)
}
array(1) {
  ["active"]=>
  int(0)
}
array(1) {
  ["closed"]=>
  int(3)
}
array(1) {
  ["active"]=>
  int(1)
}
array(1) {
  ["closed"]=>
  int(2)
}
array(1) {
  ["active"]=>
  int(0)
}
array(2) {
  [1]=>
  string(5) "small"
  [2]=>
  string(5) "large"
}
array(1) {
  [5]=>
  int(5)
}
int(3)
NULL
array(1) {
  ["closed"]=>
  int(4)
}
array(1) {
  ["closed"]=>
  array(1) {
    [2]=>
    int(1)
  }
}
array(2) {
  ["Active"]=>
  int(3)
  ["Closed"]=>
  int(4)
}
TypeError: Demo\Headcount::byName(): Argument #1 ($counts) must be of type Map<Lib.Standing, int>, array given: ["missing"] key expected Lib.Standing, got string 'missing', called in %s on line %d in Headcount.sharp on line 70
array(2) {
  [0]=>
  string(1) "5"
  [1]=>
  string(3) "tea"
}
array(2) {
  ["active"]=>
  array(1) {
    [0]=>
    int(1)
  }
  ["closed"]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(3)
  }
}
array(2) {
  [0]=>
  string(6) "Active"
  [1]=>
  string(6) "Closed"
}
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.php on line 44
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.php on line 47
TypeError: Cannot access offset of type Lib\Size on array in enum_keys.php on line 48
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.php on line 49

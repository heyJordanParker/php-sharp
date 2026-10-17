--TEST--
PHP# collection methods change a static List, Map or Set where it lives
--FILE--
<?php
require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('StaticCollections');

// Each round runs every form, so the JIT runs compiled code before the last round, whose output is printed.
for ($round = 0; $round < 3; $round++) {
    ob_start();
    Demo\Log::forget();
    Demo\Log::note("a");
    Demo\Log::note("b");
    attempt(fn () => Demo\Log::seen());
    attempt(fn () => Demo\Archive::retitle("intro"));
    attempt(fn () => Demo\Archive::sell("tea"));
    attempt(fn () => Demo\Archive::tag());
    attempt(fn () => Demo\Branch::stack("atlas"));
    attempt(fn () => Demo\Archive::show("poems"));
    attempt(fn () => Demo\Patron::count());
    attempt(fn () => (new Demo\Archive())->keep("diary"));
    // An object in a static field is no collection: its method runs on the object itself.
    // A loop, so the tracing JIT runs the fetch it compiled inline.
    attempt(function () {
        $ticks = 0;
        for ($i = 0; $i < 3; $i++) {
            $ticks += Demo\Archive::tickTwice();
        }
        return $ticks;
    });
    // A lambda bound to a scope that may not set the static List throws, as an assignment to it from there does.
    attempt(fn () => Closure::bind(Demo\Archive::shower(), null, Demo\Patron::class)("verse"));
    attempt(fn () => Demo\Archive::$shown);
    // A change copies an array another static or a local shares, and reaches a plain PHP reference to the static.
    attempt(fn () => Demo\Archive::sharedWithStatic());
    attempt(fn () => Demo\Archive::sharedWithLocal());
    $ref = &Demo\Archive::$shelf;
    $ref = ["one"];
    Demo\Archive::shelve("two");
    var_dump($ref);
    unset($ref);
    $output = ob_get_clean();
}

echo $output;
?>
--EXPECT--
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
array(2) {
  [0]=>
  string(5) "intro"
  [1]=>
  string(5) "notes"
}
array(1) {
  ["pie"]=>
  int(7)
}
array(5) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  array(1) {
    [0]=>
    string(4) "sale"
  }
  [4]=>
  array(0) {
  }
}
array(1) {
  [0]=>
  string(5) "atlas"
}
array(1) {
  [0]=>
  string(5) "poems"
}
int(1)
array(1) {
  [0]=>
  string(5) "diary"
}
int(6)
Error: Cannot modify private(set) property Demo\Archive::$shown from scope Demo\Patron in Archive.sharp on line 49
array(1) {
  [0]=>
  string(5) "poems"
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(3) "one"
  }
  [1]=>
  array(2) {
    [0]=>
    string(3) "one"
    [1]=>
    string(3) "two"
  }
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(3) "one"
  }
  [1]=>
  array(2) {
    [0]=>
    string(3) "one"
    [1]=>
    string(3) "two"
  }
}
array(2) {
  [0]=>
  string(3) "one"
  [1]=>
  string(3) "two"
}

--TEST--
PHP# willSet and didSet run only once the object is built: after its constructor and any object initializer finish, on a method's write and plain PHP's, an unchanged value's included, and never on an initial value, a constructor's write, a named constructor's, an object initializer's or a with copy's, even when a named constructor's body throws; an initial value fills the storage without the set body, which a write in the constructor runs
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Words.inc';
fixture('Observers');

// The initial values fill the storage without `set` or an observer, a computed one included, and the static's too.
// The constructor's writes, a method's it calls included, run `set` and no observer.
$account = new Observed\Account('Ada');
echo $account->name, ' ', $account->balance, "\n";

// A write once the object is built runs both observers, and `field` in `willSet` reads the value it replaces.
echo "deposit\n";
$account->deposit(3);
echo "unchanged\n";
$account->balance = 6;
echo "plain PHP\n";
$account->name = '  Bo  ';
echo $account->name, ' ', $account->balance, "\n";

// A named constructor's body still builds the object.
echo "named\n";
$opened = Observed\Account::opening('Cy');
echo $opened->balance, "\n";

// A `with` copy is built from its values, so they run `set` and no observer.
echo "copy\n";
$copy = $account->copied();
echo $copy->name, ' ', $copy->balance, ' ', $account->balance, "\n";

// The object is built once the constructor and its object initializer have finished, so the initializer's write runs
// no observer, and a later write does.
echo "initializer\n";
$started = Observed\Tally::started();
echo $started->count, "\n";
$started->count = 6;

// A named constructor whose body throws puts back the mark it found, so a later write runs the observer.
echo "throws\n";
try {
    Observed\Tally::failing();
} catch (RuntimeException $e) {
    echo $e->getMessage(), "\n";
}
Observed\Tally::last()->count = 7;

// An initializer inside a named constructor builds its own object, and both are built once the named constructor
// returns, so later writes of each run the observer.
echo "paired\n";
$paired = Observed\Tally::paired();
echo $paired->count, ' ', Observed\Tally::last()->count, "\n";
$paired->count = 8;
Observed\Tally::last()->count = 9;

// A static's initial value ran no observer, and a later write runs it.
echo "static\n";
echo Observed\Account::reopen(), "\n";
?>
--EXPECT--
constructor [  start  ] [  ]
set [Ada]
Ada 3
deposit
will 6 from 3
did 6
unchanged
will 6 from 6
did 6
plain PHP
set [  Bo  ]
named Bo
Bo 6
named
constructor [  start  ] [  ]
set [Cy]
7
copy
set [  copy  ]
copy 9 6
initializer
3
count 6
throws
failed
count 7
paired
4 2
count 8
count 9
static
opened 3
3

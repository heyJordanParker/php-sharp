--TEST--
PHP# willSet and didSet run on every write: the initial value's, the constructor's, a method's and plain PHP's, an unchanged value's included, around a set body
--FILE--
<?php

require __DIR__ . '/Observers.sharp';

$account = new Observed\Account('Ada');
echo "deposit\n";
$account->deposit(3);
echo "unchanged\n";
$account->balance = 5;
echo "plain PHP\n";
$account->name = '  Bo  ';
echo $account->name, ' ', $account->balance, "\n";
?>
--EXPECT--
will 1
did 1
constructor
set [Ada]
named Ada
will 2
did 2
deposit
will 5
did 5
unchanged
will 5
did 5
plain PHP
set [  Bo  ]
named Bo
Bo 5

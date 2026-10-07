--TEST--
A PHP# class filters, maps and sorts a List with lambdas, passes lambdas to plain PHP, calls functions held in properties and uses a method as a value
--FILE--
<?php
require __DIR__ . '/receipts.inc';
?>
--EXPECT--
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
Call to undefined method Lib\Ledger::scale()
NULL

--TEST--
A preloaded PHP# interface's constant, default property body and operators, and an enum's operator, run as in a request with no preloading
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/interface_members_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/interface_members.inc';
library();

var_dump(class_exists(InterfaceMembers\Report::class, false), trait_exists(InterfaceMembers\Priced\Defaults::class, false));
run_rows();
?>
--EXPECT--
bool(true)
bool(true)
Priced.CURRENCY and Book.CURRENCY: ["USD","USD"]
a default body and an override: ["250 USD","rare"]
two Priced values: [true,true,false,false,-1]
two Rares as Priced, by the interface: [true,true,false,false,-1]
two Books, by the interface: [true,-1]
two Rares, by their own: [false,1]
a plain PHP class as Priced: [false,false,true,true,1]
sortedBy agrees with <: Priced by the interface, Rare by its own: [[1,2,3],[1,2,3],[3,2,1]]
a Rare and a Book, in either order, by the interface: [true,false,-1,1]
two Gems by the sub-interface, and as Priced: [[false,1],[true,true,false,false,-1]]
an enum gets the interface operator: [true,1,"1 USD"]
sortedBy on a list of Books and Rares: [1,2,3]
a Set and a Map of Measure key by its ==: [1,1,1]
enum cases: [true,false,-1]
an enum compares its cases by its own ==: [true,true,true,2]
an interface ==, + and unary -: [7,-2,7,false,true,true,false,false,true]
plain PHP reads the constant and the default body: ["USD","250 USD"]
the trait holds the operator once: ["op_Comparison@InterfaceMembers\\Priced"]

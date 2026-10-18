--TEST--
A PHP# interface's constant reads through the interface and each implementer, its default property body runs unless a class overrides it, and its operators apply to every implementer, plain PHP's through the Defaults trait, while a class's own operator for two of its values wins, and an enum orders its cases by the operator it declares
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('InterfaceMembers');
require __DIR__ . '/interface_members.inc';
library();

run_rows();
?>
--EXPECT--
Priced.CURRENCY and Book.CURRENCY: ["USD","USD"]
a default body and an override: ["250 USD","rare"]
two Priced values: [true,true,false,false,-1]
two Rares as Priced, by the interface: [true,true,false,false,-1]
two Books, by the interface: [true,-1]
two Rares, by their own: [false,1]
a plain PHP class as Priced: [false,false,true,true,1]
sortedBy, by the interface and by Rare: [[1,2,3],[3,2,1]]
enum cases: [true,false,-1]
an enum compares its cases by its own ==: [true,true,true,2]
an interface ==, + and unary -: [7,-2,7,false,true,true,false,false,true]
plain PHP reads the constant and the default body: ["USD","250 USD"]
the trait holds the operator twice: ["op_Comparison","op_Comparison@InterfaceMembers\\Priced"]

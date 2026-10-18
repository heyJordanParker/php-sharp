--TEST--
A PHP# interface's constant reads through the interface and each implementer, its default property body runs unless a class overrides it, and each operator use runs the most specific operator the static types of both operands declare, in every operand order, for enums, unions and sub-interfaces, as sortedBy, Set and Map do
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
an interface constant takes every constant-value form: ["EUR","euro","near 9","Small",8]
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

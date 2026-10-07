--TEST--
PHP# union-typed parameters, fields and properties, promoted and variadic parameters take plain PHP calls, spreads and named arguments, and refuse a wrong value
--FILE--
<?php

require __DIR__ . '/Signatures.sharp';

$empty = new Demo\Signatures("none");
$made = new Demo\Signatures("none", 4, 5);
var_dump(
    Demo\Signatures::sum(),
    Demo\Signatures::sum(1, 2, 3),
    Demo\Signatures::sum(...[1, 2, 3]),
    Demo\Signatures::spread(2, 7, 3),
    Demo\Signatures::spread(...[2, 7, 3]),
    Demo\Signatures::made(4, 5),
    Demo\Signatures::made(...[4, 5]),
    Demo\Signatures::made(),
    $empty->label(""),
    $made->label(""),
    $made->label(7),
    $made->label("seven"),
    new Demo\Signatures(3)->label(""),
    $made->matching(9),
    $empty->matching("none"),
    $made->matching("none"),
    $made->matching(null),
    $made->isSame($made),
    $made->isSame(9),
    $made->isSame($empty),
    $made->isSame(4),
    $made->doubled(4),
    $made->doubled(4, 5),
    $made->doubled(...[4, 5]),
    $empty->pay(0),
    $empty->pay(2),
    $empty->amount,
    $made->pay(2.5),
    $made->amount,
);

try {
    $made->label([]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->matching([]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    Demo\Signatures::sum(1, "two");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    Demo\Signatures::spread(...[1, 2, []]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    new Demo\Signatures([]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->isSame("nine");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->amount = "ten";
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->doubled(1, 2, 3);
} catch (Error $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(0)
int(6)
int(6)
int(19)
int(19)
int(9)
int(9)
string(4) "none"
string(4) "none"
int(9)
int(7)
string(5) "seven"
int(3)
int(9)
string(4) "none"
NULL
NULL
bool(true)
bool(true)
bool(false)
bool(false)
int(8)
int(18)
int(18)
string(6) "unpaid"
string(4) "none"
int(2)
int(9)
float(2.5)
Demo\Signatures::label(): Argument #1 ($id) must be of type string|int, array given, called in %s on line %d
Demo\Signatures::matching(): Argument #1 ($id) must be of type string|int|null, array given, called in %s on line %d
Demo\Signatures::sum(): Argument #2 must be of type int, string given, called in %s on line %d
Demo\Signatures::spread(): Argument #3 must be of type int, array given, called in %s on line %d
Demo\Signatures::__construct(): Argument #1 ($id) must be of type string|int, array given, called in %s on line %d
Demo\Signatures::isSame(): Argument #1 ($other) must be of type Demo\Signatures|int, string given, called in %s on line %d
Cannot assign string to property Demo\Signatures::$amount of type int|float
Error: Named parameter $factor overwrites previous argument

--TEST--
PHP# `== null` and `!= null` hold for null alone, never for 0, "", "0" or false
--FILE--
<?php

require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
var_dump(
    $nulls->missing(0),
    $nulls->missing(null),
    $nulls->present(""),
    $nulls->present("0"),
    $nulls->present(null),
    $nulls->absent(false),
    $nulls->absent(null),
);
?>
--EXPECT--
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)

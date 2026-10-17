--TEST--
Preloading keeps Math.max and Math.min of Sharp.Math.Math: one int or more, spread or not
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/library_math.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php

// Preloading declared the classes before this request, which never ran the files that declare them.
var_dump(class_exists(Demo\MathCalls::class, false), class_exists(Sharp\Math\Math::class, false));

use Demo\MathCalls;

var_dump(MathCalls::max(2, 7), MathCalls::max(-3, -9), MathCalls::min(2, 7), MathCalls::min(-3, -9));
var_dump(MathCalls::maxOf(0, [900, 2900]), MathCalls::maxOf(0, []), MathCalls::maxOf(0, [-5]), MathCalls::maxOfNone(0));
var_dump(MathCalls::minOf(1000, [900, 2900]), MathCalls::minOf(0, []), MathCalls::minOf(0, [-5]));
var_dump(MathCalls::maxOne(4), MathCalls::minOne(-4));
?>
--EXPECT--
bool(true)
bool(true)
int(7)
int(-3)
int(2)
int(-9)
int(2900)
int(0)
int(0)
int(0)
int(900)
int(0)
int(-5)
int(4)
int(-4)

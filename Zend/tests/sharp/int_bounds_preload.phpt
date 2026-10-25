--TEST--
PHP#'s Int.MIN / -1 and Int.MIN.floorDiv(-1) overflow with a message that names Int.MIN, preloaded, though the optimizer knows every class it calls
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/int_bounds_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(function_exists('int_bounds_rounds'), class_exists(IntBounds\Bounds::class, false));
echo int_bounds_rounds();
?>
--EXPECT--
bool(true)
bool(true)
true true
Division of Int.MIN by -1 overflows an int
Division of Int.MIN by -1 overflows an int
Division of PHP_INT_MIN by -1 is not an integer

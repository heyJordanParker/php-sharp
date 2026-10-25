--TEST--
PHP#'s Int.MIN / -1 and Int.MIN.floorDiv(-1) overflow with a message that names Int.MIN, under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('IntBounds');
require __DIR__ . '/int_bounds.inc';
echo int_bounds_rounds();
?>
--EXPECT--
jit on
true true
Division of Int.MIN by -1 overflows an int
Division of Int.MIN by -1 overflows an int
Division of PHP_INT_MIN by -1 is not an integer

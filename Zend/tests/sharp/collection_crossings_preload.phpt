--TEST--
Plain PHP preloaded with PHP# classes reads each Map and Set they give as a PHP array, though the optimizer and the JIT know every class and property it reads
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/collection_crossings_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Demo\Holdings::class, false));

function received(mixed $value): string
{
    return get_debug_type($value) . ' ' . json_encode($value);
}

$holdings = new Demo\Holdings();
// Each round runs every read, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    echo 'a property: ', received(preloaded_payload($holdings)), "\n";
    echo 'count() of a property: ', preloaded_payload_count($holdings), "\n";
    echo 'a List<Map> property: ', received(preloaded_rows($holdings)), "\n";
    echo 'a static property: ', received(preloaded_counts()), "\n";
    echo 'a return: ', received(preloaded_sizes()), "\n";
    echo 'array_merge() of a return: ', received(preloaded_sizes_merged()), "\n";
    echo 'a constant: ', received(preloaded_limits()), "\n";
    echo 'PHP# reads its own: ', $holdings->payloadClass(), ' ', $holdings->firstRowClass(), ' ', Demo\Holdings::countsClass(), "\n";
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
bool(true)
a property: array {"id":"p1"}
count() of a property: 1
a List<Map> property: array [{"a":1}]
a static property: array {"c":1}
a return: array {"b":2,"a":1}
array_merge() of a return: array {"b":2,"a":1,"c":3}
a constant: array {"card":100}
PHP# reads its own: Sharp\Map Sharp\Map Sharp\Map

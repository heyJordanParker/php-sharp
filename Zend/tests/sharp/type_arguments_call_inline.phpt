--TEST--
The optimizer never inlines a generic method whose body is `return 1;`, which reads its type arguments first
--FILE--
<?php
// Opcache's dump after optimization at every level prints each op array. Inlined, the call would be a QM_ASSIGN of 1.
$dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
    . ' -n -d opcache.enable_cli=1 -d opcache.optimization_level=-1 -d opcache.opt_debug_level=0x20000 -l '
    . escapeshellarg(__DIR__ . '/TypeArgumentsCalls.sharp') . ' 2>&1');
preg_match('/^Calls\\\\Repository::called:\n.*?\n\n/ms', $dump, $called);
foreach (explode("\n", $called[0] ?? '') as $line) {
    if (preg_match('/^\d{4} /', $line)) {
        echo $line, "\n";
    }
}
?>
--EXPECT--
0000 INIT_STATIC_METHOD_CALL 0 string("Calls\\Repository") string("one")
0001 T1 = SHARP_TYPE_ARGS string("App.Order")
0002 V0 = DO_UCALL T1
0003 RETURN V0

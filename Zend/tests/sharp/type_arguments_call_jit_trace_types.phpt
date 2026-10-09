--TEST--
The tracing JIT records no operand type for a generic call's type arguments, which are an IS_PTR and no PHP type
--EXTENSIONS--
opcache
--FILE--
<?php
// The trace dump prints each operand type the tracer recorded after a `;`. The type arguments a generic call's op1, and
// the hidden local a ZEND_SHARP_TYPE_ARGS reads, hold are no PHP type, so neither may print one.
$dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
    . ' -n -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=32M'
    . ' -d opcache.jit_hot_func=1 -d opcache.jit_hot_loop=1 -d opcache.jit_hot_return=1 -d opcache.jit_hot_side_exit=1'
    . ' -d opcache.jit_debug=0x40000 -r ' . escapeshellarg('require ' . var_export(__DIR__ . '/type_arguments_call.inc', true) . ';')
    . ' 2>&1');
$traced = [];
foreach (explode("\n", $dump) as $line) {
    if (preg_match('/ = (?:DO_UCALL|DO_FCALL|DO_FCALL_BY_NAME) T\d+| = SHARP_TYPE_ARGS CV\d+/', $line, $operation)) {
        preg_match('/ ; (op1\(.*?\))/', $line, $recorded);
        $traced[preg_replace('/\d+/', '', $operation[0]) . ' ' . ($recorded[1] ?? 'no op1 type')] = true;
    }
}
ksort($traced);
echo implode("\n", array_keys($traced)), "\n";
?>
--EXPECT--
 = DO_FCALL T no op1 type
 = DO_UCALL T no op1 type
 = SHARP_TYPE_ARGS CV no op1 type

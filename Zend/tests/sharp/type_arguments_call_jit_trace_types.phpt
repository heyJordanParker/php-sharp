--TEST--
The tracing JIT records no operand type for an IS_PTR, which is no PHP type: a generic call's type arguments, the hidden local that holds them, and a lambda's capture of it
--EXTENSIONS--
opcache
--FILE--
<?php
// The trace dump prints each operand type the tracer recorded after a `;`. The type arguments a generic call's op1, a
// NEW's op2, a ZEND_SHARP_TYPE_ARGS's op1 and a BIND_LEXICAL's op2 hold are no PHP type, so none may print one.
$run = 'require ' . var_export(__DIR__ . '/type_arguments_call.inc', true) . ';'
    . ' foreach ([1, 2] as $round) {'
    . ' Calls\Repository::orderLater(new App\Order(1))();'
    . ' Calls\Repository::orderHeld(new App\Order(1))();'
    . ' }';
$dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
    . ' -n -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=32M'
    . ' -d opcache.jit_hot_func=1 -d opcache.jit_hot_loop=1 -d opcache.jit_hot_return=1 -d opcache.jit_hot_side_exit=1'
    . ' -d opcache.jit_debug=0x40000 -r ' . escapeshellarg($run)
    . ' 2>&1');
$operations = [
    '/ = (?:DO_UCALL|DO_FCALL|DO_FCALL_BY_NAME) T\d+/' => 'op1',
    '/ = SHARP_TYPE_ARGS CV\d+/' => 'op1',
    '/ = NEW \d+ V\d+ T\d+/' => 'op2',
    '/BIND_LEXICAL T\d+ CV\d+\(\$\)/' => 'op2',
];
$traced = [];
foreach (explode("\n", $dump) as $line) {
    foreach ($operations as $pattern => $operand) {
        if (preg_match($pattern, $line, $operation)) {
            preg_match('/ ; .*?(' . $operand . '\(.*?\))/', $line, $recorded);
            $traced[preg_replace('/\d+/', '', $operation[0]) . ' ' . ($recorded[1] ?? "no $operand type")] = true;
        }
    }
}
ksort($traced);
echo implode("\n", array_keys($traced)), "\n";
?>
--EXPECT--
 = DO_FCALL T no op1 type
 = DO_UCALL T no op1 type
 = NEW  V T no op2 type
 = SHARP_TYPE_ARGS CV no op1 type
BIND_LEXICAL T CV($) no op2 type

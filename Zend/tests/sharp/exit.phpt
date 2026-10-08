--TEST--
PHP# exit ends the process with its status and skips every finally block
--FILE--
<?php

$code = 'require ' . var_export(__DIR__ . '/Exits.sharp', true) . '; Demo\Exits::stop(3); echo "after exit\n";';
exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS') . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $status);

foreach ($output as $line) {
    echo $line, "\n";
}
echo "status: $status\n";
?>
--EXPECT--
status: 3

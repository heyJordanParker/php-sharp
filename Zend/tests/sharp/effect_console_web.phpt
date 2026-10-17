--TEST--
PHP# Console writes a page's output and the host's error log under PHP's built-in web server, which defines no STDOUT or STDERR
--FILE--
<?php
require __DIR__ . '/../../../sapi/cli/tests/php_cli_server.inc';

$classes = var_export(__DIR__ . '/effect_classes.inc', true);
php_cli_server_start(<<<PHP
    require $classes;
    \$console = new Sharp\\Console();
    \$console->write('served ');
    \$console->writeLine('by Console');
    \$console->writeError('to the error log');
    echo 'and the page ends';
    PHP);

echo file_get_contents('http://' . PHP_CLI_SERVER_ADDRESS . '/'), "\n";
?>
--EXPECT--
served by Console
and the page ends

--TEST--
php --ri sharp prints the PHP# version
--FILE--
<?php

echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n --ri sharp');
?>
--EXPECT--
sharp

Version => 0.3.1

Directive => Local Value => Master Value
sharp.compile_command => no value => no value

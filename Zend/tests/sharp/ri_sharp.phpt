--TEST--
php --ri sharp prints the PHP# version
--FILE--
<?php

echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n --ri sharp');
?>
--EXPECTF--
sharp

Version => %d.%d.%d

Directive => Local Value => Master Value
sharp.compile_command => no value => no value
sharp.effect_report => no value => no value

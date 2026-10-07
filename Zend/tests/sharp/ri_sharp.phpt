--TEST--
php --ri sharp prints the Mago commit ext/sharp/sharp_unit.h was generated at
--FILE--
<?php

preg_match('/^#define SHARP_MAGO_COMMIT "([0-9a-f]{40})"$/m', file_get_contents(__DIR__ . '/../../../ext/sharp/sharp_unit.h'), $match);

$info = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n --ri sharp');
echo str_replace($match[1], '<the Mago commit in sharp_unit.h>', $info);
?>
--EXPECT--
sharp

Mago commit => <the Mago commit in sharp_unit.h>

Directive => Local Value => Master Value
sharp.compile_command => no value => no value

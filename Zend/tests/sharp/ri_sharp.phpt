--TEST--
php --ri sharp prints the Mago commit Cargo.lock pins the bridge to
--FILE--
<?php

$lock = file_get_contents(__DIR__ . '/../../../ext/sharp/Cargo.lock');
preg_match('/^name = "mago-sharp-bridge"\nversion = "[^"]*"\nsource = "[^"#]*#([0-9a-f]{40})"$/m', $lock, $match);

$info = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n --ri sharp');
echo str_replace($match[1], '<the bridge commit in Cargo.lock>', $info);
?>
--EXPECT--
sharp

Mago commit => <the bridge commit in Cargo.lock>

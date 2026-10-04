--TEST--
php --ri sharp prints the bridge's Mago commit
--FILE--
<?php

passthru(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n --ri sharp');
?>
--EXPECTF--
sharp

Mago commit => %x

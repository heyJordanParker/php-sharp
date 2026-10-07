--TEST--
A PHP# override without a parent method fails when PHP links the class
--FILE--
<?php

require __DIR__ . '/HeaderOverride.sharp';
?>
--EXPECTF--
Fatal error: Demo\Stray::size() has #[\Override] attribute, but no matching parent method exists in %sHeaderOverride.sharp on line %d

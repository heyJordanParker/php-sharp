--TEST--
phpversion('sharp') gives the release version of sharp/mago/Cargo.toml's root package
--FILE--
<?php
preg_match('/^version = "([0-9]+\.[0-9]+\.[0-9]+)"$/m', file_get_contents(__DIR__ . '/../../../sharp/mago/Cargo.toml'), $released);
var_dump($released !== [] && phpversion('sharp') === $released[1]);
?>
--EXPECT--
bool(true)

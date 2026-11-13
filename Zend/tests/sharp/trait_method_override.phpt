--TEST--
A PHP# class replaces a static and an instance method of a plain PHP trait it uses with override, spec section 22, and loads, since PHP's #[\Override] counts no trait method, and each call runs the replacement
--FILE--
<?php
require __DIR__ . '/harness/Defaulted.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('TraitOverride');

echo App\TraitOverride\Post::shownStatus(), "\n";
echo (new App\TraitOverride\Post())->shownLabel(), "\n";
?>
--EXPECT--
published
published

--TEST--
new with type arguments throws an Error when its class name reaches a class that declares no type parameters
--FILE--
<?php
// PHP# compiled `new Pair<int, string>` against the generic App.Pair, but here the name reaches a plain class.
class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias.sharp';

try {
    Aliased\Maker::pair();
} catch (Error $error) {
    echo $error::class, ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
Error: Class PlainPair declares no type parameters, so new cannot give it <int, string>

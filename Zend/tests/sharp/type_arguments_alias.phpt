--TEST--
new with type arguments throws an Error when its class name reaches a class that declares no type parameters, or another number of them
--FILE--
<?php
// PHP# compiled `new Pair<int, string>` against the generic App.Pair, but here the name reaches a plain class.
class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias.sharp';
// PHP# compiled `new Node<int>` against App.Node, which declares one type parameter, but the name reaches a class that
// declares two.
class_alias(Aliased\Twin::class, 'App\Node');

foreach ([Aliased\Maker::pair(...), Aliased\Maker::node(...)] as $make) {
    try {
        var_dump($make());
    } catch (Error $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
Error: Class PlainPair declares no type parameters, so new cannot give it <int, string>
Error: Class Aliased\Twin declares 2 type parameters, so new cannot give it <int>

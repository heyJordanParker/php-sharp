--TEST--
Method.createDelegate holds a plain PHP method to the extern lines the checker holds a call to: the method's own line, else the line of the class that declares it, never a parent's, so an inherited method takes its declaring class's line, a method whose line says uses any may have every effect, and a method of a subclass no line declares may have every effect
--FILE--
<?php
require __DIR__ . '/harness/Lineage.inc';
require __DIR__ . '/vendor/autoload.php';

use App\Delegates\Lineage;
use Lib\Lineage\Leaf;
use Lib\Lineage\Sub;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        var_export($body());
        echo "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

attempt('inherited from a pure class', fn () => Lineage::inherited()(new Sub()));
attempt('declared Http', fn () => Lineage::declared()(new Sub()));
attempt('uses any', fn () => Lineage::anyEffect());
attempt('no line', fn () => Lineage::undeclared());
?>
--EXPECT--
inherited from a pure class: 1
declared Http: 2
uses any: ValueError: `Lib.Lineage.Sub.later` may have every effect, and `Function<int(Lib.Lineage.Sub) uses Sharp.Net.Http>` allows only `Sharp.Net.Http`.
no line: ValueError: `Lib.Lineage.Leaf.own` may have every effect, and `Function<int(Lib.Lineage.Leaf)>` allows none.

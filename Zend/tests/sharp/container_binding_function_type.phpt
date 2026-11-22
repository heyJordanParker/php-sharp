--TEST--
A lambda a module binding builds, and one a class constant holds, answer `is Function<…>` as a lambda a method builds does, spec sections 21 and 32: a pure lambda fits a type that allows an effect, a wider result and no effects
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('container-binding-function-type', [
    'app/Probe/Module.sharp' => <<<'SHARP'
namespace App.Probe;

module
{
    singleton Source = new Source(() => 7);
}
SHARP,
    'app/Probe/Source.sharp' => <<<'SHARP'
namespace App.Probe;

import Sharp.Time.Clock;

public class Source
{
    public const Function<int() uses Clock> CONSTANT = () => 8;

    public Source(private Function<int() uses Clock> read) { }

    public static Source built() => new Source(() => 7);

    public string heldTypes() => Source.types(this.read);

    public static string constantTypes() => Source.types(Source.CONSTANT);

    public static string types(Any? value) =>
        Source.fits(value is Function<int() uses Clock>) + " " + Source.fits(value is Function<Any?() uses Clock>) + " " + Source.fits(value is Function<int()>);

    private static string fits(bool matched) => matched ? "true" : "false";
}
SHARP,
]);
$container = new Sharp\Container();
echo "a binding's lambda: ", $container->get('App\Probe\Source')->heldTypes(), "\n";
echo "a method's lambda: ", App\Probe\Source::built()->heldTypes(), "\n";
echo "a constant's lambda: ", App\Probe\Source::constantTypes(), "\n";
?>
--EXPECT--
a binding's lambda: true true true
a method's lambda: true true true
a constant's lambda: true true true

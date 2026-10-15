--TEST--
Scope.run keeps each scoped service its body builds until the body returns or throws, returns what the body returns, and leaves singletons and statics to the app
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('scope-run', ['app/Shop/Tally.sharp' => <<<'SHARP'
namespace App.Shop;

public class Tally
{
    private static int count = 0;
    private static int computed = 0;

    public static lazy int first => Tally.compute();

    public static int bump()
    {
        Tally.count = Tally.count + 1;
        return Tally.count;
    }

    public static int started() => Tally.first;

    private static int compute()
    {
        Tally.computed = Tally.computed + 1;
        return Tally.computed * 10;
    }
}
SHARP]);
$container = new Sharp\Container();
$visit = fn () => $container->get('App\Shop\Checkout')->usedVisit();
$mailer = fn () => $container->get('App\Shop\Checkout')->usedMailer();

echo "returns: ", Sharp\Scope::run(fn () => 'done'), "\n";
[$a, $b] = Sharp\Scope::run(fn () => [$visit(), $visit()]);
echo "one scoped service in a scope: ", var_export($a === $b, true), "\n";
$c = Sharp\Scope::run($visit);
echo "the next scope builds its own: ", var_export($a !== $c, true), "\n";
$weak = WeakReference::create(Sharp\Scope::run($visit));
echo "a scope drops its services when it ends: ", var_export($weak->get() === null, true), "\n";
$request = $visit();
echo "the request is the default scope: ", var_export($request === $visit(), true), "\n";
echo "a scope's service is not the request's: ", var_export($c !== $request, true), "\n";

$inner = null;
try {
    Sharp\Scope::run(function () use ($visit, &$inner) {
        $inner = $visit();
        throw new RuntimeException('body threw');
    });
} catch (RuntimeException $e) {
    echo "throws: ", $e->getMessage(), "\n";
}
echo "after a throw the request's comes back: ", var_export($visit() === $request, true), "\n";
echo "the thrown scope's was its own: ", var_export($inner !== $request, true), "\n";

[$outer, $nested, $after] = Sharp\Scope::run(fn () => [$visit(), Sharp\Scope::run($visit), $visit()]);
echo "a nested scope is its own: ", var_export($outer !== $nested, true), "\n";
echo "the outer scope comes back after it: ", var_export($outer === $after, true), "\n";
echo "a singleton is shared across scopes: ", var_export(Sharp\Scope::run($mailer) === Sharp\Scope::run($mailer), true), "\n";

Sharp\Scope::run(fn () => App\Shop\Tally::bump());
echo "a static keeps its value across scopes: ", Sharp\Scope::run(fn () => App\Shop\Tally::bump()), "\n";
echo "a static lazy computes once across scopes: ", Sharp\Scope::run(fn () => App\Shop\Tally::started()), " ",
    Sharp\Scope::run(fn () => App\Shop\Tally::started()), "\n";
echo "Container has no scope: ", var_export(method_exists('Sharp\Container', 'scope'), true), "\n";
?>
--EXPECT--
returns: done
one scoped service in a scope: true
the next scope builds its own: true
a scope drops its services when it ends: true
the request is the default scope: true
a scope's service is not the request's: true
throws: body threw
after a throw the request's comes back: true
the thrown scope's was its own: true
a nested scope is its own: true
the outer scope comes back after it: true
a singleton is shared across scopes: true
a static keeps its value across scopes: 2
a static lazy computes once across scopes: 10 10
Container has no scope: false

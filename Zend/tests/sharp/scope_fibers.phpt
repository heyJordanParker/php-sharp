--TEST--
A scope follows the fiber that runs in it: a fiber started in a scope belongs to it, its own Scope.run is its own, and a scoped service resolved after its scope ended throws
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('scope-fibers');
$container = new Sharp\Container();
$visit = fn () => $container->get('App\Shop\Checkout')->usedVisit();
$request = $visit();

$fiber = new Fiber(fn () => Sharp\Scope::run(function () use ($visit) {
    $mine = $visit();
    Fiber::suspend();
    return [$mine, $visit()];
}));
$fiber->start();
echo "main gets the request's while a fiber is suspended in its scope: ", var_export($visit() === $request, true), "\n";
Sharp\Scope::run(function () use ($fiber, $visit, $request) {
    $before = $visit();
    $fiber->resume();
    echo "main's scope keeps its own after the fiber's scope ends: ", var_export($visit() === $before, true), "\n";
    echo "main's scope is not the request's: ", var_export($before !== $request, true), "\n";
});
[$mine, $again] = $fiber->getReturn();
echo "the fiber's scope keeps one across its suspension: ", var_export($mine === $again, true), "\n";
echo "the fiber's scope is its own: ", var_export($mine !== $request, true), "\n";
echo "main gets the request's after its scope: ", var_export($visit() === $request, true), "\n";

[$outer, $child] = Sharp\Scope::run(function () use ($visit) {
    $outer = $visit();
    $child = new Fiber($visit);
    $child->start();
    return [$outer, $child->getReturn()];
});
echo "a fiber started in a scope belongs to it: ", var_export($outer === $child, true), "\n";

$late = Sharp\Scope::run(function () use ($visit) {
    $late = new Fiber(function () use ($visit) {
        Fiber::suspend();
        return $visit();
    });
    $late->start();
    return $late;
});
try {
    $late->resume();
} catch (Sharp\ContainerError $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}

$nesting = new Fiber(fn () => Sharp\Scope::run(function () use ($visit) {
    $outer = $visit();
    $inner = Sharp\Scope::run(function () use ($visit) {
        $first = $visit();
        Fiber::suspend();
        return [$first, $visit()];
    });
    return [$outer, $inner, $visit()];
}));
$nesting->start();
$between = Sharp\Scope::run($visit);
$nesting->resume();
[$fiberOuter, [$fiberInner, $fiberInnerAgain], $fiberBack] = $nesting->getReturn();
echo "a nested scope in a fiber keeps one across its suspension: ", var_export($fiberInner === $fiberInnerAgain, true), "\n";
echo "a nested scope in a fiber is its own: ", var_export($fiberOuter !== $fiberInner, true), "\n";
echo "the fiber's outer scope comes back after it: ", var_export($fiberOuter === $fiberBack, true), "\n";
echo "main's scope while the fiber is suspended is neither: ",
    var_export($between !== $fiberOuter && $between !== $fiberInner, true), "\n";
?>
--EXPECT--
main gets the request's while a fiber is suspended in its scope: true
main's scope keeps its own after the fiber's scope ends: true
main's scope is not the request's: true
the fiber's scope keeps one across its suspension: true
the fiber's scope is its own: true
main gets the request's after its scope: true
a fiber started in a scope belongs to it: true
Sharp\ContainerError: The scope this code runs in has ended, so the container can't build the scoped App\Shop\Visit
a nested scope in a fiber keeps one across its suspension: true
a nested scope in a fiber is its own: true
the fiber's outer scope comes back after it: true
main's scope while the fiber is suspended is neither: true

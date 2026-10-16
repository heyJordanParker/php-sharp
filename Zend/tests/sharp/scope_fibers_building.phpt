--TEST--
Each fiber builds its own services: a fiber suspended in the middle of a build leaves every other fiber free to build the same service, of any lifetime, and two that build one singleton at once each get the one kept first
--FILE--
<?php
require __DIR__ . '/Container.inc';

// Each class's constructor suspends the fiber that builds it, as a constructor that waits on I/O does.
$waits = fn (string $class) => "namespace App.Shop.Waits;\n\nimport Fiber;\n\npublic class $class\n{\n"
    . "    public $class()\n    {\n        if (Fiber.getCurrent() != null) {\n            Fiber.suspend();\n        }\n    }\n}\n";
container_project('scope-fibers-building', [
    'app/Shop/Waits/Module.sharp' => "namespace App.Shop.Waits;\n\nmodule\n{\n"
        . "    scoped Visit = Visit;\n    transient Brief = Brief;\n    singleton Once = Once;\n    scoped Stuck = Stuck;\n}\n",
    'app/Shop/Waits/Visit.sharp' => $waits('Visit'),
    'app/Shop/Waits/Brief.sharp' => $waits('Brief'),
    'app/Shop/Waits/Once.sharp' => $waits('Once'),
    'app/Shop/Waits/Stuck.sharp' => $waits('Stuck'),
]);
$container = new Sharp\Container();
$finish = function (Fiber $fiber): array {
    while (!$fiber->isTerminated()) {
        $fiber->resume();
    }
    return $fiber->getReturn();
};

// Two requests, each a fiber that runs in a scope of its own, as an event loop runs them. Both suspend in the
// middle of building the service, and the second finishes first.
foreach (['scoped' => 'Visit', 'transient' => 'Brief', 'singleton' => 'Once'] as $lifetime => $class) {
    $request = fn () => Sharp\Scope::run(function () use ($container, $class) {
        $built = $container->get("App\\Shop\\Waits\\$class");
        return [$built, $container->get("App\\Shop\\Waits\\$class")];
    });
    $first = new Fiber($request);
    $second = new Fiber($request);
    $first->start();
    try {
        $second->start();
        [$secondBuilt, $secondAgain] = $finish($second);
        [$firstBuilt, $firstAgain] = $finish($first);
    } catch (Throwable $e) {
        echo "$lifetime: ", get_class($e), ": ", $e->getMessage(), "\n";
        continue;
    }
    echo "$lifetime: both requests built it\n";
    if ($lifetime === 'scoped') {
        echo "  each request keeps its own: ",
            var_export($firstBuilt === $firstAgain && $secondBuilt === $secondAgain && $firstBuilt !== $secondBuilt, true), "\n";
    } elseif ($lifetime === 'transient') {
        echo "  each get builds a new one: ", var_export($firstBuilt !== $firstAgain && $secondBuilt !== $secondAgain, true), "\n";
    } else {
        echo "  both get the one kept first: ",
            var_export($firstBuilt === $secondBuilt && $firstAgain === $secondBuilt && $secondAgain === $secondBuilt, true), "\n";
    }
}

// A fiber suspended in the middle of a build that is never resumed ends with the request, and holds no other code
// back meanwhile.
$stuck = new Fiber(fn () => $container->get('App\Shop\Waits\Stuck'));
$stuck->start();
echo "main builds what a suspended fiber is building: ", get_class($container->get('App\Shop\Waits\Stuck')), "\n";
?>
--EXPECT--
scoped: both requests built it
  each request keeps its own: true
transient: both requests built it
  each get builds a new one: true
singleton: both requests built it
  both get the one kept first: true
main builds what a suspended fiber is building: App\Shop\Waits\Stuck

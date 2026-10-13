--TEST--
Sharp\Container refuses a service no module binds, a class that needs itself, and a parameter with no class type, each with an Error that names it
--FILE--
<?php
require __DIR__ . '/Container.inc';

class Egg
{
    public function __construct(Chicken $chicken) {}
}

class Chicken
{
    public function __construct(Egg $egg) {}
}

class Loose
{
    public function __construct($anything) {}
}

container_project('container-refusals');
$container = new Sharp\Container();

foreach (['App\Mail\Mailer', 'App\Missing', 'Egg', 'Loose'] as $id) {
    try {
        $container->get($id);
    } catch (Error $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}
var_dump($container->has('App\Missing'));
?>
--EXPECT--
Error: No module binds App\Mail\Mailer
Error: No module binds App\Missing
Error: Egg needs itself, so the container can't build it
Error: The container can't fill parameter $anything of Loose: give it a class type
bool(false)

--TEST--
Sharp\Container builds only the classes a module binds, and refuses a service no module binds, a concrete class no module binds, a class that needs itself, and a parameter with no class type, each with an Error that names it
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

// The checker refuses these bindings, so a test's module, as the compiler writes one, binds them.
Sharp\Internal\testModule(['RefusalsTest'], [
    ['', 'egg', null, 'transient', 'Egg'],
    ['', 'chicken', null, 'transient', 'Chicken'],
    ['', 'loose', null, 'transient', 'Loose'],
]);
Sharp\Internal\startTest('RefusalsTest');

foreach (['App\Mail\Mailer', 'App\Missing', 'App\Mail\SmtpMailer', 'Egg', 'Loose'] as $id) {
    try {
        $container->get($id);
    } catch (Error $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}
var_dump($container->has('App\Missing'), $container->has('App\Mail\SmtpMailer'), $container->has('Egg'));
?>
--EXPECT--
Error: No module binds App\Mail\Mailer
Error: No module binds App\Missing
Error: No module binds App\Mail\SmtpMailer
Error: Egg needs itself, so the container can't build it
Error: The container can't fill parameter $anything of Loose: give it a class type
bool(false)
bool(false)
bool(true)

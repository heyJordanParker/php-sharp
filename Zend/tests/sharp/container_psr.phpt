--TEST--
Sharp\Psr\Container is PHP#'s container as a PSR-11 container: it gives the service a module binds, says whether a module binds an id, throws PSR-11's not-found error for an id no module binds, wraps each failure of the container in PSR-11's container error, and throws a constructor's exception unchanged
--FILE--
<?php
namespace Psr\Container {
    // psr/container 2.0's interfaces, which Composer loads in a project.
    interface ContainerExceptionInterface extends \Throwable
    {
    }

    interface NotFoundExceptionInterface extends ContainerExceptionInterface
    {
    }

    interface ContainerInterface
    {
        public function get(string $id);

        public function has(string $id): bool;
    }
}

namespace {
    require __DIR__ . '/Container.inc';
    require dirname(__DIR__, 3) . '/sharp/composer/src/Psr/NotFoundException.php';
    require dirname(__DIR__, 3) . '/sharp/composer/src/Psr/ContainerException.php';
    require dirname(__DIR__, 3) . '/sharp/composer/src/Psr/Container.php';

    class Needy
    {
        public function __construct(Missing $missing) {}
    }

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

    class Ledger
    {
        public function __construct()
        {
            throw new RuntimeException('the database is down');
        }
    }

    container_project('container-psr');
    $container = new Sharp\Psr\Container();

    echo "a bound service: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
    var_dump($container->has('App\Shop\Checkout'), $container->has('App\Mail\SmtpMailer'));
    try {
        $container->get('App\Mail\SmtpMailer');
    } catch (Psr\Container\NotFoundExceptionInterface $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }

    // A test's module, as the compiler writes one, binds these classes. The checker refuses the first three bindings.
    Sharp\Internal\testModule(['PsrTest'], [
        ['', 'needy', null, 'transient', 'Needy'],
        ['', 'egg', null, 'transient', 'Egg'],
        ['', 'chicken', null, 'transient', 'Chicken'],
        ['', 'loose', null, 'transient', 'Loose'],
        ['', 'ledger', null, 'transient', 'Ledger'],
    ]);
    Sharp\Internal\startTest('PsrTest');
    foreach (['Needy', 'Egg', 'Loose', 'Ledger'] as $id) {
        try {
            $container->get($id);
        } catch (Throwable $e) {
            echo $id, ": ", get_class($e), " (ContainerExceptionInterface: ", var_export($e instanceof Psr\Container\ContainerExceptionInterface, true),
                ", NotFoundExceptionInterface: ", var_export($e instanceof Psr\Container\NotFoundExceptionInterface, true), "): ", $e->getMessage(),
                $e->getPrevious() ? ", from " . get_class($e->getPrevious()) : "", "\n";
        }
    }
    Sharp\Internal\endTest();
    echo "the request goes on\n";
}
?>
--EXPECT--
a bound service: smtp
bool(true)
bool(false)
Sharp\Psr\NotFoundException: No module binds App\Mail\SmtpMailer
Needy: Sharp\Psr\ContainerException (ContainerExceptionInterface: true, NotFoundExceptionInterface: false): No module binds Missing for Needy, from Sharp\ContainerError
Egg: Sharp\Psr\ContainerException (ContainerExceptionInterface: true, NotFoundExceptionInterface: false): Egg needs itself, so the container can't build it, from Sharp\ContainerError
Loose: Sharp\Psr\ContainerException (ContainerExceptionInterface: true, NotFoundExceptionInterface: false): The container can't fill parameter $anything of Loose: give it a class type, from Sharp\ContainerError
Ledger: RuntimeException (ContainerExceptionInterface: false, NotFoundExceptionInterface: false): the database is down
the request goes on

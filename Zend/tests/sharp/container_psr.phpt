--TEST--
Sharp\Psr\Container is PHP#'s container as a PSR-11 container: it gives the service a module binds, says whether a module binds an id, and throws PSR-11's not-found error for an id no module binds
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
    require dirname(__DIR__, 3) . '/sharp/composer/src/Psr/Container.php';

    container_project('container-psr');
    $container = new Sharp\Psr\Container();

    echo "a bound service: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
    var_dump($container->has('App\Shop\Checkout'), $container->has('App\Mail\SmtpMailer'));
    try {
        $container->get('App\Mail\SmtpMailer');
    } catch (Psr\Container\NotFoundExceptionInterface $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
a bound service: smtp
bool(true)
bool(false)
Sharp\Psr\NotFoundException: No module binds App\Mail\SmtpMailer

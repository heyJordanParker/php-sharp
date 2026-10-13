--TEST--
Sharp\PhpUnit\Extension starts a test of its test method's class before PHPUnit sets the test up, so the container uses that class's test module, and ends it after the test finishes
--FILE--
<?php
// The PHPUnit 10 to 13 names the extension uses, which PHPUnit declares in a project.
namespace PHPUnit\TextUI\Configuration {
    final class Configuration
    {
    }
}

namespace PHPUnit\Runner\Extension {
    final class ParameterCollection
    {
    }

    final class Facade
    {
        public array $subscribers = [];

        public function registerSubscribers(object ...$subscribers): void
        {
            array_push($this->subscribers, ...$subscribers);
        }
    }

    interface Extension
    {
        public function bootstrap(\PHPUnit\TextUI\Configuration\Configuration $configuration, Facade $facade, ParameterCollection $parameters): void;
    }
}

namespace PHPUnit\Event\Code {
    abstract class Test
    {
    }

    final class TestMethod extends Test
    {
        public function __construct(private string $className)
        {
        }

        public function className(): string
        {
            return $this->className;
        }
    }
}

namespace PHPUnit\Event\Test {
    final class PreparationStarted
    {
        public function __construct(private \PHPUnit\Event\Code\Test $test)
        {
        }

        public function test(): \PHPUnit\Event\Code\Test
        {
            return $this->test;
        }
    }

    final class Finished
    {
    }

    interface PreparationStartedSubscriber
    {
        public function notify(PreparationStarted $event): void;
    }

    interface FinishedSubscriber
    {
        public function notify(Finished $event): void;
    }
}

namespace {
    use PHPUnit\Event\Test\Finished;
    use PHPUnit\Event\Test\FinishedSubscriber;
    use PHPUnit\Event\Test\PreparationStarted;
    use PHPUnit\Event\Test\PreparationStartedSubscriber;

    require __DIR__ . '/Container.inc';
    require dirname(__DIR__, 3) . '/sharp/composer/src/PhpUnit/Extension.php';

    $root = container_project('container-phpunit', [
        'tests/Shop/CheckoutTest.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.LogMailer;

module : App.Shop
{
    singleton Mailer = LogMailer;
}

public class CheckoutTest
{
}
SHARP,
    ]);
    require "$root/tests/Shop/CheckoutTest.sharp";
    $container = new Sharp\Container();

    $facade = new PHPUnit\Runner\Extension\Facade();
    (new Sharp\PhpUnit\Extension())->bootstrap(new PHPUnit\TextUI\Configuration\Configuration(), $facade, new PHPUnit\Runner\Extension\ParameterCollection());
    $notify = static function (object $event) use ($facade): void {
        foreach ($facade->subscribers as $subscriber) {
            if ($event instanceof PreparationStarted ? $subscriber instanceof PreparationStartedSubscriber : $subscriber instanceof FinishedSubscriber) {
                $subscriber->notify($event);
            }
        }
    };

    $notify(new PreparationStarted(new PHPUnit\Event\Code\TestMethod('App\Shop\CheckoutTest')));
    echo "while the test runs: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
    $notify(new Finished());
    echo "after it finishes: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
}
?>
--EXPECT--
while the test runs: log
after it finishes: smtp

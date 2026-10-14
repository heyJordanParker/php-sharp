--TEST--
Sharp\Container uses a test file's module while a test of a class that file declares runs, before every other module and even over a base module's binding for one class, builds every service again for each test, and forgets the module when the test ends
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('container-test-module', [
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

$greeting = $container->get('App\Shop\Checkout')->usedGreeting();
echo "before a test: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
Sharp\Internal\startTest('App\Shop\CheckoutTest');
echo "a test of a class its file declares: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
echo "over a binding for one class: ", $container->get('App\Shop\SendReceipt')->usedMailer()->name(), "\n";
echo "a singleton built before the test: ", var_export($container->get('App\Shop\Checkout')->usedGreeting() === $greeting, true), "\n";
Sharp\Internal\startTest('App\Shop\OtherTest');
echo "a test of another class: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
Sharp\Internal\startTest('app\shop\checkouttest');
Sharp\Internal\endTest();
echo "after the test: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
?>
--EXPECT--
before a test: smtp
a test of a class its file declares: log
over a binding for one class: log
a singleton built before the test: false
a test of another class: smtp
after the test: smtp

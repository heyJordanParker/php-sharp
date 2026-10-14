--TEST--
A test file's class starts each of its tests with the file's module before every other Before method, and ends it after every other After method, through the methods PHPUnit's Before and After attributes mark, which PHPUnit runs in its own process and in a child one
--FILE--
<?php
require __DIR__ . '/Container.inc';

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
$mailer = fn () => (new Sharp\Container())->get('App\Shop\Checkout')->usedMailer()->name();

// PHPUnit runs the Before methods by priority, highest first, and the After methods by priority too, so the highest
// and the lowest run first and last.
$hooks = [];
foreach ((new ReflectionClass('App\Shop\CheckoutTest'))->getMethods() as $method) {
    foreach ($method->getAttributes() as $attribute) {
        echo $method->getName(), ": ", $attribute->getName(), "(", $attribute->getArguments()[0], ")\n";
        $hooks[$attribute->getName()][] = $method->getName();
    }
}
$test = new App\Shop\CheckoutTest();
echo "before the test: ", $mailer(), "\n";
foreach ($hooks['PHPUnit\Framework\Attributes\Before'] as $before) {
    $test->$before();
}
echo "while the test runs: ", $mailer(), "\n";
foreach ($hooks['PHPUnit\Framework\Attributes\After'] as $after) {
    $test->$after();
}
echo "after it ends: ", $mailer(), "\n";

// PHPUnit's child process loads the test file again and runs the same methods around the test.
$child = <<<'PHP'
require $argv[1] . '/Container.inc';
container_load($argv[2]);
require $argv[2] . '/tests/Shop/CheckoutTest.sharp';
$test = new App\Shop\CheckoutTest();
$test->__sharpStartTest();
echo "in a child process: ", (new Sharp\Container())->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
$test->__sharpEndTest();
PHP;
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($child) . ' ' . escapeshellarg(__DIR__) . ' ' . escapeshellarg($root));
?>
--EXPECT--
__sharpStartTest: PHPUnit\Framework\Attributes\Before(9223372036854775807)
__sharpEndTest: PHPUnit\Framework\Attributes\After(-9223372036854775808)
before the test: smtp
while the test runs: log
after it ends: smtp
in a child process: log

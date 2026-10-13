--TEST--
Sharp\Container runs a test with its test module over the base modules and no environment module, the set the checker checks, so with SHARP_ENV set a test gets its test module's service and the base module's service in place of the environment module's
--ENV--
SHARP_ENV=Production
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('container-test-environment', [
    'tests/Shop/CheckoutTest.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Pricing.PriceRule;
import App.Pricing.TaxRule;

module : App.Shop
{
    transient List<PriceRule> = [TaxRule];
}

public class CheckoutTest
{
}
SHARP,
]);
require "$root/tests/Shop/CheckoutTest.sharp";
$container = new Sharp\Container();
$rules = static fn (): string => implode(' ', array_map(fn ($rule) => $rule->name(), $container->get('App\Shop\Checkout')->usedRules()));

echo "the environment module, outside a test: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
Sharp\Internal\startTest('App\Shop\CheckoutTest');
echo "the test module's service: ", $rules(), "\n";
echo "a service only the environment module rebinds: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
Sharp\Internal\endTest();
echo "the environment module, after the test: ", $container->get('App\Shop\Checkout')->usedMailer()->name(), "\n";
?>
--EXPECT--
the environment module, outside a test: log
the test module's service: tax
a service only the environment module rebinds: smtp
the environment module, after the test: log

--TEST--
Sharp\Container reads the environment module SHARP_ENV names before its base module, so the environment module's bindings win, even over the base module's binding for one class
--ENV--
SHARP_ENV=Production
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('container-environment');
$container = new Sharp\Container();

$checkout = $container->get('App\Shop\Checkout');
echo "a binding the environment module replaces: ", $checkout->usedMailer()->name(), "\n";
echo "the base module's binding for one class: ", $container->get('App\Shop\SendReceipt')->usedMailer()->name(), "\n";
echo "a binding the environment module keeps: ", implode(' ', array_map(fn ($rule) => $rule->name(), $checkout->usedRules())), "\n";
?>
--EXPECT--
a binding the environment module replaces: log
the base module's binding for one class: log
a binding the environment module keeps: tax discount

--TEST--
Sharp\Container keeps a singleton for as long as the app runs, a scoped service for its scope, and builds a transient service each time
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('container-lifetimes');
$container = new Sharp\Container();

$first = $container->get('App\Shop\Checkout');
$second = $container->get('App\Shop\Checkout');
$panel = $container->get('App\Shop\Admin\Panel');
echo "singleton: ", var_export($first->usedMailer() === $second->usedMailer(), true), "\n";
echo "singleton for another namespace: ", var_export($first->usedGreeting() === $panel->usedGreeting(), true), "\n";
echo "scoped: ", var_export($first->usedVisit() === $second->usedVisit(), true), "\n";
echo "transient: ", var_export($first->usedStamp() === $second->usedStamp(), true), "\n";
?>
--EXPECT--
singleton: true
singleton for another namespace: true
scoped: true
transient: false

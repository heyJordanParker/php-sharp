--TEST--
Sharp\Container keeps a singleton for the request, a scoped service for its scope, which is the request until Container::scope starts another, and builds a transient service each time
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('container-lifetimes');
$container = new Sharp\Container();

$first = $container->get('App\Shop\Checkout');
$second = $container->get('App\Shop\Checkout');
$scoped = Sharp\Container::scope(fn () => $container->get('App\Shop\Checkout'));
$panel = $container->get('App\Shop\Admin\Panel');
echo "singleton: ", var_export($first->usedMailer() === $second->usedMailer(), true), "\n";
echo "singleton in another scope: ", var_export($first->usedMailer() === $scoped->usedMailer(), true), "\n";
echo "singleton for another namespace: ", var_export($first->usedGreeting() === $panel->usedGreeting(), true), "\n";
echo "scoped: ", var_export($first->usedVisit() === $second->usedVisit(), true), "\n";
echo "scoped in another scope: ", var_export($first->usedVisit() === $scoped->usedVisit(), true), "\n";
echo "transient: ", var_export($first->usedStamp() === $second->usedStamp(), true), "\n";
echo "scope returns: ", Sharp\Container::scope(fn () => 'done'), "\n";
?>
--EXPECT--
singleton: true
singleton in another scope: true
singleton for another namespace: true
scoped: true
scoped in another scope: false
transient: false
scope returns: done

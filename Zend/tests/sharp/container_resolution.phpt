--TEST--
Sharp\Container gives each constructor parameter the service the checker resolved: from the nearest namespace's module, a binding for the class before one for any class, a list of classes, or a binding's expression over the services it names
--ENV--
SHARP_GREETING=hello
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('container-resolution');
$container = new Sharp\Container();

$checkout = $container->get('App\Shop\Checkout');
echo "a binding for any class: ", $checkout->usedMailer()->name(), "\n";
echo "a binding for the class: ", $container->get('App\Shop\SendReceipt')->usedMailer()->name(), "\n";
echo "a list of classes: ", implode(' ', array_map(fn ($rule) => $rule->name(), $checkout->usedRules())), "\n";
echo "an expression over its parameters: ", $checkout->usedGreeting()->usedText(), "\n";
$panel = $container->get('\App\Shop\Admin\Panel');
echo "the nested namespace's module: ", $panel->usedMailer()->name(), "\n";
echo "the parent namespace's module: ", $panel->usedGreeting()->usedText(), "\n";
var_dump($container->has('App\Shop\Checkout'), $container->has('App\Mail\Mailer'));
?>
--EXPECT--
a binding for any class: smtp
a binding for the class: queue
a list of classes: tax discount
an expression over its parameters: hello
the nested namespace's module: queue
the parent namespace's module: hello
bool(true)
bool(false)

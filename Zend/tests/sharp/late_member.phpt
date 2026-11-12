--TEST--
A late member stays unset after the constructor: ReflectionProperty::isInitialized() gives false until its first write, and a read before that write throws an Error that names it as PHP# writes it, from PHP# code and from plain PHP
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Late');

$order = new Late\Order();
$number = new ReflectionProperty(Late\Order::class, 'number');
var_dump($number->isInitialized($order));
$number->setValue($order, 'A-12');
var_dump($number->isInitialized($order));
var_dump($order->title());
var_dump($order->attempts);

$id = new ReflectionProperty(Late\Order::class, 'id');
$id->setValue($order, 7);
var_dump($order->id);

$note = new ReflectionProperty(Late\Order::class, 'note');
var_dump($note->isInitialized($order));
$order->note = null;
var_dump($note->isInitialized($order), $order->note);

foreach ([
    static fn () => (new Late\Order())->title(),
    static fn () => (new Late\Order())->number,
    static fn () => (new Late\Order())->count(),
] as $read) {
    try {
        $read();
    } catch (Error $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}

$filled = new Late\Order();
$filled->fill();
var_dump($filled->count());
?>
--EXPECT--
bool(false)
bool(true)
string(10) "Order A-12"
int(0)
int(7)
bool(false)
bool(true)
NULL
Error: Late.Order.number is read before its first write.
Error: Late.Order.number is read before its first write.
Error: Late.Order.lines is read before its first write.
int(1)

--TEST--
A plain PHP write into a PHP# property already holds the declared type, from a file without strict_types too: an assignment, ReflectionProperty::setValue() and a write through a reference each throw the TypeError that names the property and its type where PHP's weak mode would convert, and store a value of the type, an int a float takes included
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Writes');

$order = new Writes\Order();
$number = new ReflectionProperty(Writes\Order::class, 'number');

foreach ([
    'reflection' => static fn () => $number->setValue($order, 5),
    'assignment' => static function () use ($order) { $order->number = 5; },
    'reference' => static function () use ($order) { $held = &$order->number; $held = 5; },
    'int' => static function () use ($order) { $order->lines = '7'; },
    'bool' => static function () use ($order) { $order->number = true; },
] as $write => $run) {
    try {
        $run();
        echo $write, ': stored ', var_export($order->number, true), "\n";
    } catch (TypeError $e) {
        echo $write, ': ', $e->getMessage(), "\n";
    }
}

$number->setValue($order, 'A-12');
var_dump($order->number);
$order->number = 'A-13';
var_dump($order->number);
$held = &$order->number;
$held = 'A-14';
var_dump($order->number);
$order->total = 5;
var_dump($order->total);
?>
--EXPECT--
reflection: Cannot assign int to property Writes\Order::$number of type string
assignment: Cannot assign int to property Writes\Order::$number of type string
reference: Cannot assign int to reference held by property Writes\Order::$number of type string
int: Cannot assign string to property Writes\Order::$lines of type ?int
bool: Cannot assign true to property Writes\Order::$number of type string
string(4) "A-12"
string(4) "A-13"
string(4) "A-14"
float(5)

--TEST--
A machine's case holds its moves, a case with no fields is one object, a move called on a case it doesn't move from throws a StateError, Json.encode and Json.decode write and read a case as a [Tag] family's variant named by its value, and Json.decode evaluates each invariant of the case it reads
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Machines\Order;
use Machines\OrderStatus;

$order = new Order(42);
echo 'an entry point gives the one Draft: ', var_export($order->status === OrderStatus::open(), true), "\n";
$order->pay(500);
echo 'pay moves to: ', get_class($order->status), ' ', $order->status->cents, "\n";
try {
    $order->pay(5);
} catch (Sharp\StateError $error) {
    echo 'pay on Paid: ', get_class($error), ': ', $error->getMessage(), "\n";
}
$order->ship();
try {
    $order->ship();
} catch (Sharp\StateError $error) {
    echo 'ship on Shipped: ', $error->getMessage(), "\n";
}
try {
    OrderStatus::open()->cancel()->pay(cents: 5);
} catch (Sharp\StateError $error) {
    echo 'pay by name on Cancelled: ', $error->getMessage(), "\n";
}

echo 'Json.encode of Paid: ', Order::encode(OrderStatus::open()->pay(7)), "\n";
echo 'Json.encode of Shipped: ', Order::encode($order->status), "\n";
echo 'Shipped reads back as the one Shipped: ', var_export(Order::decode(Order::encode($order->status)) === $order->status, true), "\n";
$paid = Order::decode('{"status":"paid","cents":7}');
echo 'Paid reads back: ', get_class($paid), ' ', $paid->cents, "\n";
foreach (['{"status":"refunded"}', '{"status":"paid","cents":"seven"}', '{"status":"paid","cents":0}'] as $json) {
    try {
        Order::decode($json);
    } catch (Throwable $error) {
        echo $json, ': ', get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
an entry point gives the one Draft: true
pay moves to: Machines\OrderStatus\Paid 500
pay on Paid: Sharp\StateError: OrderStatus is Paid, and pay moves from Draft.
ship on Shipped: OrderStatus is Shipped, and ship moves from Paid.
pay by name on Cancelled: OrderStatus is Cancelled, and pay moves from Draft.
Json.encode of Paid: {"status":"paid","cents":7}
Json.encode of Shipped: {"status":"shipped"}
Shipped reads back as the one Shipped: true
Paid reads back: Machines\OrderStatus\Paid 7
{"status":"refunded"}: Sharp\InvalidFields: status: expected cancelled, draft, paid or shipped, got string 'refunded'
{"status":"paid","cents":"seven"}: Sharp\InvalidFields: cents: expected int, got string 'seven'
{"status":"paid","cents":0}: ValueError: OrderStatus.Paid breaks paidIsPositive.

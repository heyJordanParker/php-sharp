--TEST--
A machine's case holds its moves and no other, a case with no fields is one object, a holder moves its machine only from a case it tested, Json.encode and Json.parse<T> write and read a case as a [Tag] family's variant named by its value, and Json.decode evaluates each invariant of the case it reads
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Machines\Order;
use Machines\OrderStatus;

$order = new Order(42);
echo 'an entry point gives the one Draft: ', var_export($order->status === OrderStatus::open(), true), "\n";
echo 'pay from Draft: ', var_export($order->pay(500), true), ', moves to: ', get_class($order->status), ' ', $order->status->cents, "\n";
echo 'pay from Paid: ', var_export($order->pay(5), true), "\n";
echo 'ship from Paid: ', var_export($order->ship(), true), "\n";
echo 'ship from Shipped: ', var_export($order->ship(), true), "\n";
echo 'Draft declares pay: ', var_export(method_exists(OrderStatus::open(), 'pay'), true), "\n";
echo 'Cancelled declares pay: ', var_export(method_exists(OrderStatus::open()->cancel(), 'pay'), true), "\n";

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
pay from Draft: true, moves to: Machines\OrderStatus\Paid 500
pay from Paid: false
ship from Paid: true
ship from Shipped: false
Draft declares pay: true
Cancelled declares pay: false
Json.encode of Paid: {"status":"paid","cents":7}
Json.encode of Shipped: {"status":"shipped"}
Shipped reads back as the one Shipped: true
Paid reads back: Machines\OrderStatus\Paid 7
{"status":"refunded"}: LogicException: status: expected cancelled, draft, paid or shipped, got string 'refunded'
{"status":"paid","cents":"seven"}: LogicException: cents: expected int, got string 'seven'
{"status":"paid","cents":0}: ValueError: OrderStatus.Paid breaks paidIsPositive.

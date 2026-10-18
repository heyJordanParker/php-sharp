<?php

use NullSafeWrites\Item;
use NullSafeWrites\Order;
use NullSafeWrites\Orders;
use NullSafeWrites\Writes;

/** Each member a write through `?.` reaches, or null for no order. */
function null_safe_state(?Order $order): ?array
{
    return $order === null ? null : [
        'note' => $order->note,
        'gift' => $order->gift,
        'total' => $order->total,
        'ratio' => $order->ratio,
        'bits' => $order->bits,
        'items' => count($order->items),
        'counts' => $order->counts,
        'tags' => $order->tags,
        'stock' => $order->stock,
        'customer' => $order->customer->name,
        'label' => $order->label,
    ];
}

/** Each row runs PHP# writes through `?.` on no order and on an order, and gives what they did. */
function null_safe_rows(): array
{
    $each = static function (callable $run): array {
        $results = [];
        foreach ([null, new Order()] as $order) {
            $results[] = $run($order);
        }

        return $results;
    };
    $writes = static fn (?Order $order): Writes => new Writes(new Orders($order));

    return [
        'the block of spec section 14.4' => $each(static function (?Order $order): array {
            $orders = new Orders($order);
            $writes = new Writes($orders);
            $writes->block(1, new Item('sku-1'));

            return ['finds' => $orders->finds, 'fees' => $writes->fees, 'order' => null_safe_state($order)];
        }),
        'every compound assignment' => $each(static function (?Order $order) use ($writes): ?array {
            $writes($order)->compound($order);

            return null_safe_state($order);
        }),
        '++ and --' => $each(static function (?Order $order) use ($writes): ?array {
            $writes($order)->steps($order);

            return null_safe_state($order);
        }),
        'index writes' => $each(static function (?Order $order) use ($writes): ?array {
            $writes($order)->indexes($order);

            return null_safe_state($order);
        }),
        'a receiver with a side effect runs once' => $each(static function (?Order $order): array {
            $orders = new Orders($order);
            $writes = new Writes($orders);
            $writes->once(1);

            return ['finds' => $orders->finds, 'fees' => $writes->fees, 'order' => null_safe_state($order)];
        }),
        'a local receiver is written as it was tested' => $each(static function (?Order $order) use ($writes): ?array {
            $writes($order)->cleared($order);
            $writes($order)->inline($order);

            return null_safe_state($order);
        }),
        'a write past ?. through an index' => $each(static function (?Order $order) use ($writes): ?array {
            if ($order !== null) {
                $order->items[] = new Item('sku-1');
            }
            $writes($order)->chained($order);

            return $order === null ? null : ['sku' => $order->items[0]->sku];
        }),
        'a set body runs only when the write happens' => $each(static function (?Order $order) use ($writes): array {
            $before = Order::$labelSets;
            $writes($order)->labels($order);

            return ['sets' => Order::$labelSets - $before, 'order' => null_safe_state($order)];
        }),
        'the value of a write' => $each(static function (?Order $order) use ($writes): array {
            $run = $writes($order);

            return [$run->written($order), $run->stepped($order)];
        }),
    ];
}

for ($round = 0; $round < 100; $round++) {
    $rows = null_safe_rows();
}

foreach ($rows as $label => $results) {
    echo $label, ': ', json_encode($results), "\n";
}

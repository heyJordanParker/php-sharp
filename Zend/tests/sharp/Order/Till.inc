<?php declare(strict_types=1); namespace Demo;

class Till
{
    final public function ring(): int
    {
        $order = new \Demo\Order(1, 2, "A1");
        ((object) $order)->add(3);
        return $order->total + $order->id;
    }
}

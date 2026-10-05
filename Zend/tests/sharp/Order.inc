<?php declare(strict_types=1); namespace Demo;

class Order
{
    private int $count = 0;
    protected float $rate = 1.5 * 2;
    public private(set) int $total = 0;
    public readonly string $code;

    public function __construct(public readonly int $id, private int $base, string $code)
    {
        $this->code = $code;
        $this->count = $base;
    }

    public function add(int $amount): int
    {
        $this->count += $amount;
        $this->total = $this->count * $this->base;
        return $this->total;
    }
}

class Till
{
    public function ring(): int
    {
        $order = new \Demo\Order(1, 2, "A1");
        $order->add(3);
        return $order->total + $order->id;
    }
}

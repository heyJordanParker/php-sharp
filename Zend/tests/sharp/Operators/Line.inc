<?php declare(strict_types=1); namespace Billing;

class Line
{
    public readonly string $sku;
    public readonly \Billing\Money $price;

    public function __construct(string $sku, \Billing\Money $price)
    {
        $this->sku = $sku;
        $this->price = $price;
    }
}

<?php declare(strict_types=1); namespace Billing;

class Invoice
{
    public int $total;
    public \Billing\Money $balance;

    public function __construct(int $total, \Billing\Money $balance)
    {
        $this->total = $total;
        $this->balance = $balance;
    }
}

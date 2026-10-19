<?php declare(strict_types=1); namespace Billing;

class InvoiceQueue
{
    public private(set) int $calls = 0;
    private \Billing\Invoice $invoice;

    public function __construct(\Billing\Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    final public function next(): \Billing\Invoice
    {
        $this->calls += 1;
        return $this->invoice;
    }

    final public function halve(): int
    {
        ${'receiver#1'}->total = \intdiv((${'receiver#1'} = $this->next())->total, 2);
        return $this->invoice->total;
    }

    final public function halveBalance(): \Billing\Money
    {
        ${'receiver#1'}->balance = \Billing\Money::op_Division((${'receiver#1'} = $this->next())->balance, 2);
        return $this->invoice->balance;
    }

    final public function halveTwice(): int
    {
        ${'receiver#1'}->total = \intdiv((${'receiver#1'} = $this->next())->total, ${'receiver#2'}->total = \intdiv((${'receiver#2'} = $this->next())->total, 2));
        return $this->invoice->total;
    }
}

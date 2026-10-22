<?php declare(strict_types=1); namespace Demo;

use Lib\Ledger;
use Lib\Probe;

class Cashier
{
    private \Closure $discount;

    public function __construct(int $percent)
    {
        $this->discount = fn ($cents) => $cents - \intdiv($cents * $percent, 100);
    }

    public function receipt(array $prices, Cashier $other, Ledger $ledger): array
    {
        $count = 0;
        $paid = ((object) ((object) ((object) $prices)->filter(fn ($price) => $price > 0))->map(fn ($price) => ($this->discount)($price)))->sortedBy(fn ($price) => -$price);
        $total = Ledger::fold($paid, function (int $sum, int $price) use (&$count) {
            $count += 1;
            return $sum + $price;
        }, 0);
        $recorded = ((object) $ledger)->record($paid, fn ($price) => ($other->discount)($price));
        return [
            Probe::join(",", ((object) $paid)->map($this->format(...))),
            (string) $total,
            (string) $count,
            Probe::join(",", $recorded),
            (string) ($ledger->scale)($total),
        ];
    }

    private function format(int $cents): string { return (string) \intdiv($cents, 100) . "." . ($cents % 100 < 10 ? "0" : "") . (string) ($cents % 100); }
}

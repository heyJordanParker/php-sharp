<?php declare(strict_types=1); namespace Demo;

use Lib\Ledger;

class Checkout
{
    private \Closure $discount;

    public function __construct(int $percent)
    {
        $this->discount = fn ($cents) => $cents - \intdiv($cents * $percent, 100);
    }

    public function receipt(array $prices, Checkout $other, Ledger $ledger): array
    {
        $count = 0;
        $paid = ((object) ((object) ((object) $prices)->filter(fn ($price) => $price > 0))->map(fn ($price) => $this->discount($price)))->sortedBy(fn ($price) => -$price);
        $total = \array_reduce($paid, function (int $sum, int $price) use (&$count) {
            $count += 1;
            return $sum + $price;
        }, 0);
        $recorded = ((object) $ledger)->record($paid, fn ($price) => ((object) $other)->discount($price));
        return [
            \implode(",", ((object) $paid)->map($this->format)),
            (string) $total,
            (string) $count,
            \implode(",", $recorded),
            (string) ((object) $ledger)->scale($total),
        ];
    }

    private function format(int $cents): string { return \sprintf("%d.%02d", \intdiv($cents, 100), $cents % 100); }
}

<?php

namespace {
    use Billing\Invoice;
    use Billing\InvoiceQueue;
    use Billing\Line;
    use Billing\Money;
    use Billing\Operators;
    use Billing\Order;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Billing.inc';
    require __DIR__ . '/Operators.sharp';

    $amount = static fn (Money $money): string => "$money->cents $money->currency";
    $skus = static fn (array $lines): string => implode(' ', array_map(static fn (Line $line): string => $line->sku, $lines));

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        $operators = new Operators();
        $price = new Money(500, 'EUR');
        $zero = new Money(0, 'EUR');

        attempt(fn () => $operators->same('1', '01'));
        attempt(fn () => $operators->same('1e3', '1000'));
        attempt(fn () => $operators->sameNumber(1, 1.0));
        attempt(fn () => $operators->before('10', '9'));
        attempt(fn () => $operators->before('Zebra', 'apple'));

        attempt(fn () => $operators->mask(-1));
        attempt(fn () => $operators->mask(64));
        attempt(fn () => $operators->mask(3));
        attempt(fn () => $operators->flags(1, 2, 4));
        attempt(fn () => $operators->cleared(7, 2));
        attempt(fn () => $operators->remainder(17, 5));

        attempt(fn () => $amount($operators->sum($price, new Money(300, 'EUR'))));
        attempt(fn () => $amount($operators->difference($price, new Money(300, 'EUR'))));
        attempt(fn () => $amount($operators->scaled($price, 3)));
        attempt(fn () => $amount($operators->split($price, 3)));
        attempt(fn () => $amount($operators->leftover($price, 3)));
        attempt(fn () => $amount($operators->raised($price, 2)));
        attempt(fn () => $amount($operators->refund($price)));
        attempt(fn () => $operators->cheaper(new Money(300, 'EUR'), $price));
        attempt(fn () => $operators->compared(new Money(100, 'USD'), $price));
        attempt(fn () => $amount($operators->settled(new Money(1000, 'EUR'), new Money(300, 'EUR'), 3)));
        attempt(fn () => $operators->sameOrder(new Order(7), new Order(7)));

        $runs = Money::$equalityRuns;
        attempt(fn () => $operators->totalIsZero($zero, new Money(0, 'EUR')));
        attempt(fn () => $operators->priceIsZero($price, $zero));
        attempt(fn () => Money::$equalityRuns - $runs);
        $runs = Money::$equalityRuns;
        attempt(fn () => $operators->unpriced(null));
        attempt(fn () => $operators->priceIsZero(null, $zero));
        attempt(fn () => $operators->priceIsNotZero(null, $zero));
        attempt(fn () => $operators->priced(null));
        attempt(fn () => $operators->priced($price));
        attempt(fn () => Money::$equalityRuns - $runs);

        attempt(fn () => $skus($operators->byPrice([
            new Line('a', new Money(500, 'EUR')),
            new Line('b', new Money(100, 'USD')),
            new Line('c', new Money(300, 'EUR')),
        ])));
        attempt(fn () => implode(' ', $operators->byCode(['9', '10', 'apple', 'Zebra'])));

        $queue = new InvoiceQueue(new Invoice(40, new Money(900, 'EUR')));
        attempt(fn () => $queue->halve());
        attempt(fn () => $queue->calls);
        attempt(fn () => $amount($queue->halveBalance()));
        attempt(fn () => $queue->calls);
        attempt(fn () => $queue->halveTwice());
        attempt(fn () => $queue->calls);
        $output = ob_get_clean();
    }

    echo $output;
}

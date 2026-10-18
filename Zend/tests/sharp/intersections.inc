<?php

use Intersections\Billable;
use Intersections\Desk;
use Intersections\Parcel;
use Intersections\Shippable;

// Plain PHP classes: one is both types, and one is only Shippable.
final class PlainBoth implements Shippable, Billable
{
    public function weight(): int { return 4; }
    public function amount(): int { return 5; }
}

final class PlainShippable implements Shippable
{
    public function weight(): int { return 6; }
}

function row(string $case, Closure $run): void
{
    try {
        $result = json_encode($run());
    } catch (TypeError $error) {
        $result = 'TypeError: ' . $error->getMessage();
    }
    echo $case, ': ', $result, "\n";
}

// Each row runs twice, so the JIT runs compile the methods before the second run.
$desk = new Desk();
for ($run = 0; $run < 2; $run++) {
    row('a PHP# class that is both, as a parameter', fn () => $desk->cost(new Parcel()));
    row('a plain PHP class that is both, as a parameter', fn () => $desk->cost(new PlainBoth()));
    row('a plain PHP class that is only Shippable, as a parameter', fn () => $desk->cost(new PlainShippable()));
    row('a plain PHP class that is both, as a property', function () use ($desk) {
        $desk->last = new PlainBoth();
        return $desk->last::class;
    });
    row('a plain PHP class that is only Shippable, as a property', function () use ($desk) {
        $desk->last = new PlainShippable();
        return $desk->last::class;
    });
    row('null, as the nullable property', function () use ($desk) {
        $desk->last = null;
        return $desk->last;
    });
    row('as, of a value that is both and of one that is not', fn () => [$desk->keep(new PlainBoth())::class, $desk->keep(new PlainShippable())::class]);
    row('is, of a value that is both and of one that is not', fn () => [$desk->test(new PlainBoth()), $desk->test(new PlainShippable())]);
    row('a local of the intersection', fn () => $desk->local());
    row('the type reflection reports', fn () => (string) (new ReflectionMethod(Desk::class, 'cost'))->getParameters()[0]->getType());
}

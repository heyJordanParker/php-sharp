<?php

namespace Demo;

final class Counter
{
    public function __construct(public int $value) {}
}

final class PhpOverflow
{
    public static function add(int $a, int $b) { return $a + $b; }
    public static function preInc(int $a) { $x = $a; return ++$x; }
}

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";

foreach ([
    ['add', [1, 2], [PHP_INT_MAX, 1]],
    ['sub', [1, 2], [PHP_INT_MIN, 1]],
    ['mul', [3, 4], [PHP_INT_MAX, 2]],
    ['negate', [5], [PHP_INT_MIN]],
    ['addAssign', [1, 2], [PHP_INT_MAX, 1]],
    ['subAssign', [1, 2], [PHP_INT_MIN, 1]],
    ['mulAssign', [3, 4], [PHP_INT_MAX, 2]],
    ['preInc', [1], [PHP_INT_MAX]],
    ['postInc', [1], [PHP_INT_MAX]],
    ['postIncValue', [1], [PHP_INT_MAX]],
    ['preDec', [1], [PHP_INT_MIN]],
    ['postDec', [1], [PHP_INT_MIN]],
    ['postDecValue', [1], [PHP_INT_MIN]],
] as [$method, $warm, $overflow]) {
    for ($i = 0; $i < 100; $i++) {
        Overflow::$method(...$warm);
    }
    echo $method, ' ', Overflow::$method(...$warm), "\n";
    attempt(fn () => Overflow::$method(...$overflow));
}

for ($i = 0; $i < 100; $i++) {
    Overflow::incProperty(new Counter(1));
    Overflow::addProperty(new Counter(1), 2);
}
attempt(fn () => Overflow::incProperty(new Counter(PHP_INT_MAX)));
attempt(fn () => Overflow::addProperty(new Counter(PHP_INT_MAX), 1));

foreach ([
    'add' => [1, fn ($x) => Overflow::add($x, $x)],
    'mul' => [1, fn ($x) => Overflow::mul($x, 3)],
    'preInc' => [PHP_INT_MAX - 300, fn ($x) => Overflow::preInc($x)],
    'postDec' => [PHP_INT_MIN + 300, fn ($x) => Overflow::postDec($x)],
] as $method => [$x, $step]) {
    try {
        while (true) {
            $x = $step($x);
        }
    } catch (\ArithmeticError $e) {
        echo $method, ' loop stopped at ', $x, ' on line ', $e->getLine(), "\n";
    }
}

for ($i = 0; $i < 100; $i++) {
    PhpOverflow::add(1, 2);
    PhpOverflow::preInc(1);
}
attempt(fn () => PhpOverflow::add(PHP_INT_MAX, 1));
attempt(fn () => PhpOverflow::preInc(PHP_INT_MAX));

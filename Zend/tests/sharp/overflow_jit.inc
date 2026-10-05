<?php

namespace Demo;

final class PhpOverflow
{
    public static function add(int $a, int $b) { return $a + $b; }
    public static function preInc(int $a) { $x = $a; return ++$x; }
}

require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/Overflow.inc';
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
    Overflow::incProperty(new Cell(1));
    Overflow::addProperty(new Cell(1), 2);
    Overflow::incLoose(new LooseCounter(1));
    Overflow::addLoose(new LooseCounter(1), 2);
    Overflow::incAfterCall(1);
    Overflow::addAfterCall(1, 2);
    Overflow::sumAfterCall(1, 2);
}
attempt(fn () => Overflow::incProperty(new Cell(PHP_INT_MAX)));
attempt(fn () => Overflow::addProperty(new Cell(PHP_INT_MAX), 1));
attempt(fn () => Overflow::incLoose(new LooseCounter(PHP_INT_MAX)));
attempt(fn () => Overflow::addLoose(new LooseCounter(PHP_INT_MAX), 1));
attempt(fn () => Overflow::incAfterCall(PHP_INT_MAX));
attempt(fn () => Overflow::addAfterCall(PHP_INT_MAX, 1));
attempt(fn () => Overflow::sumAfterCall(PHP_INT_MAX, 1));

$held = new Cell(PHP_INT_MAX);
$reference = &$held->value;
attempt(fn () => Overflow::addProperty($held, 1));
attempt(fn () => Overflow::incProperty($held));
var_dump($held->value);

foreach ([
    'add' => [1, fn ($x) => Overflow::add($x, $x)],
    'mul' => [1, fn ($x) => Overflow::mul($x, 3)],
    'preInc' => [PHP_INT_MAX - 300, fn ($x) => Overflow::preInc($x)],
    'postDec' => [PHP_INT_MIN + 300, fn ($x) => Overflow::postDec($x)],
    'incAfterCall' => [PHP_INT_MAX - 300, fn ($x) => Overflow::incAfterCall($x)],
    'addAfterCall' => [PHP_INT_MAX - 300, fn ($x) => Overflow::addAfterCall($x, 1)],
    'sumAfterCall' => [PHP_INT_MAX - 300, fn ($x) => Overflow::sumAfterCall($x, 1)],
] as $method => [$x, $step]) {
    try {
        while (true) {
            $x = $step($x);
        }
    } catch (\ArithmeticError $e) {
        echo $method, ' loop stopped at ', $x, ' on line ', $e->getLine(), "\n";
    }
}

foreach ([
    'incProperty' => [new Cell(PHP_INT_MAX - 300), fn ($counter) => Overflow::incProperty($counter)],
    'addProperty' => [new Cell(PHP_INT_MAX - 300), fn ($counter) => Overflow::addProperty($counter, 1)],
    'incLoose' => [new LooseCounter(PHP_INT_MAX - 300), fn ($counter) => Overflow::incLoose($counter)],
    'addLoose' => [new LooseCounter(PHP_INT_MAX - 300), fn ($counter) => Overflow::addLoose($counter, 1)],
] as $method => [$counter, $step]) {
    try {
        while (true) {
            $step($counter);
        }
    } catch (\ArithmeticError $e) {
        echo $method, ' loop stopped at ', $counter->value, ' on line ', $e->getLine(), "\n";
    }
}

for ($i = 0; $i < 100; $i++) {
    PhpOverflow::add(1, 2);
    PhpOverflow::preInc(1);
}
attempt(fn () => PhpOverflow::add(PHP_INT_MAX, 1));
attempt(fn () => PhpOverflow::preInc(PHP_INT_MAX));

// The JIT compiled every PHP# operator itself, with no call to a VM handler.
var_dump(opcache_get_status()['jit']['sharp_operator_vm_calls']);

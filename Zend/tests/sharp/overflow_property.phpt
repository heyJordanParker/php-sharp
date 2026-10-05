--TEST--
PHP# throws ArithmeticError when ++, -- or += overflows a property
--FILE--
<?php

namespace Demo;

require __DIR__ . '/harness/Overflow.inc';

final class MagicCounter extends Cell
{
    private array $values = [];

    public function __construct(int $value)
    {
        unset($this->value);
        $this->values['value'] = $value;
    }

    public function __get(string $name): int
    {
        return $this->values[$name];
    }

    public function __set(string $name, int $value): void
    {
        $this->values[$name] = $value;
    }
}

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

$counter = new Cell(PHP_INT_MAX);
attempt(fn () => Overflow::incProperty($counter));
var_dump($counter->value);
attempt(fn () => Overflow::decProperty(new Cell(PHP_INT_MIN)));
attempt(fn () => Overflow::addProperty(new Cell(PHP_INT_MAX), 1));
attempt(fn () => Overflow::addProperty(new Cell(1), 2));

$held = new Cell(PHP_INT_MAX);
$reference = &$held->value;
attempt(fn () => Overflow::addProperty($held, 1));
attempt(fn () => Overflow::incProperty($held));
var_dump($held->value);

$magic = new MagicCounter(PHP_INT_MAX);
attempt(fn () => Overflow::incProperty($magic));
var_dump($magic->value);
attempt(fn () => Overflow::decProperty(new MagicCounter(PHP_INT_MIN)));
attempt(fn () => Overflow::addProperty(new MagicCounter(PHP_INT_MAX), 1));
attempt(fn () => Overflow::addProperty(new MagicCounter(1), 2));
?>
--EXPECT--
ArithmeticError: Integer overflow in Overflow.sharp on line 106
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 112
ArithmeticError: Integer overflow in Overflow.sharp on line 117
int(3)
ArithmeticError: Integer overflow in Overflow.sharp on line 117
ArithmeticError: Integer overflow in Overflow.sharp on line 106
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 106
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 112
ArithmeticError: Integer overflow in Overflow.sharp on line 117
int(3)

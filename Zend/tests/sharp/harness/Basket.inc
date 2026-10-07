<?php declare(strict_types=1);

namespace Lib;

final class Basket
{
    public int $writes = 0;

    /** @var list<int> */
    public array $lines = [] {
        set(array $value) {
            $this->writes++;
            /** @var list<int> $value */
            $this->lines = $value;
        }
    }

    /** @var list<int> */
    public array $totals {
        get => [array_sum($this->lines)];
    }

    public function size(): int
    {
        return count($this->lines);
    }
}

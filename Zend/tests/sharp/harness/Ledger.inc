<?php declare(strict_types=1);

namespace Lib;

abstract class Book
{
    /** @var \Closure(int): int */
    public \Closure $scale;
}

final class Ledger extends Book
{
    public function __construct(int $factor)
    {
        $this->scale = fn (int $cents): int => $cents * $factor;
    }

    /**
     * @param list<int> $amounts
     * @param \Closure(int): int $entry
     *
     * @return list<int>
     */
    public function record(array $amounts, \Closure $entry): array
    {
        return array_map($entry, $amounts);
    }
}

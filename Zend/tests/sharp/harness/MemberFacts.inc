<?php declare(strict_types=1);

namespace Lib;

use App\Facts\Tag;

class PlainLedger
{
    public string $code = 'plain';
    protected int $level = 1;
    private int $count = 0;

    public static function open(): static
    {
        return new static();
    }

    protected function reset(int $depth): void
    {
    }

    private function total(int $limit = 2, Tag $tag = new Tag('plain'), string ...$notes): int
    {
        return $limit + $this->count + $this->level;
    }
}

final class PlainJournal extends PlainLedger
{
    public function title(): string
    {
        return 'plain journal';
    }
}

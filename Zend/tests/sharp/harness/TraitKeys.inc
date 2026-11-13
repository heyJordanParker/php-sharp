<?php declare(strict_types=1);

namespace Lib;

trait Stocking
{
    public function stockLabel(): string
    {
        return 'plain stock';
    }
}

trait Recording
{
    public array $entries = [];

    public function recordEntry(int $id): void
    {
        $this->entries[] = "plain $id";
    }
}

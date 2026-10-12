<?php

namespace Illuminate\Support\Facades;

final class DB
{
    public static function table(string $table): self
    {
        return new self();
    }

    public function where(string $column, mixed $value): self
    {
        return $this;
    }

    public function count(): int
    {
        return 0;
    }
}

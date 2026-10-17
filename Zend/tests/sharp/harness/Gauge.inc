<?php

namespace Lib;

// A plain PHP base class whose typed properties a PHP# subclass narrows, and whose own code writes them.
abstract class Gauge
{
    protected ?string $label = null;

    public int|float $level = 0;

    public function relabel(?string $label): void
    {
        $this->label = $label;
    }

    public function describe(): string
    {
        return ($this->label ?? 'none') . ' ' . $this->level;
    }
}

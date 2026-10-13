<?php declare(strict_types=1);

namespace Lib;

abstract class Model
{
    public function key(): int
    {
        return 7;
    }
}

trait HasSlug
{
    public const string ROOT = '/articles/';

    public string $slug = 'intro';

    public function path(): string
    {
        return self::ROOT . $this->slug;
    }
}

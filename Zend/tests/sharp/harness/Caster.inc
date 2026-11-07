<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP parent whose array properties a subclass overrides, as Eloquent's `Model` reads `$casts`. */
abstract class Caster
{
    /** @var array<string, string> */
    protected $casts = [];

    /** @var array<string, string> */
    protected array $formats = [];

    public function getCasts(): array
    {
        return array_merge(['id' => 'int'], $this->casts);
    }

    public function getFormats(): array
    {
        return array_merge(['id' => '%d'], $this->formats);
    }
}

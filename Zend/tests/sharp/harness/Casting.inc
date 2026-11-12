<?php declare(strict_types=1);

namespace Lib;

// A plain PHP parent that declares a field and a method of one name, as Eloquent's `HasAttributes` declares `$casts`
// and `casts()`, and reads both from its own code, as Eloquent's `getCasts()` does.
abstract class Casting
{
    protected $casts = ['id' => 'int'];

    protected function casts()
    {
        return [];
    }

    public function getCasts(): array
    {
        return array_merge($this->casts, $this->casts());
    }
}

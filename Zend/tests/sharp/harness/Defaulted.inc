<?php declare(strict_types=1);

namespace Lib;

// A plain PHP trait whose methods a class that uses it replaces, static and instance alike.
trait Defaulted
{
    protected static function status(): string
    {
        return 'draft';
    }

    protected function label(): string
    {
        return 'draft';
    }
}

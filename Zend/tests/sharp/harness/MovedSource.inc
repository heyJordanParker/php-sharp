<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP override of a PHP# method that returns a `Map`, which gives its PHP array. */
final class MovedSource extends \Arrivals\Source
{
    public function status(): array
    {
        return ['status' => 'moved'];
    }
}

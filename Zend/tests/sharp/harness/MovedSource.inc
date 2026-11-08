<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP override of a PHP# method and of a static method that return a `Map`, which give their PHP arrays. */
final class MovedSource extends \Arrivals\Source
{
    public function status(): array
    {
        return ['status' => 'moved'];
    }

    public static function made(): array
    {
        return ['status' => 'remade'];
    }
}

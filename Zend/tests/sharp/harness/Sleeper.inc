<?php declare(strict_types=1);

namespace Lib;

/** A parent that serializes only its subclass's `entries`, through `__sleep`. */
abstract class Sleeper
{
    /** @return list<string> */
    public function __sleep(): array
    {
        return ['entries'];
    }
}

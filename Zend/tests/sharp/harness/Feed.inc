<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP interface whose results PHP# spreads into a `Map` and a `List`. */
interface Feed
{
    public function payload(): array;

    /** @return list<string> */
    public function names(): array;
}

final class ArrayFeed implements Feed
{
    public function payload(): array
    {
        return ['status' => 'fed'];
    }

    public function names(): array
    {
        return ['first', 'second'];
    }
}

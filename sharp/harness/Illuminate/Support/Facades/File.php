<?php

namespace Illuminate\Support\Facades;

final class File
{
    /** @return iterable<string> */
    public static function lines(string $path): iterable
    {
        foreach (new \SplFileObject($path) as $line) {
            yield rtrim($line, "\n");
        }
    }
}

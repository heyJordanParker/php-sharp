<?php

namespace Lib;

// A plain PHP factory that takes a class name string, as Laravel's container's make() does.
final class Factory
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function make(string $class): object
    {
        return new $class();
    }
}

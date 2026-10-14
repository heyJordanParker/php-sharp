<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP interface whose generic method a PHP# class implements. */
interface Wrapper
{
    /**
     * @template T
     * @param T $item
     * @return \App\Box<T>
     */
    public function wrap(mixed $item): \App\Box;
}

/** A plain PHP class whose generic method a PHP# class overrides. */
class PlainWrapper
{
    /**
     * @template T
     * @param T $item
     * @return \App\Box<T>
     */
    public function wrap(mixed $item): \App\Box
    {
        return new \App\Box();
    }
}

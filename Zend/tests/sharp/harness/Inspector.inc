<?php declare(strict_types=1);

namespace Lib;

final class Inspector
{
    /** @var list<array<string, mixed>> */
    public static array $frames = [];

    public static function look(int $n): bool
    {
        self::$frames = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        return $n > 0;
    }

    public static function pause(int $n): bool
    {
        if (\Fiber::getCurrent() !== null) {
            \Fiber::suspend();
        }
        return $n > 0;
    }
}

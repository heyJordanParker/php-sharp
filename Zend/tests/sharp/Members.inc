<?php declare(strict_types=1); namespace Demo;

use Lib\Order;
use Lib\Registry;

class Members
{
    public const int START = 10;
    protected const ?string LABEL = "members";
    public const int NEXT = Registry::VERSION + 1;
    public static private(set) Order $sorted = Order::Ascending;
    private static int $count = 0;
    public static private(set) ?string $last = "none";

    final public static function touch(): void
    {
        Members::$count = 5;
        Members::$count++;
        --Members::$count;
        Members::$count += 2;
        Members::$last ??= "never";
        Members::$last = "touched";
    }

    final public static function start(): int
    {
        return Members::START;
    }

    final public static function order(): Order
    {
        return Order::Descending;
    }

    final public static function pick(Order $order = Order::Descending): Order
    {
        return $order;
    }

    final public static function label(): string
    {
        return Registry::$label;
    }

    final public static function version(): int
    {
        return Registry::VERSION;
    }

    final public static function bump(): int
    {
        Members::$count += 1;
        return Members::$count;
    }

    final public static function late(): int
    {
        return Registry::$late;
    }

    final public static function ones(): int
    {
        $count = 0;
        foreach (Registry::$items as $index => $item) {
            if (\Sharp\Internal\equals($item, 1)) {
                $count++;
            }
        }
        return $count;
    }
}

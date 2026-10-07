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

    public static function touch(): void
    {
        Members::$count = 5;
        Members::$count++;
        --Members::$count;
        Members::$count += 2;
        Members::$last ??= "never";
        Members::$last = "touched";
    }

    public static function start(): int
    {
        return Members::START;
    }

    public static function order(): Order
    {
        return Order::Descending;
    }

    public static function pick(Order $order = Order::Descending): Order
    {
        return $order;
    }

    public static function label(): string
    {
        return Registry::$label;
    }

    public static function version(): int
    {
        return Registry::VERSION;
    }

    public static function bump(): int
    {
        Members::$count += 1;
        return Members::$count;
    }

    public static function late(): int
    {
        return Registry::$late;
    }

    public static function ones(): int
    {
        $count = 0;
        foreach (Registry::$items as $item) {
            if ($item === 1) {
                $count++;
            }
        }
        return $count;
    }
}

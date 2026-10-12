<?php declare(strict_types=1);

namespace Lib;

use App\PaginatedList;

/**
 * A plain PHP source of pages: its docblock promises PHP# a page of orders, and it returns whatever page the test
 * gave it.
 */
final class Pager
{
    public static ?PaginatedList $next = null;

    /** @return PaginatedList<\App\Order> */
    public static function orders(): PaginatedList
    {
        return self::$next;
    }
}

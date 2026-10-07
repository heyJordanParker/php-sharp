<?php declare(strict_types=1); namespace Demo;

use Lib\Registry;

class Misread
{
    public static function missing(): int
    {
        return Registry::$missing;
    }

    public static function hidden(): int
    {
        return Registry::$hidden;
    }
}

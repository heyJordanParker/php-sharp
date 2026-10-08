<?php declare(strict_types=1); namespace Demo;

class Exits
{
    public static function stop(int $code): void
    {
        try {
            exit($code);
        } finally {
            \printf("finally\n");
        }
    }
}

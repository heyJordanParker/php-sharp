<?php declare(strict_types=1); namespace Demo;

class Tags
{
    public static function of(string|array $value): array
    {
        return \Sharp\List::wrap($value);
    }
}

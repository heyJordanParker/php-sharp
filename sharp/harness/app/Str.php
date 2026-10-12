<?php

namespace App;

final class Str
{
    public static function slug(string $title): string
    {
        return strtolower(trim(preg_replace('~[^A-Za-z0-9]+~', '-', $title), '-'));
    }
}

<?php

namespace Lib;

enum Status: string
{
    case Draft = 'draft';
    case Live = 'live';
}

interface Linkable
{
    public function link(): string;
}

abstract class Entity
{
    public function id(): int
    {
        return 7;
    }
}

final class Registry
{
    public static function register(string $class): string
    {
        return "registered $class";
    }
}

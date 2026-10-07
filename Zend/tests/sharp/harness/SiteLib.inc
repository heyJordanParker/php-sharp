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

#[\Attribute]
final class Access
{
    public function __construct(public string $class) {}
}

abstract class Resource
{
    abstract protected function render(): string;

    public function id(): int
    {
        return 7;
    }

    public function show(): string
    {
        return $this->id() . ' ' . $this->render();
    }
}

final class Catalog
{
    public static function register(string $class): string
    {
        return "registered $class";
    }
}

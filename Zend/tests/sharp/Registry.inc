<?php

namespace Lib;

enum Order: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

final class Registry
{
    public const int VERSION = 2;
    public static string $label = 'registry';
    protected static int $hidden = 1;
}

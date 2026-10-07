<?php declare(strict_types=1);

namespace Lib;

enum Standing: string
{
    case Active = 'active';
    case Closed = 'closed';
}

enum Size: int
{
    case Small = 1;
    case Large = 2;
}

enum Code: string
{
    case Five = '5';
}

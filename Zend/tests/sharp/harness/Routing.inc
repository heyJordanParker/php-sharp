<?php declare(strict_types=1);

namespace Lib\Routing;

/** A plain PHP router whose route names and sort columns are strings. */
function route(string $name): string
{
    return "/$name";
}

function label(?string $name): string
{
    return $name ?? 'no route';
}

function orderBy(string $column): string
{
    return "order by $column";
}

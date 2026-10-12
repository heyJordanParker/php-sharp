<?php

namespace App;

// Rows as a database query gives them to plain PHP: objects with public properties.
final class Rows
{
    public static function withId(mixed $id): object
    {
        return (object) ['id' => $id, 'number' => 'A-1'];
    }
}

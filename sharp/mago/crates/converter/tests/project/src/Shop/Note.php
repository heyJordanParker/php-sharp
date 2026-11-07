<?php

declare(strict_types=1);

namespace Proof\Shop;

final class Note
{
    private ?string $title = null;

    // `?:` and the truthiness of a property, whose PHP# tests read it again: `'0'` and `''` are falsy, as in PHP.
    public function headed(?string $title): string
    {
        $this->title = $title;
        if ($this->title) {
            return 'headed ' . $this->title;
        }

        return $title ?: 'untitled';
    }

    // A string that may be null, which PHP's `.` and interpolation show as nothing.
    public function signed(?string $name): string
    {
        return "Dear {$name}," . $name . '.';
    }

    public function describe($value): string
    {
        if (is_string($value)) {
            return 'text ' . $value;
        }

        return 'other';
    }
}

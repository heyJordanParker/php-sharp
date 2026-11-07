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

    // `(bool)`, which the truthiness test of the value's type replaces: `'0'`, `''` and `0` are false, as in PHP.
    /** @return list<bool> */
    public function flagged(int $count, ?string $note): array
    {
        return [(bool) $count, (bool) $note];
    }

    // `.=` of a number and of a string that may be null, and `===` of a value of no known type with a string.
    public function tagged(mixed $answer, int $count, ?string $tag): string
    {
        $text = 'note';
        $text .= $count;
        $text .= $tag;

        return $text . ($answer === 'yes' ? ' agreed' : ' not');
    }

    // `===` of a string with `X::class`, the text of the class's name.
    public function namesSpec(?string $name): bool
    {
        return $name === \Proof\Spec::class;
    }

    public function describe($value): string
    {
        if (is_string($value)) {
            return 'text ' . $value;
        }

        return 'other';
    }
}

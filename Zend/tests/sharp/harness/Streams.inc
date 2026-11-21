<?php declare(strict_types=1);

namespace Lib\Streams;

/** A plain PHP stream whose `read` gives a string of bytes, as PSR-7's `StreamInterface::read` does. */
final class Stream
{
    public function __construct(private string $bytes)
    {
    }

    public function read(int $length): string
    {
        return substr($this->bytes, 0, $length);
    }
}

/** What plain PHP receives as `$value`: a string as its bytes in hexadecimal, and any other value as its type. */
function shown(mixed $value): string
{
    return is_string($value) ? 'string ' . bin2hex($value) : get_debug_type($value);
}

/** What plain PHP receives in each element of `$values`, keyed as it receives them. */
function described(array $values): string
{
    return implode(', ', array_map(fn (int|string $key): string => "$key: " . shown($values[$key]), array_keys($values)));
}

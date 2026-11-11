<?php declare(strict_types=1);

namespace Lib;

// A plain PHP class whose text is its `__toString()`, and one that inherits it.
class Email
{
    public function __construct(private string $address)
    {
    }

    public function __toString(): string
    {
        return $this->address;
    }
}

final class WorkEmail extends Email
{
}

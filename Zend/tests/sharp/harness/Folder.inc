<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP holder of the same records twice: as an array PHP types no further, and as a list its PHPDoc declares. */
final class Folder
{
    public ?array $records = null;

    /** @var list<array<string, ?string>>|null */
    public ?array $listed = null;
}

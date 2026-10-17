<?php declare(strict_types=1);

namespace Lib;

// A plain PHP class whose `__get` serves the relations it loaded, beside methods of the same names, as an Eloquent
// model's does. An `@property-read` line types one of them.
/** @property-read Guest $guest */
final class Booking
{
    /** @var array<string, Guest> */
    private array $loaded;

    public function __construct()
    {
        $this->loaded = ['guest' => new Guest('Ada'), 'host' => new Guest('Grace')];
    }

    public function guest(): Guest
    {
        return new Guest('the guest relation');
    }

    public function host(): Guest
    {
        return new Guest('the host relation');
    }

    public function __get(string $name): mixed
    {
        return $this->loaded[$name] ?? null;
    }
}

final class Guest
{
    public function __construct(public string $name)
    {
    }
}

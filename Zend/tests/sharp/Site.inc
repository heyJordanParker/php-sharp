<?php declare(strict_types=1); namespace Site;

use Lib\Access;
use Lib\Catalog;
use Lib\Linkable;
use Lib\Resource;
use Lib\Status;

#[Access(Page::class)]
class Page extends Resource implements Linkable
{
    public const int LIMIT = 3;
    public static private(set) int $views = 0;
    private Status $status = Status::Live;

    #[\Override] protected function render(): string
    {
        return "page";
    }

    #[\Override] public function id(): int
    {
        return parent::id() + 1;
    }

    public function link(): string
    {
        return "/page";
    }

    public function limit(): int
    {
        return Page::LIMIT;
    }

    public function visit(): int
    {
        Page::$views++;
        return Page::$views;
    }

    public function live(): bool
    {
        return $this->status === Status::Live;
    }

    public function draft(): Status
    {
        return Status::Draft;
    }

    public function registered(): string
    {
        return Catalog::register(Page::class);
    }
}

abstract class Shape
{
    abstract public function area(): float;

    public function sides(): int
    {
        return 0;
    }
}

class Square extends Shape
{
    #[\Override] public function area(): float
    {
        return 4.0;
    }

    #[\Override] public function sides(): int
    {
        return parent::sides() + 4;
    }
}

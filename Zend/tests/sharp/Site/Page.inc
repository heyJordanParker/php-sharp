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

    final public function link(): string
    {
        return "/page";
    }

    final public function limit(): int
    {
        return Page::LIMIT;
    }

    final public function visit(): int
    {
        Page::$views++;
        return Page::$views;
    }

    final public function live(): bool
    {
        return $this->status === Status::Live;
    }

    final public function draft(): Status
    {
        return Status::Draft;
    }

    final public function registered(): string
    {
        return Catalog::register(Page::class);
    }
}

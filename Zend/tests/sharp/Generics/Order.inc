<?php declare(strict_types=1); namespace Paging;

class Order extends DatabaseEntity implements Shareable
{
    final public function link(): string { return "/orders/{$this->id}"; }
}

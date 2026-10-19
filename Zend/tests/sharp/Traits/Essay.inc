<?php declare(strict_types=1); namespace Demo;

use Lib\Document;
use Lib\HasSlug;

class Essay extends Document implements Linked
{   use HasSlug;
    final public function link(): string
    {
        return $this->path();
    }

    final public function name(): string
    {
        return $this->slug;
    }
}

<?php declare(strict_types=1); namespace Demo;

use Lib\Copies;
use Lib\Document;
use Lib\HasSlug;

interface Linked
{
    public function name(): string;
}

class Essay extends Document implements Linked
{   use HasSlug;
    public function link(): string
    {
        return $this->path();
    }

    public function name(): string
    {
        return $this->slug;
    }
}

class Guide extends Essay
{
    public function __construct()
    {
        $this->slug = "setup";
    }

    public function topic(): string
    {
        return $this->slug;
    }
}

class Draft
{   use Copies;
}

enum Shade
{   use Copies;
    case Light;
}

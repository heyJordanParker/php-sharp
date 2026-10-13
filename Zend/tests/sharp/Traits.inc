<?php declare(strict_types=1); namespace Demo;

use Lib\HasSlug;
use Lib\Model;

interface Linked
{
    public function link(): string;
}

class Article extends Model implements Linked
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

class Guide extends Article
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

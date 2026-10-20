<?php declare(strict_types=1); namespace Demo;

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

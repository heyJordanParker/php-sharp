<?php declare(strict_types=1); namespace Demo;



class Permalink
{
    public static function of(string $title): string { return \Sharp\Text\Text::slug($title); }
}

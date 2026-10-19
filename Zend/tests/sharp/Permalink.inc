<?php declare(strict_types=1); namespace Demo;



class Permalink
{
    final public static function of(string $title): string { return \Sharp\Internal\Text\Text\slug($title); }
}

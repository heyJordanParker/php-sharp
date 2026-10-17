<?php

namespace Lib;

// A plain PHP class with a property and a method of one name, as PHP keeps the two apart.
final class Gadget
{
    public string $label = 'gadget';

    public ?\Closure $format = null;

    public function label(string $prefix): string
    {
        return $prefix . $this->label;
    }

    public function format(string $text): string
    {
        return "[{$text}]";
    }}

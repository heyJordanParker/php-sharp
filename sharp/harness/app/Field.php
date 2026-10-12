<?php

namespace App;

#[\Attribute(\Attribute::TARGET_ALL)]
final class Field
{
    public function __construct(public string $label = '', public ?Render $render = null, public bool $searchable = false)
    {
    }
}

<?php

namespace Lib;

// A plain PHP base class that types the property its PHP# subclasses override.
abstract class TypedModel
{
    protected string $table = 'models';

    public string $seen;

    // It reads the subclass's default before the subclass's own code runs, as Eloquent's constructor does.
    public function __construct()
    {
        $this->seen = $this->table;
    }
}

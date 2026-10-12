<?php

namespace Illuminate\Database\Eloquent;

// Eloquent's base model, as far as the spec's examples use it: properties PHP declares untyped and types by `@var`.
abstract class Model
{
    /** @var string|null */
    protected $table;

    /** @var array<int, string> */
    protected $fillable = [];

    /** @var array<int, string> */
    protected $with = [];

    /** @var bool */
    public $timestamps = true;

    public function __construct()
    {
    }

    public function save(): bool
    {
        return true;
    }

    public function getAttribute(string $key): mixed
    {
        return null;
    }

    public static function find(int $id): ?static
    {
        return null;
    }
}

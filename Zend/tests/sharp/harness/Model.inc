<?php

namespace Lib;

trait HasTimestamps
{
    /** @var bool */
    public $timestamps = true;
}

// A plain PHP base class with properties PHP declares untyped, typed only by `@var`, and one PHP types.
abstract class Model
{
    use HasTimestamps;

    /** @var string|null */
    protected $table;

    /** @var array<int, string> */
    protected $fillable = [];

    /** @var array */
    protected $with = [];

    protected int $perPage = 15;

    /** @var string */
    public $seen;

    // It reads the subclass's defaults before the subclass's own code runs, as Eloquent's constructor does.
    public function __construct()
    {
        $this->seen = $this->table . ' fills ' . implode(',', $this->fillable);
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }
}

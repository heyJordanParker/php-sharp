<?php declare(strict_types=1);

namespace Lib;

abstract class Document
{
    public function key(): int
    {
        return 7;
    }
}

trait HasSlug
{
    public const string ROOT = '/articles/';

    public string $slug = 'intro';

    public function path(): string
    {
        return self::ROOT . $this->slug;
    }
}

// The methods Laravel's SoftDeletes and HasUniqueStringIds share with Model, which a model's `use` replaces.
abstract class Persisted
{
    public function forceDelete(): string
    {
        return 'model force';
    }

    protected function performDeleteOnModel(): string
    {
        return 'model delete';
    }

    public function delete(): string
    {
        return $this->performDeleteOnModel();
    }

    public function getKeyType(): string
    {
        return 'int';
    }
}

trait SoftDeletes
{
    public function forceDelete(): string
    {
        return 'soft force';
    }

    protected function performDeleteOnModel(): string
    {
        return 'soft delete';
    }
}

trait HasUniqueStringIds
{
    public function getKeyType(): string
    {
        return 'string';
    }
}

interface Aligned extends \BackedEnum
{
    public function label(): string;
}

trait Copies
{
    public function copy(): string
    {
        return 'copied ' . static::class;
    }
}

<?php

namespace Illuminate\Database\Eloquent\Casts;

/**
 * Eloquent's accessor of a model's attribute, as far as the spec's examples use it.
 *
 * @template TGet
 */
final class Attribute
{
    /**
     * @template TMade
     *
     * @param (\Closure(string): TMade)|null $get
     *
     * @return self<TMade>
     */
    public static function make(?\Closure $get = null): self
    {
        return new self();
    }
}

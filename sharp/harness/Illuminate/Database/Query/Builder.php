<?php

namespace Illuminate\Database\Query;

// Laravel's query builder, as far as the spec's examples use it: a raw clause takes only SQL written in the code.
class Builder
{
    /**
     * @param literal-string $sql
     * @param list<mixed> $bindings
     */
    public function orderByRaw(string $sql, array $bindings = []): static
    {
        return $this;
    }
}

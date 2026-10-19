<?php declare(strict_types=1); namespace Demo;

class Inbox
{
    private mixed $first = "none";
    #[\Sharp\Crossing(['map', 'string', null])] private \Sharp\Map $saved;

    final public function read(string $key) { return \Demo\Settings::get($key); }

    final public function raw(string $key)
    {
        $value = \Demo\Settings::raw($key);
        return $value;
    }

    final public function keep(mixed $value)
    {
        $this->first = $value;
        return $this->first;
    }

    final public function show(string $key): string { return \Demo\Settings::describe($this->read($key)); }

    final public function isPlan(string $plan): bool { return $this->read("plan") === $plan; }

    final public function save(string $key): void
    {
        $this->saved[$key] = \Demo\Settings::get($key);
    }

    final public function load(string $key) { return $this->saved[$key] ?? null; }

    final public function showSaved(string $key): string { return \Demo\Settings::describe($this->saved[$key] ?? null); }
}

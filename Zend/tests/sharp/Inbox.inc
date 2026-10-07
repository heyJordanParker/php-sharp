<?php declare(strict_types=1); namespace Demo;

class Inbox
{
    private mixed $first = "none";
    private array $saved = [];

    public function read(string $key): mixed { return \Demo\Settings::get($key); }

    public function raw(string $key): mixed
    {
        $value = \Demo\Settings::raw($key);
        return $value;
    }

    public function keep(mixed $value): mixed
    {
        $this->first = $value;
        return $this->first;
    }

    public function show(string $key): string { return \Demo\Settings::describe($this->read($key)); }

    public function isPlan(string $plan): bool { return $this->read("plan") == $plan; }

    public function save(string $key): void
    {
        $this->saved[$key] = \Demo\Settings::get($key);
    }

    public function load(string $key): mixed { return $this->saved[$key] ?? null; }

    public function showSaved(string $key): string { return \Demo\Settings::describe($this->saved[$key] ?? null); }
}

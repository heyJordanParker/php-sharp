<?php declare(strict_types=1); namespace Demo;

class Page
{
    private string $name = "";
    private int $views = 0;

    public function __construct(string $name) { $this->name = $name; }

    public string $slug { get => \mb_strtolower($this->name, 'UTF-8'); }
    protected int $doubled { get => $this->views * 2; }
    private string $title { get => \ucfirst($this->name); }

    final public function link(): string { return \sprintf("/page/%s", $this->slug); }
    final public function visit(): void { $this->views++; }
    final public function seen(): int { return $this->doubled; }
    final public static function home(): string { return "/"; }
    final public function heading(): string { return \sprintf("%s (%d)", $this->title, $this->views); }
}

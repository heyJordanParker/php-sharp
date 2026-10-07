<?php declare(strict_types=1); namespace Demo;

class Page
{
    private string $name = "";
    private int $views = 0;

    public function __construct(string $name) { $this->name = $name; }

    public string $slug { get => \strtolower($this->name); }
    protected int $doubled { get => $this->views * 2; }
    private string $title { get => \ucfirst($this->name); }

    public function link(): string { return \sprintf("/page/%s", $this->slug); }
    public function visit(): void { $this->views++; }
    public function seen(): int { return $this->doubled; }
    public static function home(): string { return "/"; }
    public function heading(): string { return \sprintf("%s (%d)", $this->title, $this->views); }
}

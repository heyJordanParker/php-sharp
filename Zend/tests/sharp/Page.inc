<?php declare(strict_types=1); namespace Demo;

class Page
{
    private string $name = "";
    private int $views = 0;

    public function __construct(string $name) { $this->name = $name; }

    public string $slug { get => \mb_strtolower($this->name, 'UTF-8'); }
    protected int $doubled { get => $this->views * 2; }
    private string $title { get => \trim($this->name); }

    public function link(): string { return "/page/{$this->slug}"; }
    public function visit(): void { $this->views++; }
    public function seen(): int { return $this->doubled; }
    public static function home(): string { return "/"; }
    public function heading(): string { return "{$this->title} ({$this->views})"; }
}

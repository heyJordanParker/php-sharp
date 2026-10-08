<?php declare(strict_types=1); namespace Demo;

class PhpForms
{
    public function __construct(private \Sharp\Environment $environment) { }

    public \Sharp\Position $origin { get => new \Sharp\Position(__FILE__, 7, 31, 'Demo.PhpForms.origin'); }

    public function report(int $pruned): void
    {
        \printf("Pruned %d carts" . PHP_EOL, $pruned);
    }

    public function warn(string $message): void
    {
        \fwrite(STDERR, $message . PHP_EOL);
    }

    public function fail(string $message, int $code): void
    {
        $this->warn($message);
        exit($code);
    }

    public function setting(string $name): string { return $this->environment->variable($name) ?? "unset"; }

    public function target(): string { return $this->environment->arguments[1]; }

    public function folder(): string { return $this->environment->currentDirectory; }

    public function stubs(): string { return (new \Sharp\Position(__FILE__, 31, 30, 'Demo.PhpForms.stubs'))->directory . "/stubs"; }

    public function here(): array
    {
        $here = new \Sharp\Position(__FILE__, 35, 22, 'Demo.PhpForms.here');
        return [$here->file, (string) $here->line, (string) $here->column, $here->function];
    }

    public function located(): \Sharp\Position
    {
        $locate = fn () => new \Sharp\Position(__FILE__, 41, 30, 'Demo.PhpForms.located');
        return $locate();
    }

    public function kind(): string { return \Demo\PhpForms::class; }

    public function tags(string|array $value): array { return \Sharp\List::wrap($value); }
}

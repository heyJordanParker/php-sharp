<?php declare(strict_types=1); namespace Demo;

class PhpForms
{
    public function __construct(private \Sharp\Environment $environment, private \Sharp\Console $console, private \Sharp\Process $process) { }

    public \Sharp\Position $origin { get => new \Sharp\Position(__FILE__, 7, 31, 'Demo.PhpForms.origin'); }

    final public function report(int $pruned): void
    {
        $this->console->writeLine("Pruned {$pruned} carts");
    }

    final public function warn(string $message): void
    {
        $this->console->writeError($message . "\n");
    }

    final public function fail(string $message, int $code): void
    {
        $this->warn($message);
        $this->process->exit($code);
    }

    final public function setting(string $name): string { return $this->environment->variable($name) ?? "unset"; }

    final public function target(): string { return $this->environment->arguments[1]; }

    final public function folder(): string { return $this->environment->currentDirectory(); }

    final public function stubs(): string { return (new \Sharp\Position(__FILE__, 31, 30, 'Demo.PhpForms.stubs'))->directory . "/stubs"; }

    final public function here(): array
    {
        $here = new \Sharp\Position(__FILE__, 35, 22, 'Demo.PhpForms.here');
        return [$here->file, (string) $here->line, (string) $here->column, $here->function];
    }

    final public function located(): \Sharp\Position
    {
        $locate = fn () => new \Sharp\Position(__FILE__, 41, 30, 'Demo.PhpForms.located');
        return $locate();
    }

    final public function kind(): string { return \Demo\PhpForms::class; }

    final public function tags(string|array $value): array { return \Sharp\List::wrap($value); }
}

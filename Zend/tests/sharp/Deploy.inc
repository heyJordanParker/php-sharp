<?php declare(strict_types=1); namespace Demo;

class Deploy
{
    public function __construct(private \Sharp\Environment $environment)
    {
    }

    final public function variable(string $name): ?string
    {
        return $this->environment->variable($name);
    }

    final public function arguments(): array
    {
        return $this->environment->arguments;
    }

    final public function target(): string
    {
        return $this->environment->arguments[1];
    }

    final public function folder(): string
    {
        return $this->environment->currentDirectory();
    }
}

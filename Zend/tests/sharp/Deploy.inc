<?php declare(strict_types=1); namespace Demo;

class Deploy
{
    public function __construct(private \Sharp\Environment $environment)
    {
    }

    public static function create(): \Demo\Deploy
    {
        return new \Demo\Deploy(new \Sharp\Environment());
    }

    public function variable(string $name): ?string
    {
        return $this->environment->variable($name);
    }

    public function arguments(): array
    {
        return $this->environment->arguments;
    }

    public function target(): string
    {
        return $this->environment->arguments[1];
    }

    public function folder(): string
    {
        return $this->environment->currentDirectory;
    }
}

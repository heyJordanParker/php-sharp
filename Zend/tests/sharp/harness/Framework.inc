<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP framework's base class, whose own code calls its methods by their declared names and forwards the rest. */
abstract class Framework
{
    public function run(): string
    {
        return $this->handle() . ' ' . $this->describeIt();
    }

    public function describeIt(): string
    {
        return 'framework';
    }

    abstract protected function handle(): string;

    /** @param list<mixed> $arguments */
    public function __call(string $name, array $arguments): string
    {
        return "forwarded $name";
    }
}

<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class with each magic method the engine calls by its own name, for a PHP# subclass to inherit. */
class Magic
{
    public string $label = 'magic';

    public function __construct()
    {
        echo "__construct\n";
    }

    public function __destruct()
    {
        echo "__destruct\n";
    }

    public function __clone(): void
    {
        echo "__clone\n";
    }

    public function __get(string $name): string
    {
        return "__get $name";
    }

    /** @param list<mixed> $arguments */
    public function __call(string $name, array $arguments): string
    {
        return "__call $name";
    }

    public function __invoke(): string
    {
        return '__invoke';
    }

    public function __toString(): string
    {
        return '__toString';
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        return ['label' => $this->label];
    }

    /** @param array<string, string> $data */
    public function __unserialize(array $data): void
    {
        $this->label = "{$data['label']} again";
    }
}

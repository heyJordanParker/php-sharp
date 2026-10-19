<?php declare(strict_types=1); namespace Demo;

class Task
{
    public readonly \Demo\Priority $priority;

    public function __construct(int $level)
    {
        $this->priority = \Demo\Priority::from($level);
    }

    final public function rank(): int
    {
        return $this->priority->value;
    }

    final public static function parse(int $level): ?\Demo\Priority
    {
        return \Demo\Priority::tryFrom($level);
    }
}

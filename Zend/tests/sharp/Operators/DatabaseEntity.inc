<?php declare(strict_types=1); namespace Billing;

abstract class DatabaseEntity
{
    public readonly int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public static function op_Equality(?\Billing\DatabaseEntity $a, ?\Billing\DatabaseEntity $b): bool { if ($a === null || $b === null) { return $a === $b; } return $a->id === $b->id; }

    public function hash(): int { return $this->id; }
}

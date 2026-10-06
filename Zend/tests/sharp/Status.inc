<?php declare(strict_types=1); namespace Demo;

use Lib\Field;

enum Status: string implements HasLabel
{
    case Active = "a";
    #[Field("Paused by support")]
    case Paused = "p";
    case Closed = "c";

    public const \Demo\Status Default = \Demo\Status::Active;

    public function label(): string
    {
        return "{$this->name} ({$this->value})";
    }

    public function isActive(): bool
    {
        return $this === \Demo\Status::Active;
    }

    public static function parse(string $value): \Demo\Status
    {
        return \Demo\Status::tryFrom($value) ?? \Demo\Status::from("a");
    }
}

<?php declare(strict_types=1); namespace Demo;

use Lib\Field;

enum Stage: string implements HasLabel
{
    case Active = "a";
    #[Field("Paused by support")]
    case Paused = "p";
    case Closed = "c";

    public const \Demo\Stage Default = \Demo\Stage::Active;

    public function label(): string
    {
        return "{$this->name} ({$this->value})";
    }

    public function isActive(): bool
    {
        return $this === \Demo\Stage::Active;
    }

    public static function parse(string $value): \Demo\Stage
    {
        return \Demo\Stage::tryFrom($value) ?? \Demo\Stage::from("a");
    }
}

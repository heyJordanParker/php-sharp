<?php declare(strict_types=1); namespace Demo;

enum Suit
{
    case Hearts;
    case Spades;

    public function label(): string
    {
        return \strtolower($this->name);
    }

    public static function size(): int
    {
        return \count(\Demo\Suit::cases());
    }
}

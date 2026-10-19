<?php declare(strict_types=1); namespace Demo;

class Shipment
{
    public private(set) \Demo\Stage $status;

    public function __construct(\Demo\Stage $status = \Demo\Stage::Active)
    {
        $this->status = $status;
    }

    final public function code(): string
    {
        return $this->status->value;
    }

    final public function state(): string
    {
        return $this->status->name;
    }

    final public function describe(): string
    {
        return $this->status->label();
    }

    final public function close(): \Demo\Stage
    {
        $this->status = \Demo\Stage::from("c");
        return $this->status;
    }

    final public function pause(): \Demo\Stage
    {
        $this->status = \Demo\Stage::Paused;
        return $this->status;
    }

    final public static function activeLabel(): string
    {
        return ((object) \Demo\Stage::Active)->label();
    }

    final public static function fallback(): \Demo\Stage
    {
        return \Demo\Stage::Default;
    }

    final public static function find(string $code): ?\Demo\Stage
    {
        return \Demo\Stage::tryFrom($code);
    }

    final public static function kinds(): int
    {
        return \count(\Demo\Stage::cases());
    }
}

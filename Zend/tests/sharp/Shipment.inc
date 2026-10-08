<?php declare(strict_types=1); namespace Demo;

class Shipment
{
    public private(set) \Demo\Stage $status;

    public function __construct(\Demo\Stage $status = \Demo\Stage::Active)
    {
        $this->status = $status;
    }

    public function code(): string
    {
        return $this->status->value;
    }

    public function state(): string
    {
        return $this->status->name;
    }

    public function describe(): string
    {
        return $this->status->label();
    }

    public function close(): \Demo\Stage
    {
        $this->status = \Demo\Stage::from("c");
        return $this->status;
    }

    public function pause(): \Demo\Stage
    {
        $this->status = \Demo\Stage::Paused;
        return $this->status;
    }

    public static function activeLabel(): string
    {
        return ((object) \Demo\Stage::Active)->label();
    }

    public static function fallback(): \Demo\Stage
    {
        return \Demo\Stage::Default;
    }

    public static function find(string $code): ?\Demo\Stage
    {
        return \Demo\Stage::tryFrom($code);
    }

    public static function kinds(): int
    {
        return \count(\Demo\Stage::cases());
    }
}

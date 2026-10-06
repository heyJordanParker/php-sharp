<?php declare(strict_types=1); namespace Demo;

class Shipment
{
    public private(set) \Demo\Status $status;

    public function __construct(\Demo\Status $status = \Demo\Status::Active)
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

    public function close(): \Demo\Status
    {
        $this->status = \Demo\Status::from("c");
        return $this->status;
    }

    public function pause(): \Demo\Status
    {
        $this->status = \Demo\Status::Paused;
        return $this->status;
    }

    public static function activeLabel(): string
    {
        return \Demo\Status::Active->label();
    }

    public static function fallback(): \Demo\Status
    {
        return \Demo\Status::Default;
    }

    public static function find(string $code): ?\Demo\Status
    {
        return \Demo\Status::tryFrom($code);
    }

    public static function kinds(): int
    {
        return \count(\Demo\Status::cases());
    }
}

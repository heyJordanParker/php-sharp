<?php declare(strict_types=1); namespace Demo;

class Shipment
{
    public function kind(): string { return $this::class; }

    public static function classOf(\Demo\Shipment $shipment): string { return $shipment::class; }

    public static function heldClass(\Demo\Shipment $shipment): string
    {
        $held = $shipment;
        return $held::class;
    }
}

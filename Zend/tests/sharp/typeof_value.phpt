--TEST--
PHP# typeof(value) is the runtime class of this, a parameter or a local, a subclass's included
--FILE--
<?php

require __DIR__ . '/Shipment.sharp';

class ExpressShipment extends Demo\Shipment
{
}

$plain = new Demo\Shipment();
$express = new ExpressShipment();

var_dump($plain->kind());
var_dump($express->kind());
var_dump(Demo\Shipment::classOf($plain));
var_dump(Demo\Shipment::classOf($express));
var_dump(Demo\Shipment::heldClass($express));
?>
--EXPECT--
string(13) "Demo\Shipment"
string(15) "ExpressShipment"
string(13) "Demo\Shipment"
string(15) "ExpressShipment"
string(15) "ExpressShipment"

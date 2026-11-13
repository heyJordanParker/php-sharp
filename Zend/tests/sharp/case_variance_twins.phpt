--TEST--
A .sharp file names a class as written, spec section 24, so a plain PHP override that takes `Twins\Parcel\BOX` where its PHP# parent takes `Twins\Parcel\Box` takes another class, and PHP refuses the signature
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
fixture('TypeTwins');

eval('final class Courier extends Twins\Shipper
{
    public function ship(Twins\Parcel\BOX $parcel): string
    {
        return "courier";
    }
}');
?>
--EXPECTF--
Fatal error: Declaration of Courier::ship(Twins\Parcel\BOX $parcel): string must be compatible with Twins\Shipper::ship(Twins\Parcel\Box $parcel): string in %s on line %d

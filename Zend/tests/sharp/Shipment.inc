<?php declare(strict_types=1); namespace Store;

use Lib\Model;

class Shipment extends Model
{
    public Address $destination
    {
        get => $this->getAttribute("destination");
        set { $this->setAttribute("destination", $value); }
    }
}

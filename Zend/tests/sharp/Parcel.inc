<?php declare(strict_types=1); namespace Store;

use Lib\Row;

class Parcel extends Row
{
    public ?Address $destination
    {
        get => (${'as#1'} = $this->getAttribute("destination")) instanceof \Store\Address ? ${'as#1'} : null;
        set { $this->setAttribute("destination", $value); }
    }
}

<?php declare(strict_types=1); namespace Store;

use Lib\Words;

class Address
{
    protected string $region { get => \mb_strtoupper($this->region, 'UTF-8'); }
    public string $city { set { $this->city = Words::capitalized(\trim($value)); } }

    public function __construct(string $city, string $region)
    {
        $this->city = $city;
        $this->region = $region;
    }

    public function label(): string
    {
        return "{$this->city}, {$this->region}";
    }
}

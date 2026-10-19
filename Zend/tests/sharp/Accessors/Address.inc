<?php declare(strict_types=1); namespace Store;

class Address
{
    protected string $region { get => \mb_strtoupper($this->region, 'UTF-8'); }
    public string $city { set { $this->city = \ucfirst(\trim($value)); } }

    public function __construct(string $city, string $region)
    {
        $this->city = $city;
        $this->region = $region;
    }

    final public function label(): string
    {
        return \sprintf("%s, %s", $this->city, $this->region);
    }
}

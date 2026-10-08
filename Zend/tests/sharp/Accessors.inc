<?php declare(strict_types=1); namespace Store;

use InvalidArgumentException;

class Product
{
    public string $sku = "";
    public string $name = "" { get => $this->name; set { $this->name = \trim($value); } }
    public int $price = 0
    {

        set
        {
            if ($value < 0) {
                throw new InvalidArgumentException("A price is never negative.");
            }

            $this->price = $value;
        }
    }
    public private(set) int $stock = 0 { set { $this->stock = \max(0, $value); } }
    public array $tags = []
    {
        get => $this->tags;
        set
        {
            $this->tags = [];
            foreach ($value as $tag) {
                $this->tags->add(\strtolower($tag)); // PHP# makes the array a Sharp\Collection
            }
        }
    }
    public private(set) array $ratings = [];
    public int $rating = 0 { get => $this->rating; set { $this->ratings->add($value); } }

    public function tag(string $tag): void
    {
        $this->tags->add($tag); // PHP# makes the array a Sharp\Collection
    }

    public function restock(int $count): void
    {
        $this->stock = $this->stock + $count;
    }
}

class Address
{
    protected string $region { get => \strtoupper($this->region); }

    public function __construct(public string $city { set { $this->city = \ucfirst(\trim($value)); } }, string $region)
    {
        $this->region = $region;
    }

    public function label(): string
    {
        return \sprintf("%s, %s", $this->city, $this->region);
    }
}

<?php declare(strict_types=1); namespace Store;

use InvalidArgumentException;

class Product
{
    public string $sku = "";
    public string $name = "" { get => $this->name; set { $this->name = \mb_trim($value); } }
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
    public private(set) int $stock = 0 { set { $this->stock = \Sharp\Math::max(0, $value); } }
    public array $tags = []
    {
        get => $this->tags;
        set
        {
            $this->tags = [];
            foreach ($value as $tag) {
                \Sharp\Internal\add($this->tags, \mb_strtolower($tag, 'UTF-8'));
            }
        }
    }
    public private(set) array $ratings = [];
    public int $rating = 0 { get => $this->rating; set { \Sharp\Internal\add($this->ratings, $value); } }

    public function tag(string $tag): void
    {
        [${'receiver#1'} = $this->tags, ${'kept#2'} = ${'receiver#1'}, \Sharp\Internal\add(${'receiver#1'}, $tag), ${'receiver#1'} === ${'kept#2'} || ($this->tags = ${'receiver#1'})][2];
    }

    public function restock(int $count): void
    {
        $this->stock = $this->stock + $count;
    }
}

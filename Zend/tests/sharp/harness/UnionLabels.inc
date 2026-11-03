<?php declare(strict_types=1);

namespace Demo\Labels;

// The plain PHP parts of the union receivers extension_union_receiver.phpt calls an extension member on.
class PlainOrder
{
    public function label(): string
    {
        return 'plain order';
    }
}

trait HasLabel
{
    public function label(): string
    {
        return 'trait';
    }
}

class PhpOrder
{
    use HasLabel;
}

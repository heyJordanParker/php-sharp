<?php

declare(strict_types=1);

namespace App;

// PHP narrows a property `instanceof` tests, and PHP# never narrows a property with a `get` accessor, since each read
// runs the accessor (spec section 21). PHP# narrows a stored property as PHP does, so its read converts.
final class Canvas
{
    public ?Shape $shape {
        get => $this->stored;
    }

    public function __construct(private ?Shape $stored = null)
    {
    }

    public function circle(): ?Circle
    {
        return $this->shape instanceof Circle ? $this->shape : null;
    }

    public function storedCircle(): ?Circle
    {
        return $this->stored instanceof Circle ? $this->stored : null;
    }
}

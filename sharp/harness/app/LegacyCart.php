<?php

namespace App;

final class LegacyCart
{
    /** @return list<Line> */
    public function lines(): array
    {
        return [new Line()];
    }
}

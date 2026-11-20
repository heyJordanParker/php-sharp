<?php

declare(strict_types=1);

namespace Tests\Shop;

use App\Shop\Catalog;
use App\Shop\Till;
use PHPUnit\Framework\TestCase;

// A PHP# class built by a plain PHP test reads the `Map` another PHP# class returns as the `Map` the method declares. A
// PHP# class has no test double, spec section 9, so the test builds the real `Catalog`.
final class TillTest extends TestCase
{
    public function testReadsTheMapTheCatalogReturns(): void
    {
        $till = new Till(new Catalog());

        $this->assertSame(3, $till->price('tea'));
        $this->assertSame(3, $till->heldPrice('tea'));
        $this->assertSame(0, $till->price('jam'));
    }
}

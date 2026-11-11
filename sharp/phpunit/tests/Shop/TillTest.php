<?php

declare(strict_types=1);

namespace Tests\Shop;

use App\Shop\Catalog;
use App\Shop\Till;
use PHPUnit\Framework\TestCase;

// A mock of a PHP# class returns its `Map` as the PHP array a plain PHP caller gets, and a PHP# caller reads it as the
// `Map` the method declares.
final class TillTest extends TestCase
{
    public function testReadsTheMapAMockReturns(): void
    {
        $catalog = $this->createMock(Catalog::class);
        $catalog->method('prices')->willReturn(['tea' => 5]);
        $till = new Till($catalog);

        $this->assertSame(5, $till->price('tea'));
        $this->assertSame(5, $till->heldPrice('tea'));
        $this->assertSame(0, $till->price('jam'));
    }
}

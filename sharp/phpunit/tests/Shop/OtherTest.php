<?php

declare(strict_types=1);

namespace Tests\Shop;

use App\Shop\Checkout;
use PHPUnit\Framework\TestCase;
use Tests\Probe;

// A test whose file declares no test module, run after CheckoutTest: it gets the base modules.
final class OtherTest extends TestCase
{
    public function testUsesTheBaseModules(): void
    {
        $this->assertSame('smtp', Probe::mailer());
    }

    // A PHP# object is built only through its constructor, spec section 9, and the double PHPUnit generates skips it.
    public function testRefusesADoubleOfAPhpSharpClass(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("App.Shop.Checkout is built only through its constructor, which this creation doesn't run");

        $this->createMock(Checkout::class);
    }
}

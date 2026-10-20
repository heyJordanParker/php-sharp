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

    // `usedMailer` is not `virtual`, so mago refuses a class that replaces it, and the double PHPUnit generates while
    // the test runs replaces it.
    public function testDoublesAMethodThatIsNotVirtual(): void
    {
        $checkout = $this->createMock(Checkout::class);
        $checkout->expects($this->never())->method('usedMailer');

        $this->assertInstanceOf(Checkout::class, $checkout);
    }
}

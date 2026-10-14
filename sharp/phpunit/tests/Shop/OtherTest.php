<?php

declare(strict_types=1);

namespace Tests\Shop;

use PHPUnit\Framework\TestCase;
use Tests\Probe;

// A test whose file declares no test module, run after CheckoutTest: it gets the base modules.
final class OtherTest extends TestCase
{
    public function testUsesTheBaseModules(): void
    {
        $this->assertSame('smtp', Probe::mailer());
    }
}

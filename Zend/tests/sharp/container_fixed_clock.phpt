--TEST--
A test module binds Clock to new Clock.fixed(…) where the standard library's module binds the real one, so a class that takes a Clock reads the fixed instant from now() while a test of the test file's class runs, and the real time before and after it
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('container-fixed-clock', [
    'app/Billing/Module.sharp' => <<<'SHARP'
namespace App.Billing;

module
{
    transient Invoice = Invoice;
}
SHARP,
    'app/Billing/Invoice.sharp' => <<<'SHARP'
namespace App.Billing;

import Sharp.Time.Clock;

public class Invoice
{
    public Invoice(private Clock clock) { }

    public string issuedAt() => this.clock.now().toString();
}
SHARP,
    'tests/Billing/InvoiceTest.sharp' => <<<'SHARP'
namespace App.Billing;

import Sharp.Time.Clock;
import Sharp.Time.Instant;

module : App.Billing
{
    singleton Clock = new Clock.fixed(Instant.parse("2026-10-22T18:30:00Z"));
}

public class InvoiceTest
{
}
SHARP,
]);
require "$root/tests/Billing/InvoiceTest.sharp";
$container = new Sharp\Container();

$isNow = static fn (string $issued): string => var_export(abs(Sharp\Time\Instant::parse($issued)->epochSeconds - time()) <= 1, true);
echo "before a test, the real time: ", $isNow($container->get('App\Billing\Invoice')->issuedAt()), "\n";
Sharp\Internal\startTest('App\Billing\InvoiceTest');
echo "a test of the test file's class: ", $container->get('App\Billing\Invoice')->issuedAt(), "\n";
echo "a second read in the test: ", $container->get('App\Billing\Invoice')->issuedAt(), "\n";
Sharp\Internal\endTest();
echo "after the test, the real time: ", $isNow($container->get('App\Billing\Invoice')->issuedAt()), "\n";
?>
--EXPECT--
before a test, the real time: true
a test of the test file's class: 2026-10-22T18:30:00Z
a second read in the test: 2026-10-22T18:30:00Z
after the test, the real time: true

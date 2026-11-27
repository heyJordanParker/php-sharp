--TEST--
A test class takes its services in its main constructor, filled from its file's test module over the base modules at the start of each test, so each test's are new, in PHPUnit's process and in a child one, and a PHPUnit extension takes its services in its constructor, filled once from the base modules when PHPUnit creates it with no argument
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('container-phpunit-services', [
    'app/Billing/Mailer.sharp' => "namespace App.Billing;\n\npublic interface Mailer\n{\n    void send(string to);\n}\n",
    'app/Billing/NullMailer.sharp' => "namespace App.Billing;\n\npublic class NullMailer : Mailer\n{\n    public void send(string to) { }\n}\n",
    'app/Billing/FakeMailer.sharp' => "namespace App.Billing;\n\npublic class FakeMailer : Mailer\n{\n    public List<string> sent { get; private set; } = [];\n\n    public void send(string to)\n    {\n        this.sent.add(to);\n    }\n}\n",
    'app/Billing/Checkout.sharp' => "namespace App.Billing;\n\npublic class Checkout\n{\n    public Checkout(private Mailer mailer) { }\n\n    public void pay(string to)\n    {\n        this.mailer.send(to);\n    }\n}\n",
    'app/Billing/Module.sharp' => "namespace App.Billing;\n\nmodule\n{\n    singleton Mailer = NullMailer;\n    transient Checkout = Checkout;\n}\n",
    'tests/Billing/CheckoutTest.sharp' => <<<'SHARP'
namespace App.Billing;

import PHPUnit.Framework.TestCase;

module : App.Billing
{
    singleton FakeMailer = FakeMailer;
    singleton Mailer(FakeMailer fake) = fake;
}

public class CheckoutTest : TestCase
{
    public CheckoutTest(private Checkout checkout, private FakeMailer mailer) { }

    public string sendsTheReceipt()
    {
        this.checkout.pay("ada@example.com");

        return this.sent();
    }

    public string sendsNothing() => this.sent();

    private string sent() => ["sent:", ...this.mailer.sent].join(" ");
}
SHARP,
    'tests/Support/ProgressReporter.sharp' => <<<'SHARP'
namespace App.Support;

import PHPUnit.Runner.Extension.Extension;
import Sharp.IO.Console;

public class ProgressReporter : Extension
{
    public ProgressReporter(private Console console) { }

    public void bootstrap()
    {
        this.console.writeLine("progress: the run starts");
    }
}
SHARP,
]);
require "$root/tests/Billing/CheckoutTest.sharp";
require "$root/tests/Support/ProgressReporter.sharp";

// PHPUnit creates each extension once, with no argument, before the first test.
(new App\Support\ProgressReporter())->bootstrap();

// PHPUnit creates a new test object for each test, with the test's name, and runs its Before and After methods
// around the test.
foreach (['sendsTheReceipt', 'sendsNothing'] as $name) {
    $test = new App\Billing\CheckoutTest($name);
    $test->__sharpStartTest();
    echo $test->$name(), "\n";
    $test->__sharpEndTest();
}

// PHPUnit's child process loads the test file again and runs the same methods around the test.
$child = <<<'PHP'
require $argv[1] . '/Container.inc';
container_load($argv[2]);
require $argv[2] . '/tests/Billing/CheckoutTest.sharp';
$test = new App\Billing\CheckoutTest('sendsTheReceipt');
$test->__sharpStartTest();
echo "in a child process: ", $test->sendsTheReceipt(), "\n";
$test->__sharpEndTest();
PHP;
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($child) . ' ' . escapeshellarg(__DIR__) . ' ' . escapeshellarg($root));
?>
--EXPECT--
progress: the run starts
sent: ada@example.com
sent:
in a child process: sent: ada@example.com

--TEST--
A class whose law Lean proves runs, and once its proof file holds a gap, the checker refuses it and the engine does not run it
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('laws', ['composer.json' => "{\"autoload\": {\"psr-4\": {\"App\\\\\": \"app/\"}}}\n"]);
mkdir("$root/app/Shared", recursive: true);
file_put_contents("$root/app/Shared/Money.sharp", <<<'SHARP'
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
}

SHARP);
file_put_contents("$root/app/Shared/Payment.sharp", <<<'SHARP'
namespace App.Shared;

public enum Payment
{
    case Paid(int cents);
    case Pending;

    public static bool settles(Payment payment) => true;

    law aPaymentSettles(int cents) => Payment.settles(Payment.Paid(cents)) && Payment.settles(Payment.Pending);
}

SHARP);
run_mago($root);
$settings = ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'];
refusal("$root/app/Shared/Payment.sharp", $settings);
refusal("$root/app/Shared/Money.sharp", $settings);

file_put_contents("$root/app/Shared/Money.lean", <<<'LEAN'
import Code.App.Shared.Money
open Sharp

theorem addKeepsCurrency : App.Shared.Money.addKeepsCurrency := by
  sorry

LEAN);
refusal("$root/app/Shared/Money.sharp", $settings);
remove_project($root);
?>
--EXPECT--
ran
ran
CompileError: app/Shared/Money.sharp has 1 error:
line 8: Law addKeepsCurrency is not proven: app/Shared/Money.lean line 4 is a gap (sorry). Write the proof there, or change the law.

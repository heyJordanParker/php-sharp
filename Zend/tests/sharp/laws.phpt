--TEST--
A class whose laws the checker proves runs, and once an edit leaves a case of a law's proof open, the engine refuses it with the case and the goal still to show, as mago compile does
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
    public Money times(int n) => n <= 0 ? new Money(0, this.currency) : this.add(this.times(n - 1));

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
    law timesKeepsCurrency(Money m, int n) => m.times(n).currency == m.currency
    {
        if (n <= 0) {
            return;
        }
        Money.timesKeepsCurrency(m, n - 1);
        Money.addKeepsCurrency(m, m.times(n - 1));
    }
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

file_put_contents(
    "$root/app/Shared/Money.sharp",
    str_replace(
        "        Money.timesKeepsCurrency(m, n - 1);\n        Money.addKeepsCurrency(m, m.times(n - 1));\n",
        '',
        file_get_contents("$root/app/Shared/Money.sharp"),
    ),
);
refusal("$root/app/Shared/Money.sharp", $settings);
remove_project($root);
?>
--EXPECT--
ran
ran
CompileError: app/Shared/Money.sharp has 1 error:
line 10: law timesKeepsCurrency is not proven
  when n > 0, still to show: m.add(m.times(n - 1)).currency == m.currency
  help: the law for a smaller n is the call Money.timesKeepsCurrency(m, n - 1)

--TEST--
PHP# extension members run as static calls with the receiver first: name.trim() runs as trim($name) without the library loaded, and on an open class a plain PHP subclass's own public method or property runs instead, while a protected one, __call and __get leave the extension to run, as does a receiver that is no object
--FILE--
<?php
namespace Demo;

use Lib\Wallet;

require __DIR__ . '/harness/Wallet.inc';
require __DIR__ . '/WalletExtensions.sharp';
require __DIR__ . '/WalletReport.sharp';

// The checker never saw these subclasses, so the engine finds their members when the code runs.
class AuditedWallet extends Wallet
{
    public bool $isEmpty = true;

    public function total(int $extra): int
    {
        return 100 + $extra;
    }
}

class HookedWallet extends Wallet
{
    private bool $flag = true;

    public bool $isEmpty { &get => $this->flag; }
}

class HiddenWallet extends Wallet
{
    protected function total(int $extra): int
    {
        return -1;
    }
}

class MagicWallet extends Wallet
{
    public function __call(string $name, array $arguments): mixed
    {
        return 'magic';
    }

    public function __get(string $name): mixed
    {
        return 'magic';
    }
}

// Each run repeats, so the JIT variants compile the calls before the second run.
for ($run = 0; $run < 2; $run++) {
    foreach ([new Wallet(5), new Wallet(0), new AuditedWallet(5), new HookedWallet(5), new HiddenWallet(5), new MagicWallet(0)] as $wallet) {
        echo get_class($wallet), ': ', WalletReport::describe($wallet, '  ada '), "\n";
    }
    echo WalletReport::label('ada'), ' ', WalletReport::label(new Wallet(1)), "\n";
}
var_dump(class_exists('Sharp\Text\Text', false));
?>
--EXPECT--
Lib\Wallet: ada! 7 8 full
Lib\Wallet: ada! 2 3 empty
Demo\AuditedWallet: ada! 102 103 empty
Demo\HookedWallet: ada! 7 8 empty
Demo\HiddenWallet: ada! 7 8 full
Demo\MagicWallet: ada! 2 3 empty
ada wallet
Lib\Wallet: ada! 7 8 full
Lib\Wallet: ada! 2 3 empty
Demo\AuditedWallet: ada! 102 103 empty
Demo\HookedWallet: ada! 7 8 empty
Demo\HiddenWallet: ada! 7 8 full
Demo\MagicWallet: ada! 2 3 empty
ada wallet
bool(false)

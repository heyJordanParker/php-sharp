--TEST--
PHP# extension members run as static calls with the receiver first: name.trim() runs as trim($name) without the library loaded, the type the code shows chooses the member, so a value shown as a Wallet runs the extension though its object has its own method, property or hook of that name, while a value shown as an AuditedWallet runs AuditedWallet's own members, and a receiver that is no object runs the extension too. A call through the extension's class runs the same method, and of two extensions that fit, the one with the most specific receiver runs
--FILE--
<?php
namespace Demo;

use Lib\AuditedWallet;
use Lib\Wallet;

require __DIR__ . '/harness/Wallet.inc';
require __DIR__ . '/WalletExtensions.sharp';
require __DIR__ . '/AuditExtensions.sharp';
require __DIR__ . '/WalletReport.sharp';

// The checker never saw this subclass, and the extension runs on it all the same.
class HookedWallet extends Wallet
{
    private bool $flag = true;

    public bool $isEmpty { &get => $this->flag; }
}

// Each run repeats, so the JIT variants compile the calls before the second run.
for ($run = 0; $run < 2; $run++) {
    foreach ([new Wallet(5), new Wallet(0), new AuditedWallet(5), new HookedWallet(5)] as $wallet) {
        echo get_class($wallet), ': ', WalletReport::describe($wallet, '  ada '), "\n";
    }
    echo WalletReport::audited(new AuditedWallet(5)), "\n";
    echo WalletReport::label('ada'), ' ', WalletReport::label(new Wallet(1)), "\n";
    echo WalletReport::direct(new Wallet(5)), ' ', WalletReport::direct(new Wallet(0)), "\n";
    echo WalletReport::ranks(new AuditedWallet(5), new AuditedWallet(5)), "\n";
}
var_dump(class_exists('Sharp\Text\Text', false));
?>
--EXPECT--
Lib\Wallet: ada! 7 8 full
Lib\Wallet: ada! 2 3 empty
Lib\AuditedWallet: ada! 7 8 full
Demo\HookedWallet: ada! 7 8 full
102 empty
ada wallet
9 full 4 empty
wallet audited
Lib\Wallet: ada! 7 8 full
Lib\Wallet: ada! 2 3 empty
Lib\AuditedWallet: ada! 7 8 full
Demo\HookedWallet: ada! 7 8 full
102 empty
ada wallet
9 full 4 empty
wallet audited
bool(false)

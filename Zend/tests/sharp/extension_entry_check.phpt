--TEST--
Plain PHP calling an extension member through its class gets a TypeError for an argument that is not of the member's PHP# type, a List with a wrong element among them, as a call of a method does
--FILE--
<?php
namespace Demo;

use Demo\Wallets\WalletExtensions;
use Lib\Wallet;

require __DIR__ . '/harness/Wallet.inc';
require __DIR__ . '/WalletExtensions.sharp';

// Each run repeats, so the JIT variants compile the calls before the second run.
for ($run = 0; $run < 2; $run++) {
    echo WalletExtensions::counted(new Wallet(5), [1, 2]), "\n";
    try {
        WalletExtensions::counted(new Wallet(5), [1, 'x']);
    } catch (\TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECTF--
7
Demo\Wallets\WalletExtensions::counted(): Argument #2 ($amounts) must be of type List<int>, array given: [1] expected int, got string 'x', called in %s on line %d
7
Demo\Wallets\WalletExtensions::counted(): Argument #2 ($amounts) must be of type List<int>, array given: [1] expected int, got string 'x', called in %s on line %d

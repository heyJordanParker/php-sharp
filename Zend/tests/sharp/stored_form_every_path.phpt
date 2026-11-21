--TEST--
A class's stored form holds every stored field on every path that reads or writes it, spec section 10: Json.decode, parse, Query.decode and PHP's serialize() and unserialize() keep a property with a private set and a machine's case, and a get-only property reads back its stored value
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use DataInOut\Accounts;

$text = '{"id":7,"balance":"12.50","status":"frozen"}';
$account = Accounts::decoded($text);
echo 'Json.decode: ', Accounts::encoded($account), "\n";
echo 'parse: ', Accounts::encoded(Accounts::parsed(['id' => 7, 'balance' => '12.50', 'status' => 'frozen'])), "\n";
echo 'Query.decode: ', Accounts::encoded(Accounts::queried('id=7&balance=12.50&status=frozen')), "\n";
$stored = serialize($account);
echo 'serialize: ', $stored, "\n";
echo 'unserialize: ', Accounts::encoded(unserialize($stored)), "\n";
var_dump(unserialize($stored) == $account);
try {
    Accounts::decoded('{"id":7,"status":"closed"}');
} catch (Sharp\InvalidFields $e) {
    echo 'a value the machine has no case of: ', $e->getMessage(), "\n";
}
echo 'a get-only property: ', Accounts::statement('{"id":3,"version":2}'), ' ', Accounts::statement('{"id":3}'), "\n";
?>
--EXPECT--
Json.decode: {"id":7,"balance":"12.50","status":"frozen"}
parse: {"id":7,"balance":"12.50","status":"frozen"}
Query.decode: {"id":7,"balance":"12.50","status":"frozen"}
serialize: O:17:"DataInOut\Account":3:{s:2:"id";i:7;s:7:"balance";O:15:"DataInOut\Money":1:{s:5:"value";s:5:"12.50";}s:6:"status";O:30:"DataInOut\AccountStatus\Frozen":0:{}}
unserialize: {"id":7,"balance":"12.50","status":"frozen"}
bool(true)
a value the machine has no case of: status: expected DataInOut.AccountStatus, got string 'closed'
a get-only property: {"id":3,"version":2} {"id":3,"version":1}

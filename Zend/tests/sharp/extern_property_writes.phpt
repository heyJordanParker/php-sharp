--TEST--
An extern property with set writes through its plain PHP parent's __set and reads through __get, under the key [Key] names, and a value of another type throws TypeError where it enters PHP#
--FILE--
<?php
require __DIR__ . '/harness/Columns.inc';
require __DIR__ . '/vendor/autoload.php';

$account = new ExternProperties\Account();
$account->open('ada@example.com');
$account->charge(250);
echo $account->summary(), "\n";
var_dump(property_exists($account, 'email'), property_exists($account, 'totalCents'), $account->columns());
$account->email = 5;
try {
    $account->summary();
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
__set email
__set total_cents
__set total_cents
ada@example.com: 250
bool(false)
bool(false)
array(2) {
  ["email"]=>
  string(15) "ada@example.com"
  ["total_cents"]=>
  int(250)
}
__set email
TypeError: ExternProperties.Account.email: Return value must be of type string, int returned

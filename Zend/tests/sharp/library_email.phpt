--TEST--
A PHP# class calls Sharp.Net.MailAddress.isValid, which gives false, never an error, for an address that is not one
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(Demo\EmailCalls::isValid('ann@example.com'), Demo\EmailCalls::isValid('ann@'), Demo\EmailCalls::isValid(''));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)

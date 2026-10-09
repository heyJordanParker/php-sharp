--TEST--
A PHP# class calls Sharp.Net.Email.isValid, which gives false, never an error, for an address that is not one
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Net/Email.sharp';
require __DIR__ . '/EmailCalls.sharp';

var_dump(Demo\EmailCalls::isValid('ann@example.com'), Demo\EmailCalls::isValid('ann@'), Demo\EmailCalls::isValid(''));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)

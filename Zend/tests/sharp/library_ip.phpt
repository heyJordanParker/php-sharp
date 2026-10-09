--TEST--
A PHP# class calls every method of Sharp.Net.Ip, and Ip.toBinary throws ValueError on an address that is not one
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Net/Ip.sharp';
require __DIR__ . '/IpCalls.sharp';

use Demo\IpCalls;

var_dump(IpCalls::isValid('192.168.0.1'), IpCalls::isValid('::1'), IpCalls::isValid('300.1.1.1'));
var_dump(bin2hex(IpCalls::toBinary('127.0.0.1')), bin2hex(IpCalls::toBinary('::1')));

try {
    IpCalls::toBinary('nope');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
string(8) "7f000001"
string(32) "00000000000000000000000000000001"
ValueError: Ip.toBinary: the address is not an IPv4 or IPv6 address

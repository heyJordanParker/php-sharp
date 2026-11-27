--TEST--
PHP# IpAddress is an IPv4 or IPv6 address, as .NET's IPAddress: parse and tryParse read either family, toString writes RFC 5952's form, == compares the address whatever text wrote it, and isLoopback and isPrivate read 127.0.0.0/8 and ::1, and RFC 1918's ranges and fc00::/7
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\NetworkQueries;

echo json_encode(array_map(NetworkQueries::normalized(...), ['192.168.0.1', '0:0:0:0:0:0:0:1', '2001:DB8:0:0:0:0:0:1', '::ffff:10.0.0.1', '256.1.1.1', '1.2.3', 'example.com', '', ' 10.0.0.1'])), "\n";
echo NetworkQueries::family('10.0.0.1')->name, ' ', NetworkQueries::family('fe80::1')->name, "\n";
foreach (['127.0.0.1', '127.255.0.9', '::1', '10.1.2.3', '172.16.0.1', '172.31.255.255', '172.32.0.1', '192.168.10.10', '192.169.0.1', '8.8.8.8', 'fc00::1', 'fd12:3456::1', 'fe80::1', '2001:db8::1'] as $text) {
    echo $text, ' ', json_encode(NetworkQueries::facts($text)), "\n";
}
var_dump(NetworkQueries::same('::1', '0:0:0:0:0:0:0:1'), NetworkQueries::same('10.0.0.1', '10.0.0.2'), NetworkQueries::same('::ffff:10.0.0.1', '10.0.0.1'));
try {
    NetworkQueries::parsed('localhost');
} catch (ValueError|LogicException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
["192.168.0.1","::1","2001:db8::1","::ffff:10.0.0.1",null,null,null,null,null]
V4 V6
127.0.0.1 [true,false]
127.255.0.9 [true,false]
::1 [true,false]
10.1.2.3 [false,true]
172.16.0.1 [false,true]
172.31.255.255 [false,true]
172.32.0.1 [false,false]
192.168.10.10 [false,true]
192.169.0.1 [false,false]
8.8.8.8 [false,false]
fc00::1 [false,true]
fd12:3456::1 [false,true]
fe80::1 [false,false]
2001:db8::1 [false,false]
bool(true)
bool(false)
bool(false)
LogicException: IpAddress.parse: "localhost" is not an IPv4 or IPv6 address

--TEST--
PHP# Dns gives the addresses the system resolver finds for a host, each an IpAddress, the hosts file's included, and throws for a host it cannot find or a name that is no host name
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

$dns = new Sharp\Net\SystemDns();
$addresses = $dns->addresses('localhost');
var_dump($addresses !== [] && array_filter($addresses, fn ($address) => !$address instanceof Sharp\Net\IpAddress) === []);
$local = array_map(fn (Sharp\Net\IpAddress $address) => $address->toString(), $addresses);
var_dump(in_array('127.0.0.1', $local, true) || in_array('::1', $local, true));
var_dump($local === array_values(array_unique($local)));

// RFC 6761 reserves `.invalid`, so no resolver finds it, online or offline.
try {
    $dns->addresses('nothing.invalid');
} catch (RuntimeException $e) {
    echo $e::class, ': ', str_starts_with($e->getMessage(), 'Dns.addresses: "nothing.invalid" cannot be resolved: ') ? 'names the host and the failure' : $e->getMessage(), "\n";
}

foreach (['not a host', '', 'shop..example.com'] as $host) {
    try {
        $dns->addresses($host);
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
RuntimeException: names the host and the failure
ValueError: Dns.addresses: "not a host" is not a valid host name
ValueError: Dns.addresses: "" is not a valid host name
ValueError: Dns.addresses: "shop..example.com" is not a valid host name

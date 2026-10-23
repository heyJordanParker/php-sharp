--TEST--
A Map's toList that runs past memory_limit ends the request with the fatal error, and its shutdown functions run, where the hard limit would end the process
--INI--
memory_limit=32M
--FILE--
<?php
// 262,143 entries take 9.5 MiB, and their toList 76 MiB more, past the hard limit of twice memory_limit.
$map = new \Sharp\Map();
for ($i = 0; $i < 262143; $i++) {
    $map[$i] = $i;
}
register_shutdown_function(static function (): void {
    echo "The shutdown function ran\n";
});
$map->toList();
echo "Not reached\n";
?>
--EXPECTF--
Fatal error: Allowed memory size of 33554432 bytes exhausted %S(tried to allocate %d bytes) in %s on line %d
The shutdown function ran

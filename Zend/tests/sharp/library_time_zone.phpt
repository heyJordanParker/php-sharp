--TEST--
A PHP# class calls Sharp.Time.TimeZone.names, which cannot fail
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Time/TimeZone.sharp';
require __DIR__ . '/TimeZoneCalls.sharp';

$names = Demo\TimeZoneCalls::names();
var_dump(array_is_list($names), $names[0], in_array('Europe/Sofia', $names, true), in_array('UTC', $names, true));
?>
--EXPECT--
bool(true)
string(14) "Africa/Abidjan"
bool(true)
bool(true)

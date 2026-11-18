--TEST--
TimeZone's fixedOffset gives the offset of every zone whose clocks never change, from the zone's rules: a fixed offset's name, as "+03:00", "UTC" and "Etc/GMT-3", and null for a zone the database gives any change of offset, as "Europe/Sofia" and "Asia/Kolkata", which last changed in 1945. A project's struct refuses any zone that never changes its clocks in its constructor through it, and a zone of either kind keeps its one field, its name, so Json writes only that, and still round-trips through toString, DateTime.parse and Json
--FILE--
<?php
require __DIR__ . '/attempt.inc';
require __DIR__ . '/vendor/autoload.php';

use HomeZones\Zones;

var_dump(Zones::offsetMinutes('+03:00'), Zones::offsetMinutes('-05:30'), Zones::offsetMinutes('+00:00'));
var_dump(Zones::offsetMinutes('UTC'), Zones::offsetMinutes('Etc/GMT-3'), Zones::offsetMinutes('Etc/GMT+5'));
var_dump(Zones::offsetMinutes('Europe/Sofia'), Zones::offsetMinutes('Asia/Kolkata'));
var_dump(Zones::home('Europe/Sofia'), Zones::home('Asia/Kolkata'));
attempt(fn () => Zones::home('+03:00'));
attempt(fn () => Zones::home('UTC'));
attempt(fn () => Zones::home('Etc/GMT-3'));
echo Zones::encoded('+03:00'), "\n";
var_dump(Zones::roundTrips('+03:00'), Zones::roundTrips('-05:30'), Zones::roundTrips('Europe/Sofia'), Zones::roundTrips('UTC'));
?>
--EXPECTF--
int(180)
int(-330)
int(0)
int(0)
int(180)
int(-300)
NULL
NULL
string(12) "Europe/Sofia"
string(12) "Asia/Kolkata"
ValueError: A home zone follows its region's daylight saving, and the clocks of "+03:00" stay 180 minutes from UTC. in HomeZone.sharp on line %d
ValueError: A home zone follows its region's daylight saving, and the clocks of "UTC" stay 0 minutes from UTC. in HomeZone.sharp on line %d
ValueError: A home zone follows its region's daylight saving, and the clocks of "Etc/GMT-3" stay 180 minutes from UTC. in HomeZone.sharp on line %d
{"name":"+03:00"}
bool(true)
bool(true)
bool(true)
bool(true)

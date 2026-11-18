--TEST--
A PHP# class calls every member of Sharp.Time.TimeZone: names lists the time zone database, utc and tryOf name a zone, an older name of the database included, a fixed offset from -18:00 to +18:00 names a zone, as Java's ZoneId.of and Kotlin's TimeZone.of take one, tryOf gives null for a name the database lacks, an offset outside the range or written another way, an abbreviation, a name in another case and a database name PHP reads as a fixed abbreviation without the database's rules, as CET, GMT, EST and UCT, which names leaves out, the constructor's ValueError for one names tryOf and for such a name asks for the region's name, and offsetAt follows a zone's clock changes and gives a fixed offset's own
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\TimeZoneCalls;

$names = TimeZoneCalls::names();
var_dump(array_is_list($names), $names[0], in_array('Europe/Sofia', $names, true), in_array('UTC', $names, true));
var_dump(TimeZoneCalls::utc(), TimeZoneCalls::named('Europe/Sofia'), TimeZoneCalls::named('Nowhere/City'), TimeZoneCalls::named(''));
var_dump(TimeZoneCalls::named('Europe/Kiev'), TimeZoneCalls::named('EEST'), TimeZoneCalls::named('europe/sofia'), TimeZoneCalls::named('utc'));
var_dump(TimeZoneCalls::named('+03:00'), TimeZoneCalls::named('-18:00'), TimeZoneCalls::named('+18:01'), TimeZoneCalls::named('+3'), TimeZoneCalls::named('+0300'));
try {
    TimeZoneCalls::offsetMinutes('+19:00', '2026-01-01T00:00:00Z');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
var_dump(TimeZoneCalls::named('CET'), TimeZoneCalls::named('GMT'), TimeZoneCalls::named('EST'), TimeZoneCalls::named('UCT'), TimeZoneCalls::named('Europe/Brussels'), TimeZoneCalls::named('Etc/UTC'), in_array('CET', $names, true));
try {
    TimeZoneCalls::offsetMinutes('CET', '2026-07-01T00:00:00Z');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
var_dump(TimeZoneCalls::offsetMinutes('Europe/Sofia', '2026-07-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('Europe/Sofia', '2026-12-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('Asia/Kathmandu', '2026-01-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('UTC', '2026-01-01T00:00:00Z'));
var_dump(TimeZoneCalls::offsetMinutes('+03:00', '2026-07-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('-05:30', '2026-01-01T00:00:00Z'));
?>
--EXPECT--
bool(true)
string(14) "Africa/Abidjan"
bool(true)
bool(true)
string(3) "UTC"
string(12) "Europe/Sofia"
NULL
NULL
string(11) "Europe/Kiev"
NULL
NULL
NULL
string(6) "+03:00"
string(6) "-18:00"
NULL
NULL
NULL
ValueError: `TimeZone` takes a name from the time zone database, such as "Europe/Sofia", or an offset from -18:00 to +18:00, such as "+03:00", and "+19:00" is neither. `TimeZone.tryOf` gives null for it instead.
NULL
NULL
NULL
NULL
string(15) "Europe/Brussels"
string(7) "Etc/UTC"
bool(false)
ValueError: `TimeZone` takes a zone with the time zone database's rules, and PHP reads "CET" as a fixed abbreviation without them. Name the region instead, such as "Europe/Brussels". `TimeZone.tryOf` gives null for it instead.
int(180)
int(120)
int(345)
int(0)
int(180)
int(-330)

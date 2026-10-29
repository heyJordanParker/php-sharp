--TEST--
A PHP# class calls every member of Sharp.Time.TimeZone: names lists the time zone database, utc and tryOf name a zone, an older name of the database included, tryOf gives null for a name the database lacks, an offset, an abbreviation and a name in another case, the constructor's ValueError for one names tryOf, and offsetAt follows a zone's clock changes
--FILE--
<?php
foreach (['Int', 'Math', 'Time/Duration', 'Time/Instant', 'Time/Date', 'Time/TimeZone', 'ListMembers', 'SetMembers'] as $file) {
    require __DIR__ . "/../../../sharp/composer/library/Sharp/$file.sharp";
}
require __DIR__ . '/TimeZoneCalls.sharp';

use Demo\TimeZoneCalls;

$names = TimeZoneCalls::names();
var_dump(array_is_list($names), $names[0], in_array('Europe/Sofia', $names, true), in_array('UTC', $names, true));
var_dump(TimeZoneCalls::utc(), TimeZoneCalls::named('Europe/Sofia'), TimeZoneCalls::named('Nowhere/City'), TimeZoneCalls::named(''));
var_dump(TimeZoneCalls::named('Europe/Kiev'), TimeZoneCalls::named('+03:00'), TimeZoneCalls::named('EEST'), TimeZoneCalls::named('europe/sofia'), TimeZoneCalls::named('utc'));
try {
    TimeZoneCalls::offsetMinutes('+03:00', '2026-01-01T00:00:00Z');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
var_dump(TimeZoneCalls::offsetMinutes('Europe/Sofia', '2026-07-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('Europe/Sofia', '2026-12-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('Asia/Kathmandu', '2026-01-01T00:00:00Z'), TimeZoneCalls::offsetMinutes('UTC', '2026-01-01T00:00:00Z'));
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
NULL
ValueError: `TimeZone` takes a name from the time zone database, such as "Europe/Sofia", and "+03:00" is not one. `TimeZone.tryOf` gives null for it instead.
int(180)
int(120)
int(345)
int(0)

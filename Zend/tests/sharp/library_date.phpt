--TEST--
A PHP# class calls every member of Sharp.Time's Date, Instant, DateTime and Duration: ISO-8601 text both ways, calendar arithmetic that clamps a month's last day, instants before 1970, a zone's offset across a clock change, a DateTime whose offset is not its zone's, and a ValueError for a day, an instant, a nanosecond or a zone that does not exist, whatever PHP's default time zone is
--INI--
date.timezone=America/New_York
--FILE--
<?php
foreach (['Int', 'Math', 'Time/Duration', 'Time/Instant', 'Time/Date', 'Time/DateTime', 'Time/TimeZone'] as $file) {
    require __DIR__ . "/../../../sharp/composer/library/Sharp/$file.sharp";
}
require __DIR__ . '/DateCalls.sharp';

use Demo\DateCalls;

echo "Date\n";
var_dump(DateCalls::day(2026, 10, 22), DateCalls::day(1, 1, 1), DateCalls::day(2024, 2, 29));
var_dump(DateCalls::dayAfter('2026-12-31', 1), DateCalls::dayAfter('2024-03-01', -1), DateCalls::dayAfter('1970-01-01', -1));
var_dump(DateCalls::monthAfter('2026-01-31', 1), DateCalls::monthAfter('2024-01-31', 1), DateCalls::monthAfter('2026-03-15', -14));
var_dump(DateCalls::daysBetween('2026-01-01', '2027-01-01'), DateCalls::daysBetween('2026-10-22', '2026-10-01'));
var_dump(DateCalls::epochDay('1970-01-01'), DateCalls::epochDay('2000-03-01'), DateCalls::epochDay('1969-12-31'), DateCalls::ofEpochDay(-719162), DateCalls::ofEpochDay(20383));
var_dump(DateCalls::before('2026-01-01', '2026-01-02'), DateCalls::before('2026-01-02', '2026-01-02'));
var_dump(DateCalls::tryDay('2026-02-29'), DateCalls::tryDay('2026-13-01'), DateCalls::tryDay('26-01-01'), DateCalls::tryDay('2026-01-01T00:00:00Z'));
var_dump(DateCalls::startOfDay('2026-10-22', 'Europe/Sofia'), DateCalls::startOfDay('2026-03-08', 'America/Havana'), DateCalls::startOfDay('2026-11-01', 'America/Havana'));

echo "Instant\n";
var_dump(DateCalls::instant(0, 0), DateCalls::instant(1761157800, 500_000_000), DateCalls::instant(-1, 1), DateCalls::instant(-62135596800, 0));
var_dump(DateCalls::ofMilliseconds(-1), DateCalls::milliseconds('1969-12-31T23:59:59.999Z'), DateCalls::milliseconds('2026-10-22T21:30:00.25+03:00'));
var_dump(DateCalls::tryInstant('2026-10-22T21:30:00+03:00'), DateCalls::tryInstant('2026-10-22T18:30:00.123456789Z'));
var_dump(DateCalls::tryInstant('2026-10-22T24:00:00Z'), DateCalls::tryInstant('2026-10-22T18:30:00'), DateCalls::tryInstant('2026-10-22 18:30:00Z'), DateCalls::tryInstant('2026-02-30T00:00:00Z'), DateCalls::tryInstant('2026-10-22T18:30:00+19:00'));
var_dump(DateCalls::later('2026-10-22T23:59:59.9Z', 'PT0.2S'), DateCalls::earlier('1970-01-01T00:00:00Z', 'PT0.000000001S'), DateCalls::later('2026-10-22T18:30:00Z', 'P1D'));
var_dump(DateCalls::between('2026-10-22T18:30:00Z', '2026-10-22T20:00:00.5Z'), DateCalls::between('2026-10-22T18:30:00Z', '2026-10-22T18:29:55Z'));
var_dump(DateCalls::after('2026-10-22T18:30:00.000000001Z', '2026-10-22T18:30:00Z'), DateCalls::after('2026-10-22T18:30:00Z', '2026-10-22T21:30:00+03:00'));

echo "DateTime\n";
var_dump(DateCalls::inZone('2026-10-22T18:30:00Z', 'Europe/Sofia'), DateCalls::inZone('2026-10-25T01:30:00Z', 'Europe/Sofia'), DateCalls::inZone('2026-01-01T00:00:00Z', 'America/St_Johns'));
echo implode(' ', DateCalls::parts('2026-10-22T22:30:00Z', 'Europe/Sofia')), ' ', implode(' ', DateCalls::parts('2026-01-01T02:00:00Z', 'America/New_York')), "\n";
var_dump(DateCalls::zoned('2026-10-22T21:30:00+03:00[Europe/Sofia]'), DateCalls::tryZoned('2026-10-22T21:30:00+02:00[Europe/Sofia]'), DateCalls::tryZoned('2026-10-22T21:30:00+03:00[Nowhere/City]'), DateCalls::tryZoned('2026-10-22T21:30:00+03:00'));
var_dump(DateCalls::zonedLater('2026-10-25T03:30:00+03:00[Europe/Sofia]', 'PT1H'));

echo "Duration\n";
var_dump(DateCalls::duration('PT1H30M'), DateCalls::duration('P2DT1S'), DateCalls::duration('PT0.5S'), DateCalls::duration('-PT5S'), DateCalls::duration('PT0S'), DateCalls::duration('PT90M'));
var_dump(DateCalls::tryDuration('P'), DateCalls::tryDuration('PT'), DateCalls::tryDuration('1H'), DateCalls::tryDuration('PT1.5H'));
echo implode(' ', DateCalls::units('P1DT2H3M4.005S')), ' ', implode(' ', DateCalls::units('-PT1M30S')), "\n";
var_dump(DateCalls::sum('PT1H', '-PT90M'), DateCalls::scaled('PT20M', 3), DateCalls::built());

echo "Refused\n";
foreach ([
    fn () => DateCalls::day(2026, 2, 29),
    fn () => DateCalls::day(2026, 0, 1),
    fn () => DateCalls::dayAfter('2026-1-1', 1),
    fn () => DateCalls::milliseconds('yesterday'),
    fn () => DateCalls::instant(0, 1_000_000_000),
    fn () => DateCalls::inZone('2026-10-22T18:30:00Z', 'Nowhere/City'),
    fn () => DateCalls::zoned('2026-10-22T21:30:00+02:00[Europe/Sofia]'),
    fn () => DateCalls::duration('one hour'),
] as $refused) {
    try {
        $refused();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
Date
string(10) "2026-10-22"
string(10) "0001-01-01"
string(10) "2024-02-29"
string(10) "2027-01-01"
string(10) "2024-02-29"
string(10) "1969-12-31"
string(10) "2026-02-28"
string(10) "2024-02-29"
string(10) "2025-01-15"
int(365)
int(-21)
int(0)
int(11017)
int(-1)
string(10) "0001-01-01"
string(10) "2025-10-22"
bool(true)
bool(false)
NULL
NULL
NULL
NULL
string(39) "2026-10-22T00:00:00+03:00[Europe/Sofia]"
string(41) "2026-03-08T01:00:00-04:00[America/Havana]"
string(41) "2026-11-01T00:00:00-04:00[America/Havana]"
Instant
string(20) "1970-01-01T00:00:00Z"
string(24) "2025-10-22T18:30:00.500Z"
string(30) "1969-12-31T23:59:59.000000001Z"
string(20) "0001-01-01T00:00:00Z"
string(24) "1969-12-31T23:59:59.999Z"
int(-1)
int(1792693800250)
string(20) "2026-10-22T18:30:00Z"
string(30) "2026-10-22T18:30:00.123456789Z"
NULL
NULL
NULL
NULL
NULL
string(24) "2026-10-23T00:00:00.100Z"
string(30) "1969-12-31T23:59:59.999999999Z"
string(20) "2026-10-23T18:30:00Z"
string(13) "PT1H30M0.500S"
string(5) "-PT5S"
bool(true)
bool(false)
DateTime
string(39) "2026-10-22T21:30:00+03:00[Europe/Sofia]"
string(39) "2026-10-25T03:30:00+02:00[Europe/Sofia]"
string(43) "2025-12-31T20:30:00-03:30[America/St_Johns]"
2026 10 23 1 30 0 180 2025 12 31 21 0 0 -300
string(20) "2026-10-22T18:30:00Z"
NULL
NULL
NULL
string(39) "2026-10-25T03:30:00+02:00[Europe/Sofia]"
Duration
string(7) "PT1H30M"
string(7) "PT48H1S"
string(8) "PT0.500S"
string(5) "-PT5S"
string(4) "PT0S"
string(7) "PT1H30M"
NULL
NULL
NULL
NULL
1 26 1563 93784 93784005 93784005000000 0 0 -1 -90 -90000 -90000000000
string(6) "-PT30M"
string(4) "PT1H"
string(13) "PT26H3M4.005S"
Refused
ValueError: `Date` takes a day of the calendar, and 2026-2-29 is not one.
ValueError: `Date` takes a day of the calendar, and 2026-0-1 is not one.
ValueError: `Date.parse` reads an ISO-8601 day, such as "2026-10-22", and "2026-1-1" is not.
ValueError: `Instant.parse` reads ISO-8601 with an offset, such as "2026-10-22T18:30:00Z", and "yesterday" is not.
ValueError: `Instant` takes a nanosecond from 0 to 999999999, and it is outside that range.
ValueError: `TimeZone` takes a name from the time zone database, such as "Europe/Sofia", and "Nowhere/City" is not one.
ValueError: `DateTime.parse` reads ISO-8601 with an offset and a zone, such as "2026-10-22T21:30:00+03:00[Europe/Sofia]", and "2026-10-22T21:30:00+02:00[Europe/Sofia]" is not.
ValueError: `Duration.parse` reads ISO-8601, such as "PT1H30M", and "one hour" is not.
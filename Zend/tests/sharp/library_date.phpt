--TEST--
A PHP# class calls every method of Sharp.Time.Date, which reads UTC instead of PHP's default time zone, gives null for text it cannot read and throws on an unknown time zone
--INI--
date.timezone=America/New_York
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Time/Date.sharp';
require __DIR__ . '/DateCalls.sharp';

use Demo\DateCalls;

var_dump(DateCalls::format(0, 'Y-m-d H:i:s T'));
var_dump(DateCalls::formatIn(1700000000, 'Y-m-d H:i T', 'Europe/Sofia'));
var_dump(DateCalls::parse('2024-01-15 10:00'), DateCalls::parse('2024-01-15 10:00 Europe/Sofia'));
var_dump(DateCalls::parse('nope'));

try {
    DateCalls::formatIn(0, 'Y', 'Nowhere/City');
} catch (Exception $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(23) "1970-01-01 00:00:00 UTC"
string(20) "2023-11-15 00:13 EET"
int(1705312800)
int(1705305600)
NULL
DateInvalidTimeZoneException: DateTimeZone::__construct(): Unknown or bad timezone (Nowhere/City)

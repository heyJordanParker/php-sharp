--TEST--
A PHP# class reads a Date's and a DateTime's Weekday and Month and writes the texts real code writes, each by a named format or from the date's fields: ISO-8601 with an offset, a SQL DATETIME, an HTTP date, a month's first and last day, and reads a SQL DATETIME or a datetime-local value in a zone, whatever PHP's default time zone is
--INI--
date.timezone=America/New_York
--FILE--
<?php
foreach (['Int', 'Math', 'Time/Duration', 'Time/Instant', 'Time/Date', 'Time/DateTime', 'Time/TimeZone', 'Time/Weekday', 'Time/Month', 'ListMembers'] as $file) {
    require __DIR__ . "/../../../sharp/composer/library/Sharp/$file.sharp";
}
require __DIR__ . '/DateTextCalls.sharp';

use Demo\DateTextCalls;

foreach (['2026-10-22', '2024-02-10', '1969-12-31'] as $day) {
    echo $day, ': ', DateTextCalls::names($day), "\n";
}
var_dump(DateTextCalls::weekend('2026-10-25'), DateTextCalls::weekend('2026-10-22'));
echo DateTextCalls::monthBounds('2024-02-10'), "\n";
echo DateTextCalls::texts('2026-10-22T18:30:00Z', 'Europe/Sofia'), "\n";
echo DateTextCalls::texts('2026-10-22T18:30:00Z', 'UTC'), "\n";
foreach (['2026-10-22 21:30:00', '2026-10-22T21:30', '2026-10-22 21:30:00.25', '2026-03-29 03:30:00', '2026-10-25 03:30:00', '2026-10-22 24:00:00', '2026-02-30 10:00:00', '2026-10-22'] as $text) {
    echo $text, ' => ', DateTextCalls::local($text, 'Europe/Sofia'), "\n";
}
try {
    DateTextCalls::strictLocal('yesterday', 'UTC');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
2026-10-22: Thursday October 31
2024-02-10: Saturday February 29
1969-12-31: Wednesday December 31
bool(true)
bool(false)
2024-02-01 2024-02-29 2024-01-01
2026-10-22T21:30:00+03:00 | 2026-10-22 21:30:00 | Thursday | Thu, 22 Oct 2026 18:30:00 GMT
2026-10-22T18:30:00+00:00 | 2026-10-22 18:30:00 | Thursday | Thu, 22 Oct 2026 18:30:00 GMT
2026-10-22 21:30:00 => 2026-10-22T21:30:00+03:00[Europe/Sofia]
2026-10-22T21:30 => 2026-10-22T21:30:00+03:00[Europe/Sofia]
2026-10-22 21:30:00.25 => 2026-10-22T21:30:00.250+03:00[Europe/Sofia]
2026-03-29 03:30:00 => 2026-03-29T04:30:00+03:00[Europe/Sofia]
2026-10-25 03:30:00 => 2026-10-25T03:30:00+03:00[Europe/Sofia]
2026-10-22 24:00:00 => null
2026-02-30 10:00:00 => null
2026-10-22 => null
ValueError: `DateTime.parseLocal` reads a date and a time of day, such as "2026-10-22 21:30:00", and "yesterday" is not.

--TEST--
A PHP# class calls the time and path members of Kotlin, Rust and .NET that PHP# lacked: the struct TimeOfDay, written, read, ordered and compared, a DateTime's timeOfDay, Path.join, Path.absolute, Files.temporaryDirectory and Files.walk, with each edge case
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\TimeAndPaths;
use Sharp\IO\SystemFiles;

echo TimeAndPaths::written(21, 30, 0, 0), ' ', TimeAndPaths::written(9, 5, 7, 250000000), ' ', TimeAndPaths::written(23, 59, 59, 1), ' ', TimeAndPaths::shortTime(7, 0), "\n";
foreach ([fn () => TimeAndPaths::written(24, 0, 0, 0), fn () => TimeAndPaths::written(1, 0, 0, 1000000000), fn () => TimeAndPaths::ordered('x', '08:00')] as $refused) {
    try {
        $refused();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
echo json_encode(TimeAndPaths::ordered('08:00', '21:30:00.5')), ' ';
var_dump(TimeAndPaths::same('21:30', '21:30:00'));
var_dump(TimeAndPaths::read('21:30:00.250'), TimeAndPaths::read('24:00'), TimeAndPaths::read('9:30'), TimeAndPaths::read('21:30:60'));
echo TimeAndPaths::onClock('2026-10-22T18:30:00Z', 'Europe/Sofia'), ' ', TimeAndPaths::onClock('2026-10-22T23:30:00.5Z', 'America/New_York'), "\n";

echo TimeAndPaths::joined('a', ['b', 'c']), ' ', TimeAndPaths::joined('a/', ['b']), ' ', TimeAndPaths::joined('a', ['/b', 'c']), ' ';
echo TimeAndPaths::joined('', ['b']), ' ', TimeAndPaths::joined('a', []), "\n";
chdir('/');
echo TimeAndPaths::absolute('/x/./y/../z'), ' ', TimeAndPaths::absolute('/..'), ' ', TimeAndPaths::absolute('var/./log/..'), ' ', TimeAndPaths::absolute('missing/file.txt'), "\n";

$files = new SystemFiles();
var_dump(TimeAndPaths::temporary($files) === sys_get_temp_dir());
$root = sys_get_temp_dir() . '/sharp-walk-' . getmypid();
mkdir("$root/a/y", 0777, true);
file_put_contents("$root/a/z.txt", 'z');
file_put_contents("$root/b.txt", 'b');
symlink("$root/a", "$root/c");
echo json_encode(array_map(fn (string $path) => substr($path, strlen($root)), TimeAndPaths::walked($files, "$root/"))), "\n";
var_dump(TimeAndPaths::walked($files, "$root/missing"));
unlink("$root/c");
unlink("$root/b.txt");
unlink("$root/a/z.txt");
rmdir("$root/a/y");
rmdir("$root/a");
rmdir($root);
?>
--EXPECT--
21:30:00 09:05:07.250 23:59:59.000000001 07:00:00
ValueError: `TimeOfDay` takes a time of day, and 24:0:0 and 0 nanoseconds is not one.
ValueError: `TimeOfDay` takes a time of day, and 1:0:0 and 1000000000 nanoseconds is not one.
ValueError: `TimeOfDay.parse` reads an ISO-8601 time of day, such as "21:30:00", and "x" is not.
[-1,1,0] bool(true)
string(12) "21:30:00.250"
NULL
NULL
NULL
21:30:00 19:30:00.500
a/b/c a/b /b/c b a
/x/z / /var /missing/file.txt
bool(true)
["\/a","\/a\/y","\/a\/z.txt","\/b.txt","\/c"]
NULL

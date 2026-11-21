--TEST--
A Bytes reaches plain PHP as PHP's string of its bytes: an argument to a parameter that takes mixed, a returned value, an element of a List or a Map, and a property plain PHP reads, while a string plain PHP writes into a Bytes property arrives as Bytes, and an extern line may state a plain PHP string result as Bytes, which keeps every byte
--FILE--
<?php
require __DIR__ . '/harness/Streams.inc';
require __DIR__ . '/vendor/autoload.php';

use BytesCrossings\Archive;
use BytesCrossings\Packet;
use Lib\Streams\Stream;
use function Lib\Streams\shown;

$zip = "PK\x03\x04" . implode('', array_map(chr(...), range(0, 255)));
$path = sys_get_temp_dir() . '/sharp-bytes-crossing-' . getmypid() . '.zip';
var_dump(Archive::write($path, $zip));
$written = file_get_contents($path);
unlink($path);
var_dump(strlen($written), bin2hex(substr($written, 0, 4)), $written === $zip);

echo shown(Archive::held("\xFF\x00\xB1")), "\n";
echo Archive::listed("\xFF\x00\xB1"), "\n";
echo Archive::mapped("\xFF\x00\xB1"), "\n";
$packet = new Packet("\xFF\x00\xB1");
echo shown($packet->payload), "\n";
$packet->trailer = "\xFE\xFF";
var_dump($packet->trailerLength());
echo shown($packet->trailer), "\n";
var_dump(Archive::read(new Stream($zip), 260) === bin2hex($zip));
?>
--EXPECT--
int(260)
int(260)
string(8) "504b0304"
bool(true)
string ff00b1
0: string ff00b1, 1: string ff
whole: string ff00b1, first: string ff
string ff00b1
int(2)
string feff
bool(true)

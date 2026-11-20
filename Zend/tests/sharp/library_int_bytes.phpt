--TEST--
PHP# int.toBytes gives an int's 8 bytes in a ByteOrder, Big by default, as Rust's to_be_bytes and to_le_bytes, a negative one in two's complement, and Int.fromBytes reads them back at both ends of the range, refusing any length but 8
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\EncodingQueries;
use Sharp\ByteOrder;

echo EncodingQueries::bigEndian(258), ' ', EncodingQueries::toBytes(258, ByteOrder::Big), ' ', EncodingQueries::toBytes(258, ByteOrder::Little), "\n";
echo EncodingQueries::bigEndian(-1), ' ', EncodingQueries::bigEndian(PHP_INT_MIN), ' ', EncodingQueries::toBytes(PHP_INT_MAX, ByteOrder::Little), "\n";
$read = [];
foreach ([0, 258, -1, -258, PHP_INT_MAX, PHP_INT_MIN] as $value) {
    foreach ([ByteOrder::Big, ByteOrder::Little] as $order) {
        $read[] = EncodingQueries::fromBytes(hex2bin(EncodingQueries::toBytes($value, $order)), $order) === $value ? 'same' : "differs for $value";
    }
}
echo implode(' ', $read), "\n", EncodingQueries::fromBigEndian(hex2bin('0000000000000102')), ' ', EncodingQueries::fromBytes(hex2bin('0201000000000000'), ByteOrder::Little), "\n";
foreach (['', "\x01", str_repeat("\x00", 9)] as $bytes) {
    try {
        EncodingQueries::fromBigEndian($bytes);
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
0000000000000102 0000000000000102 0201000000000000
ffffffffffffffff 8000000000000000 ffffffffffffff7f
same same same same same same same same same same same same
258 258
ValueError: Int.fromBytes: an int is 8 bytes, and the bytes hold 0
ValueError: Int.fromBytes: an int is 8 bytes, and the bytes hold 1
ValueError: Int.fromBytes: an int is 8 bytes, and the bytes hold 9

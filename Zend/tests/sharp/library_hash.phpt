--TEST--
A PHP# class calls every method of Sharp.Data.Hash, and an unknown algorithm throws ValueError
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Hash.sharp';
require __DIR__ . '/HashCalls.sharp';

use Demo\HashCalls;

var_dump(HashCalls::of('sha256', 'abc'));
var_dump(HashCalls::hmac('sha256', 'data', 'key'));
var_dump(HashCalls::md5('abc'), HashCalls::sha1('abc'));
var_dump(HashCalls::crc32('The quick brown fox jumped over the lazy dog.'));
var_dump(HashCalls::equals('abc', 'abc'), HashCalls::equals('abc', 'abd'));

try {
    HashCalls::of('nope', 'abc');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(64) "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"
string(64) "5031fe3d989c6d1537a013fa6e739da23463fdaec3b70137d828e36ace221bd0"
string(32) "900150983cd24fb0d6963f7d28e17f72"
string(40) "a9993e364706816aba3e25717850c26c9cd0d89d"
int(2191738434)
bool(true)
bool(false)
ValueError: hash(): Argument #1 ($algo) must be a valid hashing algorithm

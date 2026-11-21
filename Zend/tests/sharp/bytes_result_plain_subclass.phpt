--TEST--
A PHP# method that returns a Bytes declares \Sharp\Bytes|string, so a plain PHP subclass may return PHP's string of the bytes, which a plain PHP caller gets from either class and a PHP# caller reads as the Bytes, and a PHP# override of a plain PHP method keeps its type: a string under mixed, and the Bytes under object
--FILE--
<?php
require __DIR__ . '/harness/Streams.inc';
require __DIR__ . '/vendor/autoload.php';

use BytesCrossings\Digest;
use BytesCrossings\MixedCopy;
use BytesCrossings\ObjectCopy;

final class Short extends Digest
{
    public function raw(): string
    {
        return "\x01\x02";
    }
}

var_dump(bin2hex((new Digest())->raw()), bin2hex((new Short())->raw()));
var_dump(Digest::shown(new Digest()), Digest::shown(new Short()));
var_dump(bin2hex((new MixedCopy())->raw()), MixedCopy::shown(new MixedCopy()));
var_dump(get_class((new ObjectCopy())->raw()), ObjectCopy::shown(new ObjectCopy()));
?>
--EXPECT--
string(6) "ff00b1"
string(4) "0102"
string(6) "ff00b1"
string(4) "0102"
string(4) "0b0c"
string(4) "0b0c"
string(11) "Sharp\Bytes"
string(4) "0d0e"

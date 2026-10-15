--TEST--
Only a PHP# struct implements Sharp\Struct, as only an enum implements UnitEnum
--FILE--
<?php
final class Fake implements Sharp\Struct
{
}
?>
--EXPECTF--
Fatal error: Non-struct class Fake cannot implement interface Sharp\Struct in %s on line %d

--TEST--
Plain PHP cannot declare Sharp\Int, because PHP reserves int as a class name, and only the standard library's .sharp files declare it
--FILE--
<?php
namespace Sharp;

class Int {}
?>
--EXPECTF--
Fatal error: Cannot use "Int" as a class name as it is reserved in %s on line %d

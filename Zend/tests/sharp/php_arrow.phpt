--TEST--
PHP# raises ParseError at the .sharp line of a PHP ->
--FILE--
<?php

try {
    require __DIR__ . '/PhpArrow.sharp';
} catch (ParseError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
ParseError: `->` is PHP syntax: PHP# writes member access with `.` in PhpArrow.sharp on line 8

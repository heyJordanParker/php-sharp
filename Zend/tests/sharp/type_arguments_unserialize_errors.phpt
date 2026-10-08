--TEST--
unserialize fails, with the warning malformed data gives, on type arguments it cannot give the object
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

$key = 's:14:"' . "\0<sharp>\0types" . '";';
$inputs = [
    'a class that does not load' => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 's:11:"App.Missing";}',
    'a class without type parameters' => 'O:9:"App\Order":2:{s:2:"id";i:1;' . $key . 's:9:"App.Order";}',
    'a value that is not a string' => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 'i:1;}',
    'a reference in place of the text' => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 'R:2;}',
    'a text that is no type' => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 's:5:"App.(";}',
    'too many type arguments' => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 's:14:"App.Order, int";}',
    'a class with __unserialize' => 'O:10:"App\Ledger":2:{s:4:"kept";b:1;' . $key . 's:11:"App.Missing";}',
];
foreach ($inputs as $case => $input) {
    echo $case, ":\n";
    var_dump(unserialize($input));
}
var_dump(Lib\Snapshot::$restored);
?>
--EXPECTF--
a class that does not load:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a class without type parameters:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a value that is not a string:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a reference in place of the text:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a text that is no type:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
too many type arguments:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a class with __unserialize:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
array(0) {
}

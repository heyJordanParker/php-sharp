--TEST--
unserialize fails, with the warning malformed data gives, on type arguments it cannot give the object
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

$key = 's:14:"' . "\0<sharp>\0types" . '";';
$list = static fn (string $text): string => 'O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 's:' . strlen($text) . ':"' . $text . '";}';
$pair = static fn (string $text): string => 'O:8:"App\Pair":3:{s:3:"key";i:1;s:5:"value";i:2;' . $key . 's:' . strlen($text) . ':"' . $text . '";}';
$inputs = [
    'a class that does not load' => [$list('App.Missing'), []],
    'a class without type parameters' => ['O:9:"App\Order":2:{s:2:"id";i:1;' . $key . 's:9:"App.Order";}', []],
    'a value that is not a string' => ['O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 'i:1;}', []],
    'a reference in place of the text' => ['O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}' . $key . 'R:2;}', []],
    'a text that is no type' => [$list('App.('), []],
    'too many type arguments' => [$list('App.Order, int'), []],
    'a class with __unserialize' => ['O:10:"App\Ledger":2:{s:4:"kept";b:1;' . $key . 's:11:"App.Missing";}', []],
    'a type argument allowed_classes leaves out' => [$pair('App.Order, int'), ['allowed_classes' => ['App\Pair']]],
    'type arguments on a class without type parameters' => [$pair('App.Order<int>, int'), []],
    'a generic class without its type arguments' => [$pair('App.PaginatedList, int'), []],
    'a built-in type with too many type arguments' => [$pair('List<int, int>, int'), []],
    'a type argument outside its bound' => [$list('int'), []],
    'a nested type argument outside its bound' => [$pair('App.PaginatedList<int>, int'), []],
    'a union member outside the bound' => [$list('App.Order|int'), []],
];
foreach ($inputs as $case => [$input, $options]) {
    echo $case, ":\n";
    var_dump(unserialize($input, $options));
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
a type argument allowed_classes leaves out:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
type arguments on a class without type parameters:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a generic class without its type arguments:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a built-in type with too many type arguments:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a type argument outside its bound:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a nested type argument outside its bound:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
a union member outside the bound:

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
array(0) {
}

--TEST--
PHP# a struct and an interface name Iterable<T>, the lazy operations reach a plain PHP Traversable, whose own member wins, and [] is an empty List an Iterable holds
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$stream = new IterableKinds\Stream();
echo IterableKinds\Reads::fed($stream), "\n";
var_dump($stream instanceof IteratorAggregate);
echo implode(',', iterator_to_array($stream)), "\n";
echo implode(',', IterableKinds\Reads::counted(new IterableKinds\Countdown(3))), "\n";
echo implode(',', iterator_to_array(new IterableKinds\Countdown(2))), "\n";
$cursor = Illuminate\Support\LazyCollection::make([4, 5, 6]);
echo get_class(IterableKinds\Reads::filtered($cursor)), ' ', implode(',', IterableKinds\Reads::cursor($cursor)), "\n";
var_dump(IterableKinds\Reads::none(), IterableKinds\Reads::named(), IterableKinds\Reads::named(['a', 'b']));
?>
--EXPECT--
8
bool(true)
1,2,3
30,20
2,1
Illuminate\Support\LazyCollection 5
array(0) {
}
int(0)
int(2)

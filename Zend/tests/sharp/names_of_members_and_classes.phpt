--TEST--
nameof gives a method's name and a class's own name, and Class<T>.name() gives a type's full name as PHP# writes it, with dots
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Titles');

foreach ((new Titles\Report())->names() as $name) {
    echo $name, "\n";
}
?>
--EXPECT--
Counted
index
total
Titles.Counted
Titles.Counted
List<string>
Map<string, int>
int?

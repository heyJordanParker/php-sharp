--TEST--
Class<T>.properties() gives every property a class declares, one of a union of scalars and of a nullable union included, with the class value of its type, and a generic type keeps its type arguments
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

foreach (App\Probe\Printer::describe(App\Probe\Shapes::class) as $line) {
    echo $line, "\n";
}
var_dump(App\Probe\Printer::compare());
?>
--EXPECT--
either: int|string arguments=[]
maybeEither: (int|string)? arguments=[]
owner: App.Probe.Box|App.Probe.Shelf arguments=[]
withDefault: string arguments=[]
held: App.Probe.Holder arguments=[App.Probe.Box]
array(5) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
}

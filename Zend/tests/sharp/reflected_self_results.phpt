--TEST--
A method declared to return Self reads, through reflection on a class value, as that class value's class, with its type arguments: an inherited one as the subclass, as static binds in PHP, inside a nullable and a List too
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

foreach (SelfResults\Report::all() as $line) {
    echo $line, "\n";
}
var_dump(SelfResults\Report::generic());
?>
--EXPECT--
copy(): SelfResults.Base
maybe(): SelfResults.Base?
all(): List<SelfResults.Base>
copy(): SelfResults.Sub
maybe(): SelfResults.Sub?
all(): List<SelfResults.Sub>
array(2) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
}

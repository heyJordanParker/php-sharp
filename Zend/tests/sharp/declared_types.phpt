--TEST--
Class<T>.properties() gives each instance property and field of a class, its own first and then those it inherits, with its name, the class value of its whole declared type, `?` included, and its attributes, every one of them for the root bound Object, and Class<T>.typeArguments() gives a type's type arguments as class values
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Declared');

$report = new Declared\Report();
foreach ($report->describe() as $line) {
    echo $line, "\n";
}
var_dump($report->compare());
?>
--EXPECT--
tags: List<string> arguments=[string] attributes=[Field()]
stock: int? arguments=[] attributes=[Field(units)]
related: Map<string, Declared.Product> arguments=[string, Declared.Product] attributes=[]
id: int arguments=[] attributes=[]
array(7) {
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
  [5]=>
  bool(true)
  [6]=>
  bool(true)
}

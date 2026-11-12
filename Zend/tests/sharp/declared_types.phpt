--TEST--
Class<T>.properties() gives each instance property and field of a class, its own first and then those it inherits, with its name, the class value of its type, whether it is nullable and its attributes, and Class<T>.typeArguments() gives a type's type arguments as class values
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
tags: List<string> nullable=false arguments=[string] attributes=[Field()]
stock: int nullable=true arguments=[] attributes=[Field(units)]
related: Map<string, Declared.Product> nullable=false arguments=[string, Declared.Product] attributes=[]
id: int nullable=false arguments=[] attributes=[]
array(6) {
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
}

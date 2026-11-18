--TEST--
Class<T>.fields() gives each field and auto-property's storage of a class, its own first and then those it inherits, with its name, the class value of its whole declared type, `?` included, and its attributes, every one of them for the root bound Object, Class<T>.properties() gives the properties and no field, and Class<T>.typeArguments() gives a type's type arguments as class values
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

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
properties: related, id
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

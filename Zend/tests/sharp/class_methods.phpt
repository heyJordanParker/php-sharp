--TEST--
Class<T>.methods() gives each instance and static method of a class, its own first and then those it inherits, with its result type and each parameter's type, nullable and optional, the type arguments of the class value substituted, a plain PHP method's PHP types as PHP# writes them, and the attributes of each method and parameter
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/Crate.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Methods');

foreach (App\Shop\Report::describe() as $line) {
    echo $line, "\n";
}
var_dump(App\Shop\Report::compare());
?>
--EXPECT--
first(items: List<App.Shop.Order> fields=0, key: int|string fields=0, note: int|string? optional fields=0): App.Shop.Order fields=0
all(): List<App.Shop.Order>? fields=0
count(label: string fields=0): int fields=0
index(): Map<string, App.Shop.Order> fields=0
tag(label: string fields=1): void fields=1
parse(value: Map<int|string, Any?>|Object fields=0): App.Shop.Box fields=0
tryParse(value: Map<int|string, Any?>|Object fields=0): App.Shop.Box? fields=0
weigh(grams: int? fields=0, tags: Map<int|string, Any?> optional fields=0): float? fields=0
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

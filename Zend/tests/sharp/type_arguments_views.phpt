--TEST--
No view of a PHP# object shows its type arguments: var_dump, print_r, var_export, debug_zval_dump, get_object_vars, an (array) cast, foreach and json_encode
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

echo "var_dump\n";
var_dump(Queue::orders());
var_dump(new App\PaginatedList([]));

echo "print_r\n";
print_r(Queue::orders());

echo "var_export\n";
var_export(Queue::orders());

echo "\ndebug_zval_dump\n";
debug_zval_dump(Queue::orders());

echo "get_object_vars\n";
var_dump(get_object_vars(Queue::orders()));

echo "an (array) cast\n";
var_dump((array) Queue::orders());

echo "foreach\n";
$pair = Queue::pair();
foreach ($pair as $name => $value) {
    echo $name, "\n";
}
foreach (new App\PaginatedList([]) as $name => $value) {
    echo $name, "\n";
}

echo "json_encode\n";
echo json_encode(Queue::orders()), "\n";
echo json_encode(Queue::pair()), "\n";
?>
--EXPECTF--
var_dump
object(App\PaginatedList)#%d (1) {
  ["items"]=>
  array(0) {
  }
}
object(App\PaginatedList)#%d (1) {
  ["items"]=>
  array(0) {
  }
}
print_r
App\PaginatedList Object
(
    [items] => Array
        (
        )

)
var_export
\App\PaginatedList::__set_state(array(
   'items' =>%w
  array (
  ),
))
debug_zval_dump
object(App\PaginatedList)#%d (1) refcount(%d){
  ["items"]=>
  array(0) interned {
  }
}
get_object_vars
array(1) {
  ["items"]=>
  array(0) {
  }
}
an (array) cast
array(1) {
  ["items"]=>
  array(0) {
  }
}
foreach
key
value
items
json_encode
{"items":[]}
{"key":{"id":2},"value":3}

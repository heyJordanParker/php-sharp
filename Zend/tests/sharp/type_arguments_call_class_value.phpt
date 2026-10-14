--TEST--
A class value carries its type arguments through plain PHP to new and to a static member it reads or writes
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use App\Order;
use Calls\Repository;

// Plain PHP reads a class value as the class name, and the value keeps its type arguments as it passes through, so `new`
// creates the class with them, and type arguments `new` names win. A hot function's second call runs the code the
// tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    $inspection = Repository::orderInspection();
    $pages = Repository::orderPages();
    var_dump($pages, $pages === 'App\PaginatedList', $pages === Repository::sharedPages());
    // PHP#'s `==` tells the two apart where plain PHP's `===` above does not.
    echo implode(', ', array_map(fn (bool $same) => var_export($same, true), Repository::sameTypes())), "\n";
    echo shown(Repository::paged($pages)), ', ', shown(Repository::sharedPaged()), ', ',
        shown(Repository::orderPaged(Repository::sharedPages())), "\n";
    echo shown($inspection->listed()), ', ', $inspection->remade(8)::class, ' ', $inspection->remade(8)->id, ', ',
        Repository::sourceCounted(), "\n";
    echo implode(', ', Repository::orderWrittenThrough(new Order(3))), "\n";
}
?>
--EXPECT--
string(17) "App\PaginatedList"
bool(true)
bool(true)
false, true, true, true, false
App\PaginatedList<App.Order>, App\PaginatedList<App.SharedOrder>, App\PaginatedList<App.Order>
App\PaginatedList<App.Order>, App\Order 8, 82
8, 8, 1, 0
string(17) "App\PaginatedList"
bool(true)
bool(true)
false, true, true, true, false
App\PaginatedList<App.Order>, App\PaginatedList<App.SharedOrder>, App\PaginatedList<App.Order>
App\PaginatedList<App.Order>, App\Order 8, 82
8, 8, 2, 0

--TEST--
A class value enters PHP# from plain PHP, as an argument or a property value, only as a class value of its type: a name plain PHP writes carries no type arguments, so only a class whose header fixes them enters as a Class<PaginatedList<Order>>
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use Calls\Repository;
use Calls\Shelf;

// A hot function's second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    $pages = Repository::orderPages();
    foreach (['App\OrderPage', [Repository::sharedPages()][0], 'App\PaginatedList', 'App\Missing', 7] as $type) {
        try {
            echo shown(Repository::paged($type)), "\n";
        } catch (Error $error) {
            echo $error->getMessage(), "\n";
        }
    }
    $shelf = new Shelf();
    foreach ([$pages, Repository::sharedPages()] as $type) {
        try {
            $shelf->pages = $type;
            echo 'written: ', $shelf->pages === $pages ? 'orderPages' : 'other', "\n";
        } catch (TypeError $error) {
            echo $error->getMessage(), "\n";
        }
    }
}
?>
--EXPECTF--
App\OrderPage<>
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, Class<App.PaginatedList<App.SharedOrder>> given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
written: orderPages
Cannot assign Class<App.PaginatedList<App.SharedOrder>> to property Calls\Shelf::$pages of type Class<App.PaginatedList<App.Order>>?
App\OrderPage<>
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, Class<App.PaginatedList<App.SharedOrder>> given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
written: orderPages
Cannot assign Class<App.PaginatedList<App.SharedOrder>> to property Calls\Shelf::$pages of type Class<App.PaginatedList<App.Order>>?

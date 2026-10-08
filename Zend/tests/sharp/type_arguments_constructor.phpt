--TEST--
A PHP# object has its type arguments before its constructor runs
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

// PaginatedList's constructor hands this to Lib\Witness::see before it returns.
Lib\Witness::$look = static function (object $object): void {
    echo get_class($object), ': ', implode(', ', (new ReflectionObject($object))->getTypeArguments()), "\n";
};

App\Queue::orders();
App\Queue::entities()->copy();
App\Queue::page();
?>
--EXPECT--
App\PaginatedList: App.Order
App\PaginatedList: App.DatabaseEntity
App\PaginatedList: App.DatabaseEntity
App\OrderPage:

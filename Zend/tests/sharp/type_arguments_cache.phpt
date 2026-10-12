--TEST--
A new whose type arguments name a type parameter of its class spells them once for each class of this, and keeps a list that lives for the request too
--FILE--
<?php
require __DIR__ . '/type_arguments_cache.inc';
?>
--EXPECT--
App\OrderMaker: App.Order spelled
App\OrderMaker: App.Order cached
App\Maker: int spelled
App\Maker: int cached
App\OrderMaker: App.Order cached
App\ListMaker: List<int> spelled
App\ListMaker: List<int> cached
App\OrderMaker: App.Order cached

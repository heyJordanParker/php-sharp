--TEST--
A new whose type arguments name a type parameter of its class keeps what it spelled for the class of this, spells again for another class, and never keeps a list that lives for the request
--FILE--
<?php
require __DIR__ . '/type_arguments_cache.inc';
?>
--EXPECT--
App\OrderMaker: App.Order spelled
App\OrderMaker: App.Order cached
App\Maker: int spelled
App\Maker: int cached
App\OrderMaker: App.Order spelled
App\ListMaker: List<int> spelled
App\ListMaker: List<int> spelled
App\OrderMaker: App.Order cached

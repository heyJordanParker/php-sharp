--TEST--
PHP# runs generic classes, interfaces and methods erased, with type arguments written, inferred and in headers
--FILE--
<?php

require __DIR__ . '/Generics.sharp';

use Paging\Catalog;

var_dump(Catalog::paged(), Catalog::listed(), Catalog::written(), Catalog::inferred(), Catalog::headed());
var_dump(Catalog::fed(), Catalog::checked(), Catalog::firstId(), Catalog::shared(), Catalog::loaded());

echo new ReflectionMethod(Paging\PaginatedList::class, 'first')->getReturnType(), "\n";
echo new ReflectionMethod(Paging\Store::class, 'first')->getReturnType(), "\n";
echo new ReflectionMethod(Paging\Store::class, 'share')->getParameters()[0]->getType(), "\n";
echo new ReflectionMethod(Paging\Store::class, 'load')->getParameters()[0]->getType(), "\n";
echo new ReflectionClass(Paging\OrderPage::class)->getParentClass()->name, "\n";
var_dump(new ReflectionClass(Paging\EntityValidator::class)->getInterfaceNames());
?>
--EXPECT--
int(1)
int(1)
int(2)
int(2)
int(1)
int(1)
bool(true)
int(1)
string(9) "/orders/4"
int(5)
Paging\DatabaseEntity
mixed
Paging\DatabaseEntity&Paging\Shareable
string
Paging\PaginatedList
array(1) {
  [0]=>
  string(16) "Paging\Validator"
}

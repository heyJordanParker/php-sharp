--TEST--
PHP# attributes on a class, its members and their parameters read through Reflection as on the PHP twin
--FILE--
<?php

require __DIR__ . '/Attributes.inc';
require __DIR__ . '/Product.sharp';

$sharp = describe(Demo\Product::class);
$php = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
    'require ' . var_export(__DIR__ . '/Attributes.inc', true) . ';'
    . ' require ' . var_export(__DIR__ . '/Product.inc', true) . ';'
    . ' echo describe(Demo\Product::class);'
));

echo $sharp, $sharp === $php ? "same as the PHP twin\n" : "different from the PHP twin:\n$php";

$product = new Demo\Product(7);
echo $product->restock(3), "\n";

$name = (new ReflectionProperty(Demo\Product::class, 'name'))->getAttributes()[0]->newInstance();
$action = (new ReflectionMethod(Demo\Product::class, 'restock'))->getAttributes(Lib\Action::class)[0]->newInstance();
echo $name->label, ' ', $name->limit, ' ', var_export($action->write, true), ' ', $action->weight, "\n";
?>
--EXPECT--
class Demo\Product: Lib\Entity {"label":"Products","order":6}
class Demo\Product: Demo\Searchable []
class Demo\Product: Lib\Entity ["Stock"]
property $stock: Lib\Field []
property $name: Lib\Field {"label":"Name","limit":9223372036854775807}
property $id: Lib\Field {"label":null}
parameter $id of __construct: Lib\Field {"label":null}
parameter $start of __construct: Lib\Field []
method restock: Lib\Action [true,-1.5]
method restock: Demo\Retry [3]
parameter $amount of restock: Lib\Field ["amount"]
same as the PHP twin
4
Name 9223372036854775807 true -1.5

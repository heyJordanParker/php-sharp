--TEST--
PHP# properties open with virtual and abstract, an override is a property, a protected field is overridden by a property, an interface states a property, and accessor bodies run over a plain PHP parent's untyped property
--FILE--
<?php
require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/Overrides.sharp';

$square = new Overrides\Square();
echo $square->describe(), "\n";
$square->sides = -2;
echo $square->sides, ' ', $square->cornerCount(), "\n";

foreach (['kind', 'label', 'sides'] as $name) {
    $property = new ReflectionProperty(Overrides\Shape::class, $name);
    echo $name, ': ', $property->isAbstract() ? 'abstract' : 'concrete', ', ', $property->isVirtual() ? 'virtual' : 'backed', "\n";
}

// The protected field `corners` is overridden by a property, so reflection tells the two declarations apart.
foreach ([Overrides\Shape::class, Overrides\Square::class] as $class) {
    echo $class, '::corners: ', (new ReflectionProperty($class, 'corners'))->isField() ? 'field' : 'property', "\n";
}

// Model's constructor reads the override's default, and Model's own write runs the override's set body.
$order = new Overrides\Order();
echo $order->seen, '| ', $order->tableName(), "\n";
$order->setTable('  rush_orders  ');
echo $order->tableName(), "\n";
$table = new ReflectionProperty(Overrides\Order::class, 'table');
echo $table->hasType() ? 'typed' : 'untyped', ', ', count($table->getAttributes(Override::class)), " Override\n";

$person = new Overrides\Person('Ada');
echo $person->name, ' ', $person instanceof Overrides\Named ? 'named' : 'not named', "\n";

// The interface states `name` get-only, and `Account` gives it with a narrower `set` of its own.
$account = new Overrides\Account();
$account->rename('Bo');
echo $account->name, "\n";
try {
    $account->name = 'Cy';
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// An interface states `size` with `set`, and a write through the interface reaches the class's property.
echo Overrides\Box::grow(new Overrides\Box()), ' ';
$size = new ReflectionProperty(Overrides\Sized::class, 'size');
echo $size->hasHook(PropertyHookType::Set) ? 'set' : 'no set', "\n";
?>
--EXPECT--
square four sides 4
0 4
kind: abstract, virtual
label: concrete, virtual
sides: concrete, backed
Overrides\Shape::corners: field
Overrides\Square::corners: property
orders fills | orders
rush_orders
untyped, 1 Override
Ada named
Bo
Cannot modify private(set) property Overrides\Account::$name from global scope
2 set

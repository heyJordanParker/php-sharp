--TEST--
PHP# properties open with virtual and abstract, an override is a property, a protected field is overridden by a property, an interface states a property, and accessor bodies run over a plain PHP parent's untyped property
--FILE--
<?php
require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/harness/Witness.inc';
fixture('Overrides');

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

// A get-only static keeps its initial value, and one that isn't constant runs once, when the request first uses the
// class's statics.
echo Overrides\Registry::$size, ' ', Overrides\Registry::$label, ' ', Overrides\Registry::total(), "\n";
try {
    Overrides\Registry::$size = 4;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// An initial value that throws fails that first use, and runs no more, so the static stays unset.
foreach ([1, 2] as $attempt) {
    try {
        Overrides\Faulty::read();
    } catch (Throwable $error) {
        echo $attempt, ' ', $error->getMessage(), "\n";
    }
}

// A static computed property, a static `set` body and a static observer run as an instance property's do, through
// their class's hidden static methods. The initial value fills the storage, so the observer runs only on a later write.
echo Overrides\Tally::run(), ' ', Overrides\Tally::current(), "\n";
Overrides\Tally::rename('Bo');

// A static with a `get` body and no storage needs no initial value, and computes on each read.
echo Overrides\Tally::square(), "\n";

// PHP has no static hooks, so the storage of a static with accessors is private: plain PHP reaches it only through a
// method, never as a raw value its accessors never saw.
try {
    var_dump(Overrides\Tally::$total);
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// Every path through the constructor sets the get-only `T?` `note` and the settable `level`, so neither has an
// initial value, and `note` stays PHP's `readonly` with no default. The settable `T?` `nickname` writes `= null`.
$claimed = new Overrides\Member('hi', true);
$open = new Overrides\Member('hi', false);
var_dump($claimed->note, $claimed->level, $open->note, $open->level, $open->nickname);
$note = new ReflectionProperty(Overrides\Member::class, 'note');
var_dump($note->isReadOnly(), $note->hasDefaultValue());
try {
    $claimed->note = 'again';
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// A plain PHP subclass, as a test double, overrides a closed PHP# property: PHP# checks only `.sharp` code. A closed
// method runs as a `final` one, so the subclass inherits `name()`, closed_methods_final.phpt shows.
class LegacyFigure extends Overrides\Figure
{
    protected string $title = 'legacy';
}
echo (new LegacyFigure())->name(), ' ', (new Overrides\Figure())->name(), "\n";
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
3 READY 7
Cannot modify private(set) property Overrides\Registry::$size from global scope
1 no code
2 Typed static property Overrides\Faulty::$code must not be accessed before initialization
16 7
label Bo
4
Cannot access private property Overrides\Tally::$total
string(2) "hi"
int(2)
NULL
int(1)
NULL
bool(true)
bool(false)
Cannot modify readonly property Overrides\Member::$note
legacy figure

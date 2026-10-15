--TEST--
A plain PHP trait is a PHP# type: is, a parameter from plain PHP and a List element hold any object whose class, a class it extends or a trait it uses uses the trait
--FILE--
<?php

require __DIR__ . '/harness/Traits.inc';
require __DIR__ . '/Traits.sharp';
require __DIR__ . '/Inheritance.sharp';

trait Wraps
{
    use Lib\Copies;
}

class LegacyCopy
{
    use Lib\Copies;
}

final class SubCopy extends LegacyCopy
{
}

final class Wrapped
{
    use Wraps;
}

class Greeting implements Demo\Greets
{
    public function hi(): string
    {
        return 'hi';
    }
}

final class CopyingGreeting extends Greeting
{
    use Lib\Copies;
}

use Demo\Seeder;

foreach ([new Demo\Draft(), Demo\Shade::Light, new LegacyCopy(), new SubCopy(), new Wrapped(), new stdClass(), 'Lib\Copies'] as $value) {
    echo is_object($value) ? get_class($value) : var_export($value, true), ': ', var_export(Seeder::owns($value), true), "\n";
}

echo Seeder::seed(new SubCopy()), "\n";
echo Seeder::count([new LegacyCopy(), new Wrapped()]), "\n";
// A struct's and an event's headers name the trait, as a class's does.
echo (new Demo\Seal(3))->copy(), "\n", (new Demo\Copied(4))->copy(), "\n";
var_dump(Seeder::owns(new Demo\Seal(3)), Seeder::owns(new Demo\Copied(4)));

// A type parameter bounded by the trait, alone or beside an interface, checks a call from plain PHP against its bound.
echo Seeder::bound(new Wrapped()), "\n", Seeder::both(new CopyingGreeting()), "\n";
foreach ([
    fn () => Seeder::seed(new stdClass()),
    fn () => Seeder::count([new LegacyCopy(), new stdClass()]),
    fn () => Seeder::bound(new stdClass()),
    fn () => Seeder::both(new Greeting()),
] as $call) {
    try {
        $call();
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECTF--
Demo\Draft: true
Demo\Shade: true
LegacyCopy: true
SubCopy: true
Wrapped: true
stdClass: false
'Lib\\Copies': false
copied SubCopy
2
copied Demo\Seal
copied Demo\Copied
bool(true)
bool(true)
copied Wrapped
hi copied CopyingGreeting
Demo\Seeder::seed(): Argument #1 ($owner) must be of type Lib.Copies, stdClass given, called in %s on line %d
Demo\Seeder::count(): Argument #1 ($owners) must be of type List<Lib.Copies>, array given: [1] expected Lib.Copies, got object stdClass, called in %s on line %d
Demo\Seeder::bound(): Argument #1 ($owner) must be of type Lib.Copies, stdClass given, called in %s on line %d
Demo\Seeder::both(): Argument #1 ($owner) must be of type Demo.Greets & Lib.Copies, Greeting given, called in %s on line %d

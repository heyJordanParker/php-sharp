--TEST--
Method.invoke, Property.get and Property.set reach a member as a direct use from the calling code would, check each argument and value against its PHP# type in strict mode, run an override and a computed property's get, and refuse a receiver of another class
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ReflectionCalls');

use App\Calls\Calls;
use App\Calls\Register;
use App\Calls\Till;
use App\Elsewhere\Outside;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        var_export($body());
        echo "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

$till = new Till();
$register = new Register();

attempt('add', fn () => Calls::method('add')->invoke($till, [2, [3, 4]]));
attempt('add "2"', fn () => Calls::method('add')->invoke($till, ['2', [3]]));
attempt('add ["a"]', fn () => Calls::method('add')->invoke($till, [2, ['a']]));
attempt('add three', fn () => Calls::method('add')->invoke($till, [2, [3], 9]));
attempt('add one', fn () => Calls::method('add')->invoke($till, [2]));
attempt('add on another class', fn () => Calls::method('add')->invoke(new stdClass(), [2, [3]]));
attempt('name on the subclass', fn () => Calls::method('name')->invoke($register, []));
attempt('kind', fn () => Calls::method('kind')->invoke($register, []));
attempt('secret from plain PHP', fn () => Calls::method('secret')->invoke($till, []));
attempt('guarded from plain PHP', fn () => Calls::method('guarded')->invoke($till, []));
attempt('guarded from the subclass', fn () => Register::guardedFrom($till));
attempt('near from plain PHP', fn () => Calls::method('near')->invoke($till, []));
attempt('near from App.Calls', fn () => Calls::nearFromInside($till));
attempt('near from App.Elsewhere', fn () => Outside::near($till));

attempt('get total', fn () => Calls::property('total')->get($till));
attempt('set total 5', fn () => Calls::property('total')->set($till, 5));
attempt('get total', fn () => Calls::property('total')->get($till));
attempt('set total "6"', fn () => Calls::property('total')->set($till, '6'));
attempt('get doubled', fn () => Calls::property('doubled')->get($till));
attempt('set label', fn () => Calls::property('label')->set($till, 'other'));
attempt('set amounts ["a"]', fn () => Calls::property('amounts')->set($till, ['a']));
attempt('set amounts [1, 2]', fn () => Calls::property('amounts')->set($till, [1, 2]));
attempt('get amounts', fn () => Calls::property('amounts')->get($till));
attempt('get count from plain PHP', fn () => Calls::property('count')->get($till));
attempt('get total on another class', fn () => Calls::property('total')->get(new stdClass()));
attempt('stock from plain PHP', fn () => Calls::property('stock')->get($till));
attempt('stock from App.Calls', fn () => Calls::stockFromInside($till));
attempt('stock from App.Elsewhere', fn () => Outside::stock($till));
attempt('restock from App.Elsewhere', fn () => Outside::restock($till));
?>
--EXPECT--
add: 10
add "2": TypeError: App\Calls\Till::add(): Argument #1 ($amount) must be of type int, string given
add ["a"]: TypeError: App\Calls\Till::add(): Argument #2 ($more) must be of type List<int>, array given: [0] expected int, got string 'a'
add three: ArgumentCountError: `invoke` passes App\Calls\Till::add() at most 2 arguments, and was given 3.
add one: ArgumentCountError: Too few arguments to function App\Calls\Till::add(), 1 passed and exactly 2 expected
add on another class: TypeError: `invoke` takes a receiver of `App\Calls\Till`, and was given `stdClass`.
name on the subclass: 'register'
kind: 'till'
secret from plain PHP: Error: Call to private method App\Calls\Till::secret() from global scope
guarded from plain PHP: Error: Call to protected method App\Calls\Till::guarded() from global scope
guarded from the subclass: 8
near from plain PHP: 9
near from App.Calls: 9
near from App.Elsewhere: Error: Call to internal method App\Calls\Till::near() from App\Elsewhere\Outside, outside namespace App\Calls
get total: 0
set total 5: NULL
get total: 5
set total "6": TypeError: Cannot assign string to property App\Calls\Till::$total of type int
get doubled: 2
set label: Error: Cannot modify readonly property App\Calls\Till::$label
set amounts ["a"]: TypeError: Cannot assign array to property App\Calls\Till::$amounts of type List<int>: [0] expected int, got string 'a'
set amounts [1, 2]: NULL
get amounts: array (
  0 => 1,
  1 => 2,
)
get count from plain PHP: Error: Cannot access private property App\Calls\Till::$count
get total on another class: TypeError: `get` takes a receiver of `App\Calls\Till`, and was given `stdClass`.
stock from plain PHP: 3
stock from App.Calls: 3
stock from App.Elsewhere: Error: Cannot access internal property App\Calls\Till::$stock from App\Elsewhere\Outside, outside namespace App\Calls
restock from App.Elsewhere: Error: Cannot access internal property App\Calls\Till::$stock from App\Elsewhere\Outside, outside namespace App\Calls

--TEST--
The checker refuses each operator, equality, condition and pattern line PHP# gives no meaning, and the engine shows every refusal with its line, in the order of the file
--FILE--
<?php
require __DIR__ . '/project.inc';

$files = [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"inc\"]\n",
    'Billing.inc' => file_get_contents(__DIR__ . '/harness/Billing.inc'),
    // The fixtures sort Lists and read Maps, whose members the standard library declares.
    'vendor/heyjordanparker/php-sharp-composer/composer.json' => '{"name": "heyjordanparker/php-sharp-composer"}',
];
foreach (['ListMembers', 'MapMembers'] as $members) {
    $files["vendor/heyjordanparker/php-sharp-composer/library/Sharp/$members.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$members.sharp");
}
foreach (glob(__DIR__ . '/Operators/*.sharp') as $file) {
    $files['Operators/' . basename($file)] = file_get_contents($file);
}
$root = compile_project('operators-refusal', $files);
file_put_contents("$root/Coupon.sharp", <<<'SHARP'
namespace Billing;

public class Coupon
{
    public static bool operator ==(Coupon a, Coupon b) => true;
}

SHARP);
file_put_contents("$root/Refusals.sharp", <<<'SHARP'
namespace Billing;

public class Refusals
{
    public bool sameCart(Cart cart, Cart otherCart) => cart == otherCart;

    public int unflagged(int permissions, int write)
    {
        if (permissions & write) {
            return 1;
        }
        return 0;
    }

    public bool shown(bool isAdmin, bool isOwner)
    {
        const shown = isAdmin | isOwner;
        return shown;
    }

    public string label(Status status)
    {
        string label = match (status) {
            Status.Active => "on",
            Status.Paused => "paused",
        };
        return label;
    }

    public string size(int n)
    {
        string size = match (n) {
            < 10 => "small",
        };
        return size;
    }

    public Any? total(Tab order)
    {
        if (order.total is int) { return order.total + 1; }
        return null;
    }

    public float radius(Any? shape)
    {
        let area = 0.0;
        if (shape is Circle circle) {
            area = circle.radius;
        }
        return circle.radius;
    }

    public bool undesigned(Any? entity)
    {
        if (!entity is HasDesign) {
            return true;
        }
        return false;
    }

    public bool unsettled(Payment result)
    {
        if (result is not Paid or Refunded) {
            return true;
        }
        return false;
    }

    public bool nullable(Any? x)
    {
        if (x is int?) {
            return true;
        }
        return false;
    }

    public bool squared(Circle circle) => circle is Square;

    public int drained(int remaining)
    {
        while (remaining) {
            remaining -= 1;
        }
        return remaining;
    }

    public bool earlier(bool a, bool b) => a < b;

    public bool sooner(Status a, Status b) => a < b;

    public int ranked(Status a, Status b) => a <=> b;

    public bool shorter(List<int> lines, List<int> other) => lines < other;
}

SHARP);
refusal(["$root/Coupon.sharp", "$root/Refusals.sharp"], ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile']);
remove_project($root);
?>
--EXPECT--
CompileError: Coupon.sharp has 1 error:
line 5: `Coupon` declares `operator ==` without `public int hash()`: declare it in `Coupon` or a parent, so equal values hash alike.
CompileError: Refusals.sharp has 16 errors:
line 5: `==` cannot compare `Cart` with `Cart`: `Cart` declares no `operator ==`.
  `==` on a class instance exists only where its class declares `operator ==`.
  help: Use `===` to test whether both sides are the same object.
line 9: `if` takes a `bool`, but this is `int`.
  PHP# conditions are `bool`, so PHP's truthiness never applies.
  help: Compare the value, as in `count > 0` or `name != ""`.
line 17: `|` takes `int`, but both sides are `bool`: write `||` for two `bool` values.
  PHP# gives `|`, `&`, `^`, `~`, `<<`, `>>` and their compound forms to `int` only.
  help: `||`, `&&` and `!=` join two `bool` values.
line 23: This `match` misses `Status.Closed`.
  A `match` may leave out `default` only when its arms handle every case of an enum or a sealed interface, or every value of a `List`.
  help: Add an arm for each value it misses, or add `default => …` as the last arm.
line 32: A `match` needs a `default` arm.
  A `match` may leave out `default` only when its arms handle every case of an enum or a sealed interface, or every value of a `List`.
  help: Add `default => …` as the last arm.
line 40: `order.total` is `Any?` here: a property is not narrowed, because it could change between the test and the use.
  PHP# narrows a local or a parameter after `is`, never a property.
  help: Copy the value into a local first: test it with a name, as in `if (order.total is int t)`, and use `t`.
line 50: `circle` exists only where `shape is Circle circle` is true.
  help: A pattern variable holds a value only where its pattern matches. Test the value again here, or declare a local for `circle` before the test.
line 55: Write `entity is not HasDesign`: `!` applies to `entity` before `is` tests it.
  `is` binds with the comparisons, so `!entity is HasDesign` reads as `(!entity) is HasDesign`, and a negative test is written `is not`.
line 63: Write `not (Paid or Refunded)`, or `(not Paid) or Refunded`.
  In a pattern, `not` beside `or` needs parentheses. C# reads `not Paid or Refunded` as `(not Paid) or Refunded`, where `or Refunded` adds nothing.
line 71: A type pattern is never nullable: null never matches a type.
  help: Test for null with `x == null`, or join both with `or`, as in `x is int or null`.
line 77: This pattern never matches the value it tests.
  PHP# makes a pattern that can never match an error, as C# does (CS8121).
  help: Remove the pattern, or test a value that can match it.
line 81: `while` takes a `bool`, but this is `int`.
  PHP# conditions are `bool`, so PHP's truthiness never applies.
  help: Compare the value, as in `count > 0` or `name != ""`.
line 87: `<` orders numbers, strings, and classes, structs and enums that declare `operator <=>`, and `bool` is none of them.
  A `bool` and a collection have no order, and an enum's cases have one only where it declares `operator <=>`.
  help: Order a number or a string taken from the value instead.
line 89: `<` orders numbers, strings, and classes, structs and enums that declare `operator <=>`, and `Status` is none of them.
  A `bool` and a collection have no order, and an enum's cases have one only where it declares `operator <=>`.
  help: Declare `public static int operator <=>(Status a, Status b)` in `Status`: an enum orders its cases once it declares `operator <=>`.
line 91: `<=>` orders numbers, strings, and classes, structs and enums that declare `operator <=>`, and `Status` is none of them.
  A `bool` and a collection have no order, and an enum's cases have one only where it declares `operator <=>`.
  help: Declare `public static int operator <=>(Status a, Status b)` in `Status`: an enum orders its cases once it declares `operator <=>`.
line 93: `<` orders numbers, strings, and classes, structs and enums that declare `operator <=>`, and `List<int>` is none of them.
  A `bool` and a collection have no order, and an enum's cases have one only where it declares `operator <=>`.
  help: Order a number or a string taken from the value instead.

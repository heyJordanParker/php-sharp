--TEST--
The checker refuses each operator, equality, condition and pattern line PHP# gives no meaning, and the engine shows every refusal with its line, in the order of the file
--FILE--
<?php
require __DIR__ . '/project.inc';

$files = [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"inc\"]\n",
    'Billing.inc' => file_get_contents(__DIR__ . '/harness/Billing.inc'),
];
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
line 9: `if` takes a `bool`, but this is `int`.
line 17: `|` takes `int`, but both sides are `bool`: write `||` for two `bool` values.
line 23: This `match` misses `Status.Closed`.
line 32: A `match` needs a `default` arm.
line 40: `order.total` is `Any?` here: a property is not narrowed, because it could change between the test and the use.
line 50: `circle` exists only where `shape is Circle circle` is true.
line 55: Write `entity is not HasDesign`: `!` applies to `entity` before `is` tests it.
line 63: Write `not (Paid or Refunded)`, or `(not Paid) or Refunded`.
line 71: A type pattern is never nullable: null never matches a type.
line 77: This pattern never matches the value it tests.
line 81: `while` takes a `bool`, but this is `int`.
line 87: `<` orders numbers, strings, and classes and enums that declare `operator <=>`, and `bool` is none of them.
line 89: `<` orders numbers, strings, and classes and enums that declare `operator <=>`, and `Status` is none of them.
line 91: `<=>` orders numbers, strings, and classes and enums that declare `operator <=>`, and `Status` is none of them.
line 93: `<` orders numbers, strings, and classes and enums that declare `operator <=>`, and `List<int>` is none of them.

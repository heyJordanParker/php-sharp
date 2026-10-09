--TEST--
The checker refuses each operator, equality, condition and pattern line PHP# gives no meaning, and the engine shows every refusal with its line, in the order of the file
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('operators-refusal', [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"inc\"]\n",
    'Billing.inc' => file_get_contents(__DIR__ . '/harness/Billing.inc'),
    'Operators.sharp' => file_get_contents(__DIR__ . '/Operators.sharp'),
]);
file_put_contents("$root/Refusals.sharp", <<<'SHARP'
namespace Billing;

public class Coupon
{
    public static bool operator ==(Coupon a, Coupon b) => true;
}

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

    public int nested(bool a, bool c, int b, int d, int e) => a ? b : c ? d : e;
}

SHARP);
refusal("$root/Refusals.sharp", ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile']);
remove_project($root);
?>
--EXPECT--
CompileError: Refusals.sharp has 14 errors:
line 5: `Coupon` declares `operator ==` without `public int hash()`: declare it in `Coupon` or a parent, so equal values hash alike.
line 10: `==` cannot compare `Cart` with `Cart`: `Cart` declares no `operator ==`.
line 14: `if` takes a `bool`, but this is `int`.
line 22: `|` takes `int`, but both sides are `bool`: write `||` for two `bool` values.
line 28: This `match` misses `Status.Closed`.
line 37: A `match` needs a `default` arm.
line 45: `order.total` is `Any?` here: a property is not narrowed, because it could change between the test and the use.
line 55: `circle` exists only where `shape is Circle circle` is true.
line 60: Write `entity is not HasDesign`: `!` applies to `entity` before `is` tests it.
line 68: Write `not (Paid or Refunded)`, or `(not Paid) or Refunded`.
line 76: A type pattern is never nullable: null never matches a type.
line 82: This pattern never matches the value it tests.
line 86: `while` takes a `bool`, but this is `int`.
line 92: Unparenthesized `a ? b : c ? d : e` is not supported. Use either `(a ? b : c) ? d : e` or `a ? b : (c ? d : e)`.

--TEST--
A generic method that plain PHP, call_user_func, array_map or a plain PHP property hook calls gets the bounds of its type parameters, and one a PHP# property hook calls gets the hook's type arguments
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Box;
use App\Order;
use Calls\Labeled;
use Calls\Repository;

class PlainShelf
{
    public function __construct(private Order $order)
    {
    }

    public Box $boxed {
        get => Repository::box($this->order);
    }
}

$order = new Order(1);
echo shown(Repository::box($order)), "\n";
echo shown(call_user_func([Repository::class, 'box'], $order)), "\n";
echo shown(array_map([Repository::class, 'box'], [$order])[0]), "\n";
echo shown(array_map(Repository::box(...), [$order])[0]), "\n";
echo shown((new PlainShelf($order))->boxed), "\n";
echo shown((new Labeled($order))->boxed), "\n";
?>
--EXPECT--
App\Box<Any?>
App\Box<Any?>
App\Box<Any?>
App\Box<Any?>
App\Box<Any?>
App\Box<App.Order>

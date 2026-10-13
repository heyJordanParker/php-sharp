--TEST--
A method reads the type arguments it runs with, its own and its class's, in is, as, a match arm, typeof, new and a static member
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use App\Box;
use App\Order;
use App\SharedOrder;
use Calls\Repository;

// The methods read the type arguments they run with: Calls\Inspection<App.Order>'s, and Repository's generic calls'. A
// hot function's second call runs the code the tracing JIT compiled on its first.
$order = new Order(1);
foreach ([1, 2] as $round) {
    $inspection = Repository::orderInspection();
    var_dump($inspection->holds(new Order(1)), $inspection->holds(new SharedOrder(1)));
    var_dump($inspection->kept(new SharedOrder(1)), $inspection->kept(new Order(4))->id);
    // A Box plain PHP creates holds Box's bound, so it is no Box<App.Order>.
    echo $inspection->arm(new Order(1)), ', ', $inspection->arm(new Box()), ', ', $inspection->arm(Repository::written($order)), "\n";
    echo $inspection->kind(), ', ', $inspection->limit(), ', ', $inspection->made(5)::class, ' ', $inspection->made(5)->id, "\n";
    var_dump(Repository::orderBoxed(Repository::written($order)), Repository::orderBoxed(new Box()));
    echo Repository::freshOrder(6)::class, ', ', Repository::orderLimit(), "\n";
}
?>
--EXPECT--
bool(true)
bool(false)
NULL
int(4)
item, other, box
App\Order, 20, App\Order 5
bool(true)
bool(false)
App\Order, 20
bool(true)
bool(false)
NULL
int(4)
item, other, box
App\Order, 20, App\Order 5
bool(true)
bool(false)
App\Order, 20

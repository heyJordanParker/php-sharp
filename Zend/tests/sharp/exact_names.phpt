--TEST--
A PHP# class, and any class below it, finds a method only by its exact name: `total` and `Total` are two methods, every caller, plain PHP included, names the one it means, another case is an Error that names the declared method, and the engine still finds each method it calls by PHP's own name. A PHP# class is found only by its exact name, by `new`, `instanceof`, `class_exists`, reflection, unserialize and the autoloader
--FILE--
<?php
require __DIR__ . '/harness/Framework.inc';
require __DIR__ . '/harness/Grid.inc';
require __DIR__ . '/harness/Magic.inc';
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ExactNames');

function attempt(callable $call): void
{
    try {
        echo var_export($call(), true), "\n";
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo "== twins\n";
$order = new ExactNames\Order();
echo $order->total(), ' ', $order->Total(), ' ', $order->sum(), "\n";
foreach (['total', 'Total'] as $name) {
    echo $order->$name(), ' ', call_user_func([$order, $name]), ' ', ($order->$name(...))(), ' ',
        (new ReflectionMethod($order, $name))->invoke($order), "\n";
}
echo ExactNames\Order::kind(), ' ', call_user_func('ExactNames\Order::kind'), ' ', call_user_func([ExactNames\Order::class, 'kind']), "\n";

echo "== another case reaches nothing\n";
attempt(fn () => $order->TOTAL());
attempt(fn () => $order->sum2());
attempt(fn () => ExactNames\Order::Kind());
$name = 'tOTAL';
attempt(fn () => $order->$name());
var_dump(
    method_exists($order, 'Total'),
    method_exists($order, 'TOTAL'),
    method_exists(ExactNames\Order::class, 'kind'),
    method_exists(ExactNames\Order::class, 'KIND'),
    is_callable([$order, 'total']),
    is_callable([$order, 'TOTAL']),
    is_callable('ExactNames\Order::KIND'),
    (new ReflectionClass($order))->hasMethod('Total'),
    (new ReflectionClass($order))->hasMethod('TOTAL'),
);
attempt(fn () => new ReflectionMethod($order, 'TOTAL'));
attempt(fn () => call_user_func([$order, 'TOTAL']));

echo "== a plain PHP parent's own code and its __call, under a PHP# child\n";
$job = new ExactNames\Job();
echo $job->run(), ' | ', $job->describeIt(), ' | ', $job->describeit(), ' | ', $job->DescribeIt(), "\n";

echo "== a plain PHP class below a PHP# class\n";
class LegacyOrder extends ExactNames\Order
{
    public function extra(): int
    {
        return $this->total() * 10 + $this->Total();
    }
}
$legacy = new LegacyOrder();
echo $legacy->extra(), ' ', $legacy->Total(), "\n";
attempt(fn () => $legacy->EXTRA());
attempt(fn () => $legacy->tOtal());

echo "== protocols the engine calls\n";
$shelf = new ExactNames\Shelf();
var_dump(isset($shelf['book']), isset($shelf['pen']), $shelf['book']);
$shelf['pen'] = 'blue';
unset($shelf['pen']);
foreach ($shelf as $key => $value) {
    echo "$key => $value\n";
}
echo count($shelf), ' ', json_encode($shelf), ' ', "$shelf", "\n";

echo "== a PHP# class is found only by its exact name\n";
// A plain PHP class whose name differs from a PHP# class's only in case is a second class, which keeps PHP's own rule.
eval('namespace ExactNames; class ORDER { public function kind(): string { return "plain ORDER"; } }');
$built = 'ExactNames\\' . 'Order';
echo get_class(new ExactNames\Order()), ' | ', get_class(new $built()), ' | ', (new ExactNames\ORDER())->kind(), ' | ',
    get_class(new ExactNames\order()), "\n";
$wrong = 'ExactNames\\' . 'SHELF';
var_dump(
    class_exists('ExactNames\Shelf'),
    class_exists('\ExactNames\Shelf'),
    class_exists($wrong),
    class_exists($wrong, false),
    $shelf instanceof $wrong,
    is_a($shelf, $wrong),
    is_a($shelf, 'ExactNames\Shelf'),
    ExactNames\Shelf::class,
);
attempt(fn () => new $wrong());
attempt(fn () => new ReflectionClass($wrong));
echo (new ReflectionClass('ExactNames\Shelf'))->getName(), ' ', get_class(unserialize('O:16:"ExactNames\SHELF":0:{}')), "\n";
// The autoloader is asked for the name as written, so another case loads nothing.
var_dump(class_exists('ExactNames\CHARM'), class_exists('ExactNames\Charm'));

echo "== magic methods a PHP# class inherits\n";
$charm = new ExactNames\Charm();
echo $charm->charm(), ' | ', $charm->missing, ' | ', $charm->spell(), ' | ', $charm(), ' | ', $charm, "\n";
$copy = clone $charm;
$back = unserialize(serialize($charm));
echo $back->label, "\n";
unset($copy, $back);
?>
--EXPECT--
== twins
1 2 3
1 1 1 1
2 2 2 2
order order order
== another case reaches nothing
Error: Call to undefined method ExactNames\Order::TOTAL(); did you mean Total()?
Error: Call to undefined method ExactNames\Order::sum2()
Error: Call to undefined method ExactNames\Order::Kind(); did you mean kind()?
Error: Call to undefined method ExactNames\Order::tOTAL(); did you mean Total()?
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
ReflectionException: Method ExactNames\Order::TOTAL() does not exist
TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, class ExactNames\Order does not have a method "TOTAL"
== a plain PHP parent's own code and its __call, under a PHP# child
job framework | framework | a second method beside describeIt | forwarded DescribeIt
== a plain PHP class below a PHP# class
12 2
Error: Call to undefined method LegacyOrder::EXTRA(); did you mean extra()?
Error: Call to undefined method LegacyOrder::tOtal(); did you mean Total()?
== protocols the engine calls
bool(true)
bool(false)
string(6) "a book"
offsetSet
offsetUnset
0 => 0
1 => 10
3 "shelf" a shelf
== a PHP# class is found only by its exact name
ExactNames\Order | ExactNames\Order | plain ORDER | ExactNames\ORDER
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
string(16) "ExactNames\Shelf"
Error: Class "ExactNames\SHELF" not found
ReflectionException: Class "ExactNames\SHELF" does not exist
ExactNames\Shelf __PHP_Incomplete_Class
bool(false)
bool(true)
== magic methods a PHP# class inherits
__construct
charm | __get missing | __call spell | __invoke | __toString
__clone
magic again
__destruct
__destruct
__destruct

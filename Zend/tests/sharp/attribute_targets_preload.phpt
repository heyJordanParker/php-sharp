--TEST--
A preloaded PHP# attribute target puts its list on the part of a declaration it names, and an accessor's attributes go on its hook, on every accessor form and on an event's and a data case's parameters
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/attribute_targets_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(AttributeTargets\Ledger::class, false));
$labels = fn (Reflector $reflector) => implode(',', array_map(
    fn (ReflectionAttribute $attribute) => $attribute->newInstance()->label,
    $reflector->getAttributes(),
));
$class = AttributeTargets\Ledger::class;
$total = new ReflectionProperty($class, 'total');
$set = $total->getHook(PropertyHookType::Set);
$constructor = new ReflectionMethod($class, '__construct');
var_dump($labels(new ReflectionClass($class)));
var_dump($labels(new ReflectionClassConstant($class, 'LIMIT')));
var_dump($labels($total));
var_dump($labels($total->getHook(PropertyHookType::Get)));
var_dump($labels($set), $labels($set->getParameters()[0]), (string) $set->getParameters()[0]->getType());
var_dump($labels((new ReflectionProperty($class, 'name'))->getHook(PropertyHookType::Get)));
var_dump($labels(new ReflectionMethod($class, 'opened#get')));
var_dump($labels(new ReflectionProperty($class, 'id')), $labels($constructor->getParameters()[0]));
var_dump($labels((new ReflectionProperty($class, 'stock'))->getHook(PropertyHookType::Get)));
var_dump($labels(new ReflectionMethod($class, 'run')), $labels((new ReflectionMethod($class, 'run'))->getParameters()[0]));
$size = new ReflectionProperty(AttributeTargets\Sized::class, 'size');
var_dump($labels($size->getHook(PropertyHookType::Get)), $labels($size->getHook(PropertyHookType::Set)->getParameters()[0]));
// The hooks that carry the attributes read and write the storage.
$ledger = new AttributeTargets\Ledger(7, 2);
$ledger->total = 5;
$ledger->name = 'cash';
var_dump($ledger->total, $ledger->name, $ledger->id, $ledger->stock);
// A static auto-property's accessors with attributes are its hidden methods, and a get-only one's `get;` is its hook.
var_dump($labels(new ReflectionMethod($class, 'counter#get')), $labels((new ReflectionMethod($class, 'counter#set'))->getParameters()[0]));
$code = new ReflectionProperty($class, 'code');
var_dump($labels($code->getHook(PropertyHookType::Get)), $code->isReadOnly(), $code->isProtectedSet(), $ledger->code);
try {
    $ledger->code = 'changed';
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
// A Map's index writes and `remove` reach the storage through a hook an attribute makes.
var_dump((new AttributeTargets\Tally())->count());
// An event's and a data case's parameters declare properties, and each list goes on the part its target names.
$settled = new AttributeTargets\Settled(1, 2, 'EUR');
$parameters = (new ReflectionMethod(AttributeTargets\Settled::class, '__construct'))->getParameters();
foreach (['orderId', 'amount', 'currency'] as $i => $name) {
    var_dump($labels(new ReflectionProperty(AttributeTargets\Settled::class, $name)), $labels($parameters[$i]), $settled->$name);
}
$paid = new ReflectionClass(AttributeTargets\Payment\Paid::class);
var_dump($labels($paid->getProperty('transactionId')), $labels($paid->getConstructor()->getParameters()[0]));
var_dump((new AttributeTargets\Payment\Paid('t1'))->transactionId);
?>
--EXPECT--
bool(true)
string(6) "ledger"
string(5) "limit"
string(5) "total"
string(4) "read"
string(5) "write"
string(6) "amount"
string(3) "int"
string(5) "named"
string(6) "opened"
string(4) "kept"
string(5) "given"
string(7) "stocked"
string(3) "run"
string(5) "times"
string(8) "measured"
string(8) "new size"
int(5)
string(4) "cash"
int(7)
int(2)
string(7) "counted"
string(4) "next"
string(5) "coded"
bool(false)
bool(true)
string(2) "L7"
Cannot modify protected(set) property AttributeTargets\Ledger::$code from global scope
int(11)
string(0) ""
string(5) "order"
int(1)
string(6) "amount"
string(0) ""
int(2)
string(4) "both"
string(4) "both"
string(3) "EUR"
string(4) "kept"
string(5) "given"
string(2) "t1"

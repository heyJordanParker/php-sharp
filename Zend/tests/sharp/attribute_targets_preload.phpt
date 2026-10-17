--TEST--
A preloaded PHP# attribute target puts its list on the part of a declaration it names, and an accessor's attributes go on its hook
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

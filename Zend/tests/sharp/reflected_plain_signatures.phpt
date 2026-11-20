--TEST--
Reflection reads every type a plain PHP signature declares, a PHP# class's inherited one included: callable as a function type of any shape, iterable, object, mixed, self, static, never, void, null, true, false, an intersection and a DNF type
--FILE--
<?php
require __DIR__ . '/harness/Signatures.inc';
require __DIR__ . '/vendor/autoload.php';

foreach ([ReflectedTypes\Row::class, Lib\Signatures::class] as $class) {
    foreach (ReflectedTypes\Report::members($class) as $line) {
        echo $line, "\n";
    }
}
var_dump(ReflectedTypes\Report::callables());
?>
--EXPECT--
parse(value: Map<string, (Any)?>|Object): ReflectedTypes.Row
tryParse(value: Map<string, (Any)?>|Object): (ReflectedTypes.Row)?
tap(callback: callable): ReflectedTypes.Row
done: (Closure)?
call(callback: callable): (callable)?
each(items: Map<int|string, (Any)?>|Traversable): Map<int|string, (Any)?>|Traversable
any(value: Object, extra: (Any)?): (Any)?
same(other: Lib.Signatures): Lib.Signatures
stop(): never
nothing(): void
both(value: Lib.Named & Lib.Sized): (Lib.Named & Lib.Sized)?
either(value: Lib.Named & Lib.Sized|int): bool|string
always(): bool
empty(): (never)?
array(7) {
  [0]=>
  string(8) "callable"
  [1]=>
  bool(true)
  [2]=>
  array(0) {
  }
  [3]=>
  bool(true)
  [4]=>
  bool(false)
  [5]=>
  bool(true)
  [6]=>
  string(80) "Lib\Model::tap(): Argument #1 ($callback) must be of type callable, string given"
}

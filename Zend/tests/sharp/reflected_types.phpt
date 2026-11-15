--TEST--
A member's type, read through properties(), methods() and parameters(), is its declared type whole, which the class value's own members take apart: a Class<T>'s argument, a function type's parameters and result, a union's and an intersection's members, a nullable type argument's arguments and its ?, a tuple's element names, and a type parameter with its bound
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ReflectedTypes');

foreach (ReflectedTypes\Report::members(ReflectedTypes\Holder::class) as $line) {
    echo $line, "\n";
}
foreach (ReflectedTypes\Report::members(ReflectedTypes\Crate::class) as $line) {
    echo $line, "\n";
}
var_dump(ReflectedTypes\Report::facts());
?>
--EXPECT--
toneClass: Class<ReflectedTypes.Tone>
reader: (Function<int(string, ReflectedTypes.Box<int>)>)?
mixed: ReflectedTypes.Box<ReflectedTypes.Tone>|ReflectedTypes.Tone
boxes: List<(ReflectedTypes.Box<ReflectedTypes.Tone>)?>
pair: [string method, int count]
swap(toneClass: Class<ReflectedTypes.Tone>, mixed: ReflectedTypes.Box<ReflectedTypes.Tone>|ReflectedTypes.Tone, boxes: List<(ReflectedTypes.Box<ReflectedTypes.Tone>)?>, pair: [string method, int count]): (Function<int(string, ReflectedTypes.Box<int>)>)?
join(joined: ReflectedTypes.Labeled & ReflectedTypes.Mark): void
item: (TItem : ReflectedTypes.Mark)?
first(items: List<TItem : ReflectedTypes.Mark>): (TItem : ReflectedTypes.Mark)?
parse(value: Map<string, (Any)?>|Object): ReflectedTypes.Crate<TItem : ReflectedTypes.Mark>
tryParse(value: Map<string, (Any)?>|Object): (ReflectedTypes.Crate<TItem : ReflectedTypes.Mark>)?
array(25) {
  [0]=>
  bool(true)
  [1]=>
  string(5) "Class"
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  bool(true)
  [7]=>
  bool(true)
  [8]=>
  bool(true)
  [9]=>
  bool(true)
  [10]=>
  bool(true)
  [11]=>
  bool(true)
  [12]=>
  array(2) {
    [0]=>
    string(6) "method"
    [1]=>
    string(5) "count"
  }
  [13]=>
  bool(true)
  [14]=>
  bool(true)
  [15]=>
  string(5) "TItem"
  [16]=>
  bool(true)
  [17]=>
  array(1) {
    [0]=>
    string(5) "TItem"
  }
  [18]=>
  bool(true)
  [19]=>
  bool(true)
  [20]=>
  bool(true)
  [21]=>
  bool(true)
  [22]=>
  bool(true)
  [23]=>
  bool(true)
  [24]=>
  bool(true)
}
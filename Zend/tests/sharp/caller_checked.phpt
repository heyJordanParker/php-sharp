--TEST--
A plain PHP method that declares no return type implements a PHP# interface's member, and each PHP# call of the member checks the result: through the interface, the plain PHP class, a PHP# subclass and its this, a null-safe call, each collection alone at any depth, and the bound type of a generic method's or a generic class's static method's result where its call binds it, naming the type the code wrote. Each member of the Set or the Map such a call gives as the PHP array it crosses into runs on the object, a Map's keys keeping their type
--FILE--
<?php
require __DIR__ . '/caller_checked.inc';
?>
--EXPECT--
through an interface: through
through an interface, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
null-safe: null-safe
null-safe, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
null-safe, of null: none
the plain class: concrete
the plain class, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
a PHP# subclass: subclass
a PHP# subclass, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
this in a PHP# subclass: this
this in a PHP# subclass, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
never: LogicException: stopped
List: 2
List, given text: TypeError: CallerChecked.Taggable.ids(): Return value must be of type List<int>, string returned
List, given strings, whose elements the check leaves: 3
Set: 3
Set, given text: TypeError: CallerChecked.Taggable.labels(): Return value must be of type Set<string>, string returned
Map: 1
Map, given text: TypeError: CallerChecked.Taggable.counts(): Return value must be of type Map<string, int>, string returned
Iterable: 6
Iterable, given text: TypeError: CallerChecked.Taggable.stream(): Return value must be of type Iterable<int>, string returned
tuple: 2 two
tuple, given text: TypeError: CallerChecked.Taggable.pair(): Return value must be of type [List<int>, string], string returned
tuple, given text for its List: TypeError: CallerChecked.Taggable.pair(): Return value must be of type [List<int>, string], array returned: [0] expected List<int>, got string 'text'
tuple, given strings in its List, whose elements the check leaves: 1 two
a generic class's static method: 42
a generic class's static method, given text: TypeError: CallerChecked.Repo.first(): Return value must be of type int, string returned
a method's type parameter: 42
a method's type parameter, given text: TypeError: CallerChecked.Generic.readFrom(): Return value must be of type int, string returned
Set count: 3
Set isEmpty: false
Set isNotEmpty: true
Set contains: [true,false]
Set toList: ["a","b","c"]
Set filter: ["a","c"]
Set map: ["a!","b!","c!"]
Set sumOf: 5
Set first: "b"
Set any: true
Set sortedBy: ["b","a","c"]
Set add: [true,false,["a","b","c","d"]]
Set remove: [true,false,["a","c"]]
Set clear: 0
Map count: 3
Map isEmpty: false
Map isNotEmpty: true
Map get: [2,3,null]
Map has: [true,true,false]
Map keys: ["a","b","10"]
Map values: [1,2,3]
Map toList: [["a",1],["b",2],["10",3]]
Map filter: [["b",2],["10",3]]
Map map: [2,4,6]
Map sumOf: 6
Map first: 2
Map any: true
Map groupBy: [[1,[1,3]],[0,[2]]]
Map associateBy: [[10,1],[20,2],[30,3]]
Map sortedBy: [3,2,1]
Map remove: [true,false,[["b",2],["10",3]]]

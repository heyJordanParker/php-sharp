--TEST--
A plain PHP method that declares no return type implements a PHP# interface's member, whether a plain class declares it or a PHP# subclass inherits it from a package's class, and each PHP# call of the member checks the result: through the interface, the PHP# subclass and its this, a null-safe call, each collection alone at any depth, and the bound type of a generic method's or a generic class's static method's result where its call binds it, naming the type the code wrote. Each member of the Set or the Map such a call gives as the PHP array it crosses into runs on the object, a Map's keys keeping their type
--FILE--
<?php

namespace Plain;

require __DIR__ . '/vendor/autoload.php';

use CallerChecked\Article;
use CallerChecked\Calls;
use CallerChecked\IntSource;
use CallerChecked\Relation;
use CallerChecked\Taggable;

// A plain class whose methods declare no return type implements each PHP# member.
final class Contact implements Taggable
{
    public mixed $given = null;

    public function morphToMany($related, $name)
    {
        return $this->given ?? new Relation($name);
    }

    public function fail($reason)
    {
        throw new \LogicException($reason);
    }

    public function ids()
    {
        return $this->given ?? [1, 2];
    }

    public function labels()
    {
        return $this->given ?? ['a', 'b', 'c'];
    }

    public function counts()
    {
        return $this->given ?? ['a' => 1];
    }

    public function stream()
    {
        return $this->given ?? new \ArrayIterator([1, 2, 3]);
    }

    public function pair()
    {
        return $this->given ?? [[1, 2], 'two'];
    }
}

final class Values implements IntSource
{
    public function __construct(private $value)
    {
    }

    public function read()
    {
        return $this->value;
    }
}

$given = static function (mixed $value): Contact {
    $owner = new Contact();
    $owner->given = $value;

    return $owner;
};
$calls = [
    'through an interface' => fn () => Calls::through(new Contact()),
    'through an interface, given text' => fn () => Calls::through($given('text')),
    'null-safe' => fn () => Calls::nullSafe(new Contact()),
    'null-safe, given text' => fn () => Calls::nullSafe($given('text')),
    'null-safe, of null' => fn () => Calls::nullSafe(null),
    'a PHP# subclass' => fn () => Calls::subclass(new Article([new Relation('subclass')])),
    'a PHP# subclass, given text' => fn () => Calls::subclass(new Article(['text'])),
    'this in a PHP# subclass' => fn () => Calls::viaThis(new Article([new Relation('this')])),
    'this in a PHP# subclass, given text' => fn () => Calls::viaThis(new Article(['text'])),
    'never' => fn () => Calls::fail(new Contact()),
    'List' => fn () => Calls::ids(new Contact()),
    'List, given text' => fn () => Calls::ids($given('text')),
    'List, given strings, whose elements the check leaves' => fn () => Calls::ids($given(['a', 'b', 'c'])),
    'Set' => fn () => Calls::labels(new Contact()),
    'Set, given text' => fn () => Calls::labels($given('text')),
    'Map' => fn () => Calls::counts(new Contact()),
    'Map, given text' => fn () => Calls::counts($given('text')),
    'Iterable' => fn () => Calls::stream(new Contact()),
    'Iterable, given text' => fn () => Calls::stream($given('text')),
    'tuple' => fn () => Calls::pair(new Contact()),
    'tuple, given text' => fn () => Calls::pair($given('text')),
    'tuple, given text for its List' => fn () => Calls::pair($given(['text', 'two'])),
    "tuple, given strings in its List, whose elements the check leaves" => fn () => Calls::pair($given([['a'], 'two'])),
    "a generic class's static method" => fn () => Calls::first(new Values(41)),
    "a generic class's static method, given text" => fn () => Calls::first(new Values('text')),
    "a method's type parameter" => fn () => Calls::readFrom(new Values(41)),
    "a method's type parameter, given text" => fn () => Calls::readFrom(new Values('text')),
];
foreach ($calls as $name => $call) {
    try {
        $result = $call();
    } catch (\TypeError|\LogicException $error) {
        $result = get_class($error) . ': ' . $error->getMessage();
    }
    echo $name, ': ', $result, "\n";
}
$members = [
    'Set' => Calls::setMembers(new Contact()),
    'Map' => Calls::mapMembers($given(['a' => 1, 'b' => 2, '10' => 3])),
];
foreach ($members as $kind => $calls) {
    foreach ($calls as $name => $call) {
        try {
            $result = json_encode($call());
        } catch (\Error $error) {
            $result = get_class($error) . ': ' . $error->getMessage();
        }
        echo $kind, ' ', $name, ': ', $result, "\n";
    }
}
?>
--EXPECT--
through an interface: through
through an interface, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
null-safe: null-safe
null-safe, given text: TypeError: CallerChecked.Taggable.morphToMany(): Return value must be of type CallerChecked.Relation, string returned
null-safe, of null: none
a PHP# subclass: subclass
a PHP# subclass, given text: TypeError: CallerChecked.Related.first(): Return value must be of type CallerChecked.Relation, string returned
this in a PHP# subclass: this
this in a PHP# subclass, given text: TypeError: CallerChecked.Related.first(): Return value must be of type CallerChecked.Relation, string returned
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

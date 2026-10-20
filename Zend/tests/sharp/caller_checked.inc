<?php

namespace Plain;

require_once __DIR__ . '/harness/fixture.inc';
\fixture('CallerChecked');
require_once __DIR__ . '/harness/CallerChecked.inc';

use CallerChecked\Article;
use CallerChecked\Calls;
use CallerChecked\Contact;
use CallerChecked\Values;

$given = static function (string $class, mixed $value): Contact {
    $owner = new $class();
    $owner->given = $value;

    return $owner;
};
$calls = [
    'through an interface' => fn () => Calls::through(new Contact()),
    'through an interface, given text' => fn () => Calls::through($given(Contact::class, 'text')),
    'null-safe' => fn () => Calls::nullSafe(new Contact()),
    'null-safe, given text' => fn () => Calls::nullSafe($given(Contact::class, 'text')),
    'null-safe, of null' => fn () => Calls::nullSafe(null),
    'the plain class' => fn () => Calls::concrete(new Contact()),
    'the plain class, given text' => fn () => Calls::concrete($given(Contact::class, 'text')),
    'a PHP# subclass' => fn () => Calls::subclass(new Article()),
    'a PHP# subclass, given text' => fn () => Calls::subclass($given(Article::class, 'text')),
    'this in a PHP# subclass' => fn () => Calls::viaThis(new Article()),
    'this in a PHP# subclass, given text' => fn () => Calls::viaThis($given(Article::class, 'text')),
    'never' => fn () => Calls::fail(new Contact()),
    'List' => fn () => Calls::ids(new Contact()),
    'List, given text' => fn () => Calls::ids($given(Contact::class, 'text')),
    'List, given strings, whose elements the check leaves' => fn () => Calls::ids($given(Contact::class, ['a', 'b', 'c'])),
    'Set' => fn () => Calls::labels(new Contact()),
    'Set, given text' => fn () => Calls::labels($given(Contact::class, 'text')),
    'Map' => fn () => Calls::counts(new Contact()),
    'Map, given text' => fn () => Calls::counts($given(Contact::class, 'text')),
    'Iterable' => fn () => Calls::stream(new Contact()),
    'Iterable, given text' => fn () => Calls::stream($given(Contact::class, 'text')),
    'tuple' => fn () => Calls::pair(new Contact()),
    'tuple, given text' => fn () => Calls::pair($given(Contact::class, 'text')),
    'tuple, given text for its List' => fn () => Calls::pair($given(Contact::class, ['text', 'two'])),
    "tuple, given strings in its List, whose elements the check leaves" => fn () => Calls::pair($given(Contact::class, [['a'], 'two'])),
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

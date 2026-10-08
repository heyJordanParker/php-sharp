--TEST--
A PHP# class calls every method of Sharp.Text.Regex, a named group gives both its number and its name, and an invalid pattern throws ValueError with no warning
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Regex.sharp';
require __DIR__ . '/RegexCalls.sharp';

use Demo\RegexCalls;

var_dump(RegexCalls::matches('/^\d+$/', '123'), RegexCalls::matches('/^\d+$/', '12a'));
var_dump(RegexCalls::match('/(\w+)@(\w+)\.com/', 'mail ann@example.com now'));
var_dump(RegexCalls::match('/(?<user>\w+)@(\w+)\.com/', 'mail ann@example.com now'));
var_dump(RegexCalls::match('/x/', 'abc'));
var_dump(RegexCalls::matchAll('/(?<digit>\d)(\w)/', '1a 2b'));
var_dump(RegexCalls::replace('/\s+/', ' ', "a  b\t c"));
var_dump(RegexCalls::split('/,\s*/', 'a, b,c'));
var_dump(RegexCalls::filter('/^a/', ['apple', 'banana', 'avocado']));
var_dump(RegexCalls::escape('a.b/c*'));

$invalid = [
    fn () => RegexCalls::matches('/(/', 'a'),
    fn () => RegexCalls::match('/(/', 'a'),
    fn () => RegexCalls::matchAll('/(/', 'a'),
    fn () => RegexCalls::replace('/(/', 'b', 'a'),
    fn () => RegexCalls::split('/(/', 'a'),
    fn () => RegexCalls::filter('/(/', ['a']),
];
foreach ($invalid as $call) {
    try {
        $call();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(false)
array(3) {
  [0]=>
  string(15) "ann@example.com"
  [1]=>
  string(3) "ann"
  [2]=>
  string(7) "example"
}
array(4) {
  [0]=>
  string(15) "ann@example.com"
  ["user"]=>
  string(3) "ann"
  [1]=>
  string(3) "ann"
  [2]=>
  string(7) "example"
}
NULL
array(2) {
  [0]=>
  array(4) {
    [0]=>
    string(2) "1a"
    ["digit"]=>
    string(1) "1"
    [1]=>
    string(1) "1"
    [2]=>
    string(1) "a"
  }
  [1]=>
  array(4) {
    [0]=>
    string(2) "2b"
    ["digit"]=>
    string(1) "2"
    [1]=>
    string(1) "2"
    [2]=>
    string(1) "b"
  }
}
string(5) "a b c"
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
array(2) {
  [0]=>
  string(5) "apple"
  [1]=>
  string(7) "avocado"
}
string(9) "a\.b\/c\*"
ValueError: Regex.matches: the pattern failed: Internal error
ValueError: Regex.match: the pattern failed: Internal error
ValueError: Regex.matchAll: the pattern failed: Internal error
ValueError: Regex.replace: the pattern failed: Internal error
ValueError: Regex.split: the pattern failed: Internal error
ValueError: Regex.filter: the pattern failed: Internal error

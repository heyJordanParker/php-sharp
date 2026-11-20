--TEST--
A PHP# class parses a Sharp.Text.Regex and calls each of its methods, a named group gives both its number and its name, tryParse gives null for a pattern that doesn't compile, parse throws ValueError naming it with no warning, and a failure while matching still throws ValueError
--INI--
pcre.jit=0
pcre.backtrack_limit=1000
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

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
var_dump(RegexCalls::tryMatches('/[/', 'q'), RegexCalls::tryMatches('/[a-z]/', 'q'));

$invalid = [
    fn () => RegexCalls::matches('/[/', 'a'),
    fn () => RegexCalls::matches('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar'),
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
NULL
bool(true)
ValueError: Regex.parse: "/[/" does not compile
ValueError: Regex.matches: the pattern failed: Backtrack limit exhausted

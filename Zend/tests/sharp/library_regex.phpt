--TEST--
A PHP# class calls every method of Sharp.Text.Regex, and an invalid pattern throws ValueError
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Regex.sharp';
require __DIR__ . '/RegexCalls.sharp';

use Demo\RegexCalls;

var_dump(RegexCalls::matches('/^\d+$/', '123'), RegexCalls::matches('/^\d+$/', '12a'));
var_dump(RegexCalls::match('/(\w+)@(\w+)\.com/', 'mail ann@example.com now'));
var_dump(RegexCalls::match('/x/', 'abc'));
var_dump(RegexCalls::matchAll('/(\d)(\w)/', '1a 2b'));
var_dump(RegexCalls::replace('/\s+/', ' ', "a  b\t c"));
var_dump(RegexCalls::split('/,\s*/', 'a, b,c'));
var_dump(RegexCalls::filter('/^a/', ['apple', 'banana', 'avocado']));
var_dump(RegexCalls::escape('a.b/c*'));

try {
    RegexCalls::matches('/(/', 'a');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECTF--
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
NULL
array(2) {
  [0]=>
  array(3) {
    [0]=>
    string(2) "1a"
    [1]=>
    string(1) "1"
    [2]=>
    string(1) "a"
  }
  [1]=>
  array(3) {
    [0]=>
    string(2) "2b"
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

Warning: preg_match(): Compilation failed: missing closing parenthesis at offset 1 in %sRegex.sharp on line %d
ValueError: Regex.matches: the pattern failed: Internal error

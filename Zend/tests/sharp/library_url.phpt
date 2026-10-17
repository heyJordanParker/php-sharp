--TEST--
A PHP# class calls every method of Sharp.Net.Url, none of which throws or gives null, and Url.isValid refuses a URL without a scheme
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Net/Url.sharp';
require __DIR__ . '/UrlCalls.sharp';

use Demo\UrlCalls;

var_dump(UrlCalls::encode('a b&c/é'), UrlCalls::encodeForm('a b&c'), UrlCalls::decode('a+b%26c'));
var_dump(UrlCalls::buildQuery(['q' => 'php sharp', 'page' => 2, 'tags' => ['a', 'b']]));
var_dump(UrlCalls::isValid('https://example.com/a?b=1'), UrlCalls::isValid('example.com'));
var_dump(UrlCalls::parseQuery('a=1&b[]=2&b[]=3'));
?>
--EXPECT--
string(18) "a%20b%26c%2F%C3%A9"
string(7) "a+b%26c"
string(5) "a b&c"
string(46) "q=php+sharp&page=2&tags%5B0%5D=a&tags%5B1%5D=b"
bool(true)
bool(false)
array(2) {
  ["a"]=>
  string(1) "1"
  ["b"]=>
  array(2) {
    [0]=>
    string(1) "2"
    [1]=>
    string(1) "3"
  }
}

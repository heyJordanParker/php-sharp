--TEST--
A PHP# class calls every member of Sharp.Net.Url: parse splits an absolute URL into its parts and keeps their percent-encoding, toString writes them back, tryParse gives null and parse throws ValueError for text that is not an absolute URL, and the encoding, query and validity statics never throw
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Net/Url.sharp';
require __DIR__ . '/UrlCalls.sharp';

use Demo\UrlCalls;

var_dump(UrlCalls::encode('a b&c/é'), UrlCalls::encodeForm('a b&c'), UrlCalls::decode('a+b%26c'));
var_dump(UrlCalls::buildQuery(['q' => 'php sharp', 'page' => 2, 'tags' => ['a', 'b']]));
var_dump(UrlCalls::isValid('https://example.com/a?b=1'), UrlCalls::isValid('example.com'));
var_dump(UrlCalls::parseQuery('a=1&b[]=2&b[]=3'));
echo implode(' | ', UrlCalls::parts('https://ada:secret@example.com:8443/a%20b/c?x=1&y=2#top')), "\n";
echo implode(' | ', UrlCalls::parts('http://example.com')), "\n";
var_dump(UrlCalls::written('https://ada:secret@example.com:8443/a%20b/c?x=1&y=2#top'), UrlCalls::written('http://example.com'));
var_dump(UrlCalls::written('example.com/path'), UrlCalls::written('/relative'), UrlCalls::written(''));
var_dump(UrlCalls::queryValues('https://example.com/?a=1&b=two'), UrlCalls::queryValues('https://example.com/'));
var_dump(UrlCalls::built());

try {
    UrlCalls::parts('not a url');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
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
https | example.com | 8443 | /a%20b/c | x=1&y=2 | top | ada | secret
http | example.com | 0 |  | - | - | - | -
string(55) "https://ada:secret@example.com:8443/a%20b/c?x=1&y=2#top"
string(18) "http://example.com"
NULL
NULL
NULL
array(2) {
  ["a"]=>
  string(1) "1"
  ["b"]=>
  string(3) "two"
}
array(0) {
}
string(32) "https://example.com/search?q=a+b"
ValueError: `Url.parse` reads an absolute URL, such as "https://example.com/path", and "not a url" is not one.

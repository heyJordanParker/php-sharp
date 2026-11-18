--TEST--
A PHP# class imports PHP 8.4's HTML DOM, which the standard library names, and reads a page through it: the text of its first paragraph, null for a page without one, and each link's target
--EXTENSIONS--
dom
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$html = '<!DOCTYPE html><html><body><p>Hello <b>there</b></p><p>second</p><a href="/one">1</a><a href="/two">2</a></body></html>';
var_dump(DomPages\Page::first($html));
var_dump(DomPages\Page::first('<!DOCTYPE html><html><body></body></html>'));
var_dump(DomPages\Page::links($html));
?>
--EXPECT--
string(11) "Hello there"
NULL
array(2) {
  [0]=>
  string(4) "/one"
  [1]=>
  string(4) "/two"
}

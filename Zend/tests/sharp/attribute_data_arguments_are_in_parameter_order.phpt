--TEST--
PHP# attributeData<T>() reads [Route("/offers", method: "GET")] on Route(string path, string method = "POST", int priority = 0) as ["/offers", "GET", 0], each named argument at its parameter's place and each left-out default filled in, and createInstance rebuilds an equal Route from it
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

use WrittenAttributes\Reader;

[$data] = Reader::written('offers');
var_dump($data->type, $data->arguments);
var_dump(Reader::rebuilt('offers') == Reader::built('offers'));
?>
--EXPECT--
string(23) "WrittenAttributes\Route"
array(3) {
  [0]=>
  string(7) "/offers"
  [1]=>
  string(3) "GET"
  [2]=>
  int(0)
}
bool(true)

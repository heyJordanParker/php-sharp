--TEST--
PHP# attributeData<T>() reads each attribute as written, its class and the call of its constructor in parameter order with named arguments placed and defaults filled in, builds none, so a printing constructor never runs, and createInstance rebuilds an equal attribute from it
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

use WrittenAttributes\Reader;

foreach (['publish', 'review', 'offers'] as $name) {
    echo "$name:\n";
    foreach (Reader::written($name) as $data) {
        var_dump($data->type, $data->arguments);
    }
    var_dump(Reader::rebuilt($name) == Reader::built($name));
}
echo "shout read as written: ", count(Reader::written('shout')), "\n";
echo "a pure method counts publish's: ", Reader::counted(), "\n";
?>
--EXPECT--
publish:
string(24) "WrittenAttributes\Access"
array(1) {
  [0]=>
  enum(WrittenAttributes\Level::Edit)
}
string(24) "WrittenAttributes\Access"
array(1) {
  [0]=>
  string(24) "WrittenAttributes\System"
}
bool(true)
review:
string(24) "WrittenAttributes\Access"
array(1) {
  [0]=>
  enum(WrittenAttributes\Level::Edit)
}
bool(true)
offers:
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
shout read as written: 1
a pure method counts publish's: 2

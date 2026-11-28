--TEST--
PHP# Bytes stores as standard base64 text, through toString() and Bytes.parse, every byte kept, and Bytes.parse throws ValueError on text that is not base64
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\Library;

var_dump(Library::stored("\xFB\xFF\x00a"), Library::restored('+/8AYQ=='), Library::encoded("\xFB\xFF\x00a"));
try {
    Library::restored('@@');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(8) "+/8AYQ=="
array(4) {
  [0]=>
  int(251)
  [1]=>
  int(255)
  [2]=>
  int(0)
  [3]=>
  int(97)
}
string(19) "{"data":"+/8AYQ=="}"
ValueError: Base64.decode: the text is not valid base64

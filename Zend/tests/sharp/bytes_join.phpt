--TEST--
a + b joins two Bytes, spec section 18: the bytes of a followed by those of b
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\JoinedBytes;

var_dump(JoinedBytes::joined());
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}

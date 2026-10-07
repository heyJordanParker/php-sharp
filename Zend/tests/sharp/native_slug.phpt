--TEST--
Plain PHP calls the native body of Text.slug as the internal function Sharp\Internal\Text\Text\slug
--FILE--
<?php
var_dump(\Sharp\Internal\Text\Text\slug("A B"));
echo new ReflectionFunction('Sharp\Internal\Text\Text\slug');
?>
--EXPECT--
string(3) "a-b"
Function [ <internal:sharp> function Sharp\Internal\Text\Text\slug ] {

  - Parameters [1] {
    Parameter #0 [ <required> string $title ]
  }
  - Return [ string ]
}

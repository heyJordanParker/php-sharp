--TEST--
Plain PHP calls the native body of StringMembers.slugOf as the internal function Sharp\Internal\Text\StringMembers\slugOf
--FILE--
<?php
var_dump(\Sharp\Internal\Text\StringMembers\slugOf("A B"));
echo new ReflectionFunction('Sharp\Internal\Text\StringMembers\slugOf');
?>
--EXPECT--
string(3) "a-b"
Function [ <internal:sharp> function Sharp\Internal\Text\StringMembers\slugOf ] {

  - Parameters [1] {
    Parameter #0 [ <required> string $title ]
  }
  - Return [ string ]
}

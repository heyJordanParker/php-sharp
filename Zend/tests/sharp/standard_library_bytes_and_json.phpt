--TEST--
A string reads as its bytes, trims the characters it is given, Json.encode takes its switches, Json.parse compares by structure, and Sql.identifier gives only an allowed name
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/Json.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/JsonValue.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Net/Ip.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('Payloads');

use Payloads\Report;

echo json_encode(Report::bytes(), JSON_UNESCAPED_UNICODE), "\n";
foreach ([[0, 9], [3, 2], [-1, 2]] as [$start, $end]) {
    try {
        Report::byteRange($start, $end);
    } catch (OutOfRangeException $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}
echo json_encode(Report::trims(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
foreach (Report::encodings() as $encoded) {
    echo $encoded, "\n";
}
var_dump(Report::comparisons());
var_dump(Report::value('{"a":[1,2]}'));
echo Report::column('created_at'), "\n";
try {
    Report::column('name; drop table orders');
} catch (ValueError $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}
?>
--EXPECT--
[5,6,[195,169,99,111,108,101],"é","cole",[192,168,0,1],16,[]]
OutOfRangeException: `byteSubstring` takes a range inside the bytes, and the range is outside it.
OutOfRangeException: `byteSubstring` takes a range inside the bytes, and the range is outside it.
OutOfRangeException: `byteSubstring` takes a range inside the bytes, and the range is outside it.
["sku","sku","sku--","/path","-a-","a"]
{"url":"https://example.com/a","name":"école","total":1.0}
{"url":"https:\/\/example.com\/a","name":"\u00e9cole","total":1}
{"url":"https://example.com/a","name":"école","total":1}
{"url":"https:\/\/example.com\/a","name":"école","total":1.0}
{"a":[1,2.5,null]}
array(8) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(false)
  [3]=>
  bool(true)
  [4]=>
  bool(false)
  [5]=>
  bool(false)
  [6]=>
  bool(true)
  [7]=>
  bool(false)
}
array(1) {
  ["a"]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
}
created_at
ValueError: `Sql.identifier` takes a name the query allows, and "name; drop table orders" is none of them.

--TEST--
Sharp.Encoding.Json.encode's indent, spec section 24: 0 writes one line, and 2 or 4 write each nested value on its own line that many spaces deeper than its parent, with ": " after a key and [] and {} for an empty value, so indent 4 writes the bytes of PHP's JSON_PRETTY_PRINT with the flags Json.encode's defaults give, and a negative indent throws ValueError
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/Json.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('JsonCalls');

use Demo\JsonCalls;

foreach ([0, 2, 4] as $indent) {
    echo "indent $indent:\n", JsonCalls::indented($indent), "\n";
}

$php = json_encode(
    [
        'name' => 'two words',
        'note' => "line one\nline two",
        'path' => 'a/é',
        'total' => 1.0,
        'lists' => [[1, 2], []],
        'maps' => ['inner' => ['deep' => new stdClass()]],
        'none' => new stdClass(),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
);
var_dump(JsonCalls::indented(4) === $php);

try {
    JsonCalls::indented(-1);
} catch (ValueError $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}
?>
--EXPECT--
indent 0:
{"name":"two words","note":"line one\nline two","path":"a/é","total":1.0,"lists":[[1,2],[]],"maps":{"inner":{"deep":{}}},"none":{}}
indent 2:
{
  "name": "two words",
  "note": "line one\nline two",
  "path": "a/é",
  "total": 1.0,
  "lists": [
    [
      1,
      2
    ],
    []
  ],
  "maps": {
    "inner": {
      "deep": {}
    }
  },
  "none": {}
}
indent 4:
{
    "name": "two words",
    "note": "line one\nline two",
    "path": "a/é",
    "total": 1.0,
    "lists": [
        [
            1,
            2
        ],
        []
    ],
    "maps": {
        "inner": {
            "deep": {}
        }
    },
    "none": {}
}
bool(true)
ValueError: Json.encode: indent is -1, and it must be 0 or more

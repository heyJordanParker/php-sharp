--TEST--
A plain PHP string that crosses into a PHP# string is valid UTF-8, each byte sequence that is not UTF-8 replaced by U+FFFD, in a parameter, a List's elements, a variadic's values and a Map's keys, two keys that become one throw, one that crosses into Bytes keeps every byte, and Bytes crosses into a plain PHP string parameter as the string it holds
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/harness/Witness.inc';

use Demo\Library;

// A release counted 4 and threw JsonException "Malformed UTF-8 characters" from the second.
var_dump(Library::textLength("caf\xE9"), Library::textJson("caf\xE9"), Library::textLength('café'));
echo json_encode(Library::textLengths(["caf\xE9", "\xFF", 'ok'])), ' ', json_encode(Library::eachLength("caf\xE9", 'ok')), "\n";
echo json_encode(Library::keyLengths(["caf\xE9" => 1, 'ok' => 2])), "\n";
try {
    Library::keyLengths(["a\xE9" => 1, "a\xFF" => 2]);
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// Bytes keeps what text replaces.
echo json_encode(Library::byteValues("caf\xE9\xFF")), ' ', json_encode(Library::byteCounts(["\xE9", "\xFF\x00"])), "\n";
// A plain PHP method that takes a string gets the string Bytes holds.
echo bin2hex((function () { ob_start(); Library::sayBytes("caf\xE9\xFF"); return ob_get_clean(); })()), "\n";
?>
--EXPECT--
int(4)
string(8) ""caf�""
int(4)
[4,1,2] [4,2]
[4,2]
ValueError: This `Map` holds both `"a\xE9"` and `"a\xFF"`, which are one key once each byte sequence that is not UTF-8 becomes U+FFFD
[99,97,102,233,255] [1,2]
636166e9ff

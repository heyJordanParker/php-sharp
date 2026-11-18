--TEST--
Json.encode writes <, >, &, ' and " inside a string as their codes by default, so the JSON can't close a <script> it is put in, and escapeHtml: false writes the bytes PHP's json_encode writes without JSON_HEX_*
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo JsonHtml\Writes::escaped(), "\n";
echo JsonHtml\Writes::kept(), "\n";
var_dump(JsonHtml\Writes::kept() === json_encode(['note' => "</script><b a='1'>&\"x\""], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
--EXPECT--
{"note":"\u003C/script\u003E\u003Cb a=\u00271\u0027\u003E\u0026\u0022x\u0022"}
{"note":"</script><b a='1'>&\"x\""}
bool(true)

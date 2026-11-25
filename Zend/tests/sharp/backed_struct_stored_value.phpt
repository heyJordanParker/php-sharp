--TEST--
A struct backed by a built-in type is stored as its value, and reading it back runs its constructor, which may refuse it
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", Demo\Backing::answers()), "\n";
?>
--EXPECT--
encoded: {"email":"a@b.c","id":7}
decoded: a@b.c 7
equal: true
parsed: x@y.z
same value: true, other value: false
map: {"3":"three"}
decimal: "1.50"
"1.50"
"9223372036854775808"
"2026-10-22T18:30:00Z"
"2026-01-31"
"PT1H30M"
"2001:db8::1"
"aGk="
"https://example.com/a"
"2026-10-22T20:30:00+02:00[Europe/Paris]"
"18:30:00"
readings ==: true, keys: 1
bad email: email: "not-an-email" is no email address

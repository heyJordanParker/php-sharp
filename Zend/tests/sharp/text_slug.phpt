--TEST--
A PHP# class turns titles into slugs with the string member slug, which runs the native body of StringMembers.slugOf
--FILE--
<?php
require __DIR__ . '/text_slug.inc';
?>
--EXPECT--
string(20) "unicode-strasse-2024"
string(10) "privet-mir"
string(0) ""
string(3) "a-b"
string(21) "bei-jing-huan-ying-ni"
string(11) "caf-au-lait"
string(20) "unicode-strasse-2024"
string(10) "privet-mir"
string(0) ""
string(3) "a-b"
string(21) "bei-jing-huan-ying-ni"
string(11) "caf-au-lait"

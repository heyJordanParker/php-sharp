--TEST--
A PHP# class turns titles into slugs with the string member slug, which runs the native body of StringMembers.slugOf
--FILE--
<?php

require __DIR__ . '/vendor/autoload.php';

$titles = ["Ünïcödé Straße — 2024!", "Привет мир", "", "--a  b--", "北京欢迎你", "caf\xE9 au lait"];
for ($run = 0; $run < 2; $run++) {
    foreach ($titles as $title) {
        var_dump(Demo\Permalink::of($title));
    }
}
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

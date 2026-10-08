<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Text.sharp';
require __DIR__ . '/Permalink.sharp';

$titles = ["Ünïcödé Straße — 2024!", "Привет мир", "", "--a  b--", "北京欢迎你", "caf\xE9 au lait"];
for ($run = 0; $run < 2; $run++) {
    foreach ($titles as $title) {
        var_dump(Demo\Permalink::of($title));
    }
}

<?php

require __DIR__ . '/vendor/autoload.php';

for ($round = 0; $round < 100; $round++) {
    $results = TypeTests\TypeTests::tested();
}

foreach ($results as $index => $result) {
    echo $index, ': ', var_export($result, true), "\n";
}

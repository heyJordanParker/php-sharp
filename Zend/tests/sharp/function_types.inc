<?php

require __DIR__ . '/harness/fixture.inc';
fixture('FunctionTypes');

for ($round = 0; $round < 100; $round++) {
    $results = (new Demo\FunctionTypes())->tested(new Demo\Meter(), fn ($x) => $x * 2);
}

foreach ($results as $index => $result) {
    echo $index, ': ', var_export($result, true), "\n";
}

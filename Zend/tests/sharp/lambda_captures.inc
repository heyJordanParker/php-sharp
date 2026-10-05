<?php

require __DIR__ . '/Lambdas.sharp';

// Each call runs twice, so the JIT variants compile the methods and closures before the second run.
$lambdas = new Demo\Lambdas();
for ($run = 0; $run < 2; $run++) {
    echo $lambdas->run(3), "\n";
    echo implode(",", Demo\Lambdas::doubled([1, 2, 3], 2)), "\n";
    echo Demo\Lambdas::passes(2), "\n";
    echo $lambdas->nested(5), "\n";
}

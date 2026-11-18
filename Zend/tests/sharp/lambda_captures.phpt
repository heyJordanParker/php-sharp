--TEST--
PHP# lambdas capture the variables themselves, give each loop pass its own let, and pass to plain PHP methods
--FILE--
<?php

require __DIR__ . '/harness/Callbacks.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/vendor/autoload.php';

// Each call runs twice, so the JIT variants compile the methods and closures before the second run.
$lambdas = new Demo\Lambdas();
for ($run = 0; $run < 2; $run++) {
    echo $lambdas->run(3), "\n";
    echo implode(",", Demo\Lambdas::doubled([1, 2, 3], 2)), "\n";
    echo Demo\Lambdas::passes(2), "\n";
    echo $lambdas->nested(5), "\n";
    echo implode(" ", Demo\Lambdas::pipeline([3, 1, 4, 1, 5, 9, 2, 6], ["tea" => 50, "cake" => 300, "7" => 20])), "\n";
    echo Demo\Lambdas::total([3, 1, 4]), "\n";
    echo implode(" / ", Demo\Lambdas::failures([3, 1, 4])), "\n";
    echo implode(",", Demo\Lambdas::captured([3, 1, 4])), "\n";
    echo implode(",", Demo\Lambdas::scaled([3, 1, 4])), "\n";
}
?>
--EXPECT--
17
2,4,6
0,102,101
18
30,10,10,50,90 9,6,5,4,3,2,1,1 3,1,1,5,9 30,10,50,90 tea,7 50,20 100,600,40
19
`first` finds an element the predicate matches, and none does. / 3
3,6,7
6,2,8
17
2,4,6
0,102,101
18
30,10,10,50,90 9,6,5,4,3,2,1,1 3,1,1,5,9 30,10,50,90 tea,7 50,20 100,600,40
19
`first` finds an element the predicate matches, and none does. / 3
3,6,7
6,2,8

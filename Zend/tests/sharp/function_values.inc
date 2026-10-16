<?php

require __DIR__ . '/harness/fixture.inc';
fixture('FunctionValues');

/** Each row runs one rule of spec section 14 on the functions its PHP# code made, the second of two runs, so the JIT has compiled the code it runs. */
function function_values(): array
{
    $values = new Demo\FunctionValues();
    $rows = [];
    for ($run = 0; $run < 2; $run++) {
        $rows = [...$values->calls(), $values->forOfPasses(), $values->forCounterPasses(), $values->forCounterChangedByALambda()];
    }

    return $rows;
}

<?php

require __DIR__ . '/harness/fixture.inc';
require_once __DIR__ . '/harness/Callbacks.inc';
require_once __DIR__ . '/harness/Words.inc';
fixture('FunctionValues');
// The value of `"  a  ".trim` calls the standard library's `Sharp\Text\StringMembers`, which a project's autoloader finds.
fixture_files(['sharp\text\stringmembers' => __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp']);

/** Each row runs one rule of spec section 14 on the functions its PHP# code made, the second of two runs, so the JIT has compiled the code it runs. */
function function_values(): array
{
    $values = new Demo\FunctionValues();
    $rows = [];
    for ($run = 0; $run < 2; $run++) {
        $rows = [
            ...$values->calls(),
            $values->forOfPasses(),
            $values->forCounterPasses(),
            $values->forCounterChangedByALambda(),
            ...$values->methodValues(),
            $values->nestedCounterPasses(),
            ...$values->crossings(),
            $values->storedRate(),
        ];
    }

    return $rows;
}

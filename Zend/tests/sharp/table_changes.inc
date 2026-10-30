<?php
require __DIR__ . '/harness/fixture.inc';
fixture('TableChanges');

use TableChanges\Box;
use TableChanges\Journal;
use TableChanges\Ledger;

// Each round runs every change, so the JIT runs compiled code before the last round, whose output is printed.
for ($round = 0; $round < 3; $round++) {
    ob_start();
    echo json_encode(Ledger::emptiness([], [])), ' ', json_encode(Ledger::emptiness(['a' => 1], [1])), "\n";
    echo json_encode(Ledger::copiedSet()), ' ', json_encode(Ledger::copiedMap()), "\n";
    echo json_encode(Ledger::elements()), "\n";

    $journal = new Journal();
    $journal->put('tea', 1);
    $journal->putMissing('tea', 2);
    $journal->putMissing('milk', 3);
    var_dump($journal->tag('vip'), $journal->tag('vip'));
    echo json_encode($journal->stock), ' ', $journal->writes, "\n";

    echo json_encode(Ledger::kept(['a' => 1, 'b' => 2, '7' => 3], [1, 2, 3])), "\n";

    $box = new Box();
    echo implode(' ', array_map(fn ($pick) => Ledger::counted($pick, [1], ['a' => 1, 'b' => 2], [1, 2, 3], $box), [0, 1, 2, 3])), "\n";
    var_dump(Ledger::holds(false, [3], $box, 3), Ledger::holds(true, [3], $box, 3), Ledger::holds(true, [3], $box, 7));
    echo json_encode([
        Ledger::found(true, ['tea'], ['milk'], 'tea'),
        Ledger::found(true, ['tea'], ['milk'], 'milk'),
        Ledger::found(false, ['tea'], ['milk'], 'milk'),
        Ledger::found(false, ['tea'], ['milk'], 'tea'),
    ]), "\n";
    $output = ob_get_clean();
}

echo $output;

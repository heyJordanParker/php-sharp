<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ListChanges');

use ListChanges\Journal;
use ListChanges\Ledger;

// Each round runs every change, so the JIT runs compiled code before the last round, whose output is printed.
for ($round = 0; $round < 3; $round++) {
    ob_start();
    $ledger = new Ledger();
    $ledger->lines = ['a', 'b', 'c'];
    echo $ledger->dropLine(), ' ', json_encode($ledger->lines), "\n";
    echo $ledger->dropLine(), ' ', json_encode($ledger->lines), "\n";

    $journal = new Journal();
    $journal->audited = ['x', 'y'];
    echo $journal->dropAudited(), ' ', json_encode($journal->audited), ' ', $journal->writes, "\n";

    Ledger::$archive = ['old', 'new'];
    echo Ledger::dropArchived(), ' ', json_encode(Ledger::$archive), "\n";

    echo json_encode(Ledger::removedAt(['a', 'b', 'c'], 1)), "\n";
    echo json_encode(Ledger::left(['a', 'b', 'c'], 0)), "\n";
    echo Ledger::removedFirst(['only']), "\n";
    echo json_encode(Ledger::emptiness([])), ' ', json_encode(Ledger::emptiness(['a'])), "\n";

    var_dump(Ledger::lastCount(null), Ledger::lastCount([[1, 2], [3]]));

    echo json_encode(Ledger::indexed(['a', 'b'])), "\n";

    // Repeated changes to a List<int> property leave it a reference, which the next change takes as it is, and a bad
    // element plain PHP wrote through a reference of its own throws at the next change.
    $counted = new Ledger();
    for ($count = 1; $count <= 3; $count++) {
        $counted->count($count);
    }
    echo json_encode($counted->counts), "\n";
    $alias = &$counted->counts;
    $alias[] = 'z';
    try {
        $counted->count(4);
    } catch (TypeError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
    unset($alias);

    foreach ([
        fn () => Ledger::removedFirst([]),
        fn () => Ledger::left([], 0),
        fn () => Ledger::left(['a'], 3),
        fn () => (new Ledger())->dropLine(),
    ] as $refused) {
        try {
            $refused();
        } catch (OutOfRangeException $error) {
            echo get_class($error), ': ', $error->getMessage(), "\n";
        }
    }
    $output = ob_get_clean();
}

echo $output;

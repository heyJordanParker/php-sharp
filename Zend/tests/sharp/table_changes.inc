<?php
require __DIR__ . '/harness/Basket.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('TableChanges');
require __DIR__ . '/LibraryProbe/Stage.sharp';
require __DIR__ . '/LibraryProbe/Tables.sharp';

use TableChanges\Box;
use TableChanges\Drawer;
use TableChanges\Journal;
use TableChanges\Ledger;
use TableChanges\Memo;
use TableChanges\Registry;
use TableChanges\Shelf;

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
    echo json_encode(Sharp\Probe\Tables::crossed()), "\n";
    $names = function () {
        yield 'a';
        yield 'b';
        yield 'c';
    };
    echo implode(' ', [
        Ledger::streamed(true, [1], $names()),
        Ledger::streamed(false, [1], $names()),
        ...array_map(fn ($pick) => Ledger::listed($pick, [1], $box, $names()), [0, 1, 2]),
    ]), "\n";

    $shelf = new Shelf();
    $shelf->keep(false, [], [1, -1, 2]);
    echo json_encode($shelf->kept), "\n";
    try {
        $shelf->kept[] = 'x';
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
    foreach ([[true, [1, 2, 3], []], [false, [], ['a' => 1, 'b' => 2]]] as [$listed, $items, $counts]) {
        $shelf->group($listed, $items, $counts);
        echo json_encode($shelf->grouped), ' ', json_encode($shelf->associated), "\n";
        foreach (['grouped', 'associated'] as $property) {
            try {
                $shelf->$property[7] = 'x';
            } catch (TypeError $error) {
                echo $error->getMessage(), "\n";
            }
        }
    }

    $drawer = new Drawer();
    $numbers = &$drawer->numbers;
    $prices = &$drawer->prices;
    var_dump($drawer->addNumber(5), $drawer->removePrice('tea'));
    echo json_encode($drawer->numbers), ' ', json_encode($numbers), ' ', json_encode($drawer->prices), ' ', json_encode($prices), "\n";
    unset($numbers, $prices);

    $memo = new Memo();
    echo $memo->at(10), ' ', Memo::cached(10), ' ', json_encode($memo->stepped()), ' ', json_encode($memo->tagged()), "\n";
    echo json_encode(Memo::counted(new Lib\Basket())), "\n";

    // The statics keep their values from round to round.
    Registry::count('tea', $round);
    var_dump(Registry::tag("vip$round"), Registry::tag("vip$round"));
    echo Registry::counted('tea'), ' ', json_encode(Registry::tagged()), ' ', Registry::written(), "\n";
    $output = ob_get_clean();
}

echo $output;

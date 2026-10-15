<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/RowSet.inc';
require __DIR__ . '/Grid.sharp';
require __DIR__ . '/Point.sharp';

for ($round = 0; $round < 100; $round++) {
    ob_start();
    // A List index is a place: add, insert, remove, set and clear change the list stored there.
    attempt(fn () => Demo\Grid::changed([[1, 2], [3]]));
    attempt(fn () => Demo\Grid::cleared([[1, 2], [3]], 0));
    // A change separates the list from a copy that shares it, and an argument reads the list before the change.
    attempt(fn () => Demo\Grid::kept([[1, 2], [3]]));
    attempt(fn () => Demo\Grid::deep([[[1]], [[2]]]));
    attempt(fn () => Demo\Grid::sets([[1 => 1], [2 => 2]]));
    attempt(fn () => Demo\Grid::maps([['a' => 1], ['b' => 2]]));
    // Through a property, and through a property's set hook, which runs once for each change.
    $grid = new Demo\Grid();
    attempt(fn () => $grid->held());
    attempt(fn () => [$grid->hooked(), $grid->writes]);
    // A write into a Map a property holds goes through the property, and its set hook, as a change does.
    $grid = new Demo\Grid();
    attempt(fn () => [$grid->totalled(), $grid->writes]);
    // A clear writes the element back through the property's set hook.
    $grid = new Demo\Grid();
    attempt(fn () => [$grid->emptied(), $grid->writes]);
    // After ?., a change reaches the element the list stores, and a null element skips the call, on a
    // local, a property and a property's set hook, whichever path the method takes.
    attempt(fn () => Demo\Grid::skipped([[1], null]));
    $grid = new Demo\Grid();
    attempt(fn () => $grid->spareSkipped());
    attempt(fn () => [$grid->linesSkipped(), $grid->writes]);
    attempt(fn () => Demo\Grid::otherSkips([null], [null], [null]));
    // The index before ?. is a bare read, so a missing index throws, whatever the method, and on a
    // property and a property's set hook, which never runs.
    attempt(fn () => Demo\Grid::pastAdd([[1]]));
    attempt(fn () => Demo\Grid::pastClear([[1]]));
    attempt(fn () => Demo\Grid::pastRemove([[1]]));
    attempt(fn () => Demo\Grid::pastCount([[1]]));
    attempt(fn () => Demo\Grid::pastContains([[1]]));
    attempt(fn () => Demo\Grid::pastEntries([[1]]));
    $grid = new Demo\Grid();
    attempt(fn () => $grid->spareMissing());
    attempt(function () use ($grid) { $grid->linesMissing(); });
    attempt(fn () => $grid->writes);
    // is, as and match read a List index bare too: a null element fails the test, and a missing index throws.
    attempt(fn () => [Demo\Grid::tested([3, null], 0), Demo\Grid::tested([3, null], 1)]);
    attempt(fn () => Demo\Grid::tested([3], 9));
    attempt(fn () => [Demo\Grid::cast([3, null], 0), Demo\Grid::cast([3, null], 1)]);
    attempt(fn () => Demo\Grid::cast([3], 9));
    attempt(fn () => [Demo\Grid::matched([3, null], 0), Demo\Grid::matched([3, null], 1)]);
    attempt(fn () => Demo\Grid::matched([3], 9));
    // An index write replaces the element, and throws past either end, where PHP would append or leave a gap.
    attempt(fn () => Demo\Grid::replaced([1, 2], 1, 5));
    attempt(fn () => Demo\Grid::replaced([1, 2], 2, 5));
    attempt(fn () => Demo\Grid::replaced([1, 2], -1, 5));
    // op=, ++ and -- write what the operator gives; x[i]++ gives the element before, and x[i] = v gives v.
    attempt(fn () => Demo\Grid::stepped([1, 2, 7]));
    attempt(fn () => Demo\Grid::joined(['a'], [1.25]));
    attempt(fn () => Demo\Grid::stepPast([1]));
    // Through a property, and through a property's set hook, which runs once for each write.
    $grid = new Demo\Grid();
    attempt(fn () => $grid->rowsReplaced());
    attempt(fn () => [$grid->tableReplaced(), $grid->writes]);
    // An index that runs something runs once.
    attempt(fn () => (new Demo\Grid())->stepOnce([[1, 2], [3, 4]]));
    // An element a plain PHP ArrayAccess object gives is a collection of its own.
    attempt(fn () => Demo\Grid::doubled(new Lib\RowSet([[1, 2]])));
    // An index that runs something runs once.
    attempt(fn () => (new Demo\Grid())->once([[1, 2], [3]]));
    // A missing index throws as a bare read does, and leaves no gap in the list.
    attempt(fn () => Demo\Grid::missing([[1]], 5));
    attempt(fn () => Demo\Grid::cleared([[1]], 1));
    attempt(fn () => Demo\Grid::missingWrite([['a' => 1]], 2));
    attempt(fn () => (new Demo\Grid())->rowsMissing(9));
    $grid = new Demo\Grid();
    attempt(fn () => [$grid->tableMissing(9), $grid->writes]);
    // An element that is an object is the object, so a change to it sticks, and a missing index throws.
    attempt(fn () => Demo\Point::moved([new Demo\Point()], 0)[0]->x);
    attempt(fn () => Demo\Point::moved([new Demo\Point()], 5));
    // A property read after ?. gives null for a null element, on a local, a property and a hooked
    // property, and a missing index throws.
    attempt(fn () => (new Demo\Point())->read([new Demo\Point(), null]));
    attempt(fn () => Demo\Point::readPast([new Demo\Point()]));
    attempt(fn () => (new Demo\Point())->farPast([new Demo\Point()]));
    $output = ob_get_clean();
}

echo $output;

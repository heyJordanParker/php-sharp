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
    // After ?., a change reaches the element the list stores, and a null element skips the call.
    attempt(fn () => Demo\Grid::maybeAdded([[1], null]));
    attempt(fn () => Demo\Grid::surelyAdded([[1]], 0));
    $grid = new Demo\Grid();
    attempt(fn () => [$grid->linesAdded(), $grid->writes]);
    // An element a plain PHP ArrayAccess object gives is a collection of its own.
    attempt(fn () => Demo\Grid::doubled(new Lib\RowSet([[1, 2]])));
    // An index that runs something runs once.
    attempt(fn () => (new Demo\Grid())->once([[1, 2], [3]]));
    // A missing index throws as a bare read does, and leaves no gap in the list.
    attempt(fn () => Demo\Grid::missing([[1]], 5));
    attempt(fn () => Demo\Grid::cleared([[1]], 1));
    attempt(fn () => Demo\Grid::missingWrite([['a' => 1]], 2));
    attempt(fn () => Demo\Grid::surelyAdded([[1]], 3));
    // An element that is an object is the object, so a change to it sticks, and a missing index throws.
    attempt(fn () => Demo\Point::moved([new Demo\Point()], 0)[0]->x);
    attempt(fn () => Demo\Point::moved([new Demo\Point()], 5));
    $output = ob_get_clean();
}

echo $output;

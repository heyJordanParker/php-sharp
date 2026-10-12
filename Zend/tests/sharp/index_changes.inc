<?php

require __DIR__ . '/attempt.inc';
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
    // An index that runs something runs once.
    attempt(fn () => (new Demo\Grid())->once([[1, 2], [3]]));
    // A missing index throws as a bare read does, and leaves no gap in the list.
    attempt(fn () => Demo\Grid::missing([[1]], 5));
    attempt(fn () => Demo\Grid::cleared([[1]], 1));
    attempt(fn () => Demo\Grid::missingWrite([['a' => 1]], 2));
    // An element that is an object is the object, so a change to it sticks.
    attempt(fn () => Demo\Point::moved([new Demo\Point()])[0]->x);
    $output = ob_get_clean();
}

echo $output;

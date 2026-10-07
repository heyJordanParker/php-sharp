<?php

require __DIR__ . '/harness/Shapes.inc';
require __DIR__ . '/Patterns.sharp';

use Demo\Circle;
use Demo\Square;
use Demo\Status;

for ($round = 0; $round < 100; $round++) {
    $patterns = new Demo\Patterns();
    $results = [
        $patterns->area(new Circle(2.0)),
        $patterns->area(new Square(3.0)),
        $patterns->side(new Square(4.0)),
        $patterns->side(new Circle(4.0)),
        $patterns->radius(new Circle(5.0)),
        $patterns->radius(new Square(5.0)),
        $patterns->describe(null),
        $patterns->describe(new Circle(11.0)),
        $patterns->describe(new Circle(1.0)),
        $patterns->describe(new Square(1.0)),
        $patterns->grade(-1),
        $patterns->grade(95),
        $patterns->grade(60),
        $patterns->grade(80),
        $patterns->count(new Circle(1.0)),
        $patterns->count(new Square(2.0)),
        $patterns->count(new Square(0.5)),
        $patterns->count(null),
        $patterns->wide(new Circle(2.0)),
        $patterns->wide(new Circle(1.0)),
        $patterns->first($patterns),
        $patterns->state(Status::Open),
        $patterns->state(Status::Archived),
        $patterns->closed(Status::Closed),
        $patterns->closed(null),
    ];
}

var_dump(...$results);

--TEST--
PHP# `is`, `is not`, `as` and `match` test, narrow and dispatch on a value's type, value and properties
--FILE--
<?php

require __DIR__ . '/harness/Shapes.inc';
require __DIR__ . '/vendor/autoload.php';

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
        $patterns->big(new Circle(11.0)),
        $patterns->big(new Circle(1.0)),
        $patterns->big(new Square(0.0)),
        $patterns->lines([]),
        $patterns->lines([new Circle(1.0)]),
        $patterns->lines([new Square(2.0), new Circle(1.0), new Circle(3.0)]),
        $patterns->lines([new Square(0.5), new Circle(1.0)]),
        $patterns->lines([new Square(1.0)]),
        $patterns->head([new Circle(7.0)]),
        $patterns->head([new Circle(7.0), new Circle(1.0)]),
        $patterns->head([]),
        $patterns->head(null),
        $patterns->ends([new Circle(1.0), new Square(1.0), new Square(1.0), new Circle(1.0)]),
        $patterns->ends([new Square(1.0), new Circle(2.0)]),
        $patterns->ends([new Circle(5.0)]),
        $patterns->ends([new Square(1.0), new Circle(9.0), new Square(3.0)]),
        $patterns->ends([new Square(4.0)]),
        $patterns->ends([]),
        $patterns->circles([new Square(1.0), new Circle(1.0), new Circle(2.0)]),
        $patterns->circles([new Square(1.0), new Circle(1.0), new Square(2.0)]),
        $patterns->circles([new Square(1.0)]),
        $patterns->listed([]),
        $patterns->listed([7]),
        $patterns->listed(['a', 'b', 'c']),
        $patterns->listed(['a', 1]),
        $patterns->listed([1 => 5]),
        $patterns->listed('text'),
        $patterns->listed(new Circle(1.0)),
        $patterns->rows([[], [3]]),
        $patterns->rows([[], ['x']]),
        $patterns->rows([[], 3]),
        $patterns->rows([[], [1 => 3]]),
    ];
}

var_dump(...$results);
?>
--EXPECT--
float(12)
float(9)
float(4)
float(0)
float(5)
float(0)
string(7) "nothing"
string(10) "big circle"
string(6) "circle"
string(6) "square"
string(7) "invalid"
string(3) "top"
string(4) "pass"
string(4) "fail"
int(1)
int(2)
int(0)
int(0)
bool(true)
bool(false)
string(5) "first"
string(4) "open"
string(4) "done"
int(2)
int(0)
float(11)
float(0)
float(-1)
int(0)
int(1)
int(4)
int(-1)
int(-1)
float(7)
float(0)
float(0)
float(0)
float(-2)
float(3)
float(5)
float(4)
float(0)
float(0)
int(2)
int(-1)
int(0)
int(0)
int(7)
int(102)
int(-1)
int(-1)
int(-1)
int(-1)
bool(true)
bool(false)
bool(false)
bool(false)

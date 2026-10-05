--TEST--
PHP# abstract and final classes, abstract methods and interfaces are PHP's
--FILE--
<?php

require __DIR__ . '/Shapes.sharp';

final class Square extends Demo\Shape implements Demo\Measured
{
    public function area(): float
    {
        return 4.0;
    }

    protected function name(): string
    {
        return 'square';
    }
}

$square = new Square();
var_dump($square->area(), $square->describe(), $square instanceof Demo\Measured);

$shape = new ReflectionClass(Demo\Shape::class);
var_dump($shape->isAbstract(), $shape->getMethod('area')->isAbstract(), $shape->getMethod('name')->isProtected());
var_dump((new ReflectionClass(Demo\Unit::class))->isFinal());

$measured = new ReflectionClass(Demo\Measured::class);
var_dump($measured->isInterface(), $measured->getMethod('area')->isPublic());

try {
    new Demo\Shape();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
float(4)
string(6) "square"
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
Cannot instantiate abstract class Demo\Shape

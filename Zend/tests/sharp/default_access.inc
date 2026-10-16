<?php

require __DIR__ . '/harness/fixture.inc';
fixture('Defaults');

use Defaults\Counter;

echo Counter::make()->total(), "\n";

$counter = new Counter(5);
foreach (['count', 'views', 'size'] as $name) {
    echo $name, ' private: ', var_export((new ReflectionProperty(Counter::class, $name))->isPrivate(), true), "\n";
}
echo 'MAX private: ', var_export((new ReflectionClassConstant(Counter::class, 'MAX'))->isPrivate(), true), "\n";
echo 'empty private: ', var_export((new ReflectionMethod(Counter::class, 'empty'))->isPrivate(), true), "\n";
echo 'bonus private: ', var_export((new ReflectionMethod(Counter::class, 'bonus'))->isPrivate(), true), "\n";

$reads = [
    fn () => $counter->views,
    fn () => $counter->size,
    fn () => Counter::MAX,
    fn () => Counter::empty(),
    fn () => $counter->bonus(),
];
foreach ($reads as $read) {
    try {
        $read();
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
}

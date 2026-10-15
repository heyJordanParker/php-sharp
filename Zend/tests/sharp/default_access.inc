<?php

require __DIR__ . '/Defaults.sharp';

use Defaults\Counter;

echo Counter::make()->total(), "\n";

$counter = new Counter(5);
foreach (['count', 'views', 'size'] as $name) {
    echo $name, ' private: ', var_export((new ReflectionProperty(Counter::class, $name))->isPrivate(), true), "\n";
}
echo 'MAX private: ', var_export((new ReflectionClassConstant(Counter::class, 'MAX'))->isPrivate(), true), "\n";
echo 'empty private: ', var_export((new ReflectionMethod(Counter::class, 'empty'))->isPrivate(), true), "\n";

$reads = [
    fn () => $counter->views,
    fn () => $counter->size,
    fn () => Counter::MAX,
    fn () => Counter::empty(),
];
foreach ($reads as $read) {
    try {
        $read();
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
}

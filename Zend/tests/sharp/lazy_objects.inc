<?php

use LazyObjects\Holder;

// The plain PHP twin of Holder, whose collections are PHP arrays.
final class PlainHolder
{
    public array $items = [1, 2];
    public array $counts = ['a' => 1];
    public array $tags = ['x'];
}

$holder = new ReflectionClass(Holder::class);
$ghost = $holder->newLazyGhost(static function (Holder $holder): void {});
echo 'a lazy ghost reads its collections: ', json_encode([$ghost->items, $ghost->counts, $ghost->tags]), "\n";
$proxy = $holder->newLazyProxy(static fn (): Holder => new Holder());
echo 'a lazy proxy reads its collections: ', json_encode([$proxy->items, $proxy->counts, $proxy->tags]), "\n";

// ReflectionProperty::isInitialized() on a lazy proxy that getValue() or getRawValue() initialized, PHP# then plain PHP.
foreach (['items', 'counts', 'tags'] as $name) {
    foreach (['getValue', 'getRawValue'] as $read) {
        $initialized = [];
        foreach ([Holder::class, PlainHolder::class] as $class) {
            $reflection = new ReflectionClass($class);
            $proxy = $reflection->newLazyProxy(static fn (): object => $reflection->newInstanceWithoutConstructor());
            $property = new ReflectionProperty($class, $name);
            $property->$read($proxy);
            $initialized[] = var_export($property->isInitialized($proxy), true);
        }
        echo "isInitialized() of $name after $read() on a lazy proxy: ", implode(', ', $initialized), "\n";
    }
}

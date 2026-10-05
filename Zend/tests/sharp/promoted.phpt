--TEST--
PHP# constructor parameters with an access modifier declare fields and properties
--FILE--
<?php

require __DIR__ . '/Ticket.sharp';

foreach ((new ReflectionClass(Demo\Ticket::class))->getProperties() as $property) {
    $set = match (true) {
        $property->isPrivate() => '',
        $property->isReadOnly() => ' readonly',
        $property->isPrivateSet() => ' private(set)',
        $property->isProtectedSet() => ' protected(set)',
        default => '',
    };
    echo $property->isPrivate() ? 'private' : 'public', $set, ' ', $property->getType(), ' ', $property->getName(),
        $property->isPromoted() ? ' promoted' : '', "\n";
}

$ticket = new Demo\Ticket(10, 'A1', 3, 2);
echo $ticket->code, ' ', $ticket->seats, ' ', $ticket->total(), "\n";

try {
    $ticket->code = 'B2';
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
private int price promoted
public readonly string code promoted
public protected(set) int seats promoted
A1 3 24
Cannot modify readonly property Demo\Ticket::$code

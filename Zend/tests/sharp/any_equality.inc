<?php

require __DIR__ . '/Unchecked.sharp';

use Equalities\Point;
use Equalities\Suit;
use Equalities\Unchecked;

$values = [
    'string pro' => 'pro',
    'int 1' => 1,
    'true' => true,
    'list [1, 2]' => [1, 2],
    'list [2, 1]' => [2, 1],
    'map a: 1' => new Sharp\Map(['a', 1]),
    'set 2, 1' => new Sharp\Set([2, 1]),
    'Hearts' => Suit::Hearts,
    'Spades' => Suit::Spades,
    'Point(1)' => new Point(1),
    'Point(2)' => new Point(2),
    'the ticket' => Unchecked::$ticket,
    'another ticket' => new Equalities\Ticket(),
    'null' => null,
];
foreach ($values as $name => $value) {
    $equal = array_keys(array_filter(iterator_to_array(Unchecked::compare($value))));
    echo $name, ': ', implode(', ', $equal), "\n";
}

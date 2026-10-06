<?php

require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/Priority.inc';
require __DIR__ . '/harness/HasLabel.inc';
require __DIR__ . '/Status.sharp';
require __DIR__ . '/Suit.sharp';
require __DIR__ . '/Rank.sharp';
require __DIR__ . '/Shipment.sharp';
require __DIR__ . '/Task.sharp';

use Demo\HasLabel;
use Demo\Rank;
use Demo\Shipment;
use Demo\Status;
use Demo\Suit;
use Demo\Task;

function failure(callable $run): string
{
    try {
        $run();
        return 'no error';
    } catch (Error $error) {
        return $error::class . ': ' . $error->getMessage();
    }
}

for ($round = 0; $round < 100; $round++) {
    $shipment = new Shipment(Status::from('a'));
    $task = new Task(2);
    $results = [
        Status::from('a')->label(),
        Status::from('p') instanceof BackedEnum,
        Status::cases(),
        Status::parse('p'),
        Status::parse('zzz'),
        Status::Default,
        Status::Active instanceof HasLabel,
        (new ReflectionEnum(Status::class))->getInterfaceNames(),
        Status::Active->isActive(),
        Status::Paused->isActive(),
        failure(fn() => Status::from('nope')),
        (new ReflectionEnumBackedCase(Status::class, 'Paused'))->getAttributes()[0]->newInstance()->label,
        $shipment->code(),
        $shipment->state(),
        $shipment->describe(),
        $shipment->close() === Status::Closed,
        $shipment->status,
        (new Shipment())->state(),
        $shipment->pause(),
        Shipment::activeLabel(),
        Shipment::fallback(),
        Shipment::find('p') === Status::Paused,
        Shipment::find('zzz'),
        Shipment::kinds(),
        failure(fn() => new Shipment(Suit::Hearts)),
        Suit::Hearts->label(),
        Suit::size(),
        Suit::Spades instanceof UnitEnum && !Suit::Spades instanceof BackedEnum,
        Rank::from(-1),
        Rank::tryFrom(5),
        array_map(fn(Rank $rank) => $rank->value, Rank::cases()),
        Rank::High->above(Rank::Low),
        Rank::Low->above(Rank::Mid),
        $task->priority,
        $task->rank(),
        Task::parse(1),
        Task::parse(9),
    ];
}

var_dump(...$results);

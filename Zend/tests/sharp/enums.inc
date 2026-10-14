<?php

require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/Priority.inc';
require __DIR__ . '/harness/HasLabel.inc';
require __DIR__ . '/Stage.sharp';
require __DIR__ . '/Suit.sharp';
require __DIR__ . '/Rank.sharp';
require __DIR__ . '/Shipment.sharp';
require __DIR__ . '/Task.sharp';
require __DIR__ . '/PaymentResult.sharp';
require __DIR__ . '/CheckoutState.sharp';
require __DIR__ . '/Payments.sharp';

use Demo\CheckoutState;
use Demo\HasLabel;
use Demo\PaymentResult;
use Demo\Payments;
use Demo\Rank;
use Demo\Shipment;
use Demo\Stage;
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
    $shipment = new Shipment(Stage::from('a'));
    $task = new Task(2);
    $payments = new Payments();
    $results = [
        Stage::from('a')->label(),
        Stage::from('p') instanceof BackedEnum,
        Stage::cases(),
        Stage::parse('p'),
        Stage::parse('zzz'),
        Stage::Default,
        Stage::Active instanceof HasLabel,
        (new ReflectionEnum(Stage::class))->getInterfaceNames(),
        Stage::Active->isActive(),
        Stage::Paused->isActive(),
        failure(fn() => Stage::from('nope')),
        (new ReflectionEnumBackedCase(Stage::class, 'Paused'))->getAttributes()[0]->newInstance()->label,
        $shipment->code(),
        $shipment->state(),
        $shipment->describe(),
        $shipment->close() === Stage::Closed,
        $shipment->status,
        (new Shipment())->state(),
        $shipment->pause(),
        Shipment::activeLabel(),
        Shipment::fallback(),
        Shipment::find('p') === Stage::Paused,
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
        $payments->decline('card expired')->reason,
        $payments->decline('card expired') instanceof PaymentResult,
        $payments->settle($payments->pay('tx_1')),
        failure(fn() => $payments->settle($payments->decline('card expired'))),
        $payments->pay('tx_2')->label(),
        $payments->open() === CheckoutState\Open::instance(),
        $payments->open()->isOpen(),
        $payments->paying()->isOpen(),
        $payments->paying()->attempt,
        (new ReflectionClass(PaymentResult\Paid::class))->getParentClass()->getName(),
        (new ReflectionClass(PaymentResult::class))->isAbstract(),
        (new ReflectionClass(PaymentResult\Paid::class))->isFinal(),
        new PaymentResult\RequiresAction('https://bank.test') instanceof PaymentResult,
        failure(fn() => new CheckoutState\Open()),
        failure(fn() => $payments->pay('tx_3')->transactionId = 'tx_4'),
        $payments->describe($payments->pay('tx_9')),
        $payments->describe($payments->decline('card expired')),
        $payments->describe($payments->decline('no funds')),
        $payments->describe(new PaymentResult\RequiresAction('https://bank.test')),
        $payments->waiting($payments->open()),
        $payments->waiting($payments->paying()),
        $payments->outcome($payments->pay('tx_5')),
        $payments->outcome($payments->decline('no funds')),
        $payments->outcome(new PaymentResult\RequiresAction('https://bank.test')),
    ];
}

var_dump(...$results);

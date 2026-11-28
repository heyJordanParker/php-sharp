--TEST--
A PHP# enum runs from PHP# and plain PHP, and a PHP# class runs a plain PHP enum, as their PHP twins run
--FILE--
<?php

require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\CheckoutState;
use Demo\HasLabel;
use Demo\Interaction;
use Demo\PaymentResult;
use Demo\Payments;
use Demo\PopupInteraction;
use Demo\ScrollInteraction;
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
        Task::priorityOf(1),
        Task::priorityOf(9),
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
        $payments->same($payments->pay('tx_6'), $payments->pay('tx_6')),
        $payments->same($payments->pay('tx_6'), $payments->pay('tx_7')),
        $payments->same($payments->pay('tx_6'), $payments->decline('tx_6')),
        $payments->same($payments->pay('tx_6'), null),
        $payments->differs($payments->open(), $payments->open()),
        $payments->differs($payments->paying(), $payments->paying()),
        $payments->differs($payments->paying(), new CheckoutState\Paying('stripe', 2)),
        $payments->differs($payments->open(), $payments->paying()),
        $payments->differs($payments->open(), unserialize(serialize($payments->open()))),
        $payments->distinct($payments->open(), unserialize(serialize($payments->open()))),
        $payments->distinct($payments->open(), $payments->paying()),
        $payments->found([$payments->decline('a'), $payments->pay('tx_8')], $payments->pay('tx_8')),
        $payments->found([$payments->decline('a')], $payments->decline('b')),
        $payments->keyed(),
        $payments->interacted(new PopupInteraction()),
        $payments->interacted(new ScrollInteraction()),
        new ScrollInteraction() instanceof Interaction,
    ];
}

var_dump(...$results);
?>
--EXPECTF--
string(10) "Active (a)"
bool(true)
array(3) {
  [0]=>
  enum(Demo\Stage::Active)
  [1]=>
  enum(Demo\Stage::Paused)
  [2]=>
  enum(Demo\Stage::Closed)
}
enum(Demo\Stage::Paused)
enum(Demo\Stage::Active)
enum(Demo\Stage::Active)
bool(true)
array(3) {
  [0]=>
  string(13) "Demo\HasLabel"
  [1]=>
  string(8) "UnitEnum"
  [2]=>
  string(10) "BackedEnum"
}
bool(true)
bool(false)
string(67) "ValueError: "nope" is not a valid backing value for enum Demo\Stage"
string(17) "Paused by support"
string(1) "a"
string(6) "Active"
string(10) "Active (a)"
bool(true)
enum(Demo\Stage::Closed)
string(6) "Active"
enum(Demo\Stage::Paused)
string(10) "Active (a)"
enum(Demo\Stage::Active)
bool(true)
NULL
int(3)
string(%d) "TypeError: Demo\Shipment::__construct(): Argument #1 ($status) must be of type Demo\Stage, Demo\Suit given, called in %s on line %d"
string(6) "hearts"
int(2)
bool(true)
enum(Demo\Rank::Low)
NULL
array(3) {
  [0]=>
  int(-1)
  [1]=>
  int(0)
  [2]=>
  int(2)
}
bool(true)
bool(false)
enum(Demo\Priority::High)
int(2)
enum(Demo\Priority::Low)
NULL
string(12) "card expired"
bool(true)
string(4) "tx_1"
string(%d) "TypeError: Demo\Payments::settle(): Argument #1 ($paid) must be of type Demo\PaymentResult\Paid, Demo\PaymentResult\Declined given, called in %s on line %d"
string(7) "payment"
bool(true)
bool(true)
bool(false)
int(1)
string(18) "Demo\PaymentResult"
bool(true)
bool(true)
bool(true)
string(79) "Error: Call to private Demo\CheckoutState\Open::__construct() from global scope"
string(78) "Error: Cannot modify readonly property Demo\PaymentResult\Paid::$transactionId"
string(9) "Paid tx_9"
string(7) "Expired"
string(18) "Declined: no funds"
string(8) "Redirect"
bool(true)
bool(false)
string(4) "paid"
string(8) "no funds"
string(17) "https://bank.test"
bool(true)
bool(false)
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
int(1)
int(2)
bool(true)
bool(false)
array(4) {
  [0]=>
  int(2)
  [1]=>
  int(2)
  [2]=>
  int(2)
  [3]=>
  int(1)
}
string(5) "popup"
string(8) "scroll 3"
bool(true)

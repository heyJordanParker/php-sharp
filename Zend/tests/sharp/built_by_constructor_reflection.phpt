--TEST--
Reflection builds a PHP# struct or class only through its constructor: newInstanceWithoutConstructor, newLazyGhost and resetAsLazyGhost throw an Error that names the type, and newInstance, newInstanceArgs and newLazyProxy's factory run it
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Queued\Email;
use Queued\Subscriber;

$rows = [
    'newInstanceWithoutConstructor of a struct' => fn () => (new ReflectionClass(Email::class))->newInstanceWithoutConstructor(),
    'newInstanceWithoutConstructor of a class' => fn () => (new ReflectionClass(Subscriber::class))->newInstanceWithoutConstructor(),
    'newLazyGhost of a class' => fn () => (new ReflectionClass(Subscriber::class))->newLazyGhost(static function (Subscriber $subscriber): void {}),
    'resetAsLazyGhost of an object' => function () {
        $subscriber = new Subscriber(new Email('ada@example.com'));
        (new ReflectionClass(Subscriber::class))->resetAsLazyGhost($subscriber, static function (Subscriber $subscriber): void {});
        return $subscriber->email->address;
    },
    'newInstance runs the constructor' => fn () => (new ReflectionClass(Email::class))->newInstance('nobody'),
    'newInstanceArgs runs the constructor' => fn () => (new ReflectionClass(Email::class))->newInstanceArgs(['ada@example.com'])->address,
    'newLazyProxy builds through its factory' => function () {
        $proxy = (new ReflectionClass(Subscriber::class))->newLazyProxy(
            static fn (Subscriber $proxy): Subscriber => new Subscriber(new Email('lazy@example.com')),
        );
        return $proxy->email->address;
    },
    'isCloneable' => fn () => (new ReflectionClass(Subscriber::class))->isCloneable(),
];
foreach ($rows as $name => $row) {
    try {
        $made = var_export($row(), true);
        echo "$name: $made\n";
    } catch (Throwable $error) {
        echo "$name: ", $error::class, ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
newInstanceWithoutConstructor of a struct: Error: Queued.Email is built only through its constructor, which this creation doesn't run
newInstanceWithoutConstructor of a class: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run
newLazyGhost of a class: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run
resetAsLazyGhost of an object: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run
newInstance runs the constructor: ValueError: "nobody" is no email address
newInstanceArgs runs the constructor: 'ada@example.com'
newLazyProxy builds through its factory: 'lazy@example.com'
isCloneable: true

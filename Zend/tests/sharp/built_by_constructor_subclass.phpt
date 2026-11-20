--TEST--
A plain PHP class that extends a PHP# class is built only through a constructor chain that runs the PHP# one, so newInstanceWithoutConstructor and a lazy ghost of it throw an Error that names the PHP# class, as a test double that skips the constructor does
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Queued\Email;
use Queued\Subscriber;

class PlainSubscriber extends Subscriber
{
}

$rows = [
    'new runs the constructor chain' => fn () => (new PlainSubscriber(new Email('ada@example.com')))->email->address,
    'newInstanceWithoutConstructor' => fn () => (new ReflectionClass(PlainSubscriber::class))->newInstanceWithoutConstructor(),
    'newLazyGhost' => fn () => (new ReflectionClass(PlainSubscriber::class))->newLazyGhost(static function (PlainSubscriber $subscriber): void {}),
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
new runs the constructor chain: 'ada@example.com'
newInstanceWithoutConstructor: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run
newLazyGhost: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run

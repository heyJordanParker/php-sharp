--TEST--
serialize writes a PHP# struct or class as its stored form, and unserialize builds it again through its constructor, so an untouched copy reads back equal and a tampered one throws its constructor's error
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Queued\Email;
use Queued\Subscriber;

$email = new Email('ada@example.com');
$stored = serialize($email);
echo $stored, "\n";
var_dump(unserialize($stored) == $email);

$rows = [
    'a tampered address' => fn () => unserialize(str_replace('ada@example.com', 'ada.example.com', $stored)),
    'a missing field' => fn () => unserialize('O:12:"Queued\Email":0:{}'),
    'a field of another type' => fn () => unserialize('O:12:"Queued\Email":1:{s:7:"address";i:7;}'),
    'data that names the object inside its own stored form' => fn () => unserialize('O:17:"Queued\Subscriber":1:{s:5:"email";r:1;}'),
];
foreach ($rows as $name => $row) {
    try {
        $read = var_export($row(), true);
        echo "$name: read back $read\n";
    } catch (Throwable $error) {
        echo "$name: ", $error::class, ': ', $error->getMessage(), "\n";
    }
}

// The class's constructor runs once for `new` and once for the copy, which keeps one object for two references.
$subscriber = new Subscriber($email);
[$first, $second] = unserialize(serialize([$subscriber, $subscriber]));
echo Subscriber::builtCount(), ' ', $first->email->address, ' ', var_export($first === $second, true), "\n";
?>
--EXPECTF--
O:12:"Queued\Email":1:{s:7:"address";s:15:"ada@example.com";}
bool(true)
a tampered address: ValueError: "ada.example.com" is no email address
a missing field: ValueError: address: missing
a field of another type: ValueError: address: expected string, got int 7

Warning: unserialize(): Error at offset 44 of 45 bytes in %s on line %d
data that names the object inside its own stored form: read back false
2 ada@example.com true

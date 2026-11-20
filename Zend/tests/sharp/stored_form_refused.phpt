--TEST--
serialize throws an Error that names a PHP# type with no stored form: a constructor parameter that holds no property, as a service, a field that holds a function, or a Map keyed by a type with no text, and unserialize refuses data for one
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$rows = [
    'a class whose constructor takes a service' => fn () => serialize(new Queued\Stamper(new Sharp\Time\Clock())),
    'a struct with a function field' => fn () => serialize(new Queued\Hook(static fn (int $step): int => $step + 1)),
    'a struct with a Map keyed by a struct' => fn () => serialize(new Queued\Route(Queued\Payloads::byParcel())),
    'data for a struct with a function field' => fn () => unserialize('O:11:"Queued\Hook":0:{}'),
];
foreach ($rows as $name => $row) {
    try {
        $row();
        echo "$name: no error\n";
    } catch (Throwable $error) {
        echo "$name: ", $error::class, ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
a class whose constructor takes a service: Error: `serialize` writes the stored form of Queued.Stamper, the fields `Json.encode` writes, and the main constructor parameter `clock` holds no property.
a struct with a function field: Error: `serialize` writes the stored form of Queued.Hook, the fields `Json.encode` writes, and a field of it holds a function, which no stored form holds.
a struct with a Map keyed by a struct: Error: `serialize` writes the stored form of Queued.Route, the fields `Json.encode` writes, and its field `labels` is a Map keyed by Queued.Parcel, which has no stored text.
data for a struct with a function field: Error: Queued.Hook is built only through its constructor, which this creation doesn't run

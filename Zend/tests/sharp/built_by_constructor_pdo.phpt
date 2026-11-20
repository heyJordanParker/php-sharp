--TEST--
PDO's FETCH_CLASS, which creates the object before its constructor runs, throws an Error that names a PHP# struct or class, with or without FETCH_PROPS_LATE
--EXTENSIONS--
pdo_sqlite
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = [
    'FETCH_CLASS into a struct' => fn () => $pdo->query("SELECT 'ada@example.com' AS address")
        ->fetchAll(PDO::FETCH_CLASS, Queued\Email::class),
    'FETCH_CLASS into a class' => fn () => $pdo->query("SELECT 'ada@example.com' AS address")
        ->fetchAll(PDO::FETCH_CLASS, Queued\Subscriber::class),
    'FETCH_CLASS with FETCH_PROPS_LATE' => fn () => $pdo->query("SELECT 'ada@example.com' AS address")
        ->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, Queued\Email::class, ['ada@example.com']),
    'fetchObject' => fn () => $pdo->query("SELECT 'ada@example.com' AS address")->fetchObject(Queued\Email::class),
];
foreach ($rows as $name => $row) {
    try {
        $row();
        echo "$name: fetched\n";
    } catch (Throwable $error) {
        echo "$name: ", $error::class, ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
FETCH_CLASS into a struct: Error: Queued.Email is built only through its constructor, which this creation doesn't run
FETCH_CLASS into a class: Error: Queued.Subscriber is built only through its constructor, which this creation doesn't run
FETCH_CLASS with FETCH_PROPS_LATE: Error: Queued.Email is built only through its constructor, which this creation doesn't run
fetchObject: Error: Queued.Email is built only through its constructor, which this creation doesn't run

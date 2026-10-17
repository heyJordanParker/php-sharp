--TEST--
PHP# Clock reads the time and sleeps for a Duration, and Random gives seeded or secure values in the ranges it names
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

use Sharp\Time\Duration;

$clock = new Sharp\Clock();
var_dump(abs($clock->now() - time()) <= 1);
$start = $clock->timestamp();
$clock->sleep(Duration::ofMilliseconds(2));
$elapsed = $clock->elapsedSince($start);
var_dump($elapsed->nanoseconds >= 2_000_000, $clock->timestamp() >= $start);
var_dump(Duration::ofSeconds(3)->nanoseconds, Duration::ofMilliseconds(2500)->seconds);
foreach ([fn () => $clock->sleep(new Duration(-1)), fn () => $clock->elapsedSince($clock->timestamp() + 1_000_000_000)] as $refused) {
    try {
        $refused();
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}

$first = new Sharp\Random(42);
$second = new Sharp\Random(42);
$draws = [];
for ($i = 0; $i < 20; $i++) {
    $draws[] = $first->nextInt(1, 7);
}
var_dump($draws === array_map(fn () => $second->nextInt(1, 7), range(1, 20)));
var_dump(min($draws) >= 1 && max($draws) <= 6, $first->nextInt(5, 5));
$secure = new Sharp\Random();
$float = $secure->nextFloat();
var_dump($float >= 0.0 && $float < 1.0, strlen($secure->nextBytes(4)));
$shuffled = $secure->shuffle([1, 2, 3, 4]);
sort($shuffled);
var_dump($shuffled === [1, 2, 3, 4], in_array($secure->pick(['a', 'b']), ['a', 'b'], true), $secure->pick([]));
foreach ([fn () => $secure->nextInt(3, 1), fn () => $secure->nextBytes(0)] as $refused) {
    try {
        $refused();
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
int(3000000000)
int(2)
ValueError: Clock.sleep: the duration is negative
ValueError: Clock.elapsedSince: the start is later than now
bool(true)
bool(true)
int(5)
bool(true)
int(4)
bool(true)
bool(true)
NULL
ValueError: Random.nextInt: max 1 is less than min 3
ValueError: Random.nextBytes: the length 0 is less than 1

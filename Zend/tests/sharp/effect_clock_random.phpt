--TEST--
PHP# SystemClock reads the time and sleeps for a Duration, a FakeClock reads its instant until sleep or advance moves it and returns from sleep at once, and SystemRandom and a seeded FakeRandom give values in the ranges Random names
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

use Sharp\Time\Duration;

$clock = new Sharp\Time\SystemClock();
// The clock reads a second between the one before the read and the one after, however long the read takes. Both come
// from DateTimeImmutable, as now() does.
$before = (new DateTimeImmutable())->getTimestamp();
$now = $clock->now()->epochSeconds;
var_dump($before <= $now && $now <= (new DateTimeImmutable())->getTimestamp(), $clock->now()->nanosecond % 1_000 === 0);
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

// A fake clock reads its instant, and a sleep returns at once and moves it on, as an advance does.
$fixed = new Sharp\Testing\FakeClock(new Sharp\Time\Instant(1792693800, 250));
$start = $fixed->timestamp();
// A real sleep of five seconds takes five seconds at the least, so one that took less did not sleep.
$before = hrtime(true);
$fixed->sleep(Duration::ofSeconds(5));
var_dump(hrtime(true) - $before < 5_000_000_000, $fixed->now()->epochSeconds, $fixed->now()->nanosecond);
$fixed->advance(Duration::ofMilliseconds(1));
var_dump($fixed->elapsedSince($start)->nanoseconds, count($fixed->slept), $fixed->slept[0]->seconds);
try {
    $fixed->sleep(new Duration(-1));
} catch (ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}

$first = new Sharp\Testing\FakeRandom(42);
$second = new Sharp\Testing\FakeRandom(42);
$draws = [];
for ($i = 0; $i < 20; $i++) {
    $draws[] = $first->nextInt(1, 7);
}
var_dump($draws === array_map(fn () => $second->nextInt(1, 7), range(1, 20)));
var_dump(min($draws) >= 1 && max($draws) <= 6, $first->nextInt(5, 5));
$secure = new Sharp\SystemRandom();
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
bool(true)
int(3000000000)
int(2)
ValueError: Clock.sleep: the duration is negative
ValueError: Clock.elapsedSince: the start is later than now
bool(true)
int(1792693805)
int(250)
int(5001000000)
int(1)
int(5)
ValueError: FakeClock.sleep: the duration is negative
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

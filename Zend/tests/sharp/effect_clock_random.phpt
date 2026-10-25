--TEST--
PHP# Clock reads the time and sleeps for a Duration, Clock.fixed always reads one instant, returns from sleep at once and measures no time, and Random gives seeded or secure values in the ranges it names
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

use Sharp\Time\Duration;

$clock = new Sharp\Time\Clock();
var_dump(abs($clock->now()->epochSeconds - time()) <= 1, $clock->now()->nanosecond % 1_000 === 0);
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

// A fixed clock always reads its instant, returns from sleep at once, and measures no time.
$fixed = Sharp\Time\Clock::fixed(new Sharp\Time\Instant(1792693800, 250));
$start = $fixed->timestamp();
$before = hrtime(true);
$fixed->sleep(Duration::ofSeconds(5));
var_dump(hrtime(true) - $before < 1_000_000_000, $fixed->now()->epochSeconds, $fixed->now()->nanosecond);
var_dump($fixed->timestamp() === $start, $fixed->elapsedSince($start)->nanoseconds);
try {
    $fixed->sleep(new Duration(-1));
} catch (ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
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
bool(true)
int(3000000000)
int(2)
ValueError: Clock.sleep: the duration is negative
ValueError: Clock.elapsedSince: the start is later than now
bool(true)
int(1792693800)
int(250)
bool(true)
int(0)
ValueError: Clock.sleep: the duration is negative
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

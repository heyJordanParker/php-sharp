<?php

use Queued\Payloads;

/**
 * `$value` on one line: each entry of a Map or of a keyed array, each element of a Set or a list, and an object's class
 * and fields. Plain PHP reads a PHP# field that holds a Map or a Set as its PHP array, spec section 12, so
 * Payloads::kinds() says which class PHP# holds there.
 */
function shown(mixed $value): string
{
    if ($value instanceof Sharp\Map) {
        $entries = [];
        foreach ($value as $key => $held) {
            $entries[] = shown($key) . ': ' . shown($held);
        }
        return 'Map[' . implode(', ', $entries) . ']';
    }

    return match (true) {
        $value instanceof Sharp\Set => 'Set[' . implode(', ', array_map(shown(...), $value->toList())) . ']',
        $value instanceof UnitEnum => $value::class . '.' . $value->name,
        is_array($value) && array_is_list($value) => '[' . implode(', ', array_map(shown(...), $value)) . ']',
        is_array($value) => '[' . implode(', ', array_map(
            static fn (int|string $key, mixed $held): string => shown($key) . ' => ' . shown($held),
            array_keys($value),
            $value,
        )) . ']',
        is_object($value) => $value::class . '(' . implode(', ', array_map(shown(...), get_object_vars($value))) . ')',
        default => var_export($value, true),
    };
}

/** What a queue worker or a cache read gets back for `$value`. */
function read_back(mixed $value): mixed
{
    return unserialize(serialize($value));
}

/** Each PHP# read of the values `$payload` holds, as a worker in another process runs them. */
function read_payload(string $payload): string
{
    $values = unserialize($payload);
    [$counts, $bySpeed, $shipment, $ledger, $tags, $byParcel, $byCourier] = $values;
    $ledger->record('shipped', 1);

    return implode("\n", [
        count($values) . ' values',
        shown($counts) . ' ' . var_export(Payloads::holdsCounts($counts), true),
        shown($bySpeed) . ' ' . Payloads::express($bySpeed) . ' ' . var_export($bySpeed->keys()[0] === Queued\Speed::Express, true),
        shown($shipment) . ' ' . Payloads::kinds($shipment) . ' ' . var_export(Payloads::sameShipment($shipment), true),
        shown($ledger) . ' ' . Payloads::kinds($ledger),
        shown($tags) . ' ' . var_export(Payloads::tagged($tags), true),
        shown($byParcel) . ' ' . Payloads::parcel($byParcel),
        shown($byCourier) . ' ' . Payloads::courier($byCourier),
    ]);
}

/** Each row serializes a value as a queue or a cache does, reads it back, and runs PHP# code on what came back. */
function rows(): array
{
    // Plain PHP gets a Map of string keys and a Set from PHP# as PHP arrays, spec section 12, so a row that serializes
    // one of those objects makes it with new.
    return [
        'a Map<string, int>' => function () {
            $back = read_back(new Sharp\Map(['tea', 2, 'jam', 5]));
            return shown($back) . ' ' . var_export(Payloads::holdsCounts($back), true);
        },
        'a Map keyed by an enum' => function () {
            $back = read_back(Payloads::bySpeed());
            return shown($back) . ' ' . Payloads::express($back);
        },
        'a Map nested in a List' => function () {
            $back = read_back([new Sharp\Map(['tea', 1]), new Sharp\Map(['jam', 2, 'bun', 3])]);
            return shown($back) . ' ' . var_export(Payloads::holdsBatches($back), true);
        },
        'a struct field' => function () {
            $back = read_back(Payloads::shipment());
            return shown($back) . ' ' . Payloads::kinds($back) . ' ' . var_export(Payloads::sameShipment($back), true);
        },
        'a class field' => function () {
            $ledger = Payloads::ledger();
            $back = read_back($ledger);
            $back->record('shipped', 1);
            return shown($back) . ' ' . Payloads::kinds($back) . ' ' . shown($ledger);
        },
        'a Set' => function () {
            $back = read_back(new Sharp\Set(['vip', 'new']));
            return shown($back) . ' ' . var_export(Payloads::holdsTags($back), true) . ' ' . var_export(Payloads::tagged($back), true);
        },
        'an empty Map' => function () {
            $back = read_back(new Sharp\Map());
            return shown($back) . ' ' . count($back) . ' ' . var_export(Payloads::holdsCounts($back), true);
        },
        'a Map keyed by a struct' => function () {
            $back = read_back(Payloads::byParcel());
            return shown($back) . ' ' . Payloads::parcel($back);
        },
        'a Map keyed by a class that declares ==' => function () {
            $back = read_back(Payloads::byCourier());
            return shown($back) . ' ' . Payloads::courier($back);
        },
        'a Map keyed by a plain PHP object, which has no ==' => function () {
            $key = new stdClass();
            [$keyBack, $back] = read_back([$key, new Sharp\Map([$key, 'held'])]);
            return shown($back) . ' ' . var_export($back[$keyBack], true) . ' ' . var_export($back[$key], true);
        },
        'the form serialize writes' => fn () => serialize(new Sharp\Map(['tea', 2, 'jam', 5])) . ' ' . serialize(new Sharp\Set(['vip', 'new'])),
        'a read-back in a fresh process' => function () {
            $payload = serialize([new Sharp\Map(['tea', 2, 'jam', 5]), Payloads::bySpeed(), Payloads::shipment(), Payloads::ledger(),
                new Sharp\Set(['vip', 'new']), Payloads::byParcel(), Payloads::byCourier()]);
            $worker = 'require ' . var_export(__DIR__ . '/harness/fixture.inc', true) . '; require '
                . var_export(__DIR__ . '/harness/Probe.inc', true) . '; require ' . var_export(__FILE__, true) . ';'
                . ' fixture("Queued"); echo read_payload(base64_decode($argv[1]));';
            return shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($worker) . ' '
                . escapeshellarg(base64_encode($payload)) . ' 2>&1');
        },
        'a bad key count' => fn () => shown(unserialize('O:9:"Sharp\Map":3:{i:0;s:3:"tea";i:1;i:2;i:2;s:3:"jam";}')),
        'a Set under named keys' => fn () => shown(unserialize('O:9:"Sharp\Set":1:{s:1:"a";s:3:"vip";}')),
        'a wrong type, a Set where a Map is declared' => fn () => shown(tampered(Payloads::shipment(), 'O:9:"Sharp\Map"', 'O:9:"Sharp\Set"')),
        'a wrong value in a class field' => fn () => shown(tampered(Payloads::ledger(), 'i:1;i:2;}', 'i:1;s:3:"two";}')),
        'a wrong key in a struct field' => fn () => shown(tampered(Payloads::shipment(), 's:7:"fragile";', 'i:9;')),
        'a wrong element in a Set field' => fn () => shown(tampered(Payloads::crate(), 'E:20:"Queued\Speed:Express";', 'i:5;')),
        'a wrong value inside a List<Map<string, int>> field' => fn () => shown(tampered(Payloads::crate(), 's:3:"tea";i:1;i:1;}}}', 's:3:"tea";i:1;s:1:"x";}}}')),
        'a generic class field' => fn () => shown(tampered(Payloads::box(), 'i:1;i:1;}', 'i:1;s:1:"x";}')),
        'Sharp.Encoding.Serialized, which allows no class by default' => function () {
            $counts = new Sharp\Map(['tea', 2, 'jam', 5]);
            return shown(Payloads::throughSerialized($counts)) . ' ' . Payloads::serializedKinds($counts, []);
        },
        'Serialized allowing a class by its PHP# name' => fn () => shown(Payloads::throughSerializedAllowing(Payloads::crate(), ['Queued.Crate']))
            . ' ' . Payloads::serializedKinds(Payloads::crate(), ['Queued.Crate']),
        'allowed_classes => false, which still checks each key\'s class' => fn () => shown(unserialize(serialize([new Sharp\Set(['vip', 'new']), Payloads::byParcel()]), ['allowed_classes' => false])),
        '__unserialize on a Map that holds entries' => function () {
            $map = new Sharp\Map(['tea', 2, 'jam', 5]);
            $map->__unserialize(['bun', 3]);
            return shown($map);
        },
        'a second constructor call on a Set that holds entries' => function () {
            $set = new Sharp\Set(['vip', 'new']);
            $set->__construct(['old']);
            return shown($set);
        },
    ];
}

/** unserialize of the bytes serialize writes for `$value`, with `$search` replaced, which they must hold. */
function tampered(mixed $value, string $search, string $replace): mixed
{
    $serialized = serialize($value);
    if (!str_contains($serialized, $search)) {
        throw new LogicException("$serialized holds no $search");
    }
    return unserialize(str_replace($search, $replace, $serialized));
}

/** Each row's name and what it printed, or the exception it threw. */
function run_rows(): void
{
    foreach (rows() as $name => $row) {
        try {
            $printed = $row();
        } catch (Throwable $error) {
            $printed = $error::class . ': ' . $error->getMessage();
        }
        echo $name, ': ', $printed, "\n";
    }
}

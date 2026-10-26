<?php

use Demo\Carton;
use Demo\Crossings;
use Demo\Held;
use Demo\Loosely;
use Demo\Quiet;
use Demo\Subscriber;
use Demo\Withholder;
use Sharp\Map;

function reader_attempt(Closure $read): string
{
    try {
        return $read();
    } catch (TypeError $e) {
        return 'TypeError: ' . $e->getMessage();
    }
}

function reader_ksort(): string
{
    $box = new Carton();
    ksort($box->payload);

    return $box->payloadText() . ' ' . var_export($box->readsOnePayload(), true);
}

function reader_reference(): string
{
    $box = new Carton();
    $r = &$box->payload;
    $r['k'] = 1;
    $seen = [get_debug_type($r), $box->payloadText(), $box->payloadAt('k'), Crossings::cartonPayloadClass($box)];
    $box->putPayload('j', 5);
    $seen[] = json_encode($r);
    $box->replacePayload();
    $seen[] = get_debug_type($r) . ' ' . json_encode($r);
    $r = ['x' => 9];
    $seen[] = get_debug_type($r) . ' ' . $box->payloadText();
    $r['y'] = 25;
    unset($r);
    $seen[] = $box->payloadText();
    $seen[] = var_export($box->readsOnePayload(), true);

    return implode(' / ', $seen);
}

function reader_reference_wrong_type(): string
{
    $box = new Carton();
    $r = &$box->payload;
    $seen = [reader_attempt(function () use (&$r) {
        $r = 'x';
        return 'no TypeError';
    })];
    $seen[] = reader_attempt(function () use (&$r) {
        $r = ['k' => 'x'];
        return 'no TypeError';
    });
    $seen[] = json_encode($r);

    return implode(' / ', $seen);
}

function reader_copy_after_ksort(): string
{
    $box = new Carton();
    ksort($box->payload);
    $clone = clone $box;
    $other = new Carton();
    ksort($other->payload);

    return $clone->payloadText() . ' / ' . $other->copied()->payloadText();
}

function reader_copy_while_held(): string
{
    $box = new Carton();
    $r = &$box->payload;
    $clone = clone $box;
    $clone->putPayload('z', 9);
    $copied = $box->copied();
    $copied->putPayload('y', 8);

    return implode(' / ', [$box->payloadText(), json_encode($r), $clone->payloadText(), $copied->payloadText()]);
}

function reader_reference_to_what_no_plan_rebuilds(): string
{
    $seen = [];
    foreach (['loose' => 'looseType', 'shelved' => 'shelvedType', 'anyValues' => 'anyValuesType'] as $property => $type) {
        $loosely = new Loosely();
        $r = &$loosely->$property;
        unset($r);
        $seen[] = "$property " . $loosely->$type();
    }

    return implode(' / ', $seen);
}

function reader_index_write_after_ksort(): string
{
    $box = new Carton();
    ksort($box->levels);
    $levels = $box->putLevel();
    ksort($box->mixedKeys);

    return "$levels " . $box->putMixedKeys();
}

function reader_index_write_while_held(): string
{
    $box = new Carton();
    $r = &$box->tags;
    $box->tagA();

    return get_debug_type($r['a'] ?? null) . ' ' . json_encode($r);
}

function reader_reference_writes_checked_on_read(): string
{
    $seen = [];
    foreach (['x', ['n' => 1]] as $written) {
        $box = new Carton();
        $r = &$box->payload;
        $r['k'] = $written;
        $seen[] = reader_attempt(fn () => $box->payloadText());
    }

    return implode(' / ', $seen);
}

function reader_reference_key_checked_each_read(): string
{
    $box = new Carton();
    $r = &$box->levels;
    $r['zzz'] = 1;
    unset($r);

    return reader_attempt(fn () => (string) $box->putLevel()) . ' / ' . reader_attempt(fn () => (string) $box->putLevel());
}

function reader_foreach_by_reference(): string
{
    $box = new Carton();
    foreach ($box->payload as &$value) {
        $value++;
    }
    unset($value);

    return $box->payloadText();
}

function reader_array_walk(): string
{
    $box = new Carton();
    array_walk($box->payload, function (int &$value) {
        $value *= 10;
    });

    return $box->payloadText();
}

function reader_sort_set(): string
{
    $box = new Carton();
    sort($box->codes);

    return $box->codesText();
}

function reader_hidden_append(): string
{
    $subscriber = new Subscriber();
    $subscriber->hide('secret');

    return $subscriber->hiddenText();
}

function reader_field_getters(): string
{
    $withholder = new Withholder();
    $withholder->hide('secret');

    return (new Quiet())->word . ' / ' . $withholder->hiddenText();
}

function reader_union_setter(): string
{
    $box = new Carton();
    $box->picked = ['a'];

    return $box->pickedText();
}

function reader_union_parameters(): string
{
    return Crossings::unioned(['a', 'b']) . ' / ' . Crossings::unioned(7) . ' / ' . Crossings::mapOrInt(['a' => 1]);
}

function reader_array_iterator(): string
{
    $iterator = @new ArrayIterator(new Carton());
    $seen = [];
    foreach ($iterator as $property => $value) {
        $seen[] = "$property " . get_debug_type($value) . ' ' . json_encode($value);
    }
    $iterator->rewind();
    $seen[] = 'current() ' . get_debug_type($iterator->current()) . ' ' . json_encode($iterator->current());
    $seen[] = 'getArrayCopy() ' . get_debug_type($iterator->getArrayCopy()['payload']) . ' ' . json_encode($iterator->getArrayCopy());

    return implode(' / ', $seen);
}

function reader_logged_numbers(array &$log): Generator
{
    foreach ([1, 2] as $number) {
        $log[] = "read $number";
        yield $number;
    }
}

function reader_lazy_iterable(): string
{
    $log = [];
    foreach (Crossings::doubled(reader_logged_numbers($log)) as $value) {
        $log[] = "loop $value";
    }

    return implode(', ', $log);
}

function reader_iterable_of_maps(): string
{
    $rows = iterator_to_array(Crossings::keyed([1, 2]));

    return implode(',', array_map(get_debug_type(...), $rows)) . ' ' . json_encode($rows);
}

function reader_iterable_type(): string
{
    return var_export(Crossings::doubled([1]) instanceof Traversable, true) . ' '
        . (new ReflectionMethod(Crossings::class, 'doubled'))->getReturnType() . ' '
        . (new ReflectionMethod(Crossings::class, 'keyed'))->getReturnType();
}

function reader_var_export(): string
{
    return str_replace("\n", ' ', var_export((new Carton())->payload, true) . ' / ' . var_export(new Map(['a', 1]), true)
        . ' / ' . var_export(new Carton(), true));
}

// Each write puts a string where a List<int> holds ints, and the first PHP# read throws, naming the element.
function reader_list_writes_checked_on_read(): string
{
    $writes = [
        'append' => fn (Carton $box) => $box->counts[] = 'z',
        'index write' => fn (Carton $box) => $box->counts[1] = 'z',
        'nested write' => fn (Carton $box) => $box->grid[0][] = 'z',
        'reference write' => function (Carton $box) {
            $r = &$box->counts;
            $r[] = 'z';
        },
        'array_push()' => fn (Carton $box) => array_push($box->counts, 'z'),
        'foreach by reference' => function (Carton $box) {
            foreach ($box->counts as &$count) {
                $count = 'z';
            }
        },
    ];
    $seen = [];
    foreach ($writes as $write => $run) {
        $box = new Carton();
        $run($box);
        $seen[] = "$write " . reader_attempt(fn () => $box->secondDoubled() . ' ' . $box->gridFirst());
    }

    return implode(' / ', $seen);
}

// A write that fits reads back as written, and a reference plain PHP holds stays the property's, both ways.
function reader_list_writes_that_fit(): string
{
    $box = new Carton();
    $box->counts[] = 3;
    $box->counts[0] = 5;
    sort($box->counts);
    $box->grid[0][] = 2;
    $seen = [$box->countsText() . ' ' . $box->gridFirst()];
    $r = &$box->counts;
    $r[] = 7;
    $seen[] = $box->countsText();
    $box->addCount(8);
    $r[] = 9;
    $seen[] = $box->countsText() . ' ' . json_encode($r);
    $r[] = 'z';
    $seen[] = reader_attempt(fn () => $box->countsText());

    return implode(' / ', $seen);
}

// A static property, a property the constructor never set, and one whose type names its class's type parameter, which
// PHP# reads with the object's type arguments, take the same check.
function reader_list_writes_into_other_slots(): string
{
    $seen = [];
    Carton::$totals[] = 'z';
    $seen[] = 'static ' . reader_attempt(fn () => (string) Carton::secondTotal());
    Carton::$totals = [1, 2];
    Carton::$totals[1] = 3;
    $seen[] = 'static that fits ' . Carton::secondTotal();
    $box = (new ReflectionClass(Carton::class))->newInstanceWithoutConstructor();
    $box->later[] = 'z';
    $seen[] = 'uninitialized ' . reader_attempt(fn () => $box->laterText());
    $held = Held::ofList();
    $held->value[] = 'z';
    $seen[] = 'type parameter ' . reader_attempt(fn () => json_encode($held->get()));

    return implode(' / ', $seen);
}

// Plain PHP's write into a Map or a Set property crosses each entry as the property's type takes it, and a wrong one
// throws at the write, leaving the property as it was, as a write into a Map a Map's value holds does.
function reader_table_writes_checked_at_write(): string
{
    $box = new Carton();
    $writes = [
        'a string value into a Map<string, int>' => fn () => $box->payload['k'] = 'x',
        'a key no case backs into a Map<Demo.Level, int>' => fn () => $box->levels['zzz'] = 1,
        'a string element into a Set<int>' => fn () => $box->numbers[] = 'x',
        'a string value into a static Map<string, int>' => fn () => Carton::$stock['k'] = 'x',
        'a string into a nested Map<string, int>' => fn () => $box->nested['k']['m'] = 'x',
    ];
    $seen = [];
    foreach ($writes as $write => $run) {
        $seen[] = "$write " . reader_attempt(function () use ($run) {
            $run();
            return 'no TypeError';
        });
    }
    $box->payload[5] = 5;
    $box->levels['high'] = 2;
    $box->numbers[] = 2;
    $seen[] = 'writes that fit ' . $box->entriesText() . ' ' . Carton::stockText();

    return implode(' / ', $seen);
}

// A Map or a Set PHP# code makes, by a literal or a standard library method, carries its type from when it is made, so
// plain PHP's write into it is checked at the write, though it never met a typed place.
function reader_tables_typed_from_creation(): string
{
    $box = new Carton();
    $writes = [
        'a literal' => fn () => $box->handLiteral('k', 'x'),
        'groupBy()' => fn () => $box->handGrouped('k', 5),
        'associateBy()' => fn () => $box->handAssociated('k', 'x'),
        'entries()' => fn () => $box->handEntries('k', 5),
        'toSet()' => fn () => $box->handSet('x'),
        'writes that fit' => function () use ($box) {
            $box->handLiteral('k', 2);
            $box->handGrouped('odd', [3]);
            $box->handSet(4);
            return 'no TypeError';
        },
    ];
    $seen = [];
    foreach ($writes as $write => $run) {
        $seen[] = "$write " . reader_attempt(function () use ($run) {
            return $run() ?? 'no TypeError';
        });
    }

    return implode(' / ', $seen);
}

// A Map whose type names a class or a type parameter carries that type too: one an initial value or a static makes,
// with the type arguments of the object or the static, one a parameter or a result crosses into, and one a method value
// or a literal makes in a generic method, or in a lambda written there, with the method's type arguments.
function reader_tables_typed_by_names(): string
{
    $box = new Carton();
    $held = Held::ofInt();
    $writes = [
        'an initial value of a Map<string, T>' => fn () => $held->handIndex('k', 'x'),
        'a static of a Map<string, T>' => fn () => $held->handPool('k', 'x'),
        'a Map<Demo.Level, Demo.Carton> parameter' => fn () => $box->handGiven(['high' => new Carton()], 'high', 5),
        'a Map<string, Demo.Carton> result' => fn () => $box->handResult(['a' => new Carton()], 'k', 5),
        'a Map<string, Demo.Carton> local' => fn () => $box->handLocal(['a' => new Carton()], 'k', 5),
        'entries() as a method value in a generic method' => fn () => $box->handEntriesValue('k', 'x'),
        'a Map<string, TItem> [:] local in a generic method' => fn () => $box->handPlaced('k', 'x'),
        'a Map<string, T> [:] a lambda in a generic method returns' => fn () => $box->handKeyed('j', 'x'),
        'a Map<string, T> [:] a lambda in a lambda in a generic method returns' => fn () => $box->handNested('j', 'x'),
        'writes that fit' => function () use ($box, $held) {
            $box->handPlaced('k', 4);
            $box->handKeyed('j', 4);
            $box->handNested('j', 4);
            $held->handIndex('k', 2);
            $held->handPool('k', 3);
            $box->handGiven(['high' => new Carton()], 'low', new Carton());
            $box->handResult(['a' => new Carton()], 'k', new Carton());
            $box->handLocal(['a' => new Carton()], 'k', new Carton());
        },
    ];
    $seen = [];
    foreach ($writes as $write => $run) {
        $seen[] = "$write " . reader_attempt(function () use ($run) {
            return $run() ?? 'no TypeError';
        });
    }

    return implode(' / ', $seen);
}

// A write plain PHP makes into a Map's value goes through a reference the entry keeps, and the first PHP# read of the
// Map throws, naming the entry.
function reader_table_value_writes_checked_on_read(): string
{
    $writes = [
        'an append to a missing int entry' => [fn (Carton $box) => $box->payload['new'][] = 'x', 'payloadText'],
        'a decrement of a missing entry' => [fn (Carton $box) => @$box->payload['missing']--, 'payloadText'],
        'an increment past PHP_INT_MAX' => [fn (Carton $box) => $box->tallies['top']++, 'talliesText'],
        'an int into a Set<string> value' => [fn (Carton $box) => $box->tags['a'][] = 5, 'tagsText'],
        'an int into a List<string> value' => [fn (Carton $box) => $box->shelves['k'][] = 5, 'shelvesText'],
    ];
    $seen = [];
    foreach ($writes as $write => [$run, $read]) {
        $box = new Carton();
        reader_attempt(function () use ($run, $box) {
            $run($box);
            return '';
        });
        $seen[] = "$write " . reader_attempt(fn () => $box->$read());
    }

    return implode(' / ', $seen);
}

// After plain PHP's write into a Map's entry, each way PHP# code reads the Map's entries throws, naming the entry.
function reader_table_entry_reads_checked(): string
{
    // Carton's Json and Serialized reads run the standard library's classes, which no fixture loads.
    foreach (['Json' => 'Encoding/Json', 'Serialized' => 'Encoding/Serialized'] as $class => $file) {
        if (!class_exists("Sharp\\Encoding\\$class", false)) {
            require __DIR__ . "/../../../sharp/composer/library/Sharp/$file.sharp";
        }
    }
    $reads = ['shelvesText', 'shelvesValues', 'shelvesList', 'shelvesFiltered', 'shelvesGrouped', 'shelvesLooped',
        'shelvesJson', 'shelvesSerialized'];
    $seen = [];
    foreach ($reads as $read) {
        $box = new Carton();
        $box->shelves['k'][] = 5;
        $seen[] = "$read " . reader_attempt(fn () => (string) $box->$read());
    }

    return implode(' / ', $seen);
}

// A write into a Map's value that fits reads back as written.
function reader_table_value_writes_that_fit(): string
{
    $box = new Carton();
    $box->shelves['k'][] = 'b';
    $box->tags['a'][] = 'x';
    $box->nested['k']['m'] = 2;
    $box->tallies['low']++;
    $box->payload['a'] += 10;

    return implode(' / ', [$box->shelvesText(), $box->tagsText(), $box->nestedText(), $box->talliesText(),
        $box->payloadText()]);
}

// PHP#'s `+=` and `++` read the element first, so each checks what plain PHP wrote, in an instance and a static.
function reader_sharp_changes_checked(): string
{
    $seen = [];
    foreach (['+= 1' => 'bumpSecond', '++' => 'incrementSecond'] as $change => $method) {
        $box = new Carton();
        $box->counts[1] = 1.5;
        $seen[] = "$change " . reader_attempt(fn () => (string) $box->$method());
        $box = new Carton();
        $box->counts[1] = 4;
        $seen[] = "$change that fits " . $box->$method();
    }
    Carton::$totals = [1, 2];
    Carton::$totals[1] = 1.5;
    $seen[] = 'static += 1 ' . reader_attempt(fn () => (string) Carton::bumpTotal());
    Carton::$totals = [1, 2];
    $seen[] = 'static += 1 that fits ' . Carton::bumpTotal();
    Carton::$totals = [1, 2];

    return implode(' / ', $seen);
}

// After a write PHP# reads as wrong, plain PHP's own readers never throw, and its clone shares the property as stock
// PHP's does, which PHP# then reads checked.
function reader_plain_readers_after_wrong_write(): string
{
    $box = new Carton();
    $box->counts[] = 'z';
    ob_start();
    var_dump($box);
    $dumped = ob_get_clean();
    $clone = clone $box;

    return implode(' / ', [
        'var_dump ' . (str_contains($dumped, 'string(1) "z"') ? 'holds "z"' : 'no "z"'),
        'serialize ' . (str_contains(serialize($box), 's:1:"z"') ? 'holds "z"' : 'no "z"'),
        '(array) ' . json_encode(((array) $box)['counts']),
        'get_object_vars ' . json_encode(get_object_vars($box)['counts']),
        'clone ' . json_encode($clone->counts) . ' ' . reader_attempt(fn () => $clone->countsText()),
    ]);
}

// Plain PHP's clone gives the clone's typed slot a reference of its own, which the clone's property types in every
// build, so PHP's own check refuses a wrong write through it, and a clone of the clone still reads checked.
function reader_plain_clone_reference_typed(): string
{
    $box = new Carton();
    $box->counts[] = 'z';
    $clone = clone $box;
    $seen = [reader_attempt(function () use ($clone) {
        $held = &$clone->counts;
        $held = 'text';

        return 'no TypeError';
    })];
    $again = clone $clone;
    $seen[] = reader_attempt(fn () => $again->countsText());

    return implode(' / ', $seen);
}

// An element write into a property that holds false makes it an array in place, which PHP# reads checked.
function reader_false_made_an_array(): string
{
    $box = new Carton();
    @$box->marks[] = 'z';
    $seen = [reader_attempt(fn () => $box->marksText())];
    $box = new Carton();
    @$box->marks[] = 3;
    $seen[] = $box->marksText();

    return implode(' / ', $seen);
}

final class ReaderPlainBox
{
    public int $shown = 1;
    protected array $kept = ['a' => 2];
    private string $own = 'x';
}

// A plain PHP subclass declares no property of its own, so it holds exactly the properties Carton declares.
final class ReaderCartonChild extends Carton
{
}

// PHP# declares no traits, and an interface holds no fields, so a Map or a Set property reaches a class through a trait
// only from plain PHP that writes #[\Sharp\Crossing] as the bridge does.
trait ReaderCrossingTrait
{
    #[\Sharp\Crossing(['map', 'string', null])] public \Sharp\Map $payload;
    #[\Sharp\Crossing(['set', null])] public \Sharp\Set $codes;
}

final class ReaderTraitBox
{
    use ReaderCrossingTrait;

    public function __construct()
    {
        $this->payload = ['b' => 2, 'a' => 1];
        $this->codes = ['b', 'a'];
    }
}

// (array), get_mangled_object_vars(), ArrayIterator::getArrayCopy(), get_object_vars() and foreach give plain PHP each
// Map and Set property as its PHP array, whether the class declares it, inherits it, is an anonymous class that
// inherits it, or uses a trait that declares it, and an object of a class with no such property as plain PHP does.
function reader_array_casts(): string
{
    $plain = new ReaderPlainBox();
    $seen = [];
    $loop = function (object $object): array {
        $array = [];
        foreach ($object as $name => $value) {
            $array[$name] = $value;
        }

        return $array;
    };
    foreach (['(array)' => fn ($object) => (array) $object, 'get_mangled_object_vars()' => get_mangled_object_vars(...),
        'getArrayCopy()' => fn ($object) => (@new ArrayIterator($object))->getArrayCopy(),
        'get_object_vars()' => get_object_vars(...), 'foreach' => $loop] as $reader => $read) {
        $seen[] = "$reader of a plain object " . json_encode($read($plain));
        foreach (['declares' => new Carton(), 'inherits' => new ReaderCartonChild(),
            'is anonymous and inherits' => new class extends Carton {}, 'uses a trait with' => new ReaderTraitBox()]
            as $owner => $box) {
            $array = $read($box);
            $seen[] = "$reader of an object whose class $owner a Map and a Set " . get_debug_type($array['payload']) . ' '
                . json_encode($array['payload']) . ' ' . get_debug_type($array['codes']) . ' ' . json_encode($array['codes']);
        }
    }

    return implode(' / ', $seen);
}

function readers(): string
{
    $rows = [
        'ksort() of a Map property' => reader_ksort(...),
        'a reference to a Map property' => reader_reference(...),
        'a write of the wrong type through the reference' => reader_reference_wrong_type(...),
        'a value written through the reference, checked when PHP# reads it' => reader_reference_writes_checked_on_read(...),
        'a key the enum lacks, written through the reference, throws on each PHP# read' => reader_reference_key_checked_each_read(...),
        'clone and with after ksort()' => reader_copy_after_ksort(...),
        'clone and with while plain PHP holds the reference' => reader_copy_while_held(...),
        'a reference to an Any?, an Iterable and a Map<string, Any> property' => reader_reference_to_what_no_plan_rebuilds(...),
        'PHP# index writes after ksort()' => reader_index_write_after_ksort(...),
        'a PHP# index write while plain PHP holds the reference' => reader_index_write_while_held(...),
        'foreach by reference over a Map property' => reader_foreach_by_reference(...),
        'array_walk() by reference over a Map property' => reader_array_walk(...),
        'sort() of a Set property' => reader_sort_set(...),
        "a plain PHP parent appends to its PHP# subclass's get => field property" => reader_hidden_append(...),
        'get => field over a parent get hook, and get { return field; }' => reader_field_getters(...),
        'a PHP array into a Set<string>|int property with a set body' => reader_union_setter(...),
        'a PHP array into a Set<string>|int and a Map<string, int>|int parameter' => reader_union_parameters(...),
        'an ArrayIterator over a PHP# object' => reader_array_iterator(...),
        'foreach over a lazy Iterable result reads its source in step' => reader_lazy_iterable(...),
        'iterator_to_array() of a lazy Iterable<Map<string, int>> result' => reader_iterable_of_maps(...),
        'a lazy Iterable result is a Traversable, and its declared type' => reader_iterable_type(...),
        'var_export() of a Map property, a Map and a PHP# object' => reader_var_export(...),
        "plain PHP's writes into a List<int> property, checked when PHP# reads it" => reader_list_writes_checked_on_read(...),
        "plain PHP's writes that fit a List<int> property, and a reference it keeps" => reader_list_writes_that_fit(...),
        "plain PHP's writes into a static, an uninitialized and a type parameter's List<int>" => reader_list_writes_into_other_slots(...),
        "plain PHP's writes into a Map or a Set property, checked at the write" => reader_table_writes_checked_at_write(...),
        'a Map or a Set PHP# code makes, typed from when it is made' => reader_tables_typed_from_creation(...),
        'a Map whose type names a class or a type parameter, typed from when it is made' => reader_tables_typed_by_names(...),
        "plain PHP's writes into a Map's values, checked when PHP# reads it" => reader_table_value_writes_checked_on_read(...),
        "each PHP# read of a Map's entries after plain PHP's write into one" => reader_table_entry_reads_checked(...),
        "plain PHP's writes that fit a Map's values" => reader_table_value_writes_that_fit(...),
        "PHP#'s += and ++ after plain PHP's write" => reader_sharp_changes_checked(...),
        "plain PHP's readers and clone after a wrong write" => reader_plain_readers_after_wrong_write(...),
        "plain PHP's clone of a typed slot's reference, written through and cloned again" => reader_plain_clone_reference_typed(...),
        'an element write into a property that holds false' => reader_false_made_an_array(...),
        "plain PHP's array casts, get_object_vars() and foreach of an object, with a Map and a Set property declared, inherited or from a trait, and without" => reader_array_casts(...),
    ];
    $output = '';
    foreach ($rows as $row => $read) {
        $output .= "$row: " . reader_attempt($read) . "\n";
    }

    return $output;
}

function readers_rounds(): string
{
    for ($round = 0; $round < 100; $round++) {
        $output = readers();
    }

    return $output;
}

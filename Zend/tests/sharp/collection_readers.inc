<?php

use Demo\Carton;
use Demo\Crossings;
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

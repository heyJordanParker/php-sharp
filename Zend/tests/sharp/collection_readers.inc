<?php

use Demo\Carton;
use Demo\Crossings;
use Demo\Subscriber;
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
        'foreach by reference over a Map property' => reader_foreach_by_reference(...),
        'array_walk() by reference over a Map property' => reader_array_walk(...),
        'sort() of a Set property' => reader_sort_set(...),
        "a plain PHP parent appends to its PHP# subclass's get => field property" => reader_hidden_append(...),
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

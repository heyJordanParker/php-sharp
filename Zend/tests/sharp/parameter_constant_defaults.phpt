--TEST--
Reflection reads and an invoker fills a parameter default built from constants, a Map literal and a Set literal of constants included, as it does `0` and `[]`, and still refuses a default that runs code, a `new` and a Map literal holding a call, with today's messages
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use ConstantDefaults\Make;
use ConstantDefaults\Probe;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        var_export($body());
        echo "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

attempt('tag hasConstantDefault', fn () => Probe::hasConstantDefault('tag'));
attempt('tag defaultValue', fn () => Probe::defaultValue('tag'));
attempt('tag invoked with ["a"]', fn () => Probe::invoke('tag', ['a']));
attempt('tag called with "a"', fn () => Make::tag('a'));
attempt('sized defaultValue', fn () => Probe::defaultValue('sized'));
attempt('leveled defaultValue', fn () => Probe::defaultValue('leveled'));
attempt('marked defaultValue', fn () => Probe::defaultValue('marked'));
attempt('marked invoked with []', fn () => Probe::invoke('marked', []));
attempt('drafted hasConstantDefault', fn () => Probe::hasConstantDefault('drafted'));
attempt('drafted defaultValue', fn () => Probe::defaultValue('drafted'));
attempt('drafted invoked with []', fn () => Probe::invoke('drafted', []));
attempt('counted hasConstantDefault', fn () => Probe::hasConstantDefault('counted'));
attempt('counted defaultValue', fn () => Probe::defaultValue('counted'));
attempt('counted invoked with []', fn () => Probe::invoke('counted', []));
?>
--EXPECT--
tag hasConstantDefault: true
tag defaultValue: 'Map<string, int> of 0: '
tag invoked with ["a"]: 'a:0'
tag called with "a": 'a:0'
sized defaultValue: 'Map<string, int> of 1: small'
leveled defaultValue: 'Map<Level, int> of 1: Low=1'
marked defaultValue: 'Set<string> of 2'
marked invoked with []: 2
drafted hasConstantDefault: false
drafted defaultValue: ValueError: `defaultValue` reads a default that runs no code, and `draft` of `drafted` has one that runs code, which `defaultValue` never runs
drafted invoked with []: ValueError: `ConstantDefaults.Make.drafted` gives `draft` a default that runs code, which an invoker never runs, and was given no value for it.
counted hasConstantDefault: false
counted defaultValue: ValueError: `defaultValue` reads a default that runs no code, and `counts` of `counted` has one that runs code, which `defaultValue` never runs
counted invoked with []: ValueError: `ConstantDefaults.Make.counted` gives `counts` a default that runs code, which an invoker never runs, and was given no value for it.

--TEST--
Interop matrix: plain PHP extends, implements and uses a PHP# class, abstract class, interface with default bodies (its Defaults trait) and enum, by inheriting, overriding, `as` and `insteadof`, and PHP# extends, implements and uses plain PHP the same ways. Each method, `count`, `recordEntry` and the twins `total` and `Total`, is reached by a call with its exact name, a first-class callable, `Closure::fromCallable`, `method_exists`, `is_callable`, `ReflectionMethod`'s declaring class and `ReflectionClass::getMethods()`, and a call in another case is the undefined-method error naming the declared method. A plain PHP class can't declare both twins, spec section 24. Each row runs in a process of its own, with this one's opcache settings, so a row PHP refuses shows its refusal and the rows after it still run
--FILE--
<?php
// The functions every row reaches its object's methods with.
const REACH = <<<'PHP'
function attempt(callable $call): string
{
    try {
        return var_export($call(), true);
    } catch (Throwable $error) {
        return get_class($error) . ': ' . $error->getMessage();
    }
}

function wrong_case(string $method): string
{
    return match ($method) {
        'count' => 'Count',
        'recordEntry' => 'recordentry',
        'total', 'Total' => 'TOTAL',
        default => strtoupper($method),
    };
}

function show(object $object, array $methods): void
{
    echo 'methods ', implode(', ', array_map(
        static fn (ReflectionMethod $method): string => $method->name,
        (new ReflectionClass($object))->getMethods(),
    )), "\n";
    foreach ($methods as $method) {
        $wrong = wrong_case($method);
        echo implode(' | ', [
            $method,
            'call ' . attempt(fn () => $object->$method()),
            'fcc ' . attempt(fn () => ($object->$method(...))()),
            'fromCallable ' . attempt(fn () => Closure::fromCallable([$object, $method])()),
            'exists ' . var_export(method_exists($object, $method), true) . '/' . var_export(method_exists($object, $wrong), true),
            'callable ' . var_export(is_callable([$object, $method]), true) . '/' . var_export(is_callable([$object, $wrong]), true),
            'declared ' . attempt(fn () => (new ReflectionMethod($object, $method))->getDeclaringClass()->name),
            "$wrong " . attempt(fn () => $object->$wrong()),
        ]), "\n";
    }
}
PHP;

const PLAIN_ENTRIES = 'trait PlainEntries { public function recordEntry(): int { return 22; } }';
const ALL = ['count', 'recordEntry', 'total', 'Total'];
const PLAIN = ['count', 'recordEntry', 'total'];

// Each row: the plain PHP it declares, the object it reaches, and the methods it reaches.
$rows = [
    'plain extends a PHP# class' => ['class Inherits extends InteropMatrix\Ledger {}', 'new Inherits()', ALL],
    'plain overrides a PHP# class, each twin' => [
        'class Overrides extends InteropMatrix\Ledger { public function count(): int { return 11; } public function recordEntry(): int { return 12; } public function total(): int { return 13; } public function Total(): int { return 14; } }',
        'new Overrides()',
        ALL,
    ],
    'plain extends a PHP# abstract class' => ['class InheritsAbstract extends InteropMatrix\Account {}', 'new InheritsAbstract()', ALL],
    'plain overrides one twin of a PHP# abstract class' => [
        'class OverridesAbstract extends InteropMatrix\Account { public function recordEntry(): int { return 12; } public function Total(): int { return 14; } }',
        'new OverridesAbstract()',
        ALL,
    ],
    'plain takes a PHP# interface\'s default bodies' => [
        'class TakesDefaults implements InteropMatrix\Recording { use InteropMatrix\Recording\Defaults; }',
        'new TakesDefaults()',
        ALL,
    ],
    'plain overrides the camelCase default body' => [
        'class OverridesEntry implements InteropMatrix\Recording { use InteropMatrix\Recording\Defaults; public function recordEntry(): int { return 12; } }',
        'new OverridesEntry()',
        ALL,
    ],
    'plain overrides one twin\'s default body' => [
        'class OverridesTwin implements InteropMatrix\Recording { use InteropMatrix\Recording\Defaults; public function Total(): int { return 14; } }',
        'new OverridesTwin()',
        ALL,
    ],
    'plain takes a plain parent\'s static camelCase method as a first-class callable' => [
        'abstract class FinderBase { public static function findOrFail(int $id = 1): int { return $id * 10; } }'
            . ' class Finder extends FinderBase implements InteropMatrix\Recording { use InteropMatrix\Recording\Defaults; }'
            . ' echo implode(", ", array_map(Finder::findOrFail(...), [1, 2])), "\n";',
        'new Finder()',
        ['findOrFail'],
    ],
    'plain aliases default bodies with as' => [
        'class Aliases implements InteropMatrix\Recording { use InteropMatrix\Recording\Defaults { recordEntry as entry; Total as grandTotal; } }',
        'new Aliases()',
        ['recordEntry', 'entry', 'Total', 'grandTotal'],
    ],
    'plain picks a plain trait insteadof Defaults' => [
        PLAIN_ENTRIES . ' class Chooses implements InteropMatrix\Recording { use PlainEntries, InteropMatrix\Recording\Defaults { PlainEntries::recordEntry insteadof InteropMatrix\Recording\Defaults; InteropMatrix\Recording\Defaults::recordEntry as sharpEntry; } }',
        'new Chooses()',
        ['recordEntry', 'sharpEntry'],
    ],
    'plain picks Defaults insteadof a plain trait' => [
        PLAIN_ENTRIES . ' class ChoosesSharp implements InteropMatrix\Recording { use PlainEntries, InteropMatrix\Recording\Defaults { InteropMatrix\Recording\Defaults::recordEntry insteadof PlainEntries; PlainEntries::recordEntry as plainEntry; } }',
        'new ChoosesSharp()',
        ['recordEntry', 'plainEntry'],
    ],
    'a PHP# enum case' => ['', 'InteropMatrix\Kind::Open', ALL],
    'PHP# extends a plain class' => ['', 'new InteropMatrix\SharpLedger()', PLAIN],
    'PHP# overrides a plain abstract class' => ['', 'new InteropMatrix\SharpOverride()', PLAIN],
    'PHP# implements a plain interface' => ['', 'new InteropMatrix\SharpRecording()', PLAIN],
    'PHP# uses a plain trait' => ['', 'new InteropMatrix\SharpCounting()', PLAIN],
];

$options = '';
foreach (ini_get_all(null, false) as $name => $value) {
    if (str_starts_with($name, 'opcache.')) {
        $options .= ' -d ' . escapeshellarg("$name=$value");
    }
}
$prelude = 'require ' . var_export(__DIR__ . '/harness/Interop.inc', true) . '; require '
    . var_export(__DIR__ . '/harness/fixture.inc', true) . "; fixture('InteropMatrix'); " . REACH;

foreach ($rows as $row => [$declarations, $object, $methods]) {
    echo "== $row\n";
    $code = "$prelude $declarations show($object, " . var_export($methods, true) . ');';
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n$options -r " . escapeshellarg($code) . ' 2>&1');
}
?>
--EXPECT--
== plain extends a PHP# class
methods count, recordEntry, total, Total, parse, tryParse
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'InteropMatrix\\Ledger' | Count Error: Call to undefined method Inherits::Count(); did you mean count()?
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'InteropMatrix\\Ledger' | recordentry Error: Call to undefined method Inherits::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'InteropMatrix\\Ledger' | TOTAL Error: Call to undefined method Inherits::TOTAL(); did you mean Total()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'InteropMatrix\\Ledger' | TOTAL Error: Call to undefined method Inherits::TOTAL(); did you mean Total()?
== plain overrides a PHP# class, each twin

Fatal error: Cannot redeclare Overrides::Total() in Command line code on line 39
Stack trace:
#0 {main}
== plain extends a PHP# abstract class
methods count, recordEntry, total, Total
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | Count Error: Call to undefined method InheritsAbstract::Count(); did you mean count()?
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | recordentry Error: Call to undefined method InheritsAbstract::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | TOTAL Error: Call to undefined method InheritsAbstract::TOTAL(); did you mean Total()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | TOTAL Error: Call to undefined method InheritsAbstract::TOTAL(); did you mean Total()?
== plain overrides one twin of a PHP# abstract class
methods recordEntry, Total, count, total
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | Count Error: Call to undefined method OverridesAbstract::Count(); did you mean count()?
recordEntry | call 12 | fcc 12 | fromCallable 12 | exists true/false | callable true/false | declared 'OverridesAbstract' | recordentry Error: Call to undefined method OverridesAbstract::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'InteropMatrix\\Account' | TOTAL Error: Call to undefined method OverridesAbstract::TOTAL(); did you mean Total()?
Total | call 14 | fcc 14 | fromCallable 14 | exists true/false | callable true/false | declared 'OverridesAbstract' | TOTAL Error: Call to undefined method OverridesAbstract::TOTAL(); did you mean Total()?
== plain takes a PHP# interface's default bodies
methods count, recordEntry, total, Total
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'TakesDefaults' | Count Error: Call to undefined method TakesDefaults::Count(); did you mean count()?
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'TakesDefaults' | recordentry Error: Call to undefined method TakesDefaults::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'TakesDefaults' | TOTAL Error: Call to undefined method TakesDefaults::TOTAL(); did you mean Total()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'TakesDefaults' | TOTAL Error: Call to undefined method TakesDefaults::TOTAL(); did you mean Total()?
== plain overrides the camelCase default body
methods recordEntry, count, total, Total
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'OverridesEntry' | Count Error: Call to undefined method OverridesEntry::Count(); did you mean count()?
recordEntry | call 12 | fcc 12 | fromCallable 12 | exists true/false | callable true/false | declared 'OverridesEntry' | recordentry Error: Call to undefined method OverridesEntry::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'OverridesEntry' | TOTAL Error: Call to undefined method OverridesEntry::TOTAL(); did you mean Total()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'OverridesEntry' | TOTAL Error: Call to undefined method OverridesEntry::TOTAL(); did you mean Total()?
== plain overrides one twin's default body
methods Total, count, recordEntry, total
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'OverridesTwin' | Count Error: Call to undefined method OverridesTwin::Count(); did you mean count()?
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'OverridesTwin' | recordentry Error: Call to undefined method OverridesTwin::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'OverridesTwin' | TOTAL Error: Call to undefined method OverridesTwin::TOTAL(); did you mean Total()?
Total | call 14 | fcc 14 | fromCallable 14 | exists true/false | callable true/false | declared 'OverridesTwin' | TOTAL Error: Call to undefined method OverridesTwin::TOTAL(); did you mean Total()?
== plain takes a plain parent's static camelCase method as a first-class callable
10, 20
methods count, recordEntry, total, Total, findOrFail
findOrFail | call 10 | fcc 10 | fromCallable 10 | exists true/false | callable true/false | declared 'FinderBase' | FINDORFAIL Error: Call to undefined method Finder::FINDORFAIL(); did you mean findOrFail()?
== plain aliases default bodies with as
methods count, entry, recordEntry, total, grandTotal, Total
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'Aliases' | recordentry Error: Call to undefined method Aliases::recordentry(); did you mean recordEntry()?
entry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'Aliases' | ENTRY Error: Call to undefined method Aliases::ENTRY(); did you mean entry()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'Aliases' | TOTAL Error: Call to undefined method Aliases::TOTAL(); did you mean Total()?
grandTotal | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'Aliases' | GRANDTOTAL Error: Call to undefined method Aliases::GRANDTOTAL(); did you mean grandTotal()?
== plain picks a plain trait insteadof Defaults
methods recordEntry, count, sharpEntry, total, Total
recordEntry | call 22 | fcc 22 | fromCallable 22 | exists true/false | callable true/false | declared 'Chooses' | recordentry Error: Call to undefined method Chooses::recordentry(); did you mean recordEntry()?
sharpEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'Chooses' | SHARPENTRY Error: Call to undefined method Chooses::SHARPENTRY(); did you mean sharpEntry()?
== plain picks Defaults insteadof a plain trait
methods plainEntry, count, recordEntry, total, Total
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'ChoosesSharp' | recordentry Error: Call to undefined method ChoosesSharp::recordentry(); did you mean recordEntry()?
plainEntry | call 22 | fcc 22 | fromCallable 22 | exists true/false | callable true/false | declared 'ChoosesSharp' | PLAINENTRY Error: Call to undefined method ChoosesSharp::PLAINENTRY(); did you mean plainEntry()?
== a PHP# enum case
methods count, recordEntry, total, Total, cases
count | call 1 | fcc 1 | fromCallable 1 | exists true/false | callable true/false | declared 'InteropMatrix\\Kind' | Count Error: Call to undefined method InteropMatrix\Kind::Count(); did you mean count()?
recordEntry | call 2 | fcc 2 | fromCallable 2 | exists true/false | callable true/false | declared 'InteropMatrix\\Kind' | recordentry Error: Call to undefined method InteropMatrix\Kind::recordentry(); did you mean recordEntry()?
total | call 3 | fcc 3 | fromCallable 3 | exists true/false | callable true/false | declared 'InteropMatrix\\Kind' | TOTAL Error: Call to undefined method InteropMatrix\Kind::TOTAL(); did you mean Total()?
Total | call 4 | fcc 4 | fromCallable 4 | exists true/false | callable true/false | declared 'InteropMatrix\\Kind' | TOTAL Error: Call to undefined method InteropMatrix\Kind::TOTAL(); did you mean Total()?
== PHP# extends a plain class
methods parse, tryParse, count, recordEntry, total
count | call 31 | fcc 31 | fromCallable 31 | exists true/false | callable true/false | declared 'Lib\\Interop\\PlainLedger' | Count Error: Call to undefined method InteropMatrix\SharpLedger::Count(); did you mean count()?
recordEntry | call 32 | fcc 32 | fromCallable 32 | exists true/false | callable true/false | declared 'Lib\\Interop\\PlainLedger' | recordentry Error: Call to undefined method InteropMatrix\SharpLedger::recordentry(); did you mean recordEntry()?
total | call 33 | fcc 33 | fromCallable 33 | exists true/false | callable true/false | declared 'Lib\\Interop\\PlainLedger' | TOTAL Error: Call to undefined method InteropMatrix\SharpLedger::TOTAL(); did you mean total()?
== PHP# overrides a plain abstract class
methods count, recordEntry, total, parse, tryParse
count | call 41 | fcc 41 | fromCallable 41 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpOverride' | Count Error: Call to undefined method InteropMatrix\SharpOverride::Count(); did you mean count()?
recordEntry | call 42 | fcc 42 | fromCallable 42 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpOverride' | recordentry Error: Call to undefined method InteropMatrix\SharpOverride::recordentry(); did you mean recordEntry()?
total | call 43 | fcc 43 | fromCallable 43 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpOverride' | TOTAL Error: Call to undefined method InteropMatrix\SharpOverride::TOTAL(); did you mean total()?
== PHP# implements a plain interface
methods count, recordEntry, total, parse, tryParse
count | call 41 | fcc 41 | fromCallable 41 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpRecording' | Count Error: Call to undefined method InteropMatrix\SharpRecording::Count(); did you mean count()?
recordEntry | call 42 | fcc 42 | fromCallable 42 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpRecording' | recordentry Error: Call to undefined method InteropMatrix\SharpRecording::recordentry(); did you mean recordEntry()?
total | call 43 | fcc 43 | fromCallable 43 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpRecording' | TOTAL Error: Call to undefined method InteropMatrix\SharpRecording::TOTAL(); did you mean total()?
== PHP# uses a plain trait
methods parse, tryParse, count, recordEntry, total
count | call 31 | fcc 31 | fromCallable 31 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpCounting' | Count Error: Call to undefined method InteropMatrix\SharpCounting::Count(); did you mean count()?
recordEntry | call 32 | fcc 32 | fromCallable 32 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpCounting' | recordentry Error: Call to undefined method InteropMatrix\SharpCounting::recordentry(); did you mean recordEntry()?
total | call 33 | fcc 33 | fromCallable 33 | exists true/false | callable true/false | declared 'InteropMatrix\\SharpCounting' | TOTAL Error: Call to undefined method InteropMatrix\SharpCounting::TOTAL(); did you mean total()?

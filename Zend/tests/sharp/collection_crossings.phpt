--TEST--
A Map or a Set reaches plain PHP as a PHP array at every crossing, and as the object where only an object fits, a PHP array crosses back into a Map, a PHP list enters a tuple when its length and elements match, and a Map enters an Iterable as its entries
--FILE--
<?php
require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Crossings');

use Demo\Crossings;
use Demo\Envelope;
use Demo\Holdings;
use Sharp\Map;
use Sharp\Set;

function show(iterable $map): string
{
    $entries = [];
    foreach ($map as $key => $value) {
        $entries[] = var_export($key, true) . ' => ' . $value;
    }
    return implode(', ', $entries);
}

// What plain PHP received: its type, whether is_array() takes it, what array_merge() makes of it, and the type
// reflection gives the place it came from.
function received(mixed $value, ?ReflectionType $type = null): string
{
    try {
        $merged = json_encode(array_merge($value));
    } catch (TypeError) {
        $merged = 'refused by array_merge';
    }

    return get_debug_type($value) . ', is_array ' . var_export(is_array($value), true) . ', ' . $merged
        . ($type === null ? '' : ", typed $type");
}

// What plain PHP received from `$read`, or the ValueError a Map two of whose keys are one PHP array key throws.
function received_or_refused(Closure $read, ?ReflectionType $type = null): string
{
    try {
        return received($read(), $type);
    } catch (ValueError $e) {
        return 'ValueError: ' . $e->getMessage();
    }
}

function property_type(string $class, string $property): ?ReflectionType
{
    return (new ReflectionProperty($class, $property))->getType();
}

// A plain PHP class that implements a PHP# interface's `Any?` result without a return type.
final class PlainMorphable implements Demo\Morphable
{
    use Demo\Morphable\Defaults;

    public function getKey()
    {
        return 3;
    }

    public function getMorphClass(): string
    {
        return 'plain';
    }
}

// A plain PHP subclass of a PHP# class reads its parent's Map property as plain PHP, and overrides a method whose
// result is a Map with one that returns a PHP array.
final class PlainHoldings extends Holdings
{
    public function ownPayload(): mixed
    {
        return $this->payload;
    }

    public function ownPayloadIndex(): mixed
    {
        return $this->payload['id'];
    }

    public function tally(): array
    {
        return ['p' => 2];
    }
}

// A plain PHP class implements a PHP# interface method whose result is a Map, typed as reflection gives it.
final class PlainSource implements Demo\Source
{
    public function data(): array
    {
        return ['a' => 1];
    }
}

// A plain PHP index read of a PHP# static Map.
function counts_index(): mixed
{
    return Holdings::$counts['c'];
}

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    echo implode("\n", Crossings::outward(new Map(['tea', 3, '5', 1]), new Set(['a', 'b']))), "\n";
    $prices = new Map(['tea', 3]);
    echo 'a plain PHP write leaves the Map: ', show(Crossings::written($prices)), ' / ', show($prices), "\n";
    echo 'a plain PHP result into a Map local: ', show(Crossings::local()), "\n";
    echo 'a plain PHP result into a Map return: ', show(Crossings::returned()), "\n";
    echo 'Map methods on a plain PHP result: ', implode(' / ', Crossings::plainMapCalls()), "\n";
    echo 'a plain PHP result in a local without a type: ', Crossings::untypedLocal(), "\n";
    echo 'a loop over a plain PHP result keyed by string: ', implode(' ', Crossings::plainLoops()), "\n";
    echo 'a PHP array into a Map<string, int> parameter: ', show(Crossings::received(['5' => 1, 'x' => 2])), "\n";
    echo implode("\n", Crossings::objects(new Map(['tea', 3, '5', 1]), new Set(['a', 'b']))), "\n";
    echo 'a plain PHP closure a local holds: ', Crossings::counted(new Map(['a', 1, 'b', 2])), "\n";
    echo 'a plain PHP object a local holds: ', Crossings::invoked(new Map(['a', 1, 'b', 2])), "\n";
    echo 'a plain PHP closure behind a PHP# function type: ', Crossings::applied(fn ($values) => get_debug_type($values), new Map(['a', 1])), "\n";
    echo 'a PHP# lambda behind a PHP# function type takes the same Map: ', Crossings::appliedOwn(new Map(['a', 1])), "\n";
    echo 'a PHP array into a Map<string|Level, int> parameter: ', json_encode(Crossings::named(['5' => 1])), "\n";
    // A lazy plain PHP source is checked element by element as a loop reads it, so a bad element throws at the
    // read and not before, and the source is never read ahead.
    Names::$given = 0;
    echo 'a lazy plain PHP source into an Iterable<string> parameter: ', Crossings::joined(names_and('cake')), Names::$given, "\n";
    $reads = [
        fn () => Crossings::joined(names_and(3)),
        fn () => Crossings::lazilyReturned(3),
        fn () => Crossings::heldGenerator(3),
        fn () => Crossings::heldRows(3),
        fn () => Crossings::joined(Crossings::returnedLazy(3)),
        fn () => Crossings::passedGenerator(3),
    ];
    foreach ($reads as $read) {
        Names::$given = 0;
        try {
            $read();
        } catch (TypeError $e) {
            echo get_class($e), ': ', $e->getMessage(), ', after ', Names::$given, " names\n";
        }
    }
    echo 'a plain PHP class keeps its own methods: ', Crossings::lazyCount(3), "\n";
    // A tuple from plain PHP is a PHP list of exactly its elements, each of its type.
    echo 'a PHP list into a [string name, int count] parameter: ', Crossings::pair(['tea', 3]), "\n";
    foreach ([['tea'], ['tea', 'x'], ['name' => 'tea', 'count' => 3]] as $given) {
        try {
            Crossings::pair($given);
        } catch (TypeError $e) {
            echo get_class($e), ': ', $e->getMessage(), "\n";
        }
    }
    // A Map is an Iterable of its entries, from plain PHP and from PHP#, and a loop variable over it is each entry.
    echo 'a Map into an Iterable<[string, int]> parameter: ', Crossings::entries(new Map(['tea', 3, 'pie', 7])), "\n";
    echo 'a list of tuples into an Iterable<[string, int]> parameter: ', Crossings::entries([['tea', 3]]), "\n";
    echo 'a Map a PHP# call passes on: ', Crossings::ownEntries(['tea' => 3, 'pie' => 7]), "\n";
    try {
        Crossings::entries(new Map(['tea', 'x']));
    } catch (TypeError $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }

    // A return.
    $returned = fn (string $method) => (new ReflectionMethod(Crossings::class, $method))->getReturnType();
    echo 'a returned Map: ', received(Crossings::sizes(), $returned('sizes')), "\n";
    echo 'a returned Set: ', received(Crossings::tagged(), $returned('tagged')), "\n";
    echo 'a returned List<Map>: ', received(Crossings::shelves(), $returned('shelves')), "\n";
    echo 'a Map a PHP# lambda returns: ', received((Crossings::sizer())()), "\n";
    echo 'a Map a PHP# method returns from its own call: ', received(Crossings::lambdaSizes()), "\n";
    $limits = [];
    foreach (Crossings::limits() as $limit => $count) {
        $limits[] = var_export($limit, true) . '=' . var_export($count, true);
    }
    echo 'a returned Map keyed by a backed enum: ', implode(' ', $limits), "\n";
    echo 'a returned Map is identical to its PHP array: ', var_export(Crossings::sizes() === ['b' => 2, 'a' => 1], true), "\n";
    $sizes = Crossings::sizes();
    ksort($sizes);
    echo 'ksort() sorts a returned Map: ', json_encode($sizes), "\n";
    $holdings = new Holdings();
    echo 'a returned Map keyed by a List stays the Map: ', received($holdings->listKeyed()), "\n";

    // An argument.
    $envelope = new Envelope(['To' => 'a@b.co']);
    echo "a struct's Map property into an array parameter, from plain PHP: ", send_headers($envelope->headers), "\n";
    echo "a struct's Map property into an array parameter, from PHP#: ", Crossings::sent($envelope), "\n";

    // A property read, and each reader of an object's properties.
    echo 'a Map property: ', received($holdings->payload, property_type(Holdings::class, 'payload')), "\n";
    echo 'a Set property: ', received($holdings->codes, property_type(Holdings::class, 'codes')), "\n";
    echo 'a List<Map> property: ', received($holdings->rows, property_type(Holdings::class, 'rows')), "\n";
    echo 'a nullable Map property: ', received($holdings->extra, property_type(Holdings::class, 'extra')), "\n";
    echo 'a computed Map property: ', received($holdings->computed, property_type(Holdings::class, 'computed')), "\n";
    echo 'a hooked Map property: ', received($holdings->hooked, property_type(Holdings::class, 'hooked')), "\n";
    echo 'a static Map property: ', received(Holdings::$counts, property_type(Holdings::class, 'counts')), "\n";
    echo 'a Map property isset: ', var_export(isset($holdings->payload['id']), true), "\n";
    echo 'get_object_vars(): ', received(get_object_vars($holdings)['payload']), "\n";
    echo 'an (array) cast: ', received(((array) $holdings)['payload']), "\n";
    $read = null;
    foreach ($holdings as $property => $value) {
        if ($property === 'payload') {
            $read = $value;
        }
    }
    echo 'foreach over the object: ', received($read), "\n";
    echo 'ReflectionProperty::getValue(): ', received((new ReflectionProperty(Holdings::class, 'payload'))->getValue($holdings)), "\n";
    // isInitialized() and setValue() find the property's offset, which getValue() and getRawValue() then read from.
    $reflected = new ReflectionProperty(Holdings::class, 'payload');
    echo 'ReflectionProperty::isInitialized(): ', var_export($reflected->isInitialized($holdings), true), "\n";
    echo 'ReflectionProperty::getValue() after isInitialized(): ', received($reflected->getValue($holdings)), "\n";
    echo 'ReflectionProperty::getRawValue() after isInitialized(): ', received($reflected->getRawValue($holdings)), "\n";
    $reflected = new ReflectionProperty(Holdings::class, 'payload');
    $reflected->setValue($holdings, ['id' => 'p1']);
    echo 'ReflectionProperty::getValue() after setValue(): ', received($reflected->getValue($holdings)), "\n";
    echo 'ReflectionProperty::getValue() of a static: ', received((new ReflectionProperty(Holdings::class, 'counts'))->getValue()), "\n";
    echo 'a plain PHP subclass reads $this->payload: ', received((new PlainHoldings())->ownPayload()), "\n";

    // PHP# reads its own property as the object, and a write from plain PHP crosses in.
    echo 'PHP# reads its Map property: ', $holdings->payloadClass(), ' ', $holdings->firstRowClass(), ' ', Holdings::countsClass(), "\n";
    $written = new Holdings();
    $written->payload = ['id' => 'p2'];
    $written->rows = [['b' => 2]];
    Holdings::$counts = ['d' => 4];
    echo 'PHP# reads what plain PHP wrote: ', $written->payloadClass(), ' ', $written->firstRowClass(), ' ', Holdings::countsClass(), ' ', json_encode($written->payload), "\n";
    Holdings::$counts = ['c' => 1];

    // A write into plain PHP's storage takes what a parameter of the property's type takes, and PHP#'s own property
    // keeps the Map.
    $context = new RequestContext();
    $bound = &$context->payload;
    Crossings::filled($context, new Map(['tea', 3, 'pie', 7]), new Set(['a', 'b']));
    $typed = fn (string $property) => property_type(RequestContext::class, $property);
    echo 'a Map written to an array property: ', received($context->payload, $typed('payload')), "\n";
    echo 'a Map written through a reference to it: ', received($bound), "\n";
    echo 'a Set written to a ?array property: ', received($context->extra, $typed('extra')), "\n";
    echo 'a Map written to an untyped property: ', received($context->untyped), "\n";
    echo 'a Set written to a mixed property: ', received($context->anything, $typed('anything')), "\n";
    echo 'a Map written to an iterable property: ', received($context->items, $typed('items')), "\n";
    echo 'a Map written to an array|Countable property: ', received($context->either, $typed('either')), "\n";
    echo 'a Map written to a ?Countable property: ', received($context->counted, $typed('counted')), "\n";
    echo 'a Set written to a static array property: ', received(RequestContext::$registry, $typed('registry')), "\n";
    echo 'a Map written to an element of an array property: ', received($context->payloads['first']), "\n";
    echo 'a Set written to an element of a list property: ', received($context->rows[0]), "\n";
    echo 'a Map added to a list property: ', received($context->rows[1]), "\n";
    $context = new RequestContext();
    Crossings::filledOnce($context, new Map(['tea', 3]));
    echo 'a Map written by ??=: ', received($context->extra), "\n";
    Crossings::filledNested($context, new Map(['tea', 3]), new Set(['a']));
    echo 'a Map in a Map: ', received($context->payload), ' / a Map and a Set in a List: ', received($context->items), "\n";
    $other = new RequestContext();
    $other->payload = ['tea' => 1];
    Crossings::filledFromPlain($context, $other);
    echo "plain PHP's array written back: ", received($context->payload), "\n";
    echo 'a Map in an object initializer: ', received(Crossings::initialized(new Map(['tea', 3]))->payload), "\n";
    echo 'a Map in a with: ', received(Crossings::copied(new RequestContext(), new Map(['tea', 3]))->payload), "\n";
    echo "a Map written to PHP#'s own property: ", Crossings::ownWritten(new Holdings(), new Map(['id', 'p3'])), "\n";
    $context = new RequestContext();
    $context->payload = ['seed' => 1];
    Crossings::filledPlus($context, new Map(['tea', 3]));
    echo 'a Map written by +=: ', received($context->payload), "\n";
    $attributes = new Attributes();
    Crossings::attributed($attributes, new Map(['tea', 3]));
    echo 'a Map written through __set: ', received($attributes->seen['meta']), "\n";

    // A property a plain PHP parent declares, and an initializer of a class a type names.
    $job = new Demo\SendOptin();
    $job->fill(new Map(['tea', 3, 'pie', 7]));
    echo 'a Map written to an inherited array property: ', received($job->payload), "\n";
    echo 'a Map written to an element of an inherited array property: ', received($job->payloads['first']), "\n";
    echo 'a Map written to an inherited static array property: ', received(BaseJob::$registry), "\n";
    echo 'a Map written to an element of an element of an inherited list property: ', received($job->rows[0]['shelf']), "\n";
    echo 'a Map added to an inherited list property: ', received($job->rows[1]), "\n";
    echo 'a Map in an initializer of a subclass: ', received(Crossings::jobInitialized(new Map(['tea', 3]))->payload), "\n";
    echo 'a Map in a with of a subclass: ', received(Crossings::jobCopied(new Demo\SendOptin(), new Map(['tea', 3]))->payload), "\n";
    echo 'a Map in a new Self initializer: ', received(Demo\SendOptin::made(new Map(['tea', 3]))->payload), "\n";
    echo 'a Map in a new T initializer: ', received(Crossings::initializedAsContext(new Map(['tea', 3]))->payload), "\n";
    echo "PHP# reads an inherited plain PHP array with gaps as its List: ", $job->firstName(), "\n";
    echo "plain PHP's arrays written to a PHP# base's collection properties: ", (new Demo\Cellar())->restocked(), "\n";

    // A parameter's type, an attribute's argument and a constant.
    echo 'a Map parameter: ', (new ReflectionMethod(Holdings::class, 'accepted'))->getParameters()[0]->getType(), ' ', $holdings->accepted(['a' => 'b']), "\n";
    $label = (new ReflectionClass(Holdings::class))->getAttributes(Label::class)[0];
    echo 'a Map attribute argument: ', received($label->getArguments()['config']), "\n";
    echo 'a Map attribute argument, built: ', received($label->newInstance()->config), "\n";
    echo 'a Map constant: ', received(Holdings::LIMITS, (new ReflectionClassConstant(Holdings::class, 'LIMITS'))->getType()), "\n";
    echo 'a Set constant: ', received(Holdings::CODES, (new ReflectionClassConstant(Holdings::class, 'CODES'))->getType()), "\n";
    echo 'PHP# reads its Map constant: ', $holdings->limitOf('card'), "\n";
    echo 'PHP# reads its Set constant as a Set: ', var_export(Holdings::hasCode('a'), true), ' ', var_export(Holdings::hasCode('z'), true), "\n";
    echo 'PHP# holds its constants as their types: ', Holdings::constantClasses(), "\n";
    echo 'an Iterable constant: ', received(Holdings::STEPS, (new ReflectionClassConstant(Holdings::class, 'STEPS'))->getType()), ', PHP# sums it: ', Holdings::stepsTotal(), "\n";
    echo 'a tuple constant: ', received(Holdings::PAIR, (new ReflectionClassConstant(Holdings::class, 'PAIR'))->getType()), ', PHP# unpacks it: ', Holdings::pairText(), "\n";

    // An interface method a plain PHP parent's untyped method implements, and an `Any?` result without a PHP type.
    echo 'a plain PHP parent implements a PHP# interface: ', (new Demo\Bill())->label(), "\n";
    echo 'a plain PHP class implements an Any? result: ', (new PlainMorphable())->label(), "\n";
    $plainSource = new PlainSource();
    echo 'a plain PHP class implements a Map result: ', received($plainSource->data(), (new ReflectionMethod(Demo\Source::class, 'data'))->getReturnType()), "\n";
    $plainHoldings = new PlainHoldings();
    echo 'a plain PHP class overrides a Map result: ', received($plainHoldings->tally(), (new ReflectionMethod(Holdings::class, 'tally'))->getReturnType()), ', PHP# reads its own: ', $holdings->tallyClass(), "\n";
    echo 'a plain PHP index read: ', counts_index(), ' ', $plainHoldings->ownPayloadIndex(), "\n";

    // A yield, and a Map in an `Any`, a type parameter's or an `Iterable` place.
    $yielded = [];
    foreach ($holdings->generated() as $value) {
        $yielded[] = received($value);
    }
    echo 'a PHP# generator yields: ', implode(' / ', $yielded), "\n";
    echo 'an Any result: ', received(Holdings::anyResult()), "\n";
    echo 'an Any? property: ', received($holdings->loose, property_type(Holdings::class, 'loose')), "\n";
    echo 'an Iterable<Map> property: ', received($holdings->shelved, property_type(Holdings::class, 'shelved')), ' of ', get_debug_type($holdings->shelved[0]), "\n";
    $held = Demo\Held::ofMap();
    echo 'a type parameter property and result: ', received($held->value), ' / ', received($held->get()), "\n";
    echo 'a plain PHP generator into an Iterable<Map>: ', rtrim(Holdings::plainRows()), "\n";

    // A Map no PHP array holds stays the Map at every crossing, as reflection says.
    $holdingsResult = fn (string $method) => (new ReflectionMethod(Holdings::class, $method))->getReturnType();
    echo 'a Map keyed by an enum without backing values: ', received($holdings->forecast, property_type(Holdings::class, 'forecast')), ' / ', received($holdings->weatherCounts(), $holdingsResult('weatherCounts')), "\n";
    echo 'a Map holding a Map keyed by a List: ', received($holdings->nestedListKeyed(), $holdingsResult('nestedListKeyed')), "\n";

    // A Map whose key type lets two keys be one PHP array key crosses as its array, and throws when two of them are.
    echo 'a Map holding a case and its backing value: ', received_or_refused(fn () => $holdings->collided()), "\n";
    echo 'a Map holding 5 and "5" into an array parameter: ', received_or_refused(fn () => Holdings::collidedFirstKey()), "\n";
    echo 'a Map holding 5 and "5" in an Any result: ', received_or_refused(fn () => Holdings::collidedAny()), "\n";
    echo 'a Map holding 5 and "5" read by arrayOf: ', received_or_refused(fn () => Sharp\Internal\arrayOf(new Map([5, 'a', '5', 'b']))), "\n";
    echo 'a Map whose keys can collide but do not: ', received($holdings->distinct(), $holdingsResult('distinct')), ' / ', received(Holdings::mixedKeys(), $holdingsResult('mixedKeys')), ' / ', received(Holdings::$mixedCounts, property_type(Holdings::class, 'mixedCounts')), "\n";
    echo 'a PHP array from plain PHP passed back to it: ', Holdings::firstPropertyName($holdings), "\n";

    // Every other reader of a property, and empty().
    echo 'empty() of a Map property: ', var_export(empty($holdings->none), true), ' ', var_export(empty($holdings->payload), true), "\n";
    $walked = null;
    array_walk($holdings, function ($value, $property) use (&$walked) {
        if ($property === 'payload') {
            $walked = $value;
        }
    });
    echo 'array_walk() over the object: ', received($walked), "\n";
    $iterated = received((new ArrayIterator($holdings))['payload']);
    echo 'an ArrayIterator over the object: ', $iterated, "\n";
    echo 'get_mangled_object_vars(): ', received(get_mangled_object_vars($holdings)['payload']), "\n";
    $class = new ReflectionClass(Holdings::class);
    echo 'ReflectionClass::getStaticPropertyValue(): ', received($class->getStaticPropertyValue('counts')), "\n";
    echo 'ReflectionClass::getStaticProperties(): ', received($class->getStaticProperties()['counts']), "\n";
    echo 'get_class_vars(): ', received(get_class_vars(Holdings::class)['counts']), "\n";

    // A Map or a Set constant read where PHP evaluates a constant expression.
    $copies = new Demo\Copies();
    echo 'a constant copied into a constant, a literal and a static: ', Demo\Copies::reads(), "\n";
    echo 'a constant as a property default: ', $copies->limitsClass(), ' / ', received($copies->limits), "\n";
    echo 'a constant as a parameter default: ', $copies->sized(), "\n";
    $copiesLabel = (new ReflectionClass(Demo\Copies::class))->getAttributes(Label::class)[0];
    echo 'a constant as an attribute argument: ', received($copiesLabel->newInstance()->config), "\n";
    echo 'constants plain PHP reads: ', received(Demo\Copies::COPY), ' / ', received(Demo\Copies::NESTED), ' / ', received(Demo\Copies::$codes), "\n";
    $output = ob_get_clean();
}
echo $output;

// PHP# reads a Map constant through the object it made once, so a read costs about what a static read does.
$fastest = static function (string $method): float {
    $times = [];
    for ($run = 0; $run < 3; $run++) {
        $start = hrtime(true);
        Demo\Rates::$method(20000);
        $times[] = hrtime(true) - $start;
    }
    return min($times);
};
echo 'a Map constant read takes at most five static reads: ', var_export($fastest('fromConstant') <= 5 * $fastest('fromStatic'), true), "\n";
?>
--EXPECTF--
array ["tea",5]
array [0,1]
array ["tea",5]
array
array
array
a plain PHP write leaves the Map: 'tea' => 3 / 'tea' => 3
a plain PHP result into a Map local: 'tea' => 3, 5 => 1, 'jam' => 5
a plain PHP result into a Map return: 'tea' => 3, 5 => 1
Map methods on a plain PHP result: 3 / 1 / true / tea,5 / 2
a plain PHP result in a local without a type: true 1 true array
a loop over a plain PHP result keyed by string: true:tea=3 true:5=1 true:tea=3 true:5=1 true:tea true:5
a PHP array into a Map<string, int> parameter: 5 => 1, 'x' => 2
Sharp\Map 2
Sharp\Set 2
Sharp\Map {"tea":3,"5":1}
Sharp\Set
Sharp\Map 2
a plain PHP closure a local holds: array 2
a plain PHP object a local holds: array 2
a plain PHP closure behind a PHP# function type: array
a PHP# lambda behind a PHP# function type takes the same Map: true
a PHP array into a Map<string|Level, int> parameter: ["1","1","true"]
a lazy plain PHP source into an Iterable<string> parameter: tea jam cake pie 4
TypeError: Demo\Crossings::joined(): Argument #1 ($names) must be of type Iterable<string>, Generator given: [2] expected string, got int 3, after 3 names
TypeError: names_and(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
TypeError: names_generator(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
TypeError: name_rows(): Return value must be of type Iterable<string>, NameRows returned: [2] expected string, got int 3, after 3 names
TypeError: lazy_names(): Return value must be of type Iterable<string>, LazyCollection returned: [2] expected string, got int 3, after 3 names
TypeError: names_generator(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
a plain PHP class keeps its own methods: 4
a PHP list into a [string name, int count] parameter: tea 3
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given, called in %s on line %d
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given: [1] expected int, got string 'x', called in %s on line %d
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given, called in %s on line %d
a Map into an Iterable<[string, int]> parameter: tea=3 pie=7 
a list of tuples into an Iterable<[string, int]> parameter: tea=3 
a Map a PHP# call passes on: tea:3 pie:7 tea=3 pie=7 
TypeError: Demo\Crossings::entries(): Argument #1 ($given) must be of type Iterable<[string, int]>, Sharp.Map given: [0][1] expected int, got string 'x', called in %s on line %d
a returned Map: array, is_array true, {"b":2,"a":1}, typed array
a returned Set: array, is_array true, ["a","b"], typed array
a returned List<Map>: array, is_array true, [{"a":1}], typed array
a Map a PHP# lambda returns: array, is_array true, {"s":1}
a Map a PHP# method returns from its own call: array, is_array true, {"b":2,"a":1}
a returned Map keyed by a backed enum: 'low'=5 'high'=NULL
a returned Map is identical to its PHP array: true
ksort() sorts a returned Map: {"a":1,"b":2}
a returned Map keyed by a List stays the Map: Sharp\Map, is_array false, refused by array_merge
a struct's Map property into an array parameter, from plain PHP: {"To":"a@b.co"}
a struct's Map property into an array parameter, from PHP#: {"To":"a@b.co"}
a Map property: array, is_array true, {"id":"p1"}, typed array
a Set property: array, is_array true, ["a","b"], typed array
a List<Map> property: array, is_array true, [{"a":1}], typed array
a nullable Map property: null, is_array false, refused by array_merge, typed ?array
a computed Map property: array, is_array true, {"id":"p1"}, typed array
a hooked Map property: array, is_array true, {"h":"1"}, typed array
a static Map property: array, is_array true, {"c":1}, typed array
a Map property isset: true
get_object_vars(): array, is_array true, {"id":"p1"}
an (array) cast: array, is_array true, {"id":"p1"}
foreach over the object: array, is_array true, {"id":"p1"}
ReflectionProperty::getValue(): array, is_array true, {"id":"p1"}
ReflectionProperty::isInitialized(): true
ReflectionProperty::getValue() after isInitialized(): array, is_array true, {"id":"p1"}
ReflectionProperty::getRawValue() after isInitialized(): array, is_array true, {"id":"p1"}
ReflectionProperty::getValue() after setValue(): array, is_array true, {"id":"p1"}
ReflectionProperty::getValue() of a static: array, is_array true, {"c":1}
a plain PHP subclass reads $this->payload: array, is_array true, {"id":"p1"}
PHP# reads its Map property: Sharp\Map Sharp\Map Sharp\Map
PHP# reads what plain PHP wrote: Sharp\Map Sharp\Map Sharp\Map {"id":"p2"}
a Map written to an array property: array, is_array true, {"tea":3,"pie":7}, typed array
a Map written through a reference to it: array, is_array true, {"tea":3,"pie":7}
a Set written to a ?array property: array, is_array true, ["a","b"], typed ?array
a Map written to an untyped property: array, is_array true, {"tea":3,"pie":7}
a Set written to a mixed property: array, is_array true, ["a","b"], typed mixed
a Map written to an iterable property: array, is_array true, {"tea":3,"pie":7}, typed iterable
a Map written to an array|Countable property: array, is_array true, {"tea":3,"pie":7}, typed Countable|array
a Map written to a ?Countable property: Sharp\Map, is_array false, refused by array_merge, typed ?Countable
a Set written to a static array property: array, is_array true, ["a","b"], typed array
a Map written to an element of an array property: array, is_array true, {"tea":3,"pie":7}
a Set written to an element of a list property: array, is_array true, ["a","b"]
a Map added to a list property: array, is_array true, {"tea":3,"pie":7}
a Map written by ??=: array, is_array true, {"tea":3}
a Map in a Map: array, is_array true, {"shelf":{"tea":3}} / a Map and a Set in a List: array, is_array true, [{"tea":3},["a"]]
plain PHP's array written back: array, is_array true, {"tea":1}
a Map in an object initializer: array, is_array true, {"tea":3}
a Map in a with: array, is_array true, {"tea":3}
a Map written to PHP#'s own property: Sharp\Map
a Map written by +=: array, is_array true, {"seed":1,"tea":3}
a Map written through __set: array, is_array true, {"tea":3}
a Map written to an inherited array property: array, is_array true, {"tea":3,"pie":7}
a Map written to an element of an inherited array property: array, is_array true, {"tea":3,"pie":7}
a Map written to an inherited static array property: array, is_array true, {"tea":3,"pie":7}
a Map written to an element of an element of an inherited list property: array, is_array true, {"tea":3,"pie":7}
a Map added to an inherited list property: array, is_array true, {"tea":3,"pie":7}
a Map in an initializer of a subclass: array, is_array true, {"tea":3}
a Map in a with of a subclass: array, is_array true, {"tea":3}
a Map in a new Self initializer: array, is_array true, {"tea":3}
a Map in a new T initializer: array, is_array true, {"tea":3}
PHP# reads an inherited plain PHP array with gaps as its List: ada
plain PHP's arrays written to a PHP# base's collection properties: 2 true 2 jam
a Map parameter: array 1
a Map attribute argument: array, is_array true, {"variant":"success"}
a Map attribute argument, built: array, is_array true, {"variant":"success"}
a Map constant: array, is_array true, {"card":100}, typed array
a Set constant: array, is_array true, ["a","b"], typed array
PHP# reads its Map constant: 100
PHP# reads its Set constant as a Set: true false
PHP# holds its constants as their types: Sharp\Map Sharp\Set
an Iterable constant: array, is_array true, [1,2], typed iterable, PHP# sums it: 3
a tuple constant: array, is_array true, [7,"seven"], typed array, PHP# unpacks it: 7 seven
a plain PHP parent implements a PHP# interface: record:7
a plain PHP class implements an Any? result: plain:3
a plain PHP class implements a Map result: array, is_array true, {"a":1}, typed array
a plain PHP class overrides a Map result: array, is_array true, {"p":2}, typed array, PHP# reads its own: Sharp\Map
a plain PHP index read: 1 p1
a PHP# generator yields: array, is_array true, {"g":1} / array, is_array true, {"h":2}
an Any result: array, is_array true, {"k":1}
an Any? property: array, is_array true, {"k":1}, typed mixed
an Iterable<Map> property: array, is_array true, [{"s":1}], typed iterable of array
a type parameter property and result: array, is_array true, {"h":1} / array, is_array true, {"h":1}
a plain PHP generator into an Iterable<Map>: Sharp\Map:1 Sharp\Map:2
a Map keyed by an enum without backing values: Sharp\Map, is_array false, refused by array_merge, typed Sharp\Map / Sharp\Map, is_array false, refused by array_merge, typed Sharp\Map
a Map holding a Map keyed by a List: Sharp\Map, is_array false, refused by array_merge, typed Sharp\Map
a Map holding a case and its backing value: ValueError: This `Map` holds both `"low"` and `Demo.Level.Low`, which plain PHP keeps as one key, so it can't become a plain PHP array
a Map holding 5 and "5" into an array parameter: ValueError: This `Map` holds both `5` and `"5"`, which plain PHP keeps as one key, so it can't become a plain PHP array
a Map holding 5 and "5" in an Any result: ValueError: This `Map` holds both `5` and `"5"`, which plain PHP keeps as one key, so it can't become a plain PHP array
a Map holding 5 and "5" read by arrayOf: ValueError: This `Map` holds both `5` and `"5"`, which plain PHP keeps as one key, so it can't become a plain PHP array
a Map whose keys can collide but do not: array, is_array true, {"high":1,"low":2}, typed array / array, is_array true, {"0":"a","x":"b"}, typed array / array, is_array true, {"low":1}, typed array
a PHP array from plain PHP passed back to it: payload
empty() of a Map property: true false
array_walk() over the object: array, is_array true, {"id":"p1"}

Deprecated: ArrayIterator::__construct(): Using an object as a backing array for ArrayIterator is deprecated, as it allows violating class constraints and invariants in %s on line %d
an ArrayIterator over the object: array, is_array true, {"id":"p1"}
get_mangled_object_vars(): array, is_array true, {"id":"p1"}
ReflectionClass::getStaticPropertyValue(): array, is_array true, {"c":1}
ReflectionClass::getStaticProperties(): array, is_array true, {"c":1}
get_class_vars(): array, is_array true, {"c":1}
a constant copied into a constant, a literal and a static: Sharp\Map 1 Sharp\Map Sharp\Set
a constant as a property default: Sharp\Map / array, is_array true, {"card":100}
a constant as a parameter default: Sharp\Map 1
a constant as an attribute argument: array, is_array true, {"card":100}
constants plain PHP reads: array, is_array true, {"card":100} / array, is_array true, {"inner":{"card":100}} / array, is_array true, ["a","b"]
a Map constant read takes at most five static reads: true
